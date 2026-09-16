<?php

namespace Mpdf;

/**
 * The hyphen mPDF adds when it breaks a word across a line was not in the paragraph the bidi pass
 * resolved, so it carries whatever direction and embedding level WriteFlowingBlock() gives it. Given
 * none, it read as level 0 - a left-to-right run of its own - and the reordering pass moved it out of
 * the word it broke to the far end of the line. See GravityPDF/mpdf#116.
 *
 * TextRecordingMpdf hands back each line as it is drawn, which is after the reordering, so the
 * strings here read left to right whichever way the text runs.
 */
class LineBreakHyphenTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SHY = "\xc2\xad"; // U+00AD SOFT HYPHEN

	/**
	 * U+05D0 to U+05D9, the first ten letters of the Hebrew alphabet - a plain right-to-left run,
	 * with nothing that joins or reorders. Spelt out in bytes because the \u{} escape needs PHP 7.0
	 * and this suite still runs on 5.6.
	 */
	const HEBREW_WORD = "\xD7\x90\xD7\x91\xD7\x92\xD7\x93\xD7\x94\xD7\x95\xD7\x96\xD7\x97\xD7\x98\xD7\x99";

	/**
	 * A soft hyphen broken at becomes a hard one, at the end of a right-to-left line - which is its
	 * left-hand end.
	 */
	public function testASoftHyphenBreakIsDrawnAtTheEndOfTheLineItBroke()
	{
		$word = self::HEBREW_WORD . self::SHY . self::HEBREW_WORD;
		$half = $this->reverse(self::HEBREW_WORD);

		$lines = $this->drawnLines('<div dir="rtl" style="font-family: dejavusanscondensed; font-size: 14pt">' . $word . '</div>', [60, 100]);

		$this->assertSame(['-' . $half, $half], $lines);
	}

	/**
	 * A left-to-right word inside a right-to-left paragraph reads in its own direction, and so does
	 * the hyphen the hyphenator breaks it with.
	 */
	public function testAnAutomaticHyphenIsDrawnBesideTheWordItBroke()
	{
		$html = '<div dir="rtl" style="font-family: dejavusanscondensed; font-size: 14pt; hyphens: auto">'
			. self::HEBREW_WORD . ' establishment</div>';

		$lines = $this->drawnLines($html, [80, 100]);

		$this->assertSame(['establish- ' . $this->reverse(self::HEBREW_WORD), 'ment'], $lines);
	}

	/**
	 * @return string[] the text of each line, in the order it is drawn in
	 */
	private function drawnLines($html, $format)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'format' => $format]);
		$mpdf->WriteHTML($html);
		$lines = $mpdf->drawnText;
		$mpdf->cleanup();

		return $lines;
	}

	private function reverse($text)
	{
		return implode('', array_reverse(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY)));
	}

}
