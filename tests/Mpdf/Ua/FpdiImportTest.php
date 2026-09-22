<?php

namespace Mpdf\Ua;

/**
 * Importing a page from a PDF with no structure tree into a PDF/UA document.
 *
 * Such a page has nothing to copy into the structure tree. It is marked as an artifact with a
 * warning in PDFUAauto mode, refused in strict mode, and tagged as a Figure when given an /Alt.
 *
 * @group pdfua
 */
class FpdiImportTest extends PdfUaTestCase
{

	/** @var string|null The untagged source PDF, deleted in tear_down() */
	private $untaggedPdf;

	/**
	 * Start with no source PDF.
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->untaggedPdf = null;
	}

	/**
	 * Delete the source PDF a test made.
	 */
	protected function tear_down()
	{
		parent::tear_down();
		if ($this->untaggedPdf !== null && file_exists($this->untaggedPdf)) {
			@unlink($this->untaggedPdf);
			$this->untaggedPdf = null;
		}
	}

	/**
	 * Write a PDF with no structure tree, in an embedded font so the imported page passes PDF/UA's font rules.
	 *
	 * @return string The path of the PDF
	 */
	private function makeUntaggedPdf()
	{
		$src = new \Mpdf\Mpdf(['mode' => 'utf-8', 'default_font' => 'DejaVuSansCondensed']);
		$src->WriteHTML('<p>Untagged source page for an untagged FPDI import.</p>');
		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_untagged_') . '.pdf';
		$src->Output($tmp, 'F');
		return $tmp;
	}

	/**
	 * In PDFUAauto mode an untagged page is marked as an artifact and a warning names the page and file.
	 *
	 * @return void
	 */
	public function testUntaggedImportAutoModeWrapsArtifactAndWarnsNamingPage()
	{
		$this->untaggedPdf = $this->makeUntaggedPdf();

		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => true]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);

		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId, 0, 0, 150, 100);
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString(
			'/Artifact <</Type /Layout>> BDC',
			$output,
			'Untagged import in auto mode must wrap the Do operator as an Artifact'
		);

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		$basename = basename($this->untaggedPdf);
		foreach ($warnings as $w) {
			if (strpos($w, 'untagged') !== false
				&& strpos($w, 'page 1') !== false
				&& strpos($w, $basename) !== false
			) {
				$found = true;
				break;
			}
		}
		$this->assertTrue(
			$found,
			'getPdfUaWarnings() must record an untagged-import warning naming "page 1" of the source file'
		);
	}

	/**
	 * In strict mode an untagged page with no /Alt throws, naming the page and file.
	 *
	 * @return void
	 */
	public function testUntaggedImportStrictModeThrows()
	{
		$this->untaggedPdf = $this->makeUntaggedPdf();

		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => false]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();

		try {
			$mpdf->useImportedPage($pageId, 0, 0, 150, 100);
			$this->fail('strict-mode useImportedPage() on an untagged source must throw');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertStringContainsString('untagged', $e->getMessage());
			$this->assertStringContainsString('page 1', $e->getMessage());
			$this->assertStringContainsString(basename($this->untaggedPdf), $e->getMessage());
			$this->assertStringContainsString('Matterhorn 01-007', $e->getMessage());
		}
	}

	/**
	 * An untagged page given an 'alt' is tagged as a Figure with that /Alt, which strict mode accepts.
	 *
	 * @return void
	 */
	public function testUntaggedImportWithAuthorAltProducesNamedFigure()
	{
		$this->untaggedPdf = $this->makeUntaggedPdf();

		$alt  = 'Scanned invoice page rendered as an accessible figure';
		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => false]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();

		$mpdf->useImportedPage($pageId, ['x' => 0, 'y' => 0, 'width' => 150, 'alt' => $alt]);
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString(
			'/Figure <</MCID',
			$output,
			'An author-supplied /Alt must tag the imported page as a Figure with an MCID'
		);
		$this->assertStringNotContainsString(
			'/Artifact <</Type /Layout>> BDC',
			$output,
			'A named Figure must not also be wrapped as an anonymous Artifact'
		);

		// As BaseWriter::utf16BigEndianTextString() writes it
		$utf16    = "\xFE\xFF" . mb_convert_encoding($alt, 'UTF-16BE', 'UTF-8');
		$escaped  = strtr($utf16, [')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(13) => '\r']);
		$expected = '/Alt (' . $escaped . ')';
		$this->assertStringContainsString(
			$expected,
			$output,
			'The Figure struct element must carry the author-supplied /Alt text (UTF-16BE)'
		);
	}

	/**
	 * A blank 'alt' counts as none, so strict mode still throws: a whole page cannot be decorative.
	 *
	 * @return void
	 */
	public function testUntaggedImportEmptyAltStillThrowsInStrictMode()
	{
		$this->untaggedPdf = $this->makeUntaggedPdf();

		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => false]);
		$mpdf->setSourceFile($this->untaggedPdf);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();

		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf->useImportedPage($pageId, ['x' => 0, 'y' => 0, 'width' => 150, 'alt' => '   ']);
	}
}
