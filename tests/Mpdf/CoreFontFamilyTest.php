<?php

namespace Mpdf;

/**
 * CSS can name a core font, ccourier, ctimes or chelvetica, in a UTF-8 document as well as in a core-only one.
 */
class CoreFontFamilyTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each way of naming a core font in CSS draws the text in that font rather than the default one
	 *
	 * @dataProvider coreFontFamilies
	 *
	 * @param string $html
	 * @param string $baseFont the core font expected in the document
	 */
	public function testCssNamesACoreFontInAUtf8Document($html, $baseFont)
	{
		$mpdf = new Mpdf(['mode' => 'utf-8', 'compress' => false]);
		$mpdf->WriteHTML($html);

		$this->assertStringContainsString('/BaseFont /' . $baseFont . "\n", $mpdf->OutputBinaryData());
	}

	/**
	 * HTML naming a core font through an inline style, a span, a style sheet and a font-family list
	 *
	 * @return array
	 */
	public function coreFontFamilies()
	{
		return [
			'helvetica in a style attribute' => ['<p style="font-family: chelvetica">Hello</p>', 'Helvetica'],
			'times in a style attribute' => ['<p style="font-family: ctimes">Hello</p>', 'Times-Roman'],
			'courier on a span' => ['<p><span style="font-family: ccourier">Hello</span></p>', 'Courier'],
			'helvetica in a style sheet' => ['<style>p { font-family: chelvetica; }</style><p>Hello</p>', 'Helvetica'],
			'helvetica after an unknown font' => ['<p style="font-family: nosuchfont, chelvetica">Hello</p>', 'Helvetica'],
		];
	}

}
