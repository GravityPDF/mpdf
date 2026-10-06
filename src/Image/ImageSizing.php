<?php

namespace Mpdf\Image;

/**
 * The size an image is drawn at, from the lengths its HTML and CSS give it and the picture's own proportions.
 *
 * A sizing is an array of the lengths an image was given, in millimetres: w and h (0 when not given), minw, maxw, minh
 * and maxh (false when not given), natural_w and natural_h (the size it takes when given neither w nor h), extrawidth
 * and extraheight (its padding, border and margin), fit_w and fit_h (the most room it may take up), and percent: the
 * percentages of a table cell's width still to resolve, keyed as the length they stand for.
 */
class ImageSizing
{

	/**
	 * The width and height of the picture inside its padding, border and margin
	 *
	 * @param array $sizing
	 * @param float $ratioW The picture's own width, for its proportions
	 * @param float $ratioH The picture's own height
	 * @param float|null $basis The width percentages are of, or null to leave them out
	 *
	 * @return float[] Width and height
	 */
	public static function fit(array $sizing, $ratioW, $ratioH, $basis = null)
	{
		foreach ($sizing['percent'] as $key => $percent) {
			$sizing[$key] = $basis === null ? false : $percent / 100 * $basis;
		}

		$w = $sizing['w'];
		$h = $sizing['h'];

		if ($w == 0 && $h == 0) {
			$w = $sizing['natural_w'];
			$h = $sizing['natural_h'];
		}

		if ($w == 0) {
			$w = $ratioH ? abs($h * $ratioW / $ratioH) : INF;
		}

		if ($h == 0) {
			$h = $ratioW ? abs($w * $ratioH / $ratioW) : INF;
		}

		if ($sizing['minw'] && $w < $sizing['minw']) {
			$w = $sizing['minw'];
			$h = $ratioW ? abs($w * $ratioH / $ratioW) : INF;
		}
		if ($sizing['maxw'] && $w > $sizing['maxw']) {
			$w = $sizing['maxw'];
			$h = $ratioW ? abs($w * $ratioH / $ratioW) : INF;
		}
		if ($sizing['minh'] && $h < $sizing['minh']) {
			$h = $sizing['minh'];
			$w = $ratioH ? abs($h * $ratioW / $ratioH) : INF;
		}
		if ($sizing['maxh'] && $h > $sizing['maxh']) {
			$h = $sizing['maxh'];
			$w = $ratioH ? abs($h * $ratioW / $ratioH) : INF;
		}

		// 0.0001 allows for rounding errors when w == fit_w
		if (($w + $sizing['extrawidth']) > ($sizing['fit_w'] + 0.0001)) {
			$w = $sizing['fit_w'] - $sizing['extrawidth'];
			$h = abs($w * $ratioH / $ratioW);
		}

		if ($h + $sizing['extraheight'] > $sizing['fit_h']) {
			$h = $sizing['fit_h'] - $sizing['extraheight'];
			$w = abs($h * $ratioW / $ratioH);
		}

		return [$w, $h];
	}

	/**
	 * Corner radii with their percentages resolved against the image's border box, horizontal ones of its width and
	 * vertical ones of its height. A corner left with no curve on either axis is square, and dropped.
	 *
	 * @param array $objattr The image, sized
	 * @param array $radii Keyed TL/TR/BR/BL, each [horizontal, vertical] in millimetres
	 * @param array $percent The same keys and axes, for those given as a percentage
	 *
	 * @return array
	 */
	public static function radii(array $objattr, array $radii, array $percent)
	{
		$box = [
			$objattr['width'] - $objattr['margin_left'] - $objattr['margin_right'],
			$objattr['height'] - $objattr['margin_top'] - $objattr['margin_bottom'],
		];

		foreach ($percent as $corner => $shares) {
			foreach ($shares as $axis => $share) {
				$radii[$corner][$axis] = $share / 100 * $box[$axis];
			}
		}

		return array_filter($radii, function ($radius) {
			return $radius[0] > 0 && $radius[1] > 0;
		});
	}

	/**
	 * An image given a percentage of its cell's width, sized against the cell
	 *
	 * @param array $objattr The image, with its cell_sizing
	 * @param float $basis The cell's content width, in the image's unshrunk lengths
	 *
	 * @return array The image, sized
	 */
	public static function sizeInCell(array $objattr, $basis)
	{
		$sizing = $objattr['cell_sizing'];

		list($w, $h) = self::fit($sizing, $objattr['orig_w'], $objattr['orig_h'], $basis);

		$objattr['width'] = $w + $sizing['extrawidth'];
		$objattr['height'] = $h + $sizing['extraheight'];
		$objattr['image_width'] = $w;
		$objattr['image_height'] = $h;

		if (isset($objattr['border_radius'])) {
			$objattr['border_radius'] = self::radii($objattr, $objattr['border_radius'], $sizing['radius_percent']);
		}

		return $objattr;
	}

	/**
	 * A block-level image placed across the width it has to itself: the margin on the side away from the image gives
	 * way to what is left of the line, or both margins share it to centre the image. The outer width then equals
	 * $available, so the image fills its line and text-align cannot move it. An image that does not fit the line even
	 * so is left as it is, and narrowed to the line as any image is.
	 *
	 * @param array $objattr The image, with 'block' the side it sits on: L, C or R
	 * @param float $available The width of the line, in millimetres
	 *
	 * @return array The image, with its margins resolved
	 */
	public static function placeBlock(array $objattr, $available)
	{
		$gives = $objattr['block'] === 'L' ? 'margin_right' : 'margin_left';
		$free = $available - ($objattr['width'] - $objattr[$gives]);

		if ($free < 0) {
			return $objattr;
		}

		if ($objattr['block'] === 'C') {
			$objattr['margin_left'] = $objattr['margin_right'] = $free / 2;
		} else {
			$objattr[$gives] = $free;
		}

		$objattr['width'] = $available;

		return $objattr;
	}

	/**
	 * Whether a table column can narrow the image, which a percentage width or max-width lets it do
	 *
	 * @param array $sizing
	 *
	 * @return bool
	 */
	public static function isCompressible(array $sizing)
	{
		return isset($sizing['percent']['w']) || isset($sizing['percent']['maxw']);
	}

	/**
	 * The narrowest a table column can make the image, padding, border and margin included: its min-width if the
	 * column may narrow it, as a browser does, else the width it has without the percentages.
	 *
	 * @param array $sizing
	 * @param float $ratioW The picture's own width, for its proportions
	 * @param float $ratioH The picture's own height
	 * @param bool $keepWidth
	 *
	 * @return float
	 */
	public static function minimumWidth(array $sizing, $ratioW, $ratioH, $keepWidth)
	{
		if ($keepWidth || !self::isCompressible($sizing)) {
			list($w) = self::fit($sizing, $ratioW, $ratioH);

			return $w + $sizing['extrawidth'];
		}

		return ($sizing['minw'] ? $sizing['minw'] : 0) + $sizing['extrawidth'];
	}

}
