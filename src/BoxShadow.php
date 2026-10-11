<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;

/**
 * A box-shadow as PDF operators. Both kinds fill a shape and fade its edge across a ring, drawn as a Coons patch
 * mesh: an outer shadow fills the box grown by the spread and is clipped out of the box, an inset one fills the
 * padding box less the box shrunk by the spread and is clipped to the padding box. Positions and sizes come in
 * millimetres from the top left of the page, as mPDF keeps them.
 */
class BoxShadow
{

	/**
	 * How far along each tangent the control points of a quarter ellipse drawn as one curve lie
	 */
	const MAG = 0.551784;

	/**
	 * The corners of a box clockwise from the top right, each with the directions from its centre to where its arc
	 * starts and ends on the way round
	 */
	const CORNERS = [
		'TR' => [[0, -1], [1, 0]],
		'BR' => [[1, 0], [0, 1]],
		'BL' => [[0, 1], [-1, 0]],
		'TL' => [[-1, 0], [0, -1]],
	];

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var RoundedBox
	 */
	private $roundedBox;

	/**
	 * @var Gradient
	 */
	private $gradient;

	public function __construct(Mpdf $mpdf, RoundedBox $roundedBox, Gradient $gradient)
	{
		$this->mpdf = $mpdf;
		$this->roundedBox = $roundedBox;
		$this->gradient = $gradient;
	}

	/**
	 * The operators that draw one outer shadow round a box, with every graphics state they open closed
	 *
	 * @param array $clip The shape the shadow is cut out of, keyed x0, y0, x1, y1 and radii (TL/TR/BR/BL pairs)
	 * @param array $box The box that casts the shadow, keyed as $clip
	 * @param array $shadow The shadow, keyed x, y, blur and spread; the blur is 0 where the document cannot fade
	 * @param string $colour The shadow's colour in mPDF's internal form
	 * @param string $faded The same colour with its alpha at 0, for the far side of the blur
	 * @param string $colspace RGB, CMYK or Gray, the colour space of the mesh
	 * @param string[] $split The sides, top and bottom, where the block breaks across pages and the shadow is cut off
	 *
	 * @return string
	 */
	public function outer($clip, $box, $shadow, $colour, $faded, $colspace, $split = [])
	{
		$k = Mpdf::SCALE;
		$band = '';
		if ($split) {
			// Cut at the break, as the whole box's shadow would be, and with nothing of its edge on that side
			$top = in_array('top', $split, true) ? $box['y0'] : 0;
			$bottom = in_array('bottom', $split, true) ? $box['y1'] : $this->mpdf->h;
			$band = sprintf('0.000 %.3F %.3F %.3F re W n ', ($this->mpdf->h - $top) * $k, $this->mpdf->w * $k, ($top - $bottom) * $k);
			$clip = $this->pastBreaks($clip, $shadow, $split);
			$box = $this->pastBreaks($box, $shadow, $split);
		}
		$w = $box['x1'] - $box['x0'];
		$h = $box['y1'] - $box['y0'];
		$blur = $shadow['blur'];
		$spread = $shadow['spread'];
		// Keep some of the solid middle when a negative spread would close it
		if (-$spread + $blur / 2 > min($w / 2, $h / 2)) {
			$spread = $blur / 2 - min($w / 2, $h / 2) + 0.01;
		}
		$fadedEdge = $spread + $blur / 2;

		$s = ' q 0 w ' . $band . $this->roundedBox->path($this->mpdf->h, $clip['x0'], $clip['y0'], $clip['x1'], $clip['y1'], $clip['radii']);
		$s .= sprintf('0 %.3F m %.3F %.3F l ', $this->mpdf->h * $k, $this->mpdf->w * $k, $this->mpdf->h * $k);
		$s .= sprintf('%.3F 0 l 0 0 l 0 %.3F l ', $this->mpdf->w * $k, $this->mpdf->h * $k) . 'W n' . "\n";

		$ring = $this->ring($box['x0'] - $fadedEdge, $box['y0'] - $fadedEdge, $w + 2 * $fadedEdge, $h + 2 * $fadedEdge, $blur, $this->roundedBox->inset($box['radii'], $this->sides(-$fadedEdge)));

		return $s . $this->fill($shadow, $colour, $this->edge($ring, 'inner') . 'h f', $blur ? $this->mesh($ring, false, $colour, $faded, $colspace) : '');
	}

