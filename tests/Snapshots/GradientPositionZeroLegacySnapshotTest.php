<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The legacy CSS mode reads a unitless 0 in a gradient's position as the standard one does. Only the linear gradients
 * keep v7's directions: a side keyword names the side the gradient ends at, and the angle 0 points right.
 *
 * @group snapshot
 */
class GradientPositionZeroLegacySnapshotTest extends GradientPositionZeroSnapshotTest
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'gradient-position-zero-legacy';
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
	 * The standard document's gradients, with the captions of those legacy mode draws in another direction
	 *
	 * @return array
	 */
	protected function cases()
	{
		return array_replace_recursive(parent::cases(), [
			'Prefixed gradients' => [
				'-moz-linear-gradient(left 0, red, blue)' => '-moz- linear left 0: left is the side it ends at, so from red at the top right to blue at the bottom left.',
			],
			'A bare 0' => [
				'linear-gradient(0, red, blue)' => 'linear: still the angle, which points right in legacy mode: red at the left, blue at the right.',
			],
		]);
	}

}
