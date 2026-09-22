<?php

namespace Mpdf\Ua;

/**
 * An L element carries the /ListNumbering its marker style maps to (ISO 32000-1 Table 347).
 *
 * @group pdfua
 */
class ListStructureTest extends PdfUaTestCase
{

	/**
	 * An <ol type="a"> is numbered /LowerAlpha in the /List attribute object of its L.
	 */
	public function testOrderedListTypeAlphaCarriesLowerAlphaNumbering()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<ol type="a"><li>First</li><li>Second</li></ol>');

		$list = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($list, 'an L struct element must exist');
		$this->assertSame('LowerAlpha', $list->getAttributes()['ListNumbering']);

		$this->assertStringContainsString('/O /List /ListNumbering /LowerAlpha >>', $output);
	}

	/**
	 * The text of an item is content of its LBody, the marker of its Lbl, and the LI holds only the two.
	 */
	public function testItemTextBelongsToLBody()
	{
		$mpdf = $this->makeMpdf();
		$this->getOutput($mpdf, '<ul><li>First</li></ul>');

		$item = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'LI');
		$this->assertEmpty($item->getMcids());

		$children = $item->getChildren();
		$this->assertSame(['Lbl', 'LBody'], [$children[0]->getType(), $children[1]->getType()]);
		$this->assertCount(1, $children[0]->getMcids());
		$this->assertCount(1, $children[1]->getMcids());
	}

	/**
	 * An unstyled <ol> is numbered /Decimal.
	 */
	public function testDefaultOrderedListCarriesDecimalNumbering()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<ol><li>One</li><li>Two</li></ol>');

		$list = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($list);
		$this->assertSame('Decimal', $list->getAttributes()['ListNumbering']);
		$this->assertStringContainsString('/O /List /ListNumbering /Decimal >>', $output);
	}

	/**
	 * Markers set by the type attribute or by CSS both map to their /ListNumbering names.
	 */
	public function testUpperRomanAndCssStyledMarkers()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<ol type="I"><li>One</li></ol>'
			. '<ol style="list-style-type: upper-alpha"><li>A</li></ol>'
		);

		$this->assertStringContainsString('/O /List /ListNumbering /UpperRoman >>', $output);
		$this->assertStringContainsString('/O /List /ListNumbering /UpperAlpha >>', $output);
	}

	/**
	 * An unstyled <ul> is numbered /Disc.
	 */
	public function testUnorderedListCarriesDiscNumbering()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<ul><li>Bullet one</li><li>Bullet two</li></ul>');

		$list = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($list);
		$this->assertSame('Disc', $list->getAttributes()['ListNumbering']);
		$this->assertStringContainsString('/O /List /ListNumbering /Disc >>', $output);
	}

	/**
	 * A marker with no /ListNumbering name, such as lower-greek, leaves the list without a
	 * /List attribute object.
	 */
	public function testUnsupportedMarkerEmitsNoNumbering()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<ol style="list-style-type: lower-greek"><li>alpha</li></ol>'
		);

		$list = $this->findFirstOfType($mpdf->getPdfUaStructureTree()->getRoot(), 'L');
		$this->assertNotNull($list);
		$this->assertArrayNotHasKey('ListNumbering', $list->getAttributes());
		$this->assertStringNotContainsString('/O /List', $output);
	}

	/**
	 * The first struct element of a type below a node, depth first.
	 *
	 * @param \Mpdf\Ua\StructureElement $node
	 * @param string                    $type
	 *
	 * @return \Mpdf\Ua\StructureElement|null
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
}
