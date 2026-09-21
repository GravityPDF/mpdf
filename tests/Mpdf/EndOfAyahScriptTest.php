<?php

namespace Mpdf;

use Mpdf\Utils\UtfString;

/**
 * What ScriptRuns::END_OF_AYAH costs a document: the font a verse number is drawn in.
 *
 * It is measured against the same marker beside Arabic rather than against a font by name, so the
 * assertions hold whichever font package answers for Arabic.
 */
class EndOfAyahScriptTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ARABIC_ALEF = 0x0627;

	public function testAnAyahMarkerStandingAwayFromArabicIsDrawnInTheFontItIsGivenBesideArabic()
	{
		$marker = UtfString::code2utf(ScriptRuns::END_OF_AYAH);

		$besideLatin = $this->draw('<p>A ' . $marker . '</p>');
		$besideArabic = $this->draw('<p>' . UtfString::code2utf(self::ARABIC_ALEF) . ' ' . $marker . '</p>');

		$font = $this->fontOfTheAyah($besideLatin);

		$this->assertSame($this->fontOfTheAyah($besideArabic), $font);

		$this->assertNotSame(
			$besideLatin->drawnFontFamily[0],
			$font,
			'the marker leaves the Latin run rather than being drawn in the Latin font'
		);
	}

	/**
	 * A picture's text passes no seam that records what it was drawn in, so this reads the fonts the
	 * document loaded: with one <text> element holding the marker and nothing else that asks for a
	 * font, the picture loading the Arabic one is the marker reaching it.
	 *
	 * The Arabic branch of Image\Svg::markScriptToLang() writes a language tag that resolves to no
	 * font, so autoArabic is off here to reach the branch that marks a script; that is its own bug
	 * and not this one.
	 */
	public function testAnAyahMarkerInAPictureReachesTheSameFontAsArabicText()
	{
		$latin = $this->drawSvg('A');
		$ayah = $this->drawSvg('A ' . UtfString::code2utf(ScriptRuns::END_OF_AYAH));
		$arabic = $this->drawSvg('A ' . UtfString::code2utf(self::ARABIC_ALEF));

		$this->assertNotSame($latin, $arabic, 'Arabic text in a picture is given a font of its own');
		$this->assertSame($arabic, $ayah);
	}

	/**
	 * @param string $html
	 *
	 * @return TextRecordingMpdf
	 */
	private function draw($html)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'autoScriptToLang' => true,
			'autoLangToFont' => true,
		]);
		$mpdf->WriteHTML($html);
		$mpdf->cleanup();

		return $mpdf;
	}

	/**
	 * @param string $text What the picture's one <text> element holds
	 *
	 * @return string[] The fonts the document drew with
	 */
	private function drawSvg($text)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'autoScriptToLang' => true,
			'autoLangToFont' => true,
			'autoArabic' => false,
			'svgAutoFont' => true,
		]);
		$mpdf->WriteHTML(
			'<svg width="40mm" height="20mm" xmlns="http://www.w3.org/2000/svg">'
			. '<text x="2" y="15">' . $text . '</text></svg>'
		);
		$fonts = array_keys($mpdf->fonts);
		$mpdf->cleanup();

		return $fonts;
	}

	/**
	 * @param TextRecordingMpdf $mpdf
	 *
	 * @return string
	 */
	private function fontOfTheAyah(TextRecordingMpdf $mpdf)
	{
		$marker = UtfString::code2utf(ScriptRuns::END_OF_AYAH);

		foreach ($mpdf->drawnText as $line => $text) {
			if (false !== strpos($text, $marker)) {
				return $mpdf->drawnFontFamily[$line];
			}
		}

		$this->fail('the marker was never drawn');
	}

}
