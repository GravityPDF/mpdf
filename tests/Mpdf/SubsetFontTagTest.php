<?php

namespace Mpdf;

use Mpdf\Conversion\DecToAlpha;
use Mpdf\Utils\UtfString;

/**
 * A font of Supplementary Ideographic Plane characters is written as a run of subsets of up to 255
 * characters each, and each one's name carries a tag: MPDFAA, MPDFAB and on, which the writer used
 * to make by incrementing a string. GravityPDF/mpdf#316 has them counted from an integer instead,
 * since PHP 8.3 deprecates incrementing a string; the names have to come out as they did.
 */
class SubsetFontTagTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Twenty-eight subsets run AA to AZ, then carry into BA and BB, as incrementing 'AA' did
	 */
	public function testTheTagsCarryPastZ()
	{
		// 27 full subsets of CJK Extension B and a few characters into the 28th
		$text = '';
		for ($c = 0x20000; $c < 0x20000 + 255 * 27 + 10; $c++) {
			$text .= UtfString::code2utf($c);
		}

		$mpdf = new Mpdf(['mode' => 'utf-8', 'compress' => false]);
		$mpdf->WriteHTML('<p style="font-family: sun-extb">' . $text . '</p>');
		$pdf = $mpdf->OutputBinaryData();
		$mpdf->cleanup();

		preg_match_all('/\/BaseFont \/MPDF([A-Z]+)\+/', $pdf, $matches);

		$expected = [];
		foreach (range('A', 'Z') as $letter) {
			$expected[] = 'A' . $letter;
		}
		array_push($expected, 'BA', 'BB');

		$this->assertSame($expected, array_values(array_unique($matches[1])));
	}

	/**
	 * The letter counter the writer names subsets from, taken from 27, is the increment of the tag
	 * before it all the way through ZZ into AAA, where a document's subsets stop long before
	 */
	public function testTheCounterFromAaIsTheStringIncrement()
	{
		$decToAlpha = new DecToAlpha();

		$tag = 'AA';
		for ($index = 0; $index < 1000; $index++) {
			$this->assertSame($tag, $decToAlpha->convert($index + 27));
			$tag = function_exists('str_increment') ? str_increment($tag) : ++$tag;
		}
	}

}
