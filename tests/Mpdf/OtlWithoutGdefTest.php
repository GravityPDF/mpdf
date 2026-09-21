<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * A font with GSUB and no GDEF is laid out as HarfBuzz lays it out, with every glyph in class 0.
 *
 * Noto Emoji is one, as most emoji fonts are. Each case is a sequence its ccmp joins into one glyph,
 * which hb-shape draws as one glyph too.
 */
class OtlWithoutGdefTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param int[] $codepoints The paragraph's text
	 *
	 * @return int[] The codepoints of the line as it was drawn
	 */
	private function drawn(array $codepoints)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../../packages/Emoji/fonts'],
			'fontdata' => ['notoemoji' => ['R' => 'NotoEmoji-Regular.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'notoemoji',
		]);
		$mpdf->WriteHTML('<p>' . implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints)) . '</p>');

		return $mpdf->drawnCodepoints(0);
	}

	/**
	 * The font's ccmp joins the sequence into one ligature, a glyph no character maps to, which is
	 * given a Private Use code
	 *
	 * @param int[] $codepoints The sequence
	 *
	 * @dataProvider sequences
	 */
	public function testASequenceIsJoinedIntoOneGlyph(array $codepoints)
	{
		$drawn = $this->drawn($codepoints);

		$this->assertCount(1, $drawn);
		$this->assertGreaterThanOrEqual(0xE000, $drawn[0], 'the ligature is a glyph no character maps to');
		$this->assertLessThanOrEqual(0xF8FF, $drawn[0]);
	}

	/**
	 * @return array[] Each sequence Noto Emoji joins into one glyph, as its codepoints
	 */
	public function sequences()
	{
		return [
			'a ZWJ family' => [[0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467]],
			'a flag' => [[0x1F1E6, 0x1F1FA]],
			// The font's keycap is the digit and U+20E3 alone, and HarfBuzz hides the selector between them
			'a keycap with its selector' => [[0x31, 0xFE0F, 0x20E3]],
			// A tag is in plane 14, which the font's cmap reaches and mPDF's widths do not
			'the flag of England' => [[0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F]],
		];
	}

	/**
	 * A presentation selector the font has no ligature for is not left behind as a glyph of its own
	 */
	public function testASelectorIsNotDrawn()
	{
		$this->assertSame([0x2764], $this->drawn([0x2764, 0xFE0F]));
	}
}
