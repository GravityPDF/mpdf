<?php

namespace Mpdf;

use Mpdf\Css\TextVars;

/**
 * The font shorthand draws small-caps as small capitals, resets the line-height, style, weight and variant it does not
 * name, and is dropped when it names a system font.
 */
class FontShorthandResetsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * small-caps in the shorthand draws small capitals, not the text in full-size capitals
	 */
	public function testSmallCapsDrawsSmallCapitals()
	{
		$mpdf = $this->render('<style>p { font: small-caps 14pt dejavuserif }</style><p>qq</p>');

		$this->assertSame(['qq'], $mpdf->drawnText);
		$this->assertSame(TextVars::FC_SMALLCAPS, $mpdf->drawnTextvar[0] & (TextVars::FC_SMALLCAPS | TextVars::FT_UPPERCASE));
	}

	/**
	 * A shorthand with no line-height gives lines their normal height, not the one inherited
	 */
	public function testLineHeightIsResetToNormal()
	{
		$normal = $this->render('<style>p { font: 14pt dejavuserif }</style><p>qq</p>');
		$reset = $this->render('<style>div { line-height: 3 } p { font: 14pt dejavuserif }</style><div><p>qq</p></div>');

		$this->assertSame($normal->drawnLineHeight, $reset->drawnLineHeight);
		$this->assertLessThan(3 * 14 * 25.4 / 72, $reset->drawnLineHeight[0]);
	}

	/**
	 * A line-height given in the shorthand replaces the one inherited
	 */
	public function testLineHeightGivenIsKept()
	{
		$mpdf = $this->render('<style>div { line-height: 3 } p { font: 14pt/2 dejavuserif }</style><div><p>qq</p></div>');

		$this->assertEqualsWithDelta(28 * 25.4 / 72, $mpdf->drawnLineHeight[0], 0.001);
	}

	/**
	 * @dataProvider providerStyleWeightAndVariant
	 *
	 * @param string $html A document whose shorthand names no style, weight or variant, inside text that has all three
	 */
	public function testStyleWeightAndVariantAreResetToNormal($html)
	{
		$mpdf = $this->render($html);

		$this->assertSame(['qq'], $mpdf->drawnText);
		$this->assertSame([''], $mpdf->drawnFontStyle);
		$this->assertSame(0, $mpdf->drawnTextvar[0] & TextVars::FC_SMALLCAPS);
	}

	/**
	 * @return array[] Documents whose shorthand should reset the style, weight and variant it inherits
	 */
	public function providerStyleWeightAndVariant()
	{
		return [
			'block' => ['<style>div { font-style: italic; font-weight: bold; font-variant: small-caps } p { font: 14pt dejavuserif }</style><div><p>qq</p></div>'],
			'inline' => ['<style>p { font-style: italic; font-weight: bold; font-variant: small-caps } span { font: 14pt dejavuserif }</style><p><span>qq</span></p>'],
		];
	}

	/**
	 * A system font keyword, which mPDF has no font for, drops the declaration and leaves what the earlier rule set
	 */
	public function testSystemFontIsDropped()
	{
		$mpdf = $this->render('<style>p { font: italic 18pt dejavuserif } p { font: caption }</style><p>qq</p>');

		$this->assertSame(['dejavuserif'], $mpdf->drawnFontFamily);
		$this->assertSame([18.0], array_map('floatval', $mpdf->drawnFontSize));
		$this->assertSame(['I'], $mpdf->drawnFontStyle);
	}

	/**
	 * @param string $html The document
	 *
	 * @return FontStateRecordingMpdf The document written, with the state each line was drawn with
	 */
	private function render($html)
	{
		$mpdf = new FontStateRecordingMpdf();
		$mpdf->WriteHTML($html);

		return $mpdf;
	}

}
