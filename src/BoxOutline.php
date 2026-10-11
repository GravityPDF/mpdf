<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;

/**
 * Draws the outline of a box: a ring outside its border box, outline-offset away from it, that follows the box's
 * rounded corners, and the sides of a border in the two-tone styles, in the tones of an outline in that style.
 * Positions and sizes are in millimetres from the top left of the page, as RoundedBox takes them.
 */
class BoxOutline
{

	/**
	 * The border and outline styles drawn in two tones
	 */
	const THREE_D_STYLES = ['groove', 'ridge', 'inset', 'outset'];

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\RoundedBox
	 */
	private $roundedBox;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 * @param \Mpdf\RoundedBox $roundedBox
	 * @param \Mpdf\Color\ColorConverter $colorConverter
	 */
	public function __construct(Mpdf $mpdf, RoundedBox $roundedBox, ColorConverter $colorConverter)
	{
		$this->mpdf = $mpdf;
		$this->roundedBox = $roundedBox;
		$this->colorConverter = $colorConverter;
	}

	/**
	 * The operators that draw an outline around a border box, in a graphics state of their own
	 *
	 * @param float $x0 The border box's left edge
	 * @param float $y0 Its top edge
	 * @param float $x1 Its right edge
	 * @param float $y1 Its bottom edge
	 * @param array $radii The border box's corners, fitted to it, keyed TL/TR/BR/BL
	 * @param array $outline As Css\Outline::parse() gives it, and under open the sides, keyed top/right/bottom/left,
	 *                       where the box is cut off by a page or a line break. The outline is left open there, as the
	 *                       border is
	 *
	 * @return string
	 */
	public function draw($x0, $y0, $x1, $y1, array $radii, array $outline)
	{
		$open = isset($outline['open']) ? $outline['open'] : [];
		$w = $outline['w'];
		$offset = $outline['offset'];
		$box = [$x0, $y0, $x1, $y1];

		$clip = '';
		if (array_filter($open)) {
			// A cut side is drawn past the cut, square, and clipped there
			$reach = abs($offset) + $w + 1;
			$bounds = [0, 0, $this->mpdf->w, $this->mpdf->h];
			$cut = [
				'left' => [0, -1, ['TL', 'BL']],
				'top' => [1, -1, ['TL', 'TR']],
				'right' => [2, 1, ['TR', 'BR']],
				'bottom' => [3, 1, ['BR', 'BL']],
			];
			foreach ($cut as $side => $how) {
				if (empty($open[$side])) {
					continue;
				}
				list($edge, $direction, $corners) = $how;
				$bounds[$edge] = $box[$edge];
				$box[$edge] += $direction * $reach;
				foreach ($corners as $corner) {
					$radii[$corner] = [0, 0];
				}
			}
			$clip = sprintf(
				'%.3F %.3F %.3F %.3F re W n ',
				$bounds[0] * Mpdf::SCALE,
				($this->mpdf->h - $bounds[3]) * Mpdf::SCALE,
				($bounds[2] - $bounds[0]) * Mpdf::SCALE,
				($bounds[3] - $bounds[1]) * Mpdf::SCALE
			);
		}

		$c = $outline['c'];
		$s = 'q ' . $clip;
		$style = $outline['style'];
		// A colour mPDF cannot shade, such as a spot colour, draws a groove, ridge, inset or outset solid
		if ($c[0] == ColorConverter::MODE_SPOT && in_array($style, self::THREE_D_STYLES, true)) {
			$style = 'solid';
		}

		switch ($style) {
			case 'dotted':
			case 'dashed':
				$s .= $this->dashedStroke($style, $c, $w) . $this->grown($box, $radii, $offset + $w / 2) . 'h S ';
				break;

			case 'double':
				$s .= $this->fill($c) . ' ' . $this->ring($box, $radii, $offset, $offset + $w / 3)
					. $this->ring($box, $radii, $offset + 2 * $w / 3, $offset + $w) . 'f* ';
				break;

			case 'inset':
			case 'outset':
			case 'groove':
			case 'ridge':
				$s .= $this->threeD($box, $radii, $outline);
				break;

			default:
				$s .= $this->fill($c) . ' ' . $this->ring($box, $radii, $offset, $offset + $w) . 'f* ';
		}

		return $s . 'Q';
	}

