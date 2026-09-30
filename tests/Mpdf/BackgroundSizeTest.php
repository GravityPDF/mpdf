<?php

namespace Mpdf;

class BackgroundSizeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const WIDE_IMAGE = __DIR__ . '/../data/img/bayeux2.jpg'; // 292x83

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
	}

	/**
	 * The area is passed in mm and scaled on the way in, so expectations are stated the same way
	 */
	private function resize($imw, $imh, $areaW, $areaH, $keyword)
	{
		return $this->mpdf->_resizeBackgroundImage($imw, $imh, $areaW, $areaH, 0, false, false, [], ['w' => $keyword, 'h' => $keyword]);
	}

	public function testCoverOnAnImageWiderThanTheAreaBindsToTheHeight()
	{
		list($w, $h) = $this->resize(200, 50, 100, 100, 'cover');

		$this->assertEqualsWithDelta(100 * Mpdf::SCALE * 4, $w, 0.001);
		$this->assertEqualsWithDelta(100 * Mpdf::SCALE, $h, 0.001);
	}

	public function testCoverOnAnImageTallerThanTheAreaBindsToTheWidth()
	{
		list($w, $h) = $this->resize(50, 200, 100, 100, 'cover');

		$this->assertEqualsWithDelta(100 * Mpdf::SCALE, $w, 0.001);
		$this->assertEqualsWithDelta(100 * Mpdf::SCALE * 4, $h, 0.001);
	}

	/**
	 * 4:1 image, 2.5:1 area - the height still binds, but neither dimension matches the area
	 */
	public function testCoverOnAWideImageInAWideArea()
	{
		list($w, $h) = $this->resize(200, 50, 100, 40, 'cover');

		$this->assertEqualsWithDelta(40 * Mpdf::SCALE * 4, $w, 0.001);
		$this->assertEqualsWithDelta(40 * Mpdf::SCALE, $h, 0.001);
	}

	public function testContainOnAnImageWiderThanTheAreaBindsToTheWidth()
	{
		list($w, $h) = $this->resize(200, 50, 100, 100, 'contain');

		$this->assertEqualsWithDelta(100 * Mpdf::SCALE, $w, 0.001);
		$this->assertEqualsWithDelta(100 * Mpdf::SCALE / 4, $h, 0.001);
	}

	/**
	 * SetBackground() gives lengths in mm, and the image and area are worked in points
	 */
	public function testLengthsAreScaledToPoints()
	{
		list($w, $h) = $this->mpdf->_resizeBackgroundImage(200, 50, 100, 100, 0, false, false, [], ['w' => 60, 'h' => 'auto']);

		$this->assertEqualsWithDelta(60 * Mpdf::SCALE, $w, 0.001);
		$this->assertEqualsWithDelta(15 * Mpdf::SCALE, $h, 0.001);

		list($w, $h) = $this->mpdf->_resizeBackgroundImage(200, 50, 100, 100, 0, false, false, [], ['w' => 60, 'h' => 20]);

		$this->assertEqualsWithDelta(60 * Mpdf::SCALE, $w, 0.001);
		$this->assertEqualsWithDelta(20 * Mpdf::SCALE, $h, 0.001);
	}

	/**
	 * A length on a block's background draws the image at that length, in the pattern and in a footer
	 *
	 * @dataProvider backgroundLengthProvider
	 */
	public function testBackgroundLengthIsDrawnAtThatLength($footer)
	{
		$scales = $this->drawnScales('background-size: 40mm auto', $footer);

		$this->assertNotEmpty($scales, 'the background was not drawn');

		foreach ($scales as $scale) {
			$this->assertEqualsWithDelta(40 * Mpdf::SCALE, $scale[0], 0.01);
			$this->assertEqualsWithDelta(40 * Mpdf::SCALE * 83 / 292, $scale[1], 0.01);
		}
	}

	/**
	 * Whether the block is in the body, drawn through a pattern, or in a footer, drawn tile by tile
	 *
	 * @return array
	 */
	public function backgroundLengthProvider()
	{
		return [
			'body' => [false],
			'footer' => [true],
		];
	}

	/**
	 * Footers draw each tile themselves instead of going through a pattern, so they run a second
	 * copy of the same arithmetic in PrintPageBackgrounds()
	 */
	public function testFooterBackgroundIsDrawnAtCoverScale()
	{
		$scales = $this->drawnScales('background-size: cover', true);

		$this->assertNotEmpty($scales, 'the footer background was not drawn');

		foreach ($scales as $scale) {
			$this->assertEqualsWithDelta(292 / 83, $scale[0] / $scale[1], 0.001, 'aspect ratio is not preserved');
			$this->assertEqualsWithDelta(30 * Mpdf::SCALE, $scale[1], 0.001, 'the bound dimension does not match the area');
			$this->assertGreaterThan(60 * Mpdf::SCALE, $scale[0], 'the area is not covered horizontally');
		}
	}

	/**
	 * The width and height, in points, the image is drawn at for a 60mm by 30mm block with the wide image as its
	 * background
	 *
	 * @param string $size The background-size declaration
	 * @param bool $footer Whether the block is in a footer or in the body
	 *
	 * @return float[][]
	 */
	private function drawnScales($size, $footer)
	{
		$mpdf = new Mpdf(['margin_bottom' => 40]);
		$mpdf->compress = false;

		$div = '<div style="width: 60mm; height: 30mm; background-image: url(\'' . self::WIDE_IMAGE
			. '\'); ' . $size . '; background-repeat: no-repeat"></div>';

		if ($footer) {
			$mpdf->WriteHTML('<htmlpagefooter name="f">' . $div . '</htmlpagefooter>');
			$mpdf->WriteHTML('<sethtmlpagefooter name="f" value="on" show-this-page="1" />');
			$mpdf->WriteHTML('<p>body</p>');
		} else {
			$mpdf->WriteHTML($div);
		}

		$matches = [];
		preg_match_all('#([\d.]+) 0 0 ([\d.]+) [\d.\-]+ [\d.\-]+ cm\s+/I\d+ Do#', $mpdf->Output('', 'S'), $matches, PREG_SET_ORDER);

		return array_map(function ($cm) {
			return [(float) $cm[1], (float) $cm[2]];
		}, $matches);
	}

}
