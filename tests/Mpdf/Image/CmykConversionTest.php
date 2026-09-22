<?php

namespace Mpdf\Image;

use Mpdf\PageStreams;

/**
 * An RGB image in a document restricted to CMYK is converted sample by sample
 */
class CmykConversionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Full ink is a full byte, where truncating 100 * 2.55 (254.99999999999997) left it at 254
	 *
	 * @dataProvider colours
	 */
	public function testSampleIsRounded($rgb, $cmyk)
	{
		$im = imagecreatetruecolor(1, 1);
		imagesetpixel($im, 0, 0, imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]));
		ob_start();
		imagepng($im);
		$png = 'data:image/png;base64,' . base64_encode(ob_get_clean());

		$mpdf = $this->mpdf(['restrictColorSpace' => 3]);
		$mpdf->WriteHTML('<img src="' . $png . '" />');
		$image = end($mpdf->images);

		$this->assertSame('DeviceCMYK', $image['cs']);
		$this->assertSame($cmyk, bin2hex(gzuncompress($image['data'])));
	}

	/**
	 * RGB colours and the CMYK sample each becomes, in hex
	 *
	 * @return mixed[][]
	 */
	public function colours()
	{
		return [
			'black' => [[0, 0, 0], 'ffffffff'],
			'red' => [[255, 0, 0], '00ffff00'],
			'white' => [[255, 255, 255], '00000000'],
		];
	}

}
