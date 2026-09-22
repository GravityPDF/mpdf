<?php

namespace Mpdf\Ua;

use Mpdf\Buffer;
use Mpdf\Mpdf;

/**
 * What StructureWriter writes for a hand-built StructureTree, chiefly the ParentTree, whose
 * /Nums entries are indexed by MCID and must not shift when a key or an MCID is missing
 *
 * @group pdfua
 * @see StructureWriter::writeParentTree()
 */
class StructureWriterTest extends PdfUaTestCase
{

	/**
	 * @param StructureTree $tree
	 *
	 * @return string What writeStructTree() writes for the tree
	 */
	private function serialise(StructureTree $tree)
	{
		$mpdf = $this->makeMpdf();
		// Outside a page, BaseWriter::write() writes to $mpdf->buffer
		$mpdf->state  = 0;
		$mpdf->buffer = new Buffer();

		$writerProp = new \ReflectionProperty(Mpdf::class, 'writer');
		$writerProp->setAccessible(true);
		$writer = $writerProp->getValue($mpdf);

		$structureWriter = new StructureWriter($mpdf, $writer, $tree);
		$structureWriter->writeStructTree(64);

		return $mpdf->buffer->writeToString();
	}

	/**
	 * @param string $pdf
	 *
	 * @return string The ParentTree's /Nums array, its whitespace collapsed
	 */
	private function numsBody($pdf)
	{
		$this->assertSame(1, preg_match('/<<\/Nums \[(.*?)\]>>/s', $pdf, $m), 'ParentTree /Nums block not found');
		return trim(preg_replace('/\s+/', ' ', $m[1]));
	}

	/**
	 * A missing MCID is written as null, so the MCIDs after it keep their own positions.
	 */
	public function testSparseMcidGapIsPreservedWithNull()
	{
		$tree = new StructureTree();
		$tree->open('P');
		$first = $tree->getCurrent();
		$tree->close();
		$tree->open('P');
		$second = $tree->getCurrent();
		$tree->close();

		// Imported content keeps the MCIDs of its source PDF, which can leave gaps
		$tree->registerImportedMcr(0, 0, $first);
		$tree->registerImportedMcr(0, 2, $second);

		$nums = $this->numsBody($this->serialise($tree));

		$expected = '0 [' . $first->getObjNum() . ' 0 R null ' . $second->getObjNum() . ' 0 R]';
		$this->assertSame($expected, $nums);
	}

	/**
	 * Each /StructParents key is written with its value, so a skipped key shifts nothing after it.
	 */
	public function testNonContiguousStructParentsKeysResolveCorrectly()
	{
		$tree = new StructureTree();
		$tree->open('P');
		$a = $tree->getCurrent();
		$tree->close();
		$tree->open('P');
		$b = $tree->getCurrent();
		$tree->close();

		$tree->registerImportedMcr(3, 0, $a);
		$tree->registerImportedMcr(7, 0, $b);

		$nums = $this->numsBody($this->serialise($tree));

		$this->assertSame(
			'3 [' . $a->getObjNum() . ' 0 R] 7 [' . $b->getObjNum() . ' 0 R]',
			$nums
		);
	}

	/**
	 * MCIDs without gaps are written without any null.
	 */
	public function testDenseMcidMapEmitsNoNullPadding()
	{
		$tree = new StructureTree();
		$tree->open('P');
		$first = $tree->getCurrent();
		$tree->close();
		$tree->open('P');
		$second = $tree->getCurrent();
		$tree->close();

		$tree->registerImportedMcr(0, 0, $first);
		$tree->registerImportedMcr(0, 1, $second);

		$nums = $this->numsBody($this->serialise($tree));

		$this->assertStringNotContainsString('null', $nums);
		$this->assertSame(
			'0 [' . $first->getObjNum() . ' 0 R ' . $second->getObjNum() . ' 0 R]',
			$nums
		);
	}

	/**
	 * A /BBox is written with '.' decimals under a locale that uses a comma.
	 */
	public function testBBoxIsLocaleIndependent()
	{
		$original = setlocale(LC_NUMERIC, '0');
		$applied  = setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'nl_NL.UTF-8', 'nl_NL', 'German', 'Dutch');
		if ($applied === false || strpos(sprintf('%.1f', 1.5), ',') === false) {
			if ($original !== false) {
				setlocale(LC_NUMERIC, $original);
			}
			$this->markTestSkipped('No comma-decimal locale available on this host.');
		}

		try {
			$tree = new StructureTree();
			$tree->open('Figure');
			$fig = $tree->getCurrent();
			$fig->setAttribute('BBox', [10.5, 20.25, 100.125, 200.0]);
			$tree->registerImportedMcr(0, 0, $fig);
			$tree->close();

			$pdf = $this->serialise($tree);
		} finally {
			if ($original !== false) {
				setlocale(LC_NUMERIC, $original);
			}
		}

		$this->assertSame(1, preg_match('#/BBox \[([^\]]*)\]#', $pdf, $m), '/BBox array not emitted');
		$this->assertStringNotContainsString(',', $m[1], '/BBox must use "." decimal separator');
		$this->assertSame('10.5 20.25 100.125 200', trim($m[1]));
	}
}