	/**
	 * A groove, ridge, inset or outset outline, its rings split along the diagonals of the corners of the outer box
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param array $outline
	 *
	 * @return string
	 */
	private function threeD(array $box, array $radii, array $outline)
	{
		$w = $outline['w'];
		$offset = $outline['offset'];

		// The two halves of the outer box, split along the diagonals through its top right and bottom left corners
		$x0 = $box[0] - $offset - $w;
		$y0 = $box[1] - $offset - $w;
		$x1 = $box[2] + $offset + $w;
		$y1 = $box[3] + $offset + $w;
		$t = min($x1 - $x0, $y1 - $y0) / 2;
		$topLeft = $this->polygon([[$x0, $y0], [$x1, $y0], [$x1 - $t, $y0 + $t], [$x0 + $t, $y1 - $t], [$x0, $y1]]);
		$bottomRight = $this->polygon([[$x1, $y1], [$x0, $y1], [$x0 + $t, $y1 - $t], [$x1 - $t, $y0 + $t], [$x1, $y0]]);

		$s = '';
		foreach ($this->threeDRings($outline['style'], $outline['c']) as $ring) {
			list($from, $to, $first, $second) = $ring;
			$path = $this->ring($box, $radii, $offset + $from * $w, $offset + $to * $w);
			$s .= 'q ' . $topLeft . 'W n ' . $this->fill($first) . ' ' . $path . 'f* Q ';
			$s .= 'q ' . $bottomRight . 'W n ' . $this->fill($second) . ' ' . $path . 'f* Q ';
		}

		return $s;
	}

	/**
	 * The operators that draw one side of a border in a groove, ridge, inset or outset style, in the tones and rings
	 * an outline in that style is drawn in. The side is cut from its neighbours along lines from each outer corner
	 * towards its inner one, as CSS joins borders of different widths.
	 *
	 * @param array $box The border box: x0, y0, x1, y1
	 * @param array $radii The border box's corners, fitted to it, keyed TL/TR/BR/BL
	 * @param array $widths The border widths, keyed top/right/bottom/left
	 * @param string $side top, right, bottom or left
	 * @param array $border The side's border, with its style and colour
	 *
	 * @return string The operators, or an empty string where the side is in another style, or in a colour that
	 *                cannot be shaded, such as a spot colour, for the caller to draw as before
	 */
	public function borderSide(array $box, array $radii, array $widths, $side, array $border)
	{
		if (!in_array($border['style'], self::THREE_D_STYLES, true) || $border['c'][0] == ColorConverter::MODE_SPOT) {
			return '';
		}

		return $this->filledSide($box, $radii, $widths, $side, $this->threeDRings($border['style'], $border['c']));
	}

	/**
	 * The operators that draw one side of a border in any style, following the box's rounded corners: a solid or
	 * double border as rings filled between the border and padding edges, a dashed or dotted one stroked along the
	 * middle of the border, and the two-tone styles as borderSide() draws them, solid where the colour cannot be
	 * shaded
	 *
	 * @param array $box The border box: x0, y0, x1, y1
	 * @param array $radii The border box's corners, fitted to it, keyed TL/TR/BR/BL
	 * @param array $widths The border widths, keyed top/right/bottom/left
	 * @param string $side top, right, bottom or left
	 * @param array $border The side's border, with its style and colour
	 *
	 * @return string
	 */
	public function roundedBorderSide(array $box, array $radii, array $widths, $side, array $border)
	{
		$style = $border['style'];
		$c = $border['c'];

		if ($style === 'dashed' || $style === 'dotted') {
			$sideBox = ['x0' => $box[0], 'y0' => $box[1], 'x1' => $box[2], 'y1' => $box[3], 'radii' => $radii];

			return 'q ' . $this->dashedStroke($style, $c, $widths[$side])
				. $this->roundedBox->side($this->mpdf->h, $side, $sideBox, $widths[$side]) . 'S Q';
		}

		$s = $this->borderSide($box, $radii, $widths, $side, $border);
		if ($s !== '') {
			return $s;
		}

		$rings = $style === 'double' ? [[2 / 3, 1, $c, $c], [0, 1 / 3, $c, $c]] : [[0, 1, $c, $c]];

		return $this->filledSide($box, $radii, $widths, $side, $rings);
	}

