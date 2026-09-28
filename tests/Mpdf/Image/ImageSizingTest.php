<?php

namespace Mpdf\Image;

/**
 * Sizing an image given a percentage of its table cell's width, against that cell.
 */
class ImageSizingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A 100mm by 25mm picture given max-width: 50%, with 2mm of padding each side, as Img leaves it for the table:
	 * sized as though the percentage were absent
	 *
	 * @param array $radii Its corner radii, in millimetres
	 * @param array $radiusPercent Those given as a percentage
	 *
	 * @return array
	 */
	private function image(array $radii = [], array $radiusPercent = [])
	{
		$objattr = [
			'type' => 'image',
			'orig_w' => 400,
			'orig_h' => 100,
			'width' => 104,
			'height' => 29,
			'image_width' => 100,
			'image_height' => 25,
			'margin_left' => 0,
			'margin_right' => 0,
			'margin_top' => 0,
			'margin_bottom' => 0,
			'cell_sizing' => [
				'w' => 100,
				'h' => 0,
				'minw' => false,
				'maxw' => false,
				'minh' => false,
				'maxh' => false,
				'natural_w' => 100,
				'natural_h' => 25,
				'extrawidth' => 4,
				'extraheight' => 4,
				'fit_w' => 180,
				'fit_h' => 250,
				'percent' => ['maxw' => 50],
				'radius_percent' => $radiusPercent,
			],
		];

		if ($radii) {
			$objattr['border_radius'] = $radii;
		}

		return $objattr;
	}

	/**
	 * The percentage is of the basis given, and the picture keeps its proportions
	 */
	public function testThePercentageIsOfTheCell()
	{
		$objattr = ImageSizing::sizeInCell($this->image(), 60);

		$this->assertEqualsWithDelta(30, $objattr['image_width'], 0.0001);
		$this->assertEqualsWithDelta(7.5, $objattr['image_height'], 0.0001);
		$this->assertEqualsWithDelta(34, $objattr['width'], 0.0001, 'padding included');
		$this->assertEqualsWithDelta(11.5, $objattr['height'], 0.0001, 'padding included');
	}

	/**
	 * Sizing again against another cell starts from what the picture was given, not from its last size
	 */
	public function testSizingAgainStartsFromWhatItWasGiven()
	{
		$objattr = ImageSizing::sizeInCell(ImageSizing::sizeInCell($this->image(), 20), 120);

		$this->assertEqualsWithDelta(60, $objattr['image_width'], 0.0001);
	}

	/**
	 * A percentage radius is resolved against the sized border box, and an absolute one is left alone
	 */
	public function testAPercentageRadiusIsOfTheSizedPicture()
	{
		$objattr = ImageSizing::sizeInCell($this->image(['TL' => [0, 0], 'BR' => [3, 3]], ['TL' => [50, 50]]), 60);

		$this->assertEqualsWithDelta([17, 5.75], $objattr['border_radius']['TL'], 0.0001);
		$this->assertSame([3, 3], $objattr['border_radius']['BR']);
	}

}
