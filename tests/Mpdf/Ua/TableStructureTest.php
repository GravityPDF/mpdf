<?php

namespace Mpdf\Ua;

/**
 * The rows of a table are tagged in THead, TBody and TFoot groups, with a TBody made up for rows
 * that have no group, so a TR is never a child of the Table itself
 *
 * @group pdfua
 */
class TableStructureTest extends PdfUaTestCase
{

	/**
	 * A table with <thead>, <tbody> and <tfoot> has a THead, TBody and TFoot, each holding its TR.
	 */
	public function testExplicitRowGroupsNestTrBeneathGroupElements()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table>'
			. '<thead><tr><th>Head</th></tr></thead>'
			. '<tbody><tr><td>Body</td></tr></tbody>'
			. '<tfoot><tr><td>Foot</td></tr></tfoot>'
			. '</table>';
		$output = $this->getOutput($mpdf, $html);

		$table = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$this->assertNotNull($table, 'a Table struct element must exist');

		$groups = [];
		foreach ($table->getChildren() as $child) {
			$groups[] = $child->getType();
		}
		$this->assertSame(['THead', 'TBody', 'TFoot'], $groups, 'Table must group its rows into THead/TBody/TFoot');

		foreach ($table->getChildren() as $group) {
			$rowTypes = [];
			foreach ($group->getChildren() as $row) {
				$rowTypes[] = $row->getType();
			}
			$this->assertSame(['TR'], $rowTypes, $group->getType() . ' must contain its TR row');
		}

		$this->assertStringContainsString('/S /THead', $output);
		$this->assertStringContainsString('/S /TBody', $output);
		$this->assertStringContainsString('/S /TFoot', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Rows written straight under <table> are grouped in one TBody.
	 */
	public function testImplicitRowsAreWrappedInSynthesisedTbody()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table><tr><td>One</td></tr><tr><td>Two</td></tr></table>';
		$this->getOutput($mpdf, $html);

		$table = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$this->assertNotNull($table, 'a Table struct element must exist');

		$childTypes = [];
		foreach ($table->getChildren() as $child) {
			$childTypes[] = $child->getType();
		}
		$this->assertSame(['TBody'], $childTypes, 'group-less rows must live under a single synthesised TBody');

		$tbody = $table->getChildren()[0];
		$rowTypes = [];
		foreach ($tbody->getChildren() as $row) {
			$rowTypes[] = $row->getType();
		}
		$this->assertSame(['TR', 'TR'], $rowTypes, 'both implicit rows nest under the synthesised TBody');

		foreach ($table->getChildren() as $child) {
			$this->assertNotSame('TR', $child->getType(), 'TR must not be a direct child of Table');
		}
	}

	/**
	 * Rows without a group after a <thead> go in a TBody beside the THead.
	 */
	public function testHeadThenImplicitRowsSynthesiseSiblingTbody()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table>'
			. '<thead><tr><th>Head</th></tr></thead>'
			. '<tr><td>Body one</td></tr>'
			. '<tr><td>Body two</td></tr>'
			. '</table>';
		$this->getOutput($mpdf, $html);

		$table = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$this->assertNotNull($table);

		$childTypes = [];
		foreach ($table->getChildren() as $child) {
			$childTypes[] = $child->getType();
		}
		$this->assertSame(['THead', 'TBody'], $childTypes, 'the explicit THead and the synthesised TBody must be Table siblings');
	}

	/**
	 * A table in a cell groups its rows in a TBody of its own.
	 */
	public function testNestedTableGetsItsOwnRowGroup()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table><tbody><tr><td>'
			. '<table><tr><td>Inner</td></tr></table>'
			. '</td></tr></tbody></table>';
		$this->getOutput($mpdf, $html);

		$outerTable = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$this->assertNotNull($outerTable);

		$outerTbody = $this->firstChildOfType($outerTable, 'TBody');
		$this->assertNotNull($outerTbody, 'outer table must have a TBody');
		$outerTr = $this->firstChildOfType($outerTbody, 'TR');
		$this->assertNotNull($outerTr);
		$outerTd = $this->firstChildOfType($outerTr, 'TD');
		$this->assertNotNull($outerTd);

		$innerTable = $this->firstChildOfType($outerTd, 'Table');
		$this->assertNotNull($innerTable, 'the inner table must be a TD child');
		$innerTbody = $this->firstChildOfType($innerTable, 'TBody');
		$this->assertNotNull($innerTbody, 'the inner table must synthesise its own TBody');
		$this->assertNotNull($this->firstChildOfType($innerTbody, 'TR'), 'the inner TR must nest under the inner TBody');
	}

	/**
	 * A <tfoot> written before <tbody>, as HTML 4 asked, is read after the body, where it is drawn.
	 */
	public function testFooterWrittenBeforeTheBodyIsReadAfterIt()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table>'
			. '<thead><tr><th>Head</th></tr></thead>'
			. '<tfoot><tr><td>Foot</td></tr></tfoot>'
			. '<tbody><tr><td>Body</td></tr></tbody>'
			. '</table>';
		$output = preg_replace('/\s+/', ' ', $this->getOutput($mpdf, $html));

		$table = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$refs = [];
		foreach ($table->getChildren() as $group) {
			$refs[$group->getType()] = $group->getObjNum() . ' 0 R';
		}

		$this->assertStringContainsString(
			'/S /Table /P ' . $table->getParent()->getObjNum() . ' 0 R /K [' . $refs['THead'] . ' ' . $refs['TBody'] . ' ' . $refs['TFoot'] . ']',
			$output
		);
	}

	/**
	 * The header and footer rows a table repeats at each page break are artifacts; each cell is read
	 * once, on the page where the table has it.
	 */
	public function testRowsRepeatedAtAPageBreakAreArtifacts()
	{
		$rows = str_repeat('<tr><td>Body</td></tr>', 80);
		$mpdf = $this->makeMpdf();
		$this->getOutput(
			$mpdf,
			'<table><thead><tr><th>Head</th></tr></thead><tfoot><tr><td>Foot</td></tr></tfoot><tbody>' . $rows . '</tbody></table>'
		);
		$this->assertGreaterThan(1, count($mpdf->pages));

		$table = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'Table');
		$th = $this->findFirstOfType($this->firstChildOfType($table, 'THead'), 'TH');
		$footCell = $this->findFirstOfType($this->firstChildOfType($table, 'TFoot'), 'TD');

		$this->assertCount(1, $th->getMcids());
		$this->assertCount(1, $footCell->getMcids());
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