	/**
	 * One side of a border filled as rings, clipped to the side's part of the box
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param array $widths keyed top/right/bottom/left
	 * @param string $side
	 * @param array $rings As threeDRings() gives them
	 *
	 * @return string
	 */
	private function filledSide(array $box, array $radii, array $widths, $side, array $rings)
	{
		// Each ring is [from, to, top and left tone, bottom and right tone]
		$tone = in_array($side, ['top', 'left'], true) ? 2 : 3;
		$s = 'q ' . $this->sideRegion($box, $widths, $side) . 'W n ';
		$ringBox = $this->runPastBorderlessSides($box, $radii, $widths);
		foreach ($rings as $ring) {
			$s .= $this->fill($ring[$tone]) . ' ' . $this->insetPath($ringBox, $radii, $widths, 1 - $ring[1])
				. $this->insetPath($ringBox, $radii, $widths, 1 - $ring[0]) . 'f* ';
		}

		return $s . 'Q';
	}

	/**
	 * The operators that set up a stroke dashed or dotted along a line of the given width: dashes three widths long,
	 * or round dots two widths apart
	 *
	 * @param string $style dashed or dotted
	 * @param string $c A colour as ColorConverter gives it
	 * @param float $w
	 *
	 * @return string
	 */
	private function dashedStroke($style, $c, $w)
	{
		$s = $this->stroke($c) . sprintf(' %.3F w ', $w * Mpdf::SCALE);
		if ($style === 'dotted') {
			return $s . sprintf('1 J 1 j [0 %.3F] 0 d ', 2 * $w * Mpdf::SCALE);
		}

		return $s . sprintf('0 J 0 j [%.3F %.3F] 0 d ', 3 * $w * Mpdf::SCALE, 3 * $w * Mpdf::SCALE);
	}

	/**
	 * The rings a groove, ridge, inset or outset is drawn in, lit from the top left: each is [from, to, the tone of
	 * its top and left sides, the tone of its bottom and right], from and to measured as fractions of the width from
	 * the inside out. A groove or ridge is two rings, each half the width, in opposite tones. The tones are those a
	 * table border in the same style is drawn in
	 *
	 * @param string $style
	 * @param string $c A colour as ColorConverter gives it
	 *
	 * @return array
	 */
	private function threeDRings($style, $c)
	{
		$dark = $this->colorConverter->darken($c);
		$light = $this->colorConverter->lighten($c);

		switch ($style) {
			case 'inset':
				return [[0, 1, $dark, $light]];
			case 'outset':
				return [[0, 1, $light, $dark]];
			case 'groove':
				return [[0.5, 1, $dark, $light], [0, 0.5, $light, $dark]];
			default:
				return [[0.5, 1, $light, $dark], [0, 0.5, $dark, $light]];
		}
	}

	/**
	 * The part of a box a side of its border is drawn in: a quadrilateral from the side's two outer corners, along
	 * the lines towards the inner corners, to the centre of the box. The four sides' parts tile the box
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $widths keyed top/right/bottom/left
	 * @param string $side
	 *
	 * @return string
	 */
	private function sideRegion(array $box, array $widths, $side)
	{
		list($x0, $y0, $x1, $y1) = $box;
		$halfW = ($x1 - $x0) / 2;
		$halfH = ($y1 - $y0) / 2;
		// Each corner and its direction into the box
		$corners = [
			'TL' => [$x0, $y0, 1, 1],
			'TR' => [$x1, $y0, -1, 1],
			'BR' => [$x1, $y1, -1, -1],
			'BL' => [$x0, $y1, 1, -1],
		];
		$ends = ['top' => ['TL', 'TR'], 'right' => ['TR', 'BR'], 'bottom' => ['BR', 'BL'], 'left' => ['BL', 'TL']];

		$outer = [];
		$inner = [];
		foreach ($ends[$side] as $corner) {
			list($x, $y, $sx, $sy) = $corners[$corner];
			list($h, $v) = RoundedBox::CORNER_SIDES[$corner];
			$dx = $widths[$h];
			$dy = $widths[$v];
			if (!$dx && !$dy) {
				$dx = $dy = 1;
			}
			// Run the line on until it reaches the middle of the box, across or down
			$t = min($dx ? $halfW / $dx : INF, $dy ? $halfH / $dy : INF);
			$outer[] = [$x, $y];
			$inner[] = [$x + $sx * $t * $dx, $y + $sy * $t * $dy];
		}

		return $this->polygon([$outer[0], $outer[1], $inner[1], [$x0 + $halfW, $y0 + $halfH], $inner[0]]);
	}

