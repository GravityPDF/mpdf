<?php

namespace Mpdf;

/**
 * A lookup flag that sets IgnoreMarks and also names a mark attachment class skips every mark, the
 * ones in the class included: "If the IGNORE_MARKS bit is set, this supersedes any mark filtering set
 * or mark attachment class indications."
 *
 * No font in the corpus sets both, so NotoSansCoptic-IgnoreMarksClass-Synthetic is
 * NotoSansCoptic-GSUB81-Subset with one flag changed. Its second 'ccmp' Type 6 gives an overline the
 * `.cap` form where the overline before it has one, skipping the letters between; its flag was
 * IgnoreBaseGlyphs, IgnoreLigatures and class 1 (0x0106), which holds every overline, and the
 * synthetic font adds IgnoreMarks (0x010E). The overline the lookup covers is then skipped too, so
 * the lookup cannot match. `hb-shape` draws the same glyphs from both fonts as these tests expect.
 */
class IgnoreMarksAttachmentClassTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SHEI = 0x03E3;

	const SHEI_CAPITAL = 0x03E2;

	const OVERLINE = 0x0305;

	/** The overline variants have no codepoint, so they are mapped into the Private Use Area */
	const OVERLINE_XLARGE = 0xE003;

	const OVERLINE_XLARGE_CAP = 0xE008;

	const OVERLINE_XXLARGE_CAP = 0xE009;

	const OVERLINE_CAP = 0xE00A;

	/**
	 * @param string $font
	 * @param int[]  $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
	 */
	private function drawn($font, $codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		// The parsed font is cached under its key, so the two fonts must not share one
		$fontkey = strtolower(str_replace('-', '', $font));
		$mpdf = new TextRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$fontkey => [
				'R' => $font . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => $fontkey,
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	public function dataRuns()
	{
		return [
			'an overline after the capital\'s' => [
				[self::SHEI_CAPITAL, self::OVERLINE, self::OVERLINE],
				[self::SHEI_CAPITAL, self::OVERLINE_XXLARGE_CAP, self::OVERLINE_CAP],
				[self::SHEI_CAPITAL, self::OVERLINE_XXLARGE_CAP, self::OVERLINE],
			],
			'an overline across a letter' => [
				[self::SHEI_CAPITAL, self::OVERLINE, self::SHEI, self::OVERLINE],
				[self::SHEI_CAPITAL, self::OVERLINE_XXLARGE_CAP, self::SHEI, self::OVERLINE_XLARGE_CAP],
				[self::SHEI_CAPITAL, self::OVERLINE_XXLARGE_CAP, self::SHEI, self::OVERLINE_XLARGE],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testTheClassAloneLetsTheLookupMatchItsOwnMarks($codepoints, $withClass)
	{
		$this->assertSame($withClass, $this->drawn('NotoSansCoptic-GSUB81-Subset', $codepoints));
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testIgnoreMarksSkipsTheMarksInTheClass($codepoints, $withClass, $withIgnoreMarks)
	{
		$this->assertSame($withIgnoreMarks, $this->drawn('NotoSansCoptic-IgnoreMarksClass-Synthetic', $codepoints));
	}

}