	/**
	 * The operators that draw one inset shadow inside a padding box, with every graphics state they open closed
	 *
	 * @param array $box The padding box, keyed x0, y0, x1, y1 and radii (TL/TR/BR/BL pairs)
	 * @param array $shadow The shadow, keyed x, y, blur and spread; the blur is 0 where the document cannot fade
	 * @param string $colour The shadow's colour in mPDF's internal form
	 * @param string $faded The same colour with its alpha at 0, for the far side of the blur
	 * @param string $colspace RGB, CMYK or Gray, the colour space of the mesh
	 * @param string[] $split The sides, top and bottom, where the block breaks across pages and the shadow is cut off
	 *
	 * @return string
	 */
	public function inset($box, $shadow, $colour, $faded, $colspace, $split = [])
	{
		$k = Mpdf::SCALE;
		$s = ' q 0 w ' . $this->roundedBox->path($this->mpdf->h, $box['x0'], $box['y0'], $box['x1'], $box['y1'], $box['radii']) . ' W n' . "\n";

		// The padding box stays the clip, so the shadow is cut at the break
		$box = $this->pastBreaks($box, $shadow, $split);
		$x0 = $box['x0'];
		$y0 = $box['y0'];
		$w = $box['x1'] - $x0;
		$h = $box['y1'] - $y0;
		$blur = $shadow['blur'];
		$solidEdge = $shadow['spread'] - $blur / 2;

		// Whatever the clip lets through, however far the shape is offset
		$dx = abs($shadow['x']);
		$dy = abs($shadow['y']);
		$cover = sprintf('%.3F %.3F %.3F %.3F re ', ($x0 - $dx) * $k, ($this->mpdf->h - $y0 + $dy) * $k, ($w + 2 * $dx) * $k, -($h + 2 * $dy) * $k);

		if ($w <= 2 * $solidEdge || $h <= 2 * $solidEdge) {
			// The spread has closed the shape, and the shadow is solid throughout
			return $s . $this->fill($shadow, $colour, $cover . 'f', '');
		}

		$ring = $this->ring($x0 + $solidEdge, $y0 + $solidEdge, $w - 2 * $solidEdge, $h - 2 * $solidEdge, $blur, $this->roundedBox->inset($box['radii'], $this->sides($solidEdge)));

		return $s . $this->fill($shadow, $colour, $cover . $this->edge($ring, 'outer') . 'h f*', $blur ? $this->mesh($ring, true, $colour, $faded, $colspace) : '');
	}

	/**
	 * The box carried past the top or bottom of the page on each side where the block breaks, far enough that none
	 * of the shadow's edge, offset, spread or blur on that side reaches the page
	 *
	 * @param array $box Keyed x0, y0, x1, y1 and radii
	 * @param array $shadow
	 * @param string[] $split top, bottom or both
	 *
	 * @return array
	 */
	private function pastBreaks($box, $shadow, $split)
	{
		$reach = abs($shadow['y']) + abs($shadow['spread']) + $shadow['blur'] + 1;
		if (in_array('top', $split, true)) {
			$box['y0'] = min($box['y0'], 0) - $reach;
		}
		if (in_array('bottom', $split, true)) {
			$box['y1'] = max($box['y1'], $this->mpdf->h) + $reach;
		}

		return $box;
	}

	/**
	 * What both kinds of shadow draw inside their clip: the offset, the solid part in the shadow's colour and alpha,
	 * and the fade, then the end of the clip
	 *
	 * @param array $shadow
	 * @param string $colour
	 * @param string $path The solid part's path and painting operator
	 * @param string $mesh
	 *
	 * @return string
	 */
	private function fill($shadow, $colour, $path, $mesh)
	{
		$s = $shadow['x'] || $shadow['y'] ? sprintf('1 0 0 1 %.4F %.4F cm', $shadow['x'] * Mpdf::SCALE, -$shadow['y'] * Mpdf::SCALE) . "\n" : '';

		return $s . ' q ' . $this->mpdf->SetFColor($colour, true) . "\n" . $this->alpha($colour) . $path . ' Q' . "\n" . $mesh . ' Q' . "\n";
	}

	/**
	 * The same length on every side of a box, keyed as RoundedBox::inset() takes it
	 *
	 * @param float $length
	 *
	 * @return float[]
	 */
	private function sides($length)
	{
		return ['top' => $length, 'right' => $length, 'bottom' => $length, 'left' => $length];
	}

