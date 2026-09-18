<?php

namespace Mpdf;

/**
 * What the Myanmar shaper makes of a consonant stacked under its base.
 *
 * Otl::_applyGSUBrulesMyanmar() is reached only by a font offering the mym2 script - one offering the
 * older mymr goes to the generic path, which shaperForScriptTag() settles - and none of the fonts mPDF
 * bundles offers it. So the walk had no test and no fixture: the four golden masters called it 42
 * times across the corpus and every one of those calls found an empty Lookup list.
 *
 * Myanmar-Mym2Script-Synthetic is a nine-glyph subset of the Padauk Book in packages/Myanmar-Bundle
 * with its GSUB replaced: the script is retagged mym2, and the below-base forms the original draws
 * through Graphite are stated as the two features the Myanmar shaper applies in turn - blwf, in two
 * Lookups, and pstf - each ligating a Virama with the consonant after it. `hb-shape` draws what every
 * case below expects.
 *
 * It carries a name of its own rather than the donor's: OFL 1.1 clause 3 bars a modified version from
 * using a Reserved Font Name, and LICENSE-Padauk.txt reserves "Padauk" (#237). The SIL copyright the
 * licence does require stays in the name table.
 *
 * Name ID 6 keeps the donor's shape: the UTF-8 bytes of a Burmese name zero-extended into UTF-16BE
 * code units, which is how Padauk Book states its PostScript name and why TTFontFile reads it as
 * invalid and falls back to name ID 4 - "PadaukBook contains illegal characters in Name ID 6",
 * src/TTFontFile.php:719. It is the only font in tests/data/ttf that reaches that fallback, so the
 * name is malformed on purpose. Its code units stay under 256, as the donor's are, because a name
 * string above U+00FF is truncated as it is read (#238).
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
			'fontdata' => ['myanmarmym2scriptsynthetic' => [
				'R' => 'Myanmar-Mym2Script-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'myanmarmym2scriptsynthetic',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
