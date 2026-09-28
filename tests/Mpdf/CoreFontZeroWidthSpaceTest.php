<?php

namespace Mpdf;

/**
 * Windows-1252 has no code for a zero-width space (U+200B), so core-font text carries it as
 * Mpdf::ZERO_WIDTH_SPACE_WIN1252 through line breaking and drops it before drawing.
 */
class CoreFontZeroWidthSpaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	const ZWSP = "\xe2\x80\x8b";

	/**
	 * Two words too long for the box break at the zero-width space between them, which is not drawn.
	 */
	public function testALineBreaksAtAZeroWidthSpaceWhichIsNotDrawn()
	{
		$word = str_repeat('abcdefghij', 6);
		$wordLines = ['abcdefghijabcdefghijabc', 'defghijabcdefghijabcdefg', 'hijabcdefghij'];

		$this->assertSame(array_merge($wordLines, $wordLines), $this->drawnLines('<div style="width: 40mm">' . $word . self::ZWSP . $word . '</div>'));
	}

	/**
	 * A zero-width space adds nothing to the width of the line it sits on.
	 */
	public function testAZeroWidthSpaceTakesNoWidth()
	{
		$mpdf = $this->mpdf();
		$width = $mpdf->GetStringWidth('aaaabbbb') + 0.1;

		$this->assertSame(['aaaabbbb'], $this->drawnLines('<div style="width: ' . $width . 'mm">aaaa' . self::ZWSP . 'bbbb</div>'));
	}

	/**
	 * A U+001F in the source is dropped rather than taken for the marker, so it neither breaks the line nor is drawn.
	 */
	public function testAUnitSeparatorIsNotTakenForAZeroWidthSpace()
	{
		$lines = $this->drawnLines('<div style="width: 12mm">aaaa' . "\x1f" . 'bbbb</div>');

		$this->assertSame('aaaabbbb', implode('', $lines));
		$this->assertNotSame(['aaaa', 'bbbb'], $lines);
	}

	/**
	 * A table sizes its column by the words either side of a zero-width space, so it has no need to shrink the
	 * text to fit a run that would be wider than the page.
	 */
	public function testATableMeasuresTheWordsEitherSideOfAZeroWidthSpace()
	{
		$word = str_repeat('abcdefghij', 6);
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<table><tr><td>' . $word . self::ZWSP . $word . '</td></tr></table>');

		$this->assertSame([$word, $word], $mpdf->drawnText);
		foreach ($mpdf->drawnFontSize as $size) {
			$this->assertEqualsWithDelta(11, $size, 0.001);
		}
	}

	/**
	 * A heading's bookmark leaves the zero-width space out of its title.
	 */
	public function testAHeadingBookmarkLeavesTheZeroWidthSpaceOut()
	{
		$mpdf = new Mpdf(['mode' => 'c', 'h2bookmarks' => ['H1' => 0]]);
		$mpdf->WriteHTML('<h1>Head' . self::ZWSP . 'ing</h1>');

		$this->assertSame('Heading', $mpdf->BMoutlines[0]['t']);
	}

	/**
	 * Text written straight to the page with WriteText() and WriteCell() draws without the zero-width space.
	 */
	public function testWriteTextAndWriteCellLeaveTheZeroWidthSpaceOut()
	{
		$this->assertSame(['WriteText', 'WriteCell'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->WriteText(10, 10, 'Write' . self::ZWSP . 'Text');
			$mpdf->WriteCell(50, 5, 'Write' . self::ZWSP . 'Cell', 0, 1, 'C');
		}));
	}

	/**
	 * A centred WriteCell() puts its text where it would without the zero-width space, which takes no width.
	 */
	public function testAZeroWidthSpaceDoesNotMoveCentredCellText()
	{
		$this->assertSame($this->centredCellText('WriteCell'), $this->centredCellText('Write' . self::ZWSP . 'Cell'));
	}

	/**
	 * MultiCell() breaks a line at a zero-width space as it does at a space, and draws neither.
	 */
	public function testMultiCellBreaksAtAZeroWidthSpace()
	{
		$this->assertSame(['aaaa', 'bbbb'], $this->drawnStrings(function (Mpdf $mpdf) {
			$width = $mpdf->GetStringWidth('aaaabb') + $mpdf->cMarginL + $mpdf->cMarginR;
			$mpdf->MultiCell($width, 5, 'aaaa' . self::ZWSP . 'bbbb');
		}));
	}

	/**
	 * A line MultiCell() fits whole draws without the zero-width space.
	 */
	public function testMultiCellLeavesTheZeroWidthSpaceOutOfALineThatFits()
	{
		$this->assertSame(['aaaabbbb'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->MultiCell(0, 5, 'aaaa' . self::ZWSP . 'bbbb');
		}));
	}

	/**
	 * A watermark's text draws without the zero-width space.
	 */
	public function testAWatermarkLeavesTheZeroWidthSpaceOut()
	{
		$this->assertSame(['DRAFT'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->SetWatermarkText('DRA' . self::ZWSP . 'FT');
			$mpdf->showWatermarkText = true;
		}));
	}

	/**
	 * An input's value is the field's value, so the zero-width space is left out of it rather than carried.
	 */
	public function testAnInputValueLeavesTheZeroWidthSpaceOut()
	{
		$this->assertSame(['input'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<input type="text" name="field" value="in' . self::ZWSP . 'put" />');
		}));
	}

	/**
	 * A static textarea draws its text rather than holding it as a value, so it keeps the zero-width space until its
	 * lines are broken, and breaks there.
	 */
	public function testAStaticTextareaBreaksAtAZeroWidthSpace()
	{
		$this->assertSame(['aaaaaaaa', 'bbbbbbbb'], $this->drawnLines('<textarea name="notes" cols="12" rows="2">aaaaaaaa' . self::ZWSP . 'bbbbbbbb</textarea>'));
	}

	/**
	 * Text around a circle draws its letters one at a time, and draws nothing for the zero-width space.
	 */
	public function testATextCircleLeavesTheZeroWidthSpaceOut()
	{
		$this->assertSame('TopBottom', implode('', $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<textcircle r="20mm" top-text="To' . self::ZWSP . 'p" bottom-text="Bot' . self::ZWSP . 'tom" style="font-size: 12pt" />');
		})));
	}

	/**
	 * @param string $text
	 *
	 * @return string The text operators a centred 50mm WriteCell() of $text writes
	 */
	private function centredCellText($text)
	{
		$mpdf = $this->mpdf();
		$mpdf->AddPage();
		$mpdf->WriteCell(50, 5, $text, 0, 1, 'C');
		preg_match_all('/BT .*? ET/s', $this->joinedPageContents($mpdf), $matches);

		return implode("\n", $matches[0]);
	}

	/**
	 * @param callable $draw Writes into the core-font document it is given
	 *
	 * @return string[] The text each Tj and TJ on the document's pages shows, in order
	 */
	private function drawnStrings($draw)
	{
		$mpdf = $this->mpdf();
		$mpdf->AddPage();
		$draw($mpdf);

		return $this->shownBytes($this->joinedPageContents($mpdf));
	}

	/**
	 * @param string $html
	 *
	 * @return string[] The text of each line drawn, in order
	 */
	private function drawnLines($html)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		return $mpdf->drawnText;
	}
}
