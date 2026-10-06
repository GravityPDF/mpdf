<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;

/**
 * An inset box-shadow as PDF operators. The shadow is clipped to the padding box and fills it in from the padding
 * edge to the shape offset and shrunk by the spread. A blur fades it out across a ring around that shape, drawn as
 * a Coons patch mesh the way an outer shadow's edge is. Positions and sizes come in millimetres from the top left of
 * the page, as mPDF keeps them.
 */
class InsetBoxShadow
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
	 * The operators that draw one inset shadow inside a padding box, with every graphics state they open closed
	 *
	 * @param array $box The padding box, keyed x0, y0, x1, y1 and radii (TL/TR/BR/BL pairs)
	 * @param array $shadow The shadow, keyed x, y, blur and spread; the blur is 0 where the document cannot fade
	 * @param string $colour The shadow's colour in mPDF's internal form
	 * @param string $faded The same colour with its alpha at 0, for the far side of the blur
	 * @param string $colspace RGB, CMYK or Gray, the colour space of the mesh
	 *
	 * @return string
	 */
	public function operators($box, $shadow, $colour, $faded, $colspace)
	{
		$k = Mpdf::SCALE;
		$pageHeight = $this->mpdf->h;
		$x0 = $box['x0'];
		$y0 = $box['y0'];
		$w = $box['x1'] - $x0;
		$h = $box['y1'] - $y0;
		$blur = $shadow['blur'];
		$solidEdge = $shadow['spread'] - $blur / 2;

		$s = ' q 0 w ' . $this->roundedBox->path($pageHeight, $x0, $y0, $box['x1'], $box['y1'], $box['radii']) . ' W n' . "\n";
		if ($shadow['x'] || $shadow['y']) {
			$s .= sprintf('1 0 0 1 %.4F %.4F cm', $shadow['x'] * $k, -$shadow['y'] * $k) . "\n";
		}
		$s .= ' q ' . $this->mpdf->SetFColor($colour, true) . "\n" . $this->alpha($colour);

		// Whatever the clip lets through, however far the shape is offset
		$dx = abs($shadow['x']);
		$dy = abs($shadow['y']);
		$s .= sprintf('%.3F %.3F %.3F %.3F re ', ($x0 - $dx) * $k, ($pageHeight - $y0 + $dy) * $k, ($w + 2 * $dx) * $k, -($h + 2 * $dy) * $k);

		if ($w <= 2 * $solidEdge || $h <= 2 * $solidEdge) {
			// The spread has closed the shape, and the shadow is solid throughout
			return $s . 'f Q' . "\n" . ' Q' . "\n";
		}

		$sides = ['top' => $solidEdge, 'right' => $solidEdge, 'bottom' => $solidEdge, 'left' => $solidEdge];
		$ring = $this->ring($x0 + $solidEdge, $y0 + $solidEdge, $w - 2 * $solidEdge, $h - 2 * $solidEdge, $blur, $this->roundedBox->inset($box['radii'], $sides));
		$s .= $this->outerEdge($ring) . 'h f* Q' . "\n";

		if ($blur) {
			$s .= $this->gradient->CoonsPatchMesh($ring['x'], $ring['y'], $ring['w'], $ring['h'], $this->patches($ring, $colour, $faded), $ring['x'], $ring['x'] + $ring['w'], $ring['y'], $ring['y'] + $ring['h'], $colspace, true);
		}

		return $s . ' Q' . "\n";
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
	 * as an outer shadow's blur rounds a square corner. A ring wider than half the box is only as wide as that.
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
	 * The outer edge of a ring as a path run clockwise from the top of the top right corner, without a painting
	 * operator
	 *
	 * @param array $ring
	 *
	 * @return string
	 */
	private function outerEdge($ring)
	{
		$s = $this->point($ring['corners']['TR']['outer'][0]) . ' m ';
		foreach ($ring['corners'] as $corner) {
			if ($corner['outer'][0] !== $corner['outer'][1]) {
				$s .= $this->point($corner['outerControls'][0]) . ' ' . $this->point($corner['outerControls'][1]) . ' ' . $this->point($corner['outer'][1]) . ' c ';
			}
			$s .= $this->point($ring['corners'][$corner['next']]['outer'][0]) . ' l ';
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
	 * The eight Coons patches that fill a ring, a corner and the side after it in turn, coloured $outer on the outer
	 * edge and $inner on the inner one
	 *
	 * @param array $ring
	 * @param string $outer
	 * @param string $inner
	 *
	 * @return array As Gradient::CoonsPatchMesh() takes them
	 */
	private function patches($ring, $outer, $inner)
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
				$outer,
				$inner
			);
			$patches[] = $this->patch(
				$corner['inner'][1],
				$corner['outer'][1],
				$this->thirds($corner['outer'][1], $next['outer'][0]),
				$next['outer'][0],
				$next['inner'][0],
				$this->thirds($next['inner'][0], $corner['inner'][1]),
				$outer,
				$inner
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
	 * @param string $outer The colour of the outer edge
	 * @param string $inner The colour of the inner edge
	 *
	 * @return array
	 */
	private function patch($innerStart, $outerStart, $outerControls, $outerEnd, $innerEnd, $innerControls, $outer, $inner)
	{
		$points = array_merge(
			[$innerStart],
			$this->thirds($innerStart, $outerStart),
			[$outerStart],
			$outerControls,
			[$outerEnd],
			$this->thirds($outerEnd, $innerEnd),
			[$innerEnd],
			$innerControls
		);

		return ['f' => 0, 'points' => call_user_func_array('array_merge', $points), 'colors' => [$inner, $outer, $outer, $inner]];
	}
}
