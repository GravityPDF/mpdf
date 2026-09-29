<?php

namespace Mpdf;

/**
 * A list-style-image URL keeps the case it was written in, so the image is found on case-sensitive disks and
 * servers, while the keyword none is still recognised in any case.
 */
class ListStyleImageCaseTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerImages
	 *
	 * @param string $css A rule for the list
	 * @param string $image The list-style-image the marker should be given
	 */
	public function testMarkerImageKeepsItsCase($css, $image)
	{
		$mpdf = new ListMarkerRecordingMpdf();
		$mpdf->WriteHTML('<style>' . $css . '</style><ul><li>Item</li></ul>');

		$this->assertSame([$image], $mpdf->markerImages);
	}

	/**
	 * @return array[] List rules and the image their marker should be given
	 */
	public function providerImages()
	{
		return [
			'longhand' => ['ul { list-style-image: url(/Some/Path/Bullet.PNG) }', "url('/Some/Path/Bullet.PNG')"],
			'longhand, quoted' => ['ul { list-style-image: url("/Some/Path/Bullet.PNG") }', "url('/Some/Path/Bullet.PNG')"],
			'shorthand' => ['ul { list-style: square url(/Some/Path/Bullet.PNG) inside }', '/Some/Path/Bullet.PNG'],
			'longhand none in capitals' => ['ul { list-style-image: NONE }', 'none'],
			'shorthand none in capitals' => ['ul { list-style: NONE }', 'none'],
		];
	}

	/**
	 * The image is drawn when its file name has capitals
	 *
	 * @dataProvider providerDrawnImages
	 *
	 * @param string $value The list's list-style-image
	 */
	public function testImageWithCapitalsIsDrawn($value)
	{
		$mpdf = new ListMarkerRecordingMpdf();
		$mpdf->SetBasePath(__DIR__ . '/../data');
		$mpdf->WriteHTML('<ul style="list-style-image: ' . $value . '"><li>Item</li></ul>');

		$this->assertCount(1, $mpdf->images);
	}

	/**
	 * @return array[] Ways of naming an image whose file name has capitals
	 */
	public function providerDrawnImages()
	{
		return [
			'url()' => ['url(img/List-Bullet.png)'],
			'URL() in capitals' => ['URL(img/List-Bullet.png)'],
		];
	}

}
