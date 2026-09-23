<?php

namespace Mpdf\Ua;

use Mpdf\Ua\Import\FpdiStructMerger;

/**
 * Imported pages: an untagged one is wrapped as an artifact with a warning, a tagged one has its
 * structure copied into the document's, however many times the page is placed.
 *
 * @group pdfua
 */
class FpdiStructMergerTest extends PdfUaTestCase
{

	/** @var string An untagged PDF */
	private $untaggedPdf;

	/** @var string|null A tagged PDF made by the test, deleted in tear_down() */
	private $taggedPdf;

	/**
	 * Points at the untagged fixture and starts with no tagged one.
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->untaggedPdf = __DIR__ . '/../../data/pdfs/Noisy-Tube.pdf';
		$this->taggedPdf   = null;
	}

	/**
	 * Deletes the tagged PDF the test made.
	 */
	protected function tear_down()
	{
		parent::tear_down();
		if ($this->taggedPdf !== null && file_exists($this->taggedPdf)) {
			@unlink($this->taggedPdf);
			$this->taggedPdf = null;
		}
	}

	/**
	 * Writes a tagged PDF of an h1, h2 and h3.
	 *
	 * The host documents in these tests write only paragraphs, so an H2 or H3 in their output
	 * can only have come from this source.
	 *
	 * @return string The file's path
	 */
	private function makeTaggedPdf()
	{
		$source = $this->makeMpdf();
		$source->AddPage();
		$source->WriteHTML(
			'<h1>Tagged source title</h1>'
			. '<h2>Tagged source heading</h2>'
			. '<h3>Tagged source subheading</h3>'
		);
		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_tagged_') . '.pdf';
		$source->Output($tmp, 'F');
		return $tmp;
	}

	/**
	 * getUntaggedWarnings() returns the warnings added, in order.
	 */
	public function testAddAndGetUntaggedWarnings()
	{
		$mpdf = $this->makeMpdf();
		$merger = $mpdf->getPdfUaFpdiStructMerger();

		$merger->addUntaggedWarning('Test warning A');
		$merger->addUntaggedWarning('Test warning B');

		$warnings = $merger->getUntaggedWarnings();
		$this->assertCount(2, $warnings);
		$this->assertStringContainsString('Test warning A', $warnings[0]);
		$this->assertStringContainsString('Test warning B', $warnings[1]);
	}

	/**
	 * getUntaggedWarnings() empties the list it returns.
	 */
	public function testGetUntaggedWarningsClearsQueue()
	{
		$mpdf = $this->makeMpdf();
		$merger = $mpdf->getPdfUaFpdiStructMerger();

		$merger->addUntaggedWarning('Once');
		$first  = $merger->getUntaggedWarnings();
		$second = $merger->getUntaggedWarnings();

		$this->assertCount(1, $first);
		$this->assertCount(0, $second);
	}

	/**
	 * sourceIsTagged() is false for a PDF with no /StructTreeRoot.
	 */
	public function testSourceIsTaggedReturnsFalseForUntaggedSource()
	{
		if (!file_exists($this->untaggedPdf)) {
			$this->markTestSkipped('FPDI test fixture not available: ' . $this->untaggedPdf);
		}

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->untaggedPdf);
		$mpdf->importPage(1);

		$merger   = $mpdf->getPdfUaFpdiStructMerger();
		$pages    = $mpdf->getImportedPages();
		$readerId = reset($pages)['readerId'];

