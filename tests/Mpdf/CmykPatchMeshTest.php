<?php

namespace Mpdf;

/**
 * A blurred box shadow in a CMYK document is drawn as a patch mesh whose colours are percentages turned into bytes
 */
class CmykPatchMeshTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * 100% of an ink is a full byte, reached without the float to int conversion PHP 8.1 deprecates
	 */
	public function testFullInkIsAFullByte()
	{
		$html = '<div style="box-shadow: 1mm 1mm 1mm rgba(0, 0, 0, 0.5)">Shadow</div>';

		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['restrictColorSpace' => 3]);

		$pdf = $this->render($html, ['restrictColorSpace' => 3]);

		$this->assertSame(1, preg_match('/\/ShadingType 6.*?stream\n(.*?)\nendstream/s', $pdf, $mesh));
		$this->assertStringContainsString("\xFF\xFF\xFF\xFF", $mesh[1]); // rgba(0, 0, 0) is cmyk(100, 100, 100, 100)
		$this->assertStringNotContainsString("\xFE\xFE\xFE\xFE", $mesh[1]);
	}

}
