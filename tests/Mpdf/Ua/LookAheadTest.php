<?php

namespace Mpdf\Ua;

/**
 * A look-ahead that is unwound takes back the elements and marked content it tagged.
 *
 * @group pdfua
 */
class LookAheadTest extends PdfUaTestCase
{

	/**
	 * @param StructureElement $elem
	 *
	 * @return string[] The types of the element and its descendants, in document order
	 */
	private function types(StructureElement $elem)
	{
		$types = [$elem->getType()];
		foreach ($elem->getChildren() as $child) {
			$types = array_merge($types, $this->types($child));
		}

		return $types;
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return string[] The types of the elements under Document
	 */
	private function topLevel(\Mpdf\Mpdf $mpdf)
	{
		return array_map(function (StructureElement $elem) {
			return $elem->getType();
		}, $mpdf->getPdfUaStructureTree()->getRoot()->getChildren());
	}

	/**
	 * A block kept together that ran onto the next page is laid out again there, and only that layout is tagged.
	 */
	public function testKeptBlockMovedToTheNextPageIsTaggedOnce()
	{
		$mpdf = $this->makeMpdf();
		$this->getOutput(
			$mpdf,
			'<h1>Kept</h1>' . str_repeat('<p>Filler paragraph text that takes up some room on the page.</p>', 24)
			. '<div style="page-break-inside: avoid"><p>One <a href="https://example.com">link</a></p><p>Two</p><p>Three</p><p>Four</p></div><p>After</p>'
		);

		$this->assertSame(2, $mpdf->page, 'The kept block moved onto a second page');

		$types = array_count_values($this->types($mpdf->getPdfUaStructureTree()->getRoot()));
		$this->assertSame(1, $types['Div']);
		$this->assertSame(1, $types['Link']);
		$this->assertSame(['H1'], array_slice($this->topLevel($mpdf), 0, 1));
		$this->assertSame(['Div', 'P'], array_slice($this->topLevel($mpdf), -2));

		$div = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$div = $div[count($div) - 2];
		foreach ($div->getChildren() as $p) {
			$this->assertCount(1, $p->getMcids(), 'Each paragraph has the content of one layout');
		}
	}

	/**
	 * The table of contents is painted once to learn how many pages it takes, and once for real.
	 */
	public function testTableOfContentsIsTaggedOnce()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->h2toc = ['H2' => 0];
		$this->getOutput($mpdf, '<h1>Doc</h1><tocpagebreak /><h2>One</h2><p>Text one</p><pagebreak /><h2>Two</h2><p>Text two</p>');

		$types = array_count_values($this->types($mpdf->getPdfUaStructureTree()->getRoot()));
		$this->assertSame(1, $types['TOC']);
		$this->assertSame(2, $types['TOCI']);
	}

	/**
	 * The table of contents is written last and moved to where it is printed, and read there.
	 */
	public function testTableOfContentsIsReadWhereItIsPrinted()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->h2toc = ['H2' => 0];
		$this->getOutput($mpdf, '<h1>Doc</h1><tocpagebreak /><h2>One</h2><p>Text one</p><pagebreak /><h2>Two</h2><p>Text two</p>');

		$this->assertSame(['H1', 'TOC', 'H2', 'P', 'H2', 'P'], $this->topLevel($mpdf));
	}
}
