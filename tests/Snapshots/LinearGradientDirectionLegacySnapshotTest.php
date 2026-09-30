<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Linear gradient backgrounds in legacy CSS mode run as mPDF v7 drew them, prefixed or not: "left" and "right" name the
 * side the gradient ends at and "top" and "bottom" the side it starts from, with or without "to", angles run
 * counter-clockwise from pointing right, and the turn unit is not read.
 *
 * @group snapshot
 */
class LinearGradientDirectionLegacySnapshotTest extends LinearGradientDirectionSnapshotTest
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'linear-gradient-direction-legacy';
	}

	/**
	 * The CSS mode the document is drawn in
	 *
	 * @return string
	 */
	protected function cssMode()
	{
		return CssMode::LEGACY;
	}

	/**
	 * The document's title
	 *
	 * @return string
	 */
	protected function title()
	{
		return 'linear-gradient() directions in cssMode legacy';
	}

	/**
	 * The gradients of the standard document, each with a caption saying where legacy CSS mode draws the red and blue
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			'Keywords' => [
				['linear-gradient(to bottom, red, blue)', 'to bottom: red at the bottom, blue at the top.'],
				['linear-gradient(to top, red, blue)', 'to top: red at the top, blue at the bottom.'],
				['linear-gradient(to right, red, blue)', 'to right: red at the left, blue at the right.'],
				['linear-gradient(to left, red, blue)', 'to left: red at the right, blue at the left.'],
				['linear-gradient(to bottom right, red, blue)', 'to bottom right: red at the left, blue at the right, running slightly up from the bottom left corner.'],
				['linear-gradient(to top left, red, blue)', 'to top left: red at the right, blue at the left, running slightly down to the bottom left corner.'],
			],
			'Angles' => [
				['linear-gradient(0deg, red, blue)', '0deg: red at the left, blue at the right.'],
				['linear-gradient(90deg, red, blue)', '90deg: red at the bottom, blue at the top.'],
				['linear-gradient(180deg, red, blue)', '180deg: red at the right, blue at the left.'],
				['linear-gradient(270deg, red, blue)', '270deg: red at the top, blue at the bottom.'],
				['linear-gradient(45deg, red, blue)', '45deg: red at the left, blue at the right, running slightly up from the bottom left corner.'],
				['linear-gradient(135deg, red, blue)', '135deg: red at the right, blue at the left, running slightly up from the bottom right corner.'],
				['linear-gradient(0.25turn, red, blue)', '0.25turn: turn is not read, so red at the left, blue at the right.'],
			],
			'Prefixed functions' => [
				['-moz-linear-gradient(left, red, blue)', '-moz- left: red at the right, blue at the left.'],
				['-webkit-linear-gradient(top, red, blue)', '-webkit- top: red at the top, blue at the bottom.'],
				['-webkit-linear-gradient(90deg, red, blue)', '-webkit- 90deg: red at the bottom, blue at the top.'],
			],
		];
	}

}
