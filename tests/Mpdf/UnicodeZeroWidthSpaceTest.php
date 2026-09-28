<?php

namespace Mpdf;

use setasign\Fpdi\PdfParser\Type\PdfString;

/**
 * In a Unicode font a zero-width space (U+200B) is a place a line may break, and is never drawn: MultiCell() breaks
 * there as WriteHTML() does, and Cell() and Text() leave it out of what they draw.
 */
class UnicodeZeroWidthSpaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	const ZWSP = "\xe2\x80\x8b";

	/**
	 * Two words too wide for the cell together break at the zero-width space between them.
	 */
	public function testALineBreaksAtAZeroWidthSpace()
	{
		$this->assertSame(['aaaa', 'bbbb'], $this->drawnLines('aaaa' . self::ZWSP . 'bbbb', 'aaaabb'));
	}

	/**
	 * A justified cell breaks at the zero-width space as a left-aligned one does.
	 */
	public function testAJustifiedLineBreaksAtAZeroWidthSpace()
	{
		$this->assertSame(['aaaa', 'bbbb'], $this->drawnLines('aaaa' . self::ZWSP . 'bbbb', 'aaaabb', 'J'));
	}

	/**
	 * A zero-width space takes no width, so words that fit together stay on one line.
	 */
	public function testWordsThatFitStayOnOneLine()
	{
		$this->assertSame(['aaaa' . self::ZWSP . 'bbbb'], $this->drawnLines('aaaa' . self::ZWSP . 'bbbb', 'aaaabbbbb'));
	}

	/**
	 * A zero-width space takes no width in a font with no glyph for it either, so words that fit together stay on
	 * one line.
	 */
	public function testAZeroWidthSpaceTakesNoWidthInAFontWithoutAGlyphForIt()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->AddPage();
		$mpdf->SetFont('dejavusansmono', '', 11);
		$mpdf->MultiCell($this->cellWidth($mpdf, 'aaaabbbb') + 0.1, 5, 'aaaa' . self::ZWSP . 'bbbb');

		$this->assertSame(['aaaa' . self::ZWSP . 'bbbb'], $mpdf->drawnText);
	}

	/**
	 * A justified line shares its spare width among the characters it draws, so a zero-width space inside the line
	 * leaves it set exactly as it would be without one.
	 */
	public function testAJustifiedLineSpreadsOnlyTheCharactersItDraws()
	{
		$justify = function ($text) {
			return function (Mpdf $mpdf) use ($text) {
				$mpdf->MultiCell(40, 5, $text, 0, 'J');
			};
		};

		$this->assertSame(
			$this->drawnPage($justify('Zerowidth spaces are drawn as nothing at all')),
			$this->drawnPage($justify('Zero' . self::ZWSP . 'width spaces are drawn as nothing at all'))
		);
	}

	/**
	 * Write() breaks a line at a zero-width space as MultiCell() does.
	 */
	public function testWriteBreaksAtAZeroWidthSpace()
	{
		$word = str_repeat('abcdefghij', 6);
		$mpdf = new TextRecordingMpdf();
		$mpdf->AddPage();
		$mpdf->SetFont('dejavusans', '', 11);
		$mpdf->Write(5, $word . self::ZWSP . $word);

		$this->assertSame([$word, $word], $mpdf->drawnText);
	}

	/**
	 * Right-to-left text breaks at the zero-width space too, and each line keeps its own letters.
	 */
	public function testRightToLeftTextBreaksAtAZeroWidthSpace()
	{
		$this->assertSame(['םולש', 'םלוע'], $this->drawnLines('שלום' . self::ZWSP . 'עולם', 'שלוםע', '', 'rtl'));
	}

	/**
	 * Text written with WriteText() and WriteCell() draws without the zero-width space.
	 */
	public function testWriteTextAndWriteCellLeaveTheZeroWidthSpaceOut()
	{
		$this->assertSame(['WriteText', 'WriteCell'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->WriteText(10, 10, 'Write' . self::ZWSP . 'Text');
			$mpdf->WriteCell(50, 5, 'Write' . self::ZWSP . 'Cell', 0, 1, 'C');
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
	 * Right-to-left text is laid out before it is drawn, and loses the zero-width space from that layout too, so the
	 * letters either side keep their places.
	 */
	public function testRightToLeftTextLeavesTheZeroWidthSpaceOut()
	{
		$this->assertSame(['םלועםולש'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->SetDirectionality('rtl');
			$mpdf->MultiCell(0, 5, 'שלום' . self::ZWSP . 'עולם');
		}));
	}

	/**
	 * A static textarea draws its text in a monospaced font that has no glyph for U+200B, so drawing the character
	 * would draw a box.
	 */
	public function testAStaticTextareaLeavesTheZeroWidthSpaceOut()
	{
		$this->assertSame(['Zerowidth'], $this->drawnStrings(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<textarea name="notes">Zero' . self::ZWSP . 'width</textarea>');
		}));
	}

	/**
	 * An active textarea keeps the zero-width space in its value, which a Unicode string can hold, and leaves it out
	 * of the appearance that draws the value.
	 */
	public function testAnActiveTextareaKeepsTheZeroWidthSpaceInItsValueOnly()
	{
		$mpdf = $this->unicodeMpdf();
		$mpdf->useActiveForms = true;
		$mpdf->WriteHTML('<textarea name="notes">Zero' . self::ZWSP . 'width</textarea>');
		$pdf = $this->output($mpdf);

		preg_match('#/V \((.*?)\)\s*/DV#s', $pdf, $value);
		$this->assertSame('Zero' . self::ZWSP . 'width', mb_convert_encoding(substr(PdfString::unescape($value[1]), 2), 'UTF-8', 'UTF-16BE'));

		preg_match('#/AP << /N (\d+) 0 R#', $pdf, $appearance);
		$this->assertSame(['Zerowidth'], $this->shownStrings($this->object($pdf, $appearance[1])));
	}

	/**
	 * A heading's bookmark leaves the zero-width space out of its title, as it does in core fonts.
	 */
	public function testAHeadingBookmarkLeavesTheZeroWidthSpaceOut()
	{
		$mpdf = $this->mpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans', 'h2bookmarks' => ['H1' => 0]]);
		$mpdf->WriteHTML('<h1>Head' . self::ZWSP . 'ing</h1>');

		$this->assertSame('Heading', $mpdf->BMoutlines[0]['t']);
	}

	/**
	 * @param callable $draw Writes into the Unicode document it is given
	 *
	 * @return string[] The text each Tj and TJ on the page draws, as UTF-8
	 */
	private function drawnStrings($draw)
	{
		$mpdf = $this->unicodeMpdf();
		$mpdf->AddPage();
		$draw($mpdf);

		return $this->shownStrings($this->joinedPageContents($mpdf));
	}

	/**
	 * @param callable $draw Writes into the Unicode document it is given
	 *
	 * @return string The content streams of the document's pages
	 */
	private function drawnPage($draw)
	{
		$mpdf = $this->unicodeMpdf();
		$mpdf->AddPage();
		$draw($mpdf);

		return $this->joinedPageContents($mpdf);
	}

	/**
	 * @return Mpdf An uncompressed document in DejaVu Sans
	 */
	private function unicodeMpdf()
	{
		return $this->mpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans']);
	}

	/**
	 * @param string $stream
	 *
	 * @return string[] The text each Tj and TJ in $stream draws, as UTF-8: mPDF writes a Unicode font's text as
	 * UTF-16BE code points
	 */
	private function shownStrings($stream)
	{
		return array_map(function ($bytes) {
			return mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
		}, $this->shownBytes($stream));
	}

	/**
	 * @param string $text
	 * @param string $fits Text exactly as wide as the cell's content
	 * @param string $align
	 * @param string $directionality
	 *
	 * @return string[] The text of each line drawn, in order
	 */
	private function drawnLines($text, $fits, $align = '', $directionality = 'ltr')
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->AddPage();
		$mpdf->SetFont('dejavusans', '', 11);
		$mpdf->SetDirectionality($directionality);
		$mpdf->MultiCell($this->cellWidth($mpdf, $fits), 5, $text, 0, $align);

		return $mpdf->drawnText;
	}

	/**
	 * @param Mpdf $mpdf
	 * @param string $fits
	 *
	 * @return float The width of a cell whose content is as wide as $fits
	 */
	private function cellWidth(Mpdf $mpdf, $fits)
	{
		return $mpdf->GetStringWidth($fits) + $mpdf->cMarginL + $mpdf->cMarginR;
	}
}
