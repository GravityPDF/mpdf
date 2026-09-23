<?php

namespace Mpdf\Ua;

/**
 * The marked content written to page content streams: MarkedContentHelper, header, footer and
 * image artifacts, lists and tables, and blocks that run over a page break.
 *
 * @group pdfua
 */
class ContentStreamTest extends PdfUaTestCase
{

	/**
	 * begin('P', 5) writes "/P <</MCID 5>> BDC" to the page.
	 */
	public function testHelperWritesBdcToPageStream()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>test</p>');
		$mpdf->getPdfUaMarkedContentHelper()->begin('P', 5);
		$mpdf->getPdfUaMarkedContentHelper()->end();
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/P <</MCID 5>> BDC', $output);
	}

	/**
	 * end() writes EMC to the page.
	 */
	public function testHelperWritesEmcToPageStream()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>test</p>');
		$mpdf->getPdfUaMarkedContentHelper()->begin('P', 0);
		$mpdf->getPdfUaMarkedContentHelper()->end();
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('EMC', $output);
	}

	/**
	 * begin() with an MCID of -1 writes "/Artifact BMC", with no property dictionary.
	 */
	public function testArtifactHelperWritesBmcNoDictToPageStream()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>test</p>');
		$mpdf->getPdfUaMarkedContentHelper()->begin('Artifact', -1);
		$mpdf->getPdfUaMarkedContentHelper()->end();
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/Artifact BMC', $output);
		$this->assertStringNotContainsString('/Artifact <<', $output);
	}

	/**
	 * getDepth() follows begin() and end(), and an end() with nothing open leaves it at 0.
	 */
	public function testHelperTracksDepth()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>x</p>');

		$helper = $mpdf->getPdfUaMarkedContentHelper();

		$this->assertSame(0, $helper->getDepth());

		$helper->begin('P', 0);
		$this->assertSame(1, $helper->getDepth());

		$helper->begin('Span', 1);
		$this->assertSame(2, $helper->getDepth());

		$helper->end();
		$this->assertSame(1, $helper->getDepth());

		$helper->end();
		$this->assertSame(0, $helper->getDepth());

		$helper->end();
		$this->assertSame(0, $helper->getDepth());

		$mpdf->Output(null, 'S');
	}

	/**
	 * end() with nothing open writes no EMC.
	 */
	public function testEndMarkedContentWhenDepthIsZeroIsNoop()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>x</p>');

		$helper = $mpdf->getPdfUaMarkedContentHelper();
		$this->assertSame(0, $helper->getDepth());

		$helper->end();

		$this->assertSame(0, $helper->getDepth());

		$output = $mpdf->Output(null, 'S');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A running header is marked as a Pagination artifact of subtype Header, which needs BDC
	 * rather than BMC to carry the dictionary.
	 */
	public function testHeaderArtifactUsesBdcNotBmc()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<p>Header text</p>');
		$output = $this->getOutput($mpdf, '<p>Body text</p>');
		$this->assertStringContainsString('/Artifact <</Type /Pagination /Subtype /Header>> BDC', $output);
	}

	/**
	 * A running footer is marked as a Pagination artifact of subtype Footer.
	 */
	public function testFooterArtifactUsesBdcNotBmc()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLFooter('<p>Footer text</p>');
		$output = $this->getOutput($mpdf, '<p>Body text</p>');
		$this->assertStringContainsString('/Artifact <</Type /Pagination /Subtype /Footer>> BDC', $output);
	}

	/**
	 * The header's artifact BDC is closed by an EMC after it.
	 */
	public function testHeaderContentIsArtifactWrapped()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<p>My Header</p>');
		$output = $this->getOutput($mpdf, '<p>Body</p>');

		$bdcStr = '/Artifact <</Type /Pagination /Subtype /Header>> BDC';
		$bdcPos = strpos($output, $bdcStr);
		$this->assertNotFalse($bdcPos, 'Header BDC must appear in page stream');

		$afterBdc = substr($output, $bdcPos + strlen($bdcStr));
		$emcPos = strpos($afterBdc, 'EMC');
		$this->assertNotFalse($emcPos, 'Header EMC must appear after BDC');
	}

	/**
	 * The footer's artifact BDC is closed by an EMC after it.
	 */
	public function testFooterContentIsArtifactWrapped()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLFooter('<p>My Footer</p>');
		$output = $this->getOutput($mpdf, '<p>Body</p>');

		$bdcStr = '/Artifact <</Type /Pagination /Subtype /Footer>> BDC';
		$bdcPos = strpos($output, $bdcStr);
		$this->assertNotFalse($bdcPos, 'Footer BDC must appear in page stream');

		$afterBdc = substr($output, $bdcPos + strlen($bdcStr));
		$emcPos = strpos($afterBdc, 'EMC');
		$this->assertNotFalse($emcPos, 'Footer EMC must appear after BDC');
	}

	/**
	 * An image with alt="" is decorative and drawn as an artifact.
	 */
	public function testImageWithEmptyAltProducesArtifactBmc()
	{
		$mpdf = $this->makeMpdf();
		$imgPath = __DIR__ . '/../../data/img/issue1609.png';
		$html = '<p><img src="' . $imgPath . '" alt=""></p>';
		$output = $this->getOutput($mpdf, $html);
		$this->assertStringContainsString('/Artifact BMC', $output);
	}

	/**
	 * In auto mode an image with no alt attribute is reported, so the author can supply one.
	 */
	public function testImageMissingAltRecordsWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$imgPath = __DIR__ . '/../../data/img/issue1609.png';
		$html = '<p><img src="' . $imgPath . '"></p>';
		$this->getOutput($mpdf, $html);
		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty($warnings, 'Missing alt attribute must record a warning');
		$combined = implode(' ', $warnings);
		$this->assertStringContainsString('alt', $combined);
	}

	/**
	 * A single paragraph leaves marked content balanced.
	 */
	public function testBdcEmcBalancedSimple()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>Hello</p>');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A header and footer, spliced into the page, leave marked content balanced.
	 */
	public function testBdcEmcBalancedWithHeaders()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<p>Running header</p>');
		$mpdf->SetHTMLFooter('<p>Running footer</p>');
		$output = $this->getOutput($mpdf, '<p>Body paragraph</p>');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * In strict mode marked content left open at the end of the document throws.
	 */
	public function testUnbalancedMarkedContentDepthPositiveThrows()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$mpdf->WriteHTML('<p>test</p>');
		$mpdf->getPdfUaMarkedContentHelper()->begin('P', 0);
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/Unbalanced marked content operators/');
		$mpdf->Output(null, 'S');
	}

	/**
	 * In auto mode marked content left open at the end of the document is reported instead.
	 */
	public function testUnbalancedMarkedContentDepthPositiveWarns()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->WriteHTML('<p>test</p>');
		$mpdf->getPdfUaMarkedContentHelper()->begin('P', 0);
		$mpdf->Output(null, 'S');
		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty($warnings, 'Unbalanced depth must produce a warning in PDFUAauto=true mode');
		$combined = implode(' ', $warnings);
		$this->assertStringContainsString('Unbalanced', $combined);
	}

	/**
	 * ul is tagged L.
	 */
	public function testUlProducesLStruct()
	{
		$output = $this->getOutput($this->makeMpdf(), '<ul><li>Item</li></ul>');
		$this->assertStringContainsString('/S /L', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * ol is tagged L too, PDF having one list type for both.
	 */
	public function testOlProducesLStruct()
	{
		$output = $this->getOutput($this->makeMpdf(), '<ol><li>Item</li></ol>');
		$this->assertStringContainsString('/S /L', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * li is tagged LI, and its text is marked as its LBody's.
	 */
	public function testLiProducesLiWithLblAndLBody()
	{
		$output = $this->getOutput($this->makeMpdf(), '<ul><li>Item text</li></ul>');
		$this->assertStringContainsString('/S /LI', $output);
		$this->assertStringContainsString('/S /LBody', $output);
		$this->assertStringContainsString('/LBody <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A list inside a list item is tagged, with marked content balanced.
	 */
	public function testNestedListNestsCorrectly()
	{
		$html = '<ul><li>Outer<ul><li>Inner</li></ul></li></ul>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /L', $output);
		$this->assertStringContainsString('/S /LI', $output);
		$this->assertStringContainsString('/S /LBody', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * dl is tagged L, dt Lbl and dd LBody, with an LI made to hold each pair.
	 */
	public function testDtDdProducesImplicitLi()
	{
		$html = '<dl><dt>Term</dt><dd>Definition</dd></dl>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /L', $output);
		$this->assertStringContainsString('/S /LI', $output);
		$this->assertStringContainsString('/S /Lbl', $output);
		$this->assertStringContainsString('/S /LBody', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * table is tagged Table.
	 */
	public function testTableProducesTableStruct()
	{
		$html = '<table><tr><td>Cell</td></tr></table>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /Table', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * tr is tagged TR.
	 */
	public function testTrProducesTrStruct()
	{
		$html = '<table><tr><td>Cell</td></tr></table>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /TR', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * td is tagged TD, and its text is marked as the TD's.
	 */
	public function testTdProducesTdStruct()
	{
		$html = '<table><tr><td>Cell content</td></tr></table>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /TD', $output);
		$this->assertStringContainsString('/TD <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * th is tagged TH with a /Scope.
	 */
	public function testThProducesThStruct()
	{
		$html = '<table><tr><th>Header</th><td>Cell</td></tr></table>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/S /TH', $output);
		$this->assertStringContainsString('/Scope', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * td headers="col1 col2" becomes a Table attribute of /Headers [(col1) (col2)] on the TD
	 * (Matterhorn 09-004/09-005).
	 */
	public function testTdHeadersAttributeMapsToStructElement()
	{
		$html = '<table>'
			. '<tr><th id="col1">Col 1</th><th id="col2">Col 2</th></tr>'
			. '<tr><td headers="col1 col2">Data</td></tr>'
			. '</table>';
		$output = $this->getOutput($this->makeMpdf(), $html);
		$this->assertStringContainsString('/O /Table', $output);
		$this->assertStringContainsString('/Headers [(col1) (col2)]', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A table running over several pages keeps its cells tagged and marked content balanced.
	 */
	public function testTableRowAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$rows = '';
		for ($i = 0; $i < 60; $i++) {
			$rows .= '<tr><td>Row ' . $i . ' content that is long enough</td></tr>';
		}
		$html = '<table>' . $rows . '</table>';
		$output = $this->getOutput($mpdf, $html);
		$this->assertStringContainsString('/S /TD', $output);
		$this->assertBdcEmcBalanced($output);
		$this->assertGreaterThan(1, $mpdf->page, 'Table must span more than one page');
	}

	/**
	 * A paragraph over a page break has an MCR for each page it is on, since a bare MCID in /K can
	 * only refer to content on the element's own /Pg.
	 *
	 * @group pdfua
	 */
	public function testParagraphSplitAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p>' . str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 500) . '</p>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Paragraph must span more than one page');
		$this->assertStringContainsString(
			'/Type /MCR',
			$output,
			'Cross-page paragraph must produce MCR dicts (/Type /MCR) in struct element /K array'
		);
		preg_match_all('/\/Pg (\d+) 0 R/', $output, $pgMatches);
		$distinctPages = array_unique($pgMatches[1]);
		$this->assertGreaterThanOrEqual(
			2,
			count($distinctPages),
			'MCR dicts must reference at least two distinct page objects for a cross-page paragraph'
		);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A paragraph of several lines on one page is marked once, and its /K stays a bare MCID.
	 *
	 * @group pdfua
	 */
	public function testParagraphMultiLineSinglePageStillSingleMcid()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p>' . str_repeat('Lorem ipsum dolor sit amet. ', 8) . '</p>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertSame(1, $mpdf->page, 'Paragraph must fit on one page');
		preg_match_all('|/P <</MCID \d+>> BDC|', $output, $pBdcMatches);
		$this->assertSame(1, count($pBdcMatches[0]), 'Single-page paragraph must produce exactly one /P BDC');
		$this->assertStringNotContainsString('/Type /MCR', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A list item over a page break has an MCR for each page it is on.
	 *
	 * @group pdfua
	 */
	public function testListSplitAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$rows = '';
		for ($i = 0; $i < 40; $i++) {
			$rows .= '<li>Item ' . $i . ' — Lorem ipsum dolor sit amet, consectetur adipiscing elit.</li>';
		}
		$rows .= '<li>' . str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 60) . '</li>';
		$output = $this->getOutput($mpdf, '<ul>' . $rows . '</ul>');

		$this->assertGreaterThan(1, $mpdf->page, 'List must span more than one page');
		$this->assertStringContainsString('/Type /MCR', $output);
		preg_match_all('|/Pg (\d+) 0 R|', $output, $pgMatches);
		$distinctPages = array_unique($pgMatches[1]);
		$this->assertGreaterThanOrEqual(
			2,
			count($distinctPages),
			'MCR dicts must reference at least two distinct page objects for a cross-page list'
		);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A blockquote over a page break has an MCR for each page it is on.
	 *
	 * @group pdfua
	 */
	public function testBlockquoteSplitAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$html = '<blockquote>' . str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 500) . '</blockquote>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Blockquote must span more than one page');
		$this->assertStringContainsString('/Type /MCR', $output);
		preg_match_all('|/Pg (\d+) 0 R|', $output, $pgMatches);
		$this->assertGreaterThanOrEqual(2, count(array_unique($pgMatches[1])));
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A div with role="region", tagged Sect, has an MCR for each page it runs over.
	 *
	 * @group pdfua
	 */
	public function testDivWithRoleSplitAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$html = '<div role="region">' . str_repeat('Lorem ipsum dolor sit amet. ', 500) . '</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Div content must span more than one page');
		$this->assertStringContainsString('/Type /MCR', $output);
		preg_match_all('|/Pg (\d+) 0 R|', $output, $pgMatches);
		$this->assertGreaterThanOrEqual(2, count(array_unique($pgMatches[1])));
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A heading long enough to wrap over a page break has an MCR for each page it is on.
	 *
	 * @group pdfua
	 */
	public function testHeadingSplitAcrossPageBreakMcrDicts()
	{
		$mpdf = $this->makeMpdf();
		$html = '<h1 style="font-size:48pt">' . str_repeat('Heading text line ', 200) . '</h1>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Heading must span more than one page');
		$this->assertStringContainsString('/Type /MCR', $output);
		preg_match_all('|/Pg (\d+) 0 R|', $output, $pgMatches);
		$this->assertGreaterThanOrEqual(2, count(array_unique($pgMatches[1])));
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A paragraph inside a plain div, which is not tagged itself, keeps marked content balanced
	 * over a page break.
	 *
	 * @group pdfua
	 */
	public function testNestedBlockAcrossPageBreakBdcBalance()
	{
		$mpdf = $this->makeMpdf();
		$html = '<div><p>' . str_repeat('Lorem ipsum dolor sit amet. ', 500) . '</p></div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Nested block must span more than one page');
		$this->assertStringContainsString('/Type /MCR', $output);
		preg_match_all('|/Pg (\d+) 0 R|', $output, $pgMatches);
		$this->assertGreaterThanOrEqual(2, count(array_unique($pgMatches[1])));
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A role="presentation" div over a page break opens its artifact again on each page, and
	 * gets no struct element.
	 *
	 * @group pdfua
	 */
	public function testArtifactBlockAcrossPageBreakBmcBalance()
	{
		$mpdf = $this->makeMpdf();
		$html = '<div role="presentation">' . str_repeat('Decorative text. ', 500) . '</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertGreaterThan(1, $mpdf->page, 'Artifact div must span more than one page');
		preg_match_all('|/Artifact BMC|', $output, $bmcMatches);
		$this->assertGreaterThanOrEqual(2, count($bmcMatches[0]), 'Artifact must rebracket on each page');
		$this->assertStringNotContainsString('/S /Div', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A Figure carries a Layout attribute with its /BBox (Matterhorn 13-008).
	 *
	 * @group pdfua
	 */
	public function testFigureStructElementHasBBox()
	{
		$mpdf = $this->makeMpdf();
		$imgPath = __DIR__ . '/../../data/img/issue1609.png';
		if (!file_exists($imgPath)) {
			$this->markTestSkipped('Test image not available: ' . $imgPath);
		}
		$output = $this->getOutput(
			$mpdf,
			'<img src="' . $imgPath . '" alt="Test caption" width="100" height="50">'
		);
		$this->assertStringContainsString('/O /Layout', $output);
		$this->assertStringContainsString('/BBox', $output);
		$this->assertMatchesRegularExpression(
			'/\/BBox \[[\d\.\- ]+\]/',
			$output,
			'Figure /BBox must be a four-element number array [llx lly urx ury]'
		);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Each li has an Lbl for its bullet as well as an LBody for its content.
	 *
	 * Only a marker drawn outside the item gets an Lbl: a list-style-position of inside, no
	 * marker, or an image marker gives none, rather than an empty one.
	 *
	 * @group pdfua
	 */
	public function testLiStructureHasLblAndLBody()
	{
		$output = $this->getOutput($this->makeMpdf(), '<ul><li>First</li><li>Second</li></ul>');
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($output, '/S /LI'),
			'Each <li> must produce a /S /LI struct element'
		);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($output, '/S /LBody'),
			'Each <li> must produce a /S /LBody child struct element'
		);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($output, '/S /Lbl'),
			'Each <li> must produce a /S /Lbl child struct element for the list marker'
		);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Asserts every tagging BDC/BMC in the output has an EMC.
	 *
	 * Only artifact and MCID operators count as openings; a layer's /OC BDC is not tagging.
	 *
	 * @param string $output Raw PDF bytes
	 * @return void
	 */
	private function assertBdcEmcBalanced($output)
	{
		preg_match_all('|/Artifact <</Type /Pagination[^>]*>> BDC|', $output, $paginationBdc);
		preg_match_all('|/Artifact BMC\b|', $output, $artifactBmc);
		preg_match_all('|/\w+ <</MCID \d+>> BDC\b|', $output, $structBdc);
		preg_match_all('/\bEMC\b/', $output, $emcMatches);

		$opens = count($paginationBdc[0]) + count($artifactBmc[0]) + count($structBdc[0]);
		$closes = count($emcMatches[0]);

		$this->assertSame(
			$opens,
			$closes,
			sprintf(
				'BDC+BMC count (%d) must equal EMC count (%d). Pagination BDC: %d, Artifact BMC: %d, Struct BDC: %d, EMC: %d',
				$opens,
				$closes,
				count($paginationBdc[0]),
				count($artifactBmc[0]),
				count($structBdc[0]),
				$closes
			)
		);
	}
}
