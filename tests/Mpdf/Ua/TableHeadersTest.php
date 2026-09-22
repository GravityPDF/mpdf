<?php

namespace Mpdf\Ua;

/**
 * A <th> is tagged as a TH that carries its own id, /Scope and /Headers, and is left out of the
 * tree when drawn in a running header or footer
 *
 * @group pdfua
 */
class TableHeadersTest extends PdfUaTestCase
{

	/**
	 * A <th> with an id and scope carries its /ID and /Scope, and a <td> naming it in headers
	 * refers to it through /Headers.
	 */
	public function testHeaderCellCarriesScopeAndHeaderAssociation()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table>'
			. '<tr><th id="h1" scope="col">Head</th></tr>'
			. '<tr><td headers="h1">Data</td></tr>'
			. '</table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /TH', $output);
		$this->assertStringContainsString('/O /Table /Scope /Column', $output);

		$this->assertStringContainsString('/ID (h1)', $output);
		$this->assertStringContainsString('/Headers [/h1]', $output);

		$th = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'TH');
		$this->assertNotNull($th, 'a TH struct element must exist');
		$attrs = $th->getAttributes();
		$this->assertSame('Column', $attrs['Scope'], 'the TH must carry Scope=Column');
		$this->assertSame(
			\Mpdf\Ua\StructureElement::sanitiseIdForPdf('h1'),
			$th->getId(),
			'the id must be bound to the TH, not a discarded TD'
		);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * The TH is written out with an object number of its own.
	 */
	public function testHeaderCellObjNumIsAssigned()
	{
		$mpdf = $this->makeMpdf();
		$this->getOutput(
			$mpdf,
			'<table><tr><th id="h1" scope="col">Head</th></tr>'
			. '<tr><td headers="h1">Data</td></tr></table>'
		);

		$th = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'TH');
		$this->assertNotNull($th);
		$this->assertNotSame(0, $th->getObjNum(), 'the TH must be serialised with a real object number');
		$this->assertGreaterThan(0, $th->getObjNum());
	}

	/**
	 * The id of a <th> resolves to the TH in the tree.
	 */
	public function testIdResolvesToHeaderCell()
	{
		$mpdf = $this->makeMpdf();
		$this->getOutput(
			$mpdf,
			'<table><tr><th id="h1" scope="col">Head</th></tr>'
			. '<tr><td headers="h1">Data</td></tr></table>'
		);

		$idMap = $this->readIdMap($mpdf);
		$this->assertArrayHasKey('h1', $idMap, 'the id must be registered');
		$this->assertSame('TH', $idMap['h1']->getType(), 'the id must resolve to the TH struct element');

		$th = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'TH');
		$this->assertSame($th, $idMap['h1'], 'the registered element must be the TH in the tree');
	}

	/**
	 * A <th> in a running header is a pagination artifact, with no TH and its id registered nowhere.
	 */
	public function testHeaderCellInRunningHeaderRendersAsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader(
			'<table><tr><th id="hdr" scope="col">Header cell</th></tr></table>'
		);
		$output = $this->getOutput($mpdf, '<h1>Doc</h1><p>Body text.</p>');

		$this->assertStringContainsString('/Artifact <</Type /Pagination /Subtype /Header>>', $output);

		$root = $mpdf->getPdfUaStructureTree()->getRoot();
		$this->assertNull($this->findFirstOfType($root, 'TH'), 'a <th> in a header must not create a TH element');

		$this->assertNull($root->getId(), 'the header cell id must not bind to the Document root');
		$idMap = $this->readIdMap($mpdf);
		$this->assertArrayNotHasKey('hdr', $idMap, 'the header cell id must not be registered as document content');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A <th> can itself name another header cell in headers.
	 */
	public function testHeaderCellCanAlsoCarryHeadersReference()
	{
		$mpdf = $this->makeMpdf();
		$html = '<table>'
			. '<tr><th id="top" scope="col">Top</th></tr>'
			. '<tr><th id="sub" headers="top" scope="col">Sub</th></tr>'
			. '<tr><td headers="sub">Data</td></tr>'
			. '</table>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/ID (sub)', $output);
		$this->assertStringContainsString('/Headers [/top]', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return array<string, \Mpdf\Ua\StructureElement> The elements registered for each HTML id
	 */
	private function readIdMap(\Mpdf\Mpdf $mpdf)
	{
		$uaProp = new \ReflectionProperty(\Mpdf\Mpdf::class, 'ua');
		$uaProp->setAccessible(true);
		$resolver = $uaProp->getValue($mpdf)->getAriaIdResolver();

		$idMapProp = new \ReflectionProperty(\Mpdf\Ua\AriaIdResolver::class, 'idMap');
		$idMapProp->setAccessible(true);
		return $idMapProp->getValue($resolver);
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