	/**
	 * The patch mesh that fades a ring from $colour on its solid edge to $faded on the other
	 *
	 * @param array $ring
	 * @param bool $outerSolid Whether the outer edge is the solid one
	 * @param string $colour
	 * @param string $faded
	 * @param string $colspace
	 *
	 * @return string
	 */
	private function mesh($ring, $outerSolid, $colour, $faded, $colspace)
	{
		return $this->gradient->CoonsPatchMesh($ring['x'], $ring['y'], $ring['w'], $ring['h'], $this->patches($ring, $outerSolid, $colour, $faded), $ring['x'], $ring['x'] + $ring['w'], $ring['y'], $ring['y'] + $ring['h'], $colspace, true);
	}

	/**
	 * The operator that sets the fill alpha of a translucent colour, or nothing for an opaque one
	 *
	 * @param string $colour
	 *
	 * @return string
	 */
	private function alpha($colour)
	{
		$alpha = ColorConverter::alpha($colour);

		return $alpha !== null && $alpha < 1 ? $this->mpdf->SetAlpha($alpha, 'Normal', true, 'F') . "\n" : '';
	}

	/**
	 * The ring of width $width inside the rounded rectangle given, as its box and the points of its four corners. A
	 * corner tighter than the ring is wide is widened to it on the outer edge and comes to a point on the inner one,
	 * as browsers round a blurred square corner. A ring wider than half the box is only as wide as that.
	 *
	 * @param float $x
	 * @param float $y
	 * @param float $w
	 * @param float $h
	 * @param float $width
	 * @param array $radii The outer edge's radii, TL/TR/BR/BL pairs
	 *
	 * @return array Keyed x, y, w, h and corners; each corner has its outer arc's start and end (outer), the control
	 * points between them (outerControls), the inner arc's start and end (inner) and its control points
	 * (innerControls), all in the order the edges are run clockwise, and the name of the corner after it (next)
	 */
	private function ring($x, $y, $w, $h, $width, $radii)
	{
		$width = min($width, $w / 2, $h / 2);
		foreach ($radii as $corner => $r) {
			$radii[$corner] = [max($r[0], $width), max($r[1], $width)];
		}
		$radii = $this->roundedBox->fit($w, $h, $radii, ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]);

		$centres = [
			'TR' => [$x + $w - $radii['TR'][0], $y + $radii['TR'][1]],
			'BR' => [$x + $w - $radii['BR'][0], $y + $h - $radii['BR'][1]],
			'BL' => [$x + $radii['BL'][0], $y + $h - $radii['BL'][1]],
			'TL' => [$x + $radii['TL'][0], $y + $radii['TL'][1]],
		];

		$names = array_keys(self::CORNERS);
		$corners = [];
		foreach ($names as $i => $corner) {
			list($u, $v) = self::CORNERS[$corner];
			$centre = $centres[$corner];
			$ru = $radii[$corner][$u[0] ? 0 : 1];
			$rv = $radii[$corner][$v[0] ? 0 : 1];
			$iu = max(0, $ru - $width);
			$iv = max(0, $rv - $width);
			$outer = [$this->along($centre, $u, $ru), $this->along($centre, $v, $rv)];
			$inner = [$this->along($centre, $u, $iu), $this->along($centre, $v, $iv)];
			$corners[$corner] = [
				'outer' => $outer,
				'outerControls' => [$this->along($outer[0], $v, $rv * self::MAG), $this->along($outer[1], $u, $ru * self::MAG)],
				'inner' => $inner,
				'innerControls' => [$this->along($inner[0], $v, $iv * self::MAG), $this->along($inner[1], $u, $iu * self::MAG)],
				'next' => $names[($i + 1) % 4],
			];
		}

