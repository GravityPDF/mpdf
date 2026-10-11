<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;

/**
 * Draws the outline of a box: a ring outside its border box, outline-offset away from it, that follows the box's
 * rounded corners. Positions and sizes are in millimetres from the top left of the page, as RoundedBox takes them.
 */
class BoxOutline
{

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
		if ($c[0] == ColorConverter::MODE_SPOT && in_array($style, ['groove', 'ridge', 'inset', 'outset'], true)) {
			$style = 'solid';
		}

		switch ($style) {
			case 'dotted':
			case 'dashed':
				$s .= $this->stroke($c) . sprintf(' %.3F w ', $w * Mpdf::SCALE);
				if ($style === 'dotted') {
					$s .= sprintf('1 J 1 j [0 %.3F] 0 d ', 2 * $w * Mpdf::SCALE);
				} else {
					$s .= sprintf('0 J 0 j [%.3F %.3F] 0 d ', 3 * $w * Mpdf::SCALE, 3 * $w * Mpdf::SCALE);
				}
				$s .= $this->grown($box, $radii, $offset + $w / 2) . 'h S ';
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
	 * A groove, ridge, inset or outset outline: lit from the top left, so one tone along the top and left sides and
	 * another along the bottom and right, meeting on the diagonals of the corners. A groove or ridge is two rings,
	 * each half the width, in opposite tones. The tones are those a table border in the same style is drawn in
	 *
	 * @param array $box x0, y0, x1, y1
	 * @param array $radii
	 * @param array $outline
	 *
	 * @return string
	 */
	private function threeD(array $box, array $radii, array $outline)
	{
		$c = $outline['c'];
		$w = $outline['w'];
		$offset = $outline['offset'];

		$dark = $this->colorConverter->darken($c);
		$light = $this->colorConverter->lighten($c);

		switch ($outline['style']) {
			case 'inset':
				$rings = [[$offset, $offset + $w, $dark, $light]];
				break;
			case 'outset':
				$rings = [[$offset, $offset + $w, $light, $dark]];
				break;
			case 'groove':
				$rings = [[$offset + $w / 2, $offset + $w, $dark, $light], [$offset, $offset + $w / 2, $light, $dark]];
				break;
			default:
				$rings = [[$offset + $w / 2, $offset + $w, $light, $dark], [$offset, $offset + $w / 2, $dark, $light]];
		}

		// The two halves of the outer box, split along the diagonals through its top right and bottom left corners
		$x0 = $box[0] - $offset - $w;
		$y0 = $box[1] - $offset - $w;
		$x1 = $box[2] + $offset + $w;
		$y1 = $box[3] + $offset + $w;
		$t = min($x1 - $x0, $y1 - $y0) / 2;
		$topLeft = $this->polygon([[$x0, $y0], [$x1, $y0], [$x1 - $t, $y0 + $t], [$x0 + $t, $y1 - $t], [$x0, $y1]]);
		$bottomRight = $this->polygon([[$x1, $y1], [$x0, $y1], [$x0 + $t, $y1 - $t], [$x1 - $t, $y0 + $t], [$x1, $y0]]);

		$s = '';
		foreach ($rings as $ring) {
			list($from, $to, $first, $second) = $ring;
			$path = $this->ring($box, $radii, $from, $to);
			$s .= 'q ' . $topLeft . 'W n ' . $this->fill($first) . ' ' . $path . 'f* Q ';
			$s .= 'q ' . $bottomRight . 'W n ' . $this->fill($second) . ' ' . $path . 'f* Q ';
		}

		return $s;
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
