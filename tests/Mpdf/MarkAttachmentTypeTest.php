<?php

namespace Mpdf;

/**
 * A lookup flag can name a mark attachment class, which means "skip every mark that is not in it".
 * Which marks those are is GDEF's MarkAttachClassDef to say, and a font may set the flag without
 * defining that table at all - Carlito and NATS both do. No mark is then in the named class, so a
 * lookup carrying the flag skips all of them.
 *
 * mPDF read the class out of MarkAttachmentType, got null for a class the table never built, and
 * skipped nothing. Carlito's 'ccmp' replaces i and j with their dotless forms before a mark above,
 * and that rule fired - where HarfBuzz, reading the same flag, leaves the dot on.
 */
class MarkAttachmentTypeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0069 LATIN SMALL LETTER I, which the font's 'ccmp' covers */
	const I = 0x0069;

	/** U+0313 COMBINING COMMA ABOVE, one of the marks that rule looks ahead for */
	const COMMA_ABOVE = 0x0313;

	/**
	 * Draw the characters in Carlito cut down to the letters its flagged 'ccmp' lookups cover and the
	 * one mark, which keeps GDEF's lack of a MarkAttachClassDef along with the flag that names one.
	 *
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
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
			'fontdata' => ['markattachment' => [
				'R' => 'Carlito-MarkAttachmentType-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'markattachment',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The two characters draw as themselves: the lookup that would replace the i skips the mark its
	 * lookahead needs, so it cannot match. `hb-shape` draws the same pair of glyphs.
	 */
	public function testALookupNamingAnUndefinedMarkAttachmentClassSkipsTheMarkItLooksAheadFor()
	{
		$this->assertSame([self::I, self::COMMA_ABOVE], $this->drawn([self::I, self::COMMA_ABOVE]));
	}

}
