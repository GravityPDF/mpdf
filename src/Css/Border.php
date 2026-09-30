<?php

namespace Mpdf\Css;

class Border
{

	const ALL = 15;
	const TOP = 8;
	const RIGHT = 4;
	const BOTTOM = 2;
	const LEFT = 1;

	/**
	 * Whether a side of a border, as Mpdf::border_details() reads it, draws anything. A hidden side has no width, and a
	 * transparent one has false for its colour, which a side packed with a table cell keeps as NUL bytes. A colour
	 * mPDF cannot read is drawn black, as it was
	 *
	 * @param array|null $side
	 *
	 * @return bool
	 */
	public static function drawsSide($side)
	{
		return !empty($side['s']) && !empty($side['w']) && isset($side['c']) && $side['c'] !== false && strpos($side['c'], "\0") !== 0;
	}
}
