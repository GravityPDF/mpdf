<?php

namespace Mpdf;

/**
 * In standard mode the margin boxes along the top and bottom of a page, @top-left to @bottom-right in an @page rule,
 * are drawn as the page's header and footer, their content's strings with counter(page) and counter(pages) filled in.
 * A header or footer mPDF's own extensions set wins over them, and legacy mode draws none.
 */
class PageMarginBoxesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Three pages of body text
	 */
	const BODY = '<p>One</p><pagebreak /><p>Two</p><pagebreak /><p>Three</p>';

	/**
	 * A box's content is drawn in the header or footer of every page, centred or to the side its box names
	 */
	public function testTheBoxesAreDrawnInTheHeaderAndFooter()
	{
		$mpdf = $this->draw('@page { margin: 20mm; @top-center { content: "HEADER" } @bottom-right { content: counter(page) } @bottom-left { content: "Left" } }');

		$this->assertSame([
			[1, 'header', 'HEADER'], [1, 'footer', 'Left'], [1, 'footer', '1'],
			[2, 'header', 'HEADER'], [2, 'footer', 'Left'], [2, 'footer', '2'],
			[3, 'header', 'HEADER'], [3, 'footer', 'Left'], [3, 'footer', '3'],
		], $this->margins($mpdf));

		$boxes = $this->boxesByText($mpdf);
		$middle = $mpdf->w / 2;
		$this->assertEqualsWithDelta($middle, ($boxes['HEADER'][1] + $boxes['HEADER'][2]) / 2, 0.5, 'The top-center box is centred');
		$this->assertEqualsWithDelta(20.0, $boxes['Left'][1], 0.01, 'The bottom-left box starts at the left margin');
		$this->assertEqualsWithDelta($mpdf->w - 20, $boxes['3'][2], 0.01, 'The bottom-right box ends at the right margin');
	}

	/**
	 * counter(pages) is the number of pages in the document
	 */
	public function testCounterPagesIsTheNumberOfPages()
	{
		$mpdf = $this->draw('@page { @bottom-center { content: "Page " counter(page) " of " counter(pages) } }');

		$this->assertSame([[1, 'footer', 'Page 1 of 3'], [2, 'footer', 'Page 2 of 3'], [3, 'footer', 'Page 3 of 3']], $this->margins($mpdf));
	}

	/**
	 * A counter style gives the number in that style, without changing how the pages are numbered elsewhere
	 */
	public function testACounterStyleNumbersInThatStyle()
	{
		$mpdf = $this->draw('@page { @top-right { content: counter(page, upper-roman) "/" counter(pages, lower-alpha) " " counter(page, decimal) " " counter(page, unknown) } @bottom-center { content: "{PAGENO}" } }');

		$this->assertSame([
			[1, 'header', 'I/c 1 1'], [1, 'footer', '1'],
			[2, 'header', 'II/c 2 2'], [2, 'footer', '2'],
			[3, 'header', 'III/c 3 3'], [3, 'footer', '3'],
		], $this->margins($mpdf));
	}

	/**
	 * content: none in an @page :first rule leaves the first page without the header
	 */
	public function testContentNoneOnTheFirstPageRemovesItsHeader()
	{
		$mpdf = $this->draw('@page { @top-center { content: "HEADER" } } @page :first { @top-center { content: none } }');

		$this->assertSame([[2, 'header', 'HEADER'], [3, 'header', 'HEADER']], $this->margins($mpdf));
	}

	/**
	 * A box's properties cascade one by one through the @page rules: the :first page keeps the content and takes the
	 * colour
	 */
	public function testABoxCascadesPropertyByProperty()
	{
		$mpdf = $this->draw('@page { @top-center { content: "HEADER"; color: red } } @page :first { @top-center { color: blue } }');

		$this->assertSame([[1, 'header', 'HEADER'], [2, 'header', 'HEADER'], [3, 'header', 'HEADER']], $this->margins($mpdf));

		$colours = $this->inMargins($mpdf, $mpdf->drawnColours);
		$this->assertSame('0.000 0.000 1.000 rg', $colours[0], 'The first page takes the blue of :first');
		$this->assertSame('1.000 0.000 0.000 rg', $colours[1], 'The second page keeps the red');
	}

	/**
	 * The fonts, colour and text-align of a box style its text
	 */
	public function testTheBoxStylesItsText()
	{
		$mpdf = $this->draw('@page { margin: 20mm; @top-left { content: "Styled"; font: bold italic 14pt serif; color: #008000; text-align: right } }', ['mode' => 'c'], '<p>One</p>');

		$this->assertSame('BI', $this->inMargins($mpdf, $mpdf->drawnFontStyles)[0]);
		$this->assertSame(14.0, (float) $this->inMargins($mpdf, $mpdf->drawnFontSize)[0]);
		$this->assertSame('ctimes', $this->inMargins($mpdf, $mpdf->drawnFontFamily)[0]);
		$this->assertSame('0.000 0.502 0.000 rg', $this->inMargins($mpdf, $mpdf->drawnColours)[0]);

		$box = $this->boxesByText($mpdf)['Styled'];
		$this->assertGreaterThan(30.0, $box[1], 'text-align: right moves the text off the left margin');
	}

	/**
	 * :left and :right rules give mirrored pages their own boxes, each at the side of the page it names
	 */
	public function testLeftAndRightPagesTakeTheirOwnBoxes()
	{
		$css = '@page { margin: 20mm; } @page :right { @top-right { content: "Right page" } } @page :left { @top-left { content: "Left page" } }';
		$mpdf = $this->draw($css, ['mirrorMargins' => true]);

		$this->assertSame([[1, 'header', 'Right page'], [2, 'header', 'Left page'], [3, 'header', 'Right page']], $this->margins($mpdf));

		$boxes = $this->inMargins($mpdf, $mpdf->drawnBoxes);
		$this->assertEqualsWithDelta($mpdf->w - 20, $boxes[0][2], 0.01, 'The right page\'s box ends at its right margin');
		$this->assertEqualsWithDelta(20.0, $boxes[1][1], 0.01, 'The left page\'s box starts at its left margin');
	}

	/**
	 * A named page's rule gives the pages of an element with that page its own boxes
	 */
	public function testANamedPageTakesItsOwnBoxes()
	{
		$css = '@page { @top-center { content: "Main" } } @page appendix { @top-center { content: "Appendix " counter(page) } }';
		$mpdf = $this->draw($css, [], '<p>One</p><div style="page: appendix"><p>Two</p></div><div style="page: main2"><p>Three</p></div>');

		$this->assertSame([[1, 'header', 'Main'], [2, 'header', 'Appendix 2'], [3, 'header', 'Main']], $this->margins($mpdf));
	}

	/**
	 * A header mPDF's own extensions set wins over the margin boxes, and the boxes still draw the footer it leaves
	 *
	 * @dataProvider mpdfHeaderProvider
	 *
	 * @param string $css
	 * @param string $html
	 * @param callable|null $before Called on the document before the HTML is written
	 */
	public function testAnMpdfHeaderWins($css, $html, $before)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		if ($before) {
			$before($mpdf);
		}
		$mpdf->WriteHTML('<style>@page { @top-center { content: "Box" } @bottom-center { content: "Box footer" } } ' . $css . '</style>' . $html);
		$mpdf->Output('', 'S');

		$this->assertSame([
			[1, 'header', 'Own'], [1, 'footer', 'Box footer'],
			[2, 'header', 'Own'], [2, 'footer', 'Box footer'],
		], $this->margins($mpdf));
	}

	/**
	 * Headers set with odd-header-name, <htmlpageheader> and <sethtmlpageheader>, and SetHeader()
	 *
	 * @return array[]
	 */
	public function mpdfHeaderProvider()
	{
		$body = '<p>One</p><pagebreak /><p>Two</p>';

		return [
			'odd-header-name' => [
				'@page { odd-header-name: html_own; }',
				'<htmlpageheader name="own"><p>Own</p></htmlpageheader>' . $body,
				null,
			],
			'<sethtmlpageheader>' => [
				'',
				'<htmlpageheader name="own"><p>Own</p></htmlpageheader><sethtmlpageheader name="own" value="on" show-this-page="1" />' . $body,
				null,
			],
			'SetHeader()' => [
				'',
				$body,
				function (Mpdf $mpdf) {
					$mpdf->SetHeader('Own');
				},
			],
		];
	}

	/**
	 * A header the @page rules name on one page gives way to the margin boxes of the next page's rules
	 */
	public function testAHeaderNamedByAnEarlierPageRuleGivesWay()
	{
		$css = '@page { @top-center { content: "Box" } } @page :first { odd-header-name: html_own; }';
		$mpdf = $this->draw($css, [], '<htmlpageheader name="own"><p>Own</p></htmlpageheader>' . self::BODY);

		$this->assertSame([[1, 'header', 'Own'], [2, 'header', 'Box'], [3, 'header', 'Box']], $this->margins($mpdf));
	}

	/**
	 * A content value that is not read, such as a running header's string(), is dropped, so the one before it applies
	 */
	public function testAContentValueNotReadIsDropped()
	{
		$mpdf = $this->draw('@page { @top-center { content: "Kept"; content: string(chapter) } @bottom-center { content: element(footer) } }', [], '<p>One</p>');

		$this->assertSame([[1, 'header', 'Kept']], $this->margins($mpdf));
	}

	/**
	 * A string's escapes are read, and its text is drawn as text rather than read as HTML
	 */
	public function testAStringIsDrawnAsText()
	{
		$mpdf = $this->draw('@page { @top-center { content: "<b>A</b> \\"B\\" \\26  C\\\'s" } }', [], '<p>One</p>');

		$this->assertSame([[1, 'header', '<b>A</b> "B" & C\'s']], $this->margins($mpdf));
	}

	/**
	 * The corner and side boxes have no place in a header or footer, and are not drawn
	 */
	public function testTheCornerAndSideBoxesAreNotDrawn()
	{
		$mpdf = $this->draw('@page { @top-left-corner { content: "Corner" } @left-middle { content: "Side" } @top-center { content: "Top" } }', [], '<p>One</p>');

		$this->assertSame([[1, 'header', 'Top']], $this->margins($mpdf));
	}

	/**
	 * Legacy mode reads no margin box, and draws the pages as before
	 */
	public function testLegacyModeDrawsNoBoxes()
	{
		$mpdf = $this->draw('@page { margin: 20mm; @top-center { content: "HEADER" } @bottom-right { content: counter(page) } }', ['cssMode' => CssMode::LEGACY]);

		$this->assertSame([], $this->margins($mpdf));
		$this->assertSame(['One', 'Two', 'Three'], $mpdf->drawnText);
	}

	/**
	 * The tokenizer finds the at-rules nested in a block of declarations, and none a declaration without its semicolon
	 * takes in
	 */
	public function testTheTokenizerFindsNestedAtRules()
	{
		$tokenizer = new Css\StylesheetTokenizer();

		$this->assertSame(
			[['top-center', ' content: "{x}" '], ['bottom-left', ' color: red ']],
			$tokenizer->nestedAtRules('margin: 1mm; @top-center { content: "{x}" } size: a4; @Bottom-Left { color: red }')
		);
		$this->assertSame([], $tokenizer->nestedAtRules('margin: 1mm @top-center { content: "x" }'));
		$this->assertSame([], $tokenizer->nestedAtRules('margin: 1mm; @top-center { content: "x"'));
	}

	/**
	 * Writes the body after an @page rule and outputs the document
	 *
	 * @param string $css
	 * @param array $config Merged over the core-font mode
	 * @param string $body
	 *
	 * @return TextRecordingMpdf
	 */
	private function draw($css, array $config = [], $body = self::BODY)
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c']);
		$mpdf->WriteHTML('<style>' . $css . '</style>' . $body);
		$mpdf->Output('', 'S');

		return $mpdf;
	}

	/**
	 * The text drawn in the headers and footers, in page order, each line once
	 *
	 * @param TextRecordingMpdf $mpdf
	 *
	 * @return array[] Each [page, header or footer, text]
	 */
	private function margins(TextRecordingMpdf $mpdf)
	{
		$drawn = [];
		foreach (array_filter($mpdf->drawnIn) as $i => $where) {
			$line = [$mpdf->drawnBoxes[$i][0], $where, $mpdf->drawnText[$i]];

			// An HTML header is measured on the first page as well as drawn
			if ($line !== end($drawn)) {
				$drawn[] = $line;
			}
		}

		return $drawn;
	}

	/**
	 * What was recorded of each line drawn in a header or footer
	 *
	 * @param TextRecordingMpdf $mpdf
	 * @param array $recorded One of the recordings, such as drawnColours
	 *
	 * @return array Keyed by the line's place among all those drawn, in the order the headers and footers were drawn
	 */
	private function inMargins(TextRecordingMpdf $mpdf, array $recorded)
	{
		return array_values(array_intersect_key($recorded, array_filter($mpdf->drawnIn)));
	}

	/**
	 * The box of each line drawn in a header or footer, keyed by its text
	 *
	 * @param TextRecordingMpdf $mpdf
	 *
	 * @return array[]
	 */
	private function boxesByText(TextRecordingMpdf $mpdf)
	{
		return array_combine($this->inMargins($mpdf, $mpdf->drawnText), $this->inMargins($mpdf, $mpdf->drawnBoxes));
	}

}
