<?php

namespace Mpdf\Ua;

/**
 * Headings and lists inside a table cell are tagged beneath its TD, each with its own marked
 * content, and the headings count towards the document's heading sequence
 *
 * @group pdfua
 */
class TableCellStructureTest extends PdfUaTestCase
{

	/**
	 * A heading in a cell is an H2 beneath the TD.
	 */
	public function testHeadingInCellProducesH2UnderTd()
	{
		$mpdf = $this->makeMpdf();
		$html = '<h1>Doc heading</h1>'
			. '<table><tr><td><h2>Cell heading</h2><p>Body</p></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /H2', $output);
		$this->assertTdHasChildOfType($mpdf, 'H2');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A list in a cell is an L beneath the TD, with an LI for each item.
	 */
	public function testListInCellProducesListStructureUnderTd()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table><tr><td><ul><li>Item one</li><li>Item two</li></ul></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /L', $output);
		$this->assertStringContainsString('/S /LI', $output);

		$l = $this->assertTdHasChildOfType($mpdf, 'L');
		$liChildren = 0;
		foreach ($l->getChildren() as $child) {
			if ($child->getType() === 'LI') {
				$liChildren++;
			}
		}
		$this->assertSame(2, $liChildren, 'the <ul> L element must contain one LI per <li>');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A heading and a list in one cell are both children of its TD.
	 */
	public function testHeadingAndListCoexistUnderSameCell()
	{
		$mpdf = $this->makeMpdf();
		$html = '<h1>Doc heading</h1>'
			. '<table><tr><td><h2>Cell heading</h2>'
			. '<ul><li>Item one</li><li>Item two</li></ul></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$td = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'TD');
		$this->assertNotNull($td, 'a TD struct element must exist');

		$childTypes = [];
		foreach ($td->getChildren() as $child) {
			$childTypes[] = $child->getType();
		}
		$this->assertContains('H2', $childTypes, 'the cell heading must be a TD child');
		$this->assertContains('L', $childTypes, 'the cell list must be a TD child');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * The heading and each list item's LBody mark their own content, leaving the TD none of its own.
	 */
	public function testNestedCellElementsOwnTheirMarkedContent()
	{
		$mpdf = $this->makeMpdf();
		$html = '<h1>Doc heading</h1>'
			. '<table><tr><td><h2>Cell heading</h2>'
			. '<ul><li>Item one</li><li>Item two</li></ul></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/H2 <</MCID', $output);
		$this->assertSame(
			2,
			substr_count($output, '/LBody <</MCID'),
			'each <li> must own a distinct LBody marked-content sequence'
		);
		$this->assertStringNotContainsString('/TD <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A list nested in a list item sits in the item's LBody, as LI content must.
	 */
	public function testNestedListInCellWrapsInnerListInLBody()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table><tr><td><ul><li>outer'
			. '<ul><li>inner one</li><li>inner two</li></ul></li></ul></td></tr></table>';
		$this->getOutput($mpdf, $html);

		$outerL = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($outerL);
		$outerLi = $this->firstChildOfType($outerL, 'LI');
		$this->assertNotNull($outerLi, 'outer L must contain an LI');
		$lbody = $this->firstChildOfType($outerLi, 'LBody');
		$this->assertNotNull($lbody, 'the LI content must live in an LBody, not directly under LI');
		$this->assertNotNull(
			$this->firstChildOfType($lbody, 'L'),
			'the nested <ul> must be an L inside the LBody'
		);
	}

	/**
	 * Each term and definition of a definition list in a cell are an Lbl and LBody in an LI of
	 * their own, even when the end tags are left out.
	 */
	public function testDefinitionListInCellWrapsTermsInImplicitLi()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table><tr><td><dl><dt>Term one<dd>Def one'
			. '<dt>Term two<dd>Def two</dl></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$l = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($l);
		$liCount = 0;
		foreach ($l->getChildren() as $child) {
			$this->assertSame('LI', $child->getType(), 'an L may contain only LI (and L/Caption) children');
			$liCount++;
			$kinds = [];
			foreach ($child->getChildren() as $g) {
				$kinds[] = $g->getType();
			}
			$this->assertSame(['Lbl', 'LBody'], $kinds, 'each implicit LI holds the term (Lbl) and definition (LBody)');
		}
		$this->assertSame(2, $liCount, 'two dt/dd pairs → two implicit LI items');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Without PDFUAauto, an H2 in a cell as the first heading of the document throws.
	 */
	public function testCellHeadingParticipatesInSequenceStrictThrows()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/first heading in the document must be H1/');
		$mpdf->WriteHTML('<table><tr><td><h2>Cell heading</h2></td></tr></table>');
	}

	/**
	 * With PDFUAauto, an H2 in a cell as the first heading becomes an H1, with a warning.
	 */
	public function testCellHeadingParticipatesInSequenceAutoPromotes()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<table><tr><td><h2>Cell heading</h2></td></tr></table>');

		$this->assertStringContainsString('/S /H1', $output);
		$this->assertStringNotContainsString('/S /H2', $output);

		$warnings = implode("\n", $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString('first heading must be H1', $warnings);
	}

	/**
	 * A table whose cell holds a heading and a list keeps its Table and TD along with them.
	 */
	public function testHeadingAndListInCellRemainsWellFormed()
	{
		$mpdf = $this->makeMpdf();
		$html = '<h1>Doc heading</h1>'
			. '<table border="1"><tr><td><h2>Cell heading</h2>'
			. '<ul><li>Item one</li><li>Item two</li></ul></td></tr></table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /Table', $output);
		$this->assertStringContainsString('/S /TD', $output);
		$this->assertStringContainsString('/S /H2', $output);
		$this->assertStringContainsString('/S /LI', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Assert the first TD in the tree has a child of the type.
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param string     $type
	 *
	 * @return \Mpdf\Ua\StructureElement That child
	 */
	private function assertTdHasChildOfType(\Mpdf\Mpdf $mpdf, $type)
	{
		$td = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'TD');
		$this->assertNotNull($td, 'a TD struct element must exist');
		foreach ($td->getChildren() as $child) {
			if ($child->getType() === $type) {
				return $child;
			}
		}
		$this->fail('TD must have a direct ' . $type . ' child');
	}

	/**
	 * @param \Mpdf\Ua\StructureElement $node
	 * @param string                    $type
	 *
	 * @return \Mpdf\Ua\StructureElement|null The first child of the node of that type
	 */
	private function firstChildOfType($node, $type)
	{
		foreach ($node->getChildren() as $child) {
			if ($child->getType() === $type) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * @param \Mpdf\Ua\StructureElement $node
	 * @param string                    $type
	 *
	 * @return \Mpdf\Ua\StructureElement|null The first element of that type below the node, depth first
	 */
	private function findFirstOfType($node, $type)
	{
		foreach ($node->getChildren() as $child) {
			if ($child->getType() === $type) {
				return $child;
			}
			$found = $this->findFirstOfType($child, $type);
			if ($found !== null) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Assert every BDC and BMC in the PDF is closed by an EMC.
	 *
	 * @param string $output
	 */
	private function assertBdcEmcBalanced($output)
	{
		$bdcCount = preg_match_all('/\bBDC\b/', $output);
		$bmcCount = preg_match_all('/\bBMC\b/', $output);
		$emcCount = preg_match_all('/\bEMC\b/', $output);
		$this->assertEquals(
			$bdcCount + $bmcCount,
			$emcCount,
			sprintf('BDC(%d)+BMC(%d) must equal EMC(%d)', $bdcCount, $bmcCount, $emcCount)
		);
	}
}