	/**
	 * The box the rings of a border are drawn on. Along a side with no border, such as one cut by a page or a line
	 * break, both edges of a ring would lie on the box's edge, and a renderer paints that as a hairline, so the box is
	 * run out past the edge there, beyond the clip of every side. A side with a rounded corner keeps its edge, as
	 * moving it would change the curve
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param array $widths keyed top/right/bottom/left
	 *
	 * @return array
	 */
	private function runPastBorderlessSides(array $box, array $radii, array $widths)
	{
		$sides = [
			'left' => [0, -1, 'TL', 'BL'],
			'top' => [1, -1, 'TL', 'TR'],
			'right' => [2, 1, 'TR', 'BR'],
			'bottom' => [3, 1, 'BL', 'BR'],
		];
		foreach ($sides as $side => $how) {
			list($edge, $direction, $a, $b) = $how;
			if (!$widths[$side] && !max($radii[$a]) && !max($radii[$b])) {
				$box[$edge] += $direction;
			}
		}

		return $box;
	}

	/**
	 * The path of the border box moved in by a fraction of the border widths on each side, its corners moving in with
	 * it, or nothing where that closes the box up
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param array $widths keyed top/right/bottom/left
	 * @param float $f
	 *
	 * @return string
	 */
	private function insetPath(array $box, array $radii, array $widths, $f)
	{
		$d = [];
		foreach ($widths as $side => $width) {
			$d[$side] = $width * $f;
		}
		$x0 = $box[0] + $d['left'];
		$y0 = $box[1] + $d['top'];
		$x1 = $box[2] - $d['right'];
		$y1 = $box[3] - $d['bottom'];
		if ($x1 <= $x0 || $y1 <= $y0) {
			return '';
		}

		return $this->roundedBox->path($this->mpdf->h, $x0, $y0, $x1, $y1, $this->roundedBox->inset($radii, $d)) . 'h ';
	}

	/**
	 * The path of a ring between two distances outside the border box, to fill with the even-odd rule
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param float $from The inner edge's distance from the border box; negative inside it
	 * @param float $to The outer edge's
	 *
	 * @return string
	 */
	private function ring(array $box, array $radii, $from, $to)
	{
		$s = $this->grown($box, $radii, $to);
		// An inner edge that closes up leaves the whole of the outer one filled
		if ($box[2] - $box[0] + 2 * $from > 0 && $box[3] - $box[1] + 2 * $from > 0) {
			$s .= 'h ' . $this->grown($box, $radii, $from);
		}

		return $s . 'h ';
	}

	/**
	 * The path of the border box grown by a distance on every side, its rounded corners growing with it, as CSS draws
	 * an outline's edges. A square corner stays square
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param float $d
	 *
	 * @return string
	 */
	private function grown(array $box, array $radii, $d)
	{
		foreach ($radii as $corner => $r) {
			$radii[$corner] = [$r[0] > 0 ? max(0, $r[0] + $d) : 0, $r[1] > 0 ? max(0, $r[1] + $d) : 0];
		}

		return $this->roundedBox->path($this->mpdf->h, $box[0] - $d, $box[1] - $d, $box[2] + $d, $box[3] + $d, $radii);
	}

	/**
	 * A closed path through points given in millimetres from the top left of the page
	 *
	 * @param array $points Each [x, y]
	 *
	 * @return string
	 */
	private function polygon(array $points)
	{
		$s = '';
		foreach ($points as $i => $point) {
			$s .= sprintf('%.3F %.3F %s ', $point[0] * Mpdf::SCALE, ($this->mpdf->h - $point[1]) * Mpdf::SCALE, $i ? 'l' : 'm');
		}

		return $s . 'h ';
	}

	/**
	 * The operators setting a fill colour, with its alpha where it has one
	 *
	 * @param string $c A colour as ColorConverter gives it
	 *
	 * @return string
	 */
	private function fill($c)
	{
		return $this->mpdf->SetFColor($c, true) . $this->alpha($c, 'F');
	}

	/**
	 * The operators setting a stroke colour, with its alpha where it has one
	 *
	 * @param string $c A colour as ColorConverter gives it
	 *
	 * @return string
	 */
	private function stroke($c)
	{
		return $this->mpdf->SetDColor($c, true) . $this->alpha($c, 'S');
	}

	/**
	 * The operator setting the alpha of a colour that has one, or nothing for an opaque one
	 *
	 * @param string $c
	 * @param string $mode F for fills, S for strokes
	 *
	 * @return string
	 */
	private function alpha($c, $mode)
	{
		$alpha = ColorConverter::alpha($c);

		return $alpha === null ? '' : ' ' . $this->mpdf->SetAlpha($alpha, 'Normal', true, $mode);
	}
}
