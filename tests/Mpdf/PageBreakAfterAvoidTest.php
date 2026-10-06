<?php

namespace Mpdf;

/**
 * A block with page-break-after: avoid is kept on the page of the first line after it, in standard mode: where that
 * line, or the whole of a block or table kept together, lands on the next page, the block is laid out again at the top
 * of that page. Legacy mode keeps mPDF v7's look-ahead, which asks for room for one more line as tall as the block's own
 */
class PageBreakAfterAvoidTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;
	use DrawnStyles;

	const HEAD = '<h2 style="page-break-after: avoid">HEAD</h2>';
	const KEPT_TABLE = '<table style="page-break-inside: avoid"><tr><td>R1</td></tr><tr><td>R2</td></tr><tr><td>R3</td></tr><tr><td>R4</td></tr></table>';
	const TABLE = '<table><tr><td>R1</td></tr><tr><td>R2</td></tr><tr><td>R3</td></tr><tr><td>R4</td></tr></table>';
	const KEPT_BLOCK = '<div style="page-break-inside: avoid"><p>L1</p><p>L2</p><p>L3</p><p>L4</p><p>L5</p></div>';

	/**
	 * $n one-line paragraphs without margins; fifty-one fill an A4 page, so forty-eight leave room for a heading and a
	 * line and fifty for the heading alone
	 *
	 * @param int $n
	 *
	 * @return string
	 */
	private function lines($n)
	{
		return str_repeat('<p style="margin: 0">Filler</p>', $n);
	}

	/**
	 * Renders $html and reads back the page each piece of text other than the filler was drawn on
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return array [text => page, keyed in drawing order, the TextRecordingMpdf]
	 */
	private function drawn($html, array $config = [])
	{
		$mpdf = $this->drawDocument($html, $config);

		return [$this->pagesDrawn($mpdf), $mpdf];
	}

	/**
	 * @param TextRecordingMpdf $mpdf
	 *
	 * @return array The page each piece of text other than the filler was drawn on, keyed by the text
	 */
	private function pagesDrawn(TextRecordingMpdf $mpdf)
	{
		$pages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));
		unset($pages['Filler'], $pages['']);

		return $pages;
	}

	/**
	 * The first row of the issue's table: a table kept together moves whole to the next page and takes the heading
	 */
	public function testAHeadingMovesWithAKeptTableThatMovesToTheNextPage()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(48) . self::HEAD . self::KEPT_TABLE);

		$this->assertSame(['HEAD' => 2, 'R1' => 2, 'R2' => 2, 'R3' => 2, 'R4' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * The second row: a block kept together moves whole to the next page and takes the heading
	 */
	public function testAHeadingMovesWithAKeptBlockThatMovesToTheNextPage()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(44) . self::HEAD . self::KEPT_BLOCK);

		$this->assertSame(['HEAD' => 2, 'L1' => 2, 'L2' => 2, 'L3' => 2, 'L4' => 2, 'L5' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * The third row: the paragraph's first line does not fit under the heading, so the heading goes with it
	 */
	public function testAHeadingMovesWithTheFirstLineOfTheParagraphAfterIt()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . '<p>T1 first line</p>');

		$this->assertSame(['HEAD' => 2, 'T1 first line' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * The fourth row: the heading and the line both fit, so nothing is laid out twice
	 */
	public function testAHeadingStaysWhereTheLineAfterItFits()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(48) . self::HEAD . '<p>T1 first line</p>');

		$this->assertSame(['HEAD' => 1, 'T1 first line' => 1], $pages);
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * The line after the heading would fit, but not with the top margin of its paragraph
	 */
	public function testAHeadingMovesWithAParagraphWhoseTopMarginPushesItsLineOff()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(49) . self::HEAD . '<p style="margin-top: 10mm">T1</p>');

		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * A heading, a subheading and the paragraph's first line are one unit, laid out again from the heading
	 */
	public function testAChainOfHeadingsMovesTogether()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . '<h3 style="page-break-after: avoid">SUB</h3><p>T1</p>');

		$this->assertSame(['HEAD' => 2, 'SUB' => 2, 'T1' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * A table that is not kept together is kept with the heading by its first row: where that row starts the next page
	 * the heading goes with it, and where it fits the heading stays though the rest of the table moves
	 */
	public function testAHeadingIsKeptWithTheFirstRowOfATable()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(49) . self::HEAD . self::TABLE);
		$this->assertSame(['HEAD' => 2, 'R1' => 2, 'R2' => 2, 'R3' => 2, 'R4' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);

		list($pages, $mpdf) = $this->drawn($this->lines(48) . self::HEAD . self::TABLE);
		$this->assertSame(['HEAD' => 1, 'R1' => 1, 'R2' => 2, 'R3' => 2, 'R4' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * mpdf/mpdf#1801: a block almost a page tall could not share a fresh page with the line after it either, so it is
	 * left where it is instead of being pushed on to a blank page that it then overruns
	 */
	public function testAUnitTallerThanAPageIsLeftSplit()
	{
		$tall = '<div style="page-break-after: avoid"><img style="width: 100mm; height: 255mm" src="' . $this->backgroundImage() . '" />TALL</div>';

		list($pages, $mpdf) = $this->drawn($tall . '<p>T1</p>');

		$this->assertSame(['TALL' => 1, 'T1' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);
		$this->assertSame(2, $mpdf->page);
	}

	/**
	 * A kept block that does not fit a fresh page together with the heading moves alone, as the kept block always did
	 */
	public function testAKeptBlockTooTallToShareAPageWithTheHeadingMovesAlone()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . $this->keptBlock(27));

		$this->assertSame(['HEAD' => 1, 'Kept' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * Inside a table cell a heading is inline content and the row is what moves, so no unit is made of it
	 */
	public function testAHeadingInsideATableCellIsLeftAlone()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(52) . '<table><tr><td>' . self::HEAD . '<p>T1</p></td></tr></table>');

		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * Headings have page-break-after: avoid from the default stylesheet, so a plain heading is kept with its next too
	 */
	public function testAPlainHeadingIsKeptWithItsNext()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . '<h2>HEAD</h2><p>T1</p>');

		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * The first line after the heading is the next line drawn, wherever the blocks put it: nested in blocks of its own,
	 * in a list item, outside the block the heading ends, or past an empty block
	 */
	public function testTheLineAfterTheHeadingMayBeNested()
	{
		list($pages) = $this->drawn($this->lines(50) . self::HEAD . '<div><div><p>T1</p></div></div>');
		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);

		list($pages) = $this->drawn($this->lines(50) . self::HEAD . '<ul><li>I1</li><li>I2</li></ul>');
		$this->assertSame(['HEAD' => 2, 'I1' => 2, 'I2' => 2], $pages);

		list($pages) = $this->drawn($this->lines(50) . '<div>' . self::HEAD . '</div><p>T1</p>');
		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);

		list($pages) = $this->drawn($this->lines(50) . self::HEAD . '<div></div><p>T1</p>');
		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);
	}

	/**
	 * A paragraph closed by the next one's start tag is settled from inside that tag's opening, which then has to
	 * read its tag again from the rewound position rather than open it on the restored document
	 */
	public function testAParagraphWithoutAnEndTagMovesWithTheHeading()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . '<p>T1<p>T2');

		$this->assertSame(['HEAD' => 2, 'T1' => 2, 'T2' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * A heading inside a kept block is not a unit of its own: the kept block moves whole
	 */
	public function testAHeadingInsideAKeptBlockMovesWithTheBlock()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(44) . '<div style="page-break-inside: avoid">' . self::HEAD . '<p>L1</p><p>L2</p><p>L3</p><p>L4</p></div>');

		$this->assertSame(['HEAD' => 2, 'L1' => 2, 'L2' => 2, 'L3' => 2, 'L4' => 2], $pages);
		$this->assertSame(1, $mpdf->unwinds);
	}

	/**
	 * A forced page break wins over the avoid, as in CSS, so the heading stays at the foot of its page
	 */
	public function testAForcedBreakAfterTheHeadingLeavesItWhereItIs()
	{
		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . '<pagebreak />' . '<p>T1</p>');

		$this->assertSame(['HEAD' => 1, 'T1' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * The unit is unwound to a token of the WriteHTML() call that read the heading, so it does not reach into the next
	 * call: the heading stays, and the document is written without error
	 */
	public function testAUnitDoesNotOutliveTheWriteHtmlCall()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($this->lines(50) . self::HEAD);
		$mpdf->WriteHTML('<p>T1</p>');

		$this->assertSame(['HEAD' => 1, 'T1' => 2], $this->pagesDrawn($mpdf));
		$this->assertSame(0, $mpdf->unwinds);
	}

	/**
	 * What the first pass of the heading and the line registered is put back with the document and registered again on
	 * the page they end up on: a bookmark, an anchor and its link, an index entry and a link annotation
	 */
	public function testWhatTheFirstPassRegistersIsRegisteredOnceOnTheUnitsPage()
	{
		$pdf = $this->render($this->lines(50)
			. '<h2 style="page-break-after: avoid"><bookmark content="Marked" /><a name="target">HEAD</a><indexentry content="Term" /></h2>'
			. '<p><a href="#target">T1</a> <a href="https://example.com/kept">Link</a></p><pagebreak /><indexinsert />');
		$pages = $this->pages($pdf);
		$second = $this->pageObjects($pdf)[1];

		$this->assertCount(3, $pages);
		$this->assertOnlyOnPage(1, 1, '(HEAD)', $pages, 'the heading');
		$this->assertSame(1, substr_count($pdf, '/Title'));
		$this->assertSame(2, substr_count($pdf, '/Dest [' . $second . ' 0 R'), 'The bookmark and the anchor link should point at page 2');
		$this->assertOnlyOnPage(1, 1, '/URI (https://example.com/kept)', $this->annotations($pdf), 'the link');
		$this->assertIndexLists(2, 'Term', end($pages));
	}

	/**
	 * Legacy mode asks for room for one more line as tall as the heading's own, as mPDF v7 did: it knows nothing of
	 * what follows, so a kept table leaves it behind, while a paragraph's line is only kept where that check moves it
	 */
	public function testLegacyModeKeepsTheOneLineLookAhead()
	{
		$legacy = ['cssMode' => CssMode::LEGACY];

		list($pages, $mpdf) = $this->drawn($this->lines(48) . self::HEAD . self::KEPT_TABLE, $legacy);
		$this->assertSame(['HEAD' => 1, 'R1' => 2, 'R2' => 2, 'R3' => 2, 'R4' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);

		list($pages, $mpdf) = $this->drawn($this->lines(49) . self::HEAD . '<p>T1</p>', $legacy);
		$this->assertSame(['HEAD' => 1, 'T1' => 2], $pages);

		list($pages, $mpdf) = $this->drawn($this->lines(50) . self::HEAD . '<p>T1</p>', $legacy);
		$this->assertSame(['HEAD' => 2, 'T1' => 2], $pages);
		$this->assertSame(0, $mpdf->unwinds);
	}
}