		return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'corners' => $corners];
	}

	/**
	 * The point $distance from $point in the direction $axis
	 *
	 * @param float[] $point
	 * @param int[] $axis A unit vector along x or y
	 * @param float $distance
	 *
	 * @return float[]
	 */
	private function along($point, $axis, $distance)
	{
		return [$point[0] + $axis[0] * $distance, $point[1] + $axis[1] * $distance];
	}

	/**
	 * The points a third and two thirds of the way from $a to $b: the control points that run a straight patch edge
	 * at an even pace
	 *
	 * @param float[] $a
	 * @param float[] $b
	 *
	 * @return float[][]
	 */
	private function thirds($a, $b)
	{
		$dx = ($b[0] - $a[0]) / 3;
		$dy = ($b[1] - $a[1]) / 3;

		return [[$a[0] + $dx, $a[1] + $dy], [$b[0] - $dx, $b[1] - $dy]];
	}

	/**
	 * The control points of a spoke from $from to $to, both bunched at the solid end, so the fade holds most of its
	 * colour through the first half of the blur and falls away across the second
	 *
	 * @param float[] $from
	 * @param float[] $to
	 * @param bool $fromSolid Whether $from is the end that carries the shadow's colour
	 *
	 * @return float[][]
	 */
	private function spoke($from, $to, $fromSolid)
	{
		$middle = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];

		return $fromSolid ? [$from, $middle] : [$middle, $to];
	}

	/**
	 * One edge of a ring as a path run clockwise from the top of the top right corner, without a painting operator
	 *
	 * @param array $ring
	 * @param string $edge outer or inner
	 *
	 * @return string
	 */
	private function edge($ring, $edge)
	{
		$s = $this->point($ring['corners']['TR'][$edge][0]) . ' m ';
		foreach ($ring['corners'] as $corner) {
			if ($corner[$edge][0] !== $corner[$edge][1]) {
				$s .= $this->point($corner[$edge . 'Controls'][0]) . ' ' . $this->point($corner[$edge . 'Controls'][1]) . ' ' . $this->point($corner[$edge][1]) . ' c ';
			}
			$s .= $this->point($ring['corners'][$corner['next']][$edge][0]) . ' l ';
		}

		return $s;
	}

	/**
	 * A point in millimetres from the top left of the page as the operators write it
	 *
	 * @param float[] $point
	 *
	 * @return string
	 */
	private function point($point)
	{
		return sprintf('%.3F %.3F', $point[0] * Mpdf::SCALE, ($this->mpdf->h - $point[1]) * Mpdf::SCALE);
	}

	/**
	 * The eight Coons patches that fill a ring, a corner and the side after it in turn, coloured $colour on the
	 * solid edge and $faded on the other
	 *
	 * @param array $ring
	 * @param bool $outerSolid Whether the outer edge is the solid one
	 * @param string $colour
	 * @param string $faded
	 *
	 * @return array As Gradient::CoonsPatchMesh() takes them
	 */
	private function patches($ring, $outerSolid, $colour, $faded)
	{
		$patches = [];
		foreach ($ring['corners'] as $corner) {
			$next = $ring['corners'][$corner['next']];
			$patches[] = $this->patch(
				$corner['inner'][0],
				$corner['outer'][0],
				$corner['outerControls'],
				$corner['outer'][1],
				$corner['inner'][1],
				array_reverse($corner['innerControls']),
				$outerSolid,
				$colour,
				$faded
			);
			$patches[] = $this->patch(
				$corner['inner'][1],
				$corner['outer'][1],
				$this->thirds($corner['outer'][1], $next['outer'][0]),
				$next['outer'][0],
				$next['inner'][0],
				$this->thirds($next['inner'][0], $corner['inner'][1]),
				$outerSolid,
				$colour,
				$faded
			);
		}

		return $patches;
	}

	/**
	 * One patch of the ring, run from the inner edge out along a straight spoke, along the outer edge, back in along
	 * the next spoke and home along the inner edge
	 *
	 * @param float[] $innerStart
	 * @param float[] $outerStart
	 * @param float[][] $outerControls The control points from $outerStart to $outerEnd
	 * @param float[] $outerEnd
	 * @param float[] $innerEnd
	 * @param float[][] $innerControls The control points from $innerEnd back to $innerStart
	 * @param bool $outerSolid Whether the outer edge is the solid one
	 * @param string $colour The colour of the solid edge
	 * @param string $faded The colour of the other
	 *
	 * @return array
	 */
	private function patch($innerStart, $outerStart, $outerControls, $outerEnd, $innerEnd, $innerControls, $outerSolid, $colour, $faded)
	{
		$outer = $outerSolid ? $colour : $faded;
		$inner = $outerSolid ? $faded : $colour;
		$points = array_merge(
			[$innerStart],
			$this->spoke($innerStart, $outerStart, !$outerSolid),
			[$outerStart],
			$outerControls,
			[$outerEnd],
			$this->spoke($outerEnd, $innerEnd, $outerSolid),
			[$innerEnd],
			$innerControls
		);

		return ['f' => 0, 'points' => call_user_func_array('array_merge', $points), 'colors' => [$inner, $outer, $outer, $inner]];
	}
}
