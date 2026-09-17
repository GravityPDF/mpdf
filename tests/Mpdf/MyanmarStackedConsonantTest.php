<?php

namespace Mpdf;

/**
 * What the Myanmar shaper makes of a consonant stacked under its base.
 *
 * Otl::_applyGSUBrulesMyanmar() is reached only by a font offering the mym2 script - one offering the
 * older mymr goes to the generic path, which shaperForScriptTag() settles - and no font in
 * tests/data/ttf or in packages/Myanmar-Bundle offered it, so the walk had neither a test nor a
 * fixture.
 *
 * Tharlon-Mym2Script-Synthetic is a nine-glyph subset of TharLon 1.002 (OFL 1.1, no reserved font
 * names, from packages/Myanmar-Bundle) with its GSUB replaced: the script is retagged mym2, and the
 * below-base forms the original draws through a chained context are stated as the two features the
 * Myanmar shaper applies in turn - blwf, in two Lookups, and pstf - each ligating a Virama with the
 * consonant after it. `hb-shape` draws what every case below expects.
 */
class MyanmarStackedConsonantTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1000 MYANMAR LETTER KA */
	const KA = 0x1000;

	/** U+1039 MYANMAR SIGN VIRAMA, which stacks the consonant after it under the one before */
	const VIRAMA = 0x1039;

	/** U+1050 MYANMAR LETTER SHA */
	const SHA = 0x1050;

	/** U+1051 MYANMAR LETTER SSA */
	const SSA = 0x1051;

	/** The stacked forms have no codepoint, so the font maps them into the Private Use Area */
	const STACKED_KA = 0xE000;

	const STACKED_SHA = 0xE001;

	const STACKED_SSA = 0xE002;

	public function dataRuns()
	{
		return [
			'the first Lookup of blwf' => [
				[self::KA, self::VIRAMA, self::KA],
				[self::KA, self::STACKED_KA],
			],
			'the second Lookup of blwf, which the first does not cover' => [
				[self::KA, self::VIRAMA, self::SHA],
				[self::KA, self::STACKED_SHA],
			],
			'pstf, the pass after blwf' => [
				[self::KA, self::VIRAMA, self::SSA],
				[self::KA, self::STACKED_SSA],
			],
			'two stacks in one word, each in its own syllable' => [
				[self::KA, self::VIRAMA, self::KA, self::KA, self::VIRAMA, self::SHA],
				[self::KA, self::STACKED_KA, self::KA, self::STACKED_SHA],
			],
			'a consonant no feature stacks' => [
				[self::KA, self::KA],
				[self::KA, self::KA],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheStackedFormTheFontStatesIsTheOneDrawn($codepoints, $expected)
	{
		$this->assertSame($expected, $this->drawn($codepoints));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['tharlonmym2scriptsynthetic' => [
				'R' => 'Tharlon-Mym2Script-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'tharlonmym2scriptsynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