		$this->assertFalse($merger->sourceIsTagged($readerId));
	}

	/**
	 * sourceIsTagged() is true for a PDF with a /StructTreeRoot.
	 */
	public function testSourceIsTaggedReturnsTrueForTaggedSource()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$mpdf->importPage(1);

		$merger   = $mpdf->getPdfUaFpdiStructMerger();
		$pages    = $mpdf->getImportedPages();
		$readerId = reset($pages)['readerId'];

		$this->assertTrue($merger->sourceIsTagged($readerId));
	}

	/**
	 * In auto mode an untagged imported page is drawn as an artifact; strict mode throws, which
	 * FpdiImportTest covers.
	 */
	public function testImportProducesArtifactBdc()
	{
		if (!file_exists($this->untaggedPdf)) {
			$this->markTestSkipped('FPDI test fixture not available: ' . $this->untaggedPdf);
		}

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString(
			'/Artifact',
			$output,
			'Imported page stream must contain /Artifact marker'
		);
		$this->assertStringContainsString('BDC', $output);
		$this->assertStringContainsString('EMC', $output);
	}

	/**
	 * An untagged imported page is reported, since its content is lost to assistive technology.
	 */
	public function testImportRecordsWarning()
	{
		if (!file_exists($this->untaggedPdf)) {
			$this->markTestSkipped('FPDI test fixture not available: ' . $this->untaggedPdf);
		}

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$mpdf->Output(null, 'S');

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty(
			$warnings,
			'A warning must be recorded when a PDF is imported in PDFUA mode'
		);
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'untagged') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Warning should mention "untagged" source PDF');
	}

	/**
	 * A paragraph written after an untagged imported page is still tagged.
	 */
	public function testImportArtifactAndStructElementCoexist()
	{
		if (!file_exists($this->untaggedPdf)) {
			$this->markTestSkipped('FPDI test fixture not available: ' . $this->untaggedPdf);
		}

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$mpdf->WriteHTML('<p>After import</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('/Artifact <</Type /Layout>> BDC', $output);
		$this->assertStringContainsString('/S /P', $output);
		$this->assertStringContainsString('/P <</MCID', $output);
	}

	/**
	 * A tagged imported page keeps its own tagging and is not drawn as an artifact.
	 */
	public function testTaggedSourceDoesNotProduceArtifactBdc()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');

		$this->assertStringNotContainsString(
			'/Artifact <</Type /Layout>> BDC',
			$output,
			'Tagged source must not be wrapped as /Artifact'
		);
	}

	/**
	 * A tagged imported page raises no warning about untagged content.
	 */
	public function testTaggedSourceDoesNotRecordUntaggedWarning()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$mpdf->Output(null, 'S');

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'untagged') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertFalse($found, 'Tagged source must not produce an "untagged" warning');
	}

	/**
	 * A tagged imported page's struct elements are copied into the document's structure tree.
	 */
	public function testTaggedSourceMergesStructSubtree()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$mpdf->WriteHTML('<p>Host paragraph after import</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('/Type /StructTreeRoot', $output);
		$this->assertStringContainsString('/S /P', $output);
		$this->assertStringContainsString(
			'/S /H2',
			$output,
			'/S /H2 must appear — it can only come from the merged source subtree'
		);
		$this->assertStringContainsString(
			'/S /H3',
			$output,
			'/S /H3 must appear — it can only come from the merged source subtree'
		);
	}

	/**
	 * The Form XObject a tagged page is imported as carries /StructParents, since its stream
	 * holds marked content (ISO 32000-1 §14.7.4.4).
	 *
	 * Looked for near the XObject, as page dictionaries carry /StructParents too.
	 */
	public function testTaggedSourceFormXObjectHasStructParents()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');

		$foPos = strpos($output, '/Subtype /Form');
		$this->assertNotFalse(
			$foPos,
			'Output must contain a /Subtype /Form block (the FPDI Form XObject)'
		);

		$window  = 2000;
		$start   = max(0, $foPos - $window);
		$end     = min(strlen($output), $foPos + $window);
		$segment = substr($output, $start, $end - $start);

		$this->assertStringContainsString(
			'/StructParents',
			$segment,
			'Form XObject dict must contain /StructParents for tagged import'
		);
	}

	/**
	 * A page placed twice, as SetPageTemplate does, has its struct elements copied once; the
	 * second placement adds content references to them instead.
	 */
	public function testSetPageTemplateReuseDoesNotDuplicateSubtree()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdfSingle = $this->makeMpdf();
		$mpdfSingle->setSourceFile($this->taggedPdf);
		$pageId = $mpdfSingle->importPage(1);
		$mpdfSingle->AddPage();
		$mpdfSingle->useImportedPage($pageId);
		$singleOutput = $mpdfSingle->Output(null, 'S');
		$countSingle  = substr_count($singleOutput, '/S /H2');

		$mpdfDouble = $this->makeMpdf();
		$mpdfDouble->setSourceFile($this->taggedPdf);
		$pageId2 = $mpdfDouble->importPage(1);
		$mpdfDouble->AddPage();
		$mpdfDouble->useImportedPage($pageId2);
		$mpdfDouble->AddPage();
		$mpdfDouble->useImportedPage($pageId2);
		$doubleOutput = $mpdfDouble->Output(null, 'S');
		$countDouble  = substr_count($doubleOutput, '/S /H2');

		$this->assertSame(
			1,
			$countSingle,
			'/S /H2 must appear exactly once in single-placement output'
		);

		$this->assertSame(
			$countSingle,
			$countDouble,
			'/S /H2 count must not increase on reuse — mergePageStructSubtree must be idempotent'
		);

		$countH3Single = substr_count($singleOutput, '/S /H3');
		$countH3Double = substr_count($doubleOutput, '/S /H3');
		$this->assertSame(1, $countH3Single, '/S /H3 must appear exactly once in single-placement output');
		$this->assertSame($countH3Single, $countH3Double, '/S /H3 count must not increase on reuse');
	}

	/**
	 * The document's own paragraph is tagged alongside the struct elements copied from a tagged import.
	 */
	public function testTaggedImportAndHostStructElementsCoexist()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$mpdf->WriteHTML('<p>After import</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('/P <</MCID', $output);
		$this->assertStringContainsString('/S /P', $output);
		$this->assertStringContainsString(
			'/S /H2',
			$output,
			'/S /H2 from merged source subtree must coexist with host /S /P'
		);
		$this->assertStringContainsString(
			'/S /H3',
			$output,
			'/S /H3 from merged source subtree must coexist with host /S /P'
		);
	}

	/**
	 * A source element whose /K is a bare MCID, as mPDF writes an element with one piece of
	 * content, is copied with an MCR whose /Pg is a real page.
	 */
	public function testBareIntegerKMergesMcidAndResolvesPg()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');

		$this->assertMatchesRegularExpression(
			'#/S /H2\s*/P \d+ 0 R\s*/K <</Type /MCR /Pg \d+ 0 R#',
			$output,
			'Bare-integer /K source element must merge with an MCR content reference'
		);

		$this->assertSame(
			1,
			preg_match('#/S /H2\s*/P \d+ 0 R\s*/K <</Type /MCR /Pg (\d+) 0 R#', $output, $m),
			'Merged H2 element must carry exactly one MCR content reference'
		);
		$this->assertGreaterThan(
			0,
			(int) $m[1],
			'/Pg on the merged MCR must resolve to a non-zero host page object'
		);
	}

	/**
	 * The MCRs of a merged page name the Form XObject holding their content in /Stm as well as
	 * the page in /Pg, and the ParentTree has an entry for the XObject's /StructParents.
	 *
	 * FPDI imports every page as a Form XObject, so a bare MCID would point into the wrong stream.
	 */
	public function testFormXObjectMcrsCarryPgAndStmAndParentTreeKey()
	{
		$this->taggedPdf = $this->makeTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');

		$this->assertMatchesRegularExpression(
			'#<</Type /MCR /Pg \d+ 0 R /Stm \d+ 0 R /MCID \d+>>#',
			$output,
			'Merged Form-XObject MCRs must carry /Pg and /Stm'
		);

		// Searched forward from the XObject, so a neighbouring page's /StructParents is not read
		$foPos = strpos($output, '/Subtype /Form');
		$this->assertNotFalse($foPos, 'Output must contain the FPDI Form XObject');
		$this->assertSame(
			1,
			preg_match('#/Subtype /Form.{0,2000}?/StructParents (\d+)#s', substr($output, $foPos, 2100), $spMatch),
			'Form XObject dict must declare a /StructParents key'
		);
		$xobjKey = (int) $spMatch[1];

		$this->assertSame(
			1,
			preg_match('#/Nums \[(.*?)\]>>#s', $output, $numsMatch),
			'StructTreeRoot ParentTree must expose a /Nums array'
		);
		$this->assertMatchesRegularExpression(
			'#(?:^|\s)' . $xobjKey . ' \[#',
			$numsMatch[1],
			'ParentTree /Nums must contain the Form XObject /StructParents key ' . $xobjKey
		);
	}

	/**
	 * A header cell's /Scope and a cell's /ColSpan come across with an imported table.
	 */
	public function testImportedTableKeepsScopeAndSpans()
	{
		$output = $this->importTagged(
			'<table><tr><th scope="col" colspan="2">Totals</th></tr><tr><td>1</td><td>2</td></tr></table>'
		);

		$this->assertStringContainsString('/O /Table /Scope /Column /ColSpan 2', $output);
	}

	/**
	 * A form field's widget is not imported, so no empty Form element is left behind for it.
	 */
	public function testImportedFormFieldLeavesNoEmptyFormElement()
	{
		$output = $this->importTagged('<p>Name <input type="text" name="fname" title="Name"></p>', ['useActiveForms' => true]);

		$this->assertStringNotContainsString('/S /Form', $output);
	}

	/**
	 * Imports page 1 of a tagged document written from the HTML into a new PDF/UA document.
	 *
	 * @param string $html
	 * @param array  $config For the source document
	 *
	 * @return string The new document, uncompressed
	 */
	private function importTagged($html, $config = [])
	{
		$source = $this->makeMpdf($config);
		$source->WriteHTML($html);
		$this->taggedPdf = tempnam(sys_get_temp_dir(), 'mpdf_tagged_') . '.pdf';
		$source->Output($this->taggedPdf, 'F');

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->taggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);

		return $mpdf->Output(null, 'S');
	}

	/**
	 * A text string without a byte order mark is read as PDFDocEncoding, whose 0x80-0xA0 range differs
	 * from Windows-1252 (ISO 32000-1 Annex D).
	 *
	 * @return void
	 */
	public function testPdfDocEncodingUpperRange()
	{
		$merger = $this->makeMpdf()->getPdfUaFpdiStructMerger();
		$decode = new \ReflectionMethod($merger, 'pdfDocEncodingToUtf8');
		if (PHP_VERSION_ID < 80100) {
			$decode->setAccessible(true);
		}

		$expected = html_entity_decode('&#x2022;&#x2020;&#x0192;&#x2044;&#x2212;&#x201C;&#x2122;&#x0161;&#x017E;&#x20AC;', ENT_QUOTES, 'UTF-8');
		$this->assertSame($expected, $decode->invoke($merger, "\x80\x81\x86\x87\x8A\x8D\x92\x9D\x9E\xA0"));
	}
}
