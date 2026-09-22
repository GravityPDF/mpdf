<?php

namespace Mpdf\Ua;

/**
 * Importing an encrypted PDF, which FPDI cannot read, and a tagged PDF whose text strings look
 * like ciphertext. Auto mode draws a placeholder or wraps the page as an artifact and warns;
 * strict mode throws (Matterhorn 01-007).
 *
 * @group pdfua
 */
class FpdiEncryptedSourceTest extends PdfUaTestCase
{

	/** @var string|null An encrypted PDF made for the test, deleted in tear_down() */
	private $encryptedPdf;

	/** @var string|null A hand-built tagged PDF whose /Alt is junk bytes */
	private $garbageAltPdf;

	/** @var string|null A tagged PDF with readable strings */
	private $cleanTaggedPdf;

	/**
	 * Starts each test with no fixture files.
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->encryptedPdf   = null;
		$this->garbageAltPdf  = null;
		$this->cleanTaggedPdf = null;
	}

	/**
	 * Deletes the fixture files the test made.
	 */
	protected function tear_down()
	{
		parent::tear_down();
		foreach (['encryptedPdf', 'garbageAltPdf', 'cleanTaggedPdf'] as $prop) {
			if ($this->{$prop} !== null && file_exists($this->{$prop})) {
				@unlink($this->{$prop});
				$this->{$prop} = null;
			}
		}
	}

	/**
	 * Writes a one-page encrypted PDF, which FPDI rejects with CrossReferenceException::ENCRYPTED.
	 *
	 * Made without PDFUA, which would insist on the 'extract' permission.
	 *
	 * @return string The file's path
	 */
	private function makeEncryptedPdf()
	{
		$src = new \Mpdf\Mpdf(['mode' => 'c']);
		$src->SetProtection(['copy', 'print'], 'user', 'owner', 40);
		$src->WriteHTML('<p>Encrypted source content</p>');
		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_enc_') . '.pdf';
		$src->Output($tmp, 'F');
		return $tmp;
	}

	/**
	 * Writes an encrypted PDF of several pages; its page count can still be read, since encryption
	 * covers only strings and streams.
	 *
	 * @param int $pages At least 1
	 * @return string The file's path
	 */
	private function makeMultiPageEncryptedPdf($pages)
	{
		$src = new \Mpdf\Mpdf(['mode' => 'c']);
		$src->SetProtection(['copy', 'print'], 'user', 'owner', 40);
		for ($i = 1; $i <= $pages; $i++) {
			if ($i > 1) {
				$src->AddPage();
			}
			$src->WriteHTML('<p>Encrypted source page ' . $i . '</p>');
		}
		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_encN_') . '.pdf';
		$src->Output($tmp, 'F');
		return $tmp;
	}

	/**
	 * Writes a tagged PDF/UA document whose structure can be merged.
	 *
	 * @return string The file's path
	 */
	private function makeCleanTaggedPdf()
	{
		$src = $this->makeMpdf();
		$src->WriteHTML('<h1>Tagged title</h1><p>Tagged content</p>');
		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_clean_') . '.pdf';
		$src->Output($tmp, 'F');
		return $tmp;
	}

	/**
	 * Writes a one-page tagged PDF whose only P has an /Alt of 1024 0x01 bytes, which decode to
	 * U+FFFD and fail the check that imported strings are readable.
	 *
	 * @return string The file's path
	 */
	private function makeGarbageAltPdf()
	{
		// Under the 4 KiB length cap, so it fails on the share of unprintable characters instead
		$altPayload = str_repeat('01', 1024);

		$objs = [];
		$objs[1] = "<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 5 0 R >>";
		$objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
		$objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << >> >>";
		$objs[4] = "<< /Length 0 >>\nstream\n\nendstream";
		$objs[5] = "<< /Type /StructTreeRoot /K [6 0 R] >>";
		$objs[6] = "<< /Type /StructElem /S /P /P 5 0 R /Alt <" . $altPayload . "> >>";

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = [];
		foreach ($objs as $n => $body) {
			$offsets[$n] = strlen($pdf);
			$pdf .= $n . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xrefPos = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objs) + 1) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ($i = 1; $i <= count($objs); $i++) {
			$pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
		}
		$pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $xrefPos . "\n%%EOF\n";

		$tmp = tempnam(sys_get_temp_dir(), 'mpdf_garbage_') . '.pdf';
		file_put_contents($tmp, $pdf);
		return $tmp;
	}

	/**
	 * In auto mode setSourceFile() on an encrypted PDF returns a page count instead of throwing,
	 * and warns that the source is encrypted.
	 */
	public function testEncryptedSourceProducesUaWarningInAutoMode()
	{
		$this->encryptedPdf = $this->makeEncryptedPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$count = $mpdf->setSourceFile($this->encryptedPdf);

		$this->assertSame(1, $count);

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'encrypted') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue(
			$found,
			'Auto-mode setSourceFile() on an encrypted PDF must record a UA warning citing encryption'
		);
	}

	/**
	 * sourceIsEncrypted() is false for an unencrypted PDF.
	 */
	public function testSourceIsEncryptedReturnsFalseForCleanPdf()
	{
		$this->cleanTaggedPdf = $this->makeCleanTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->cleanTaggedPdf);
		$mpdf->importPage(1);

		$merger   = $mpdf->getPdfUaFpdiStructMerger();
		$pages    = $mpdf->getImportedPages();
		$readerId = reset($pages)['readerId'];

		$this->assertFalse(
			$merger->sourceIsEncrypted($readerId),
			'Clean PDF source must not be flagged as encrypted'
		);
	}

	/**
	 * In auto mode a page imported from an encrypted PDF is a placeholder, drawn as a Layout artifact.
	 */
	public function testEncryptedImportAutoModeFallsBackToArtifact()
	{
		$this->encryptedPdf = $this->makeEncryptedPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->encryptedPdf);
		$pageId = $mpdf->importPage(1);

		$this->assertStringContainsString(
			\Mpdf\Ua\Import\FpdiStructMerger::ENCRYPTED_PAGE_PLACEHOLDER_ID_PREFIX,
			$pageId,
			'Auto-mode importPage() on encrypted source must return a placeholder pageId'
		);
		$this->assertTrue(
			$mpdf->isEncryptedPlaceholder($pageId),
			'isEncryptedPlaceholder() must recognise the synthetic pageId'
		);

		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId, 0, 0, 100, 50);
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString(
			'/Artifact <</Type /Layout>> BDC',
			$output,
			'placeholder must emit /Artifact <</Type /Layout>> BDC on the page'
		);
		$this->assertStringContainsString('EMC', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$encMessages = 0;
		foreach ($warnings as $w) {
			if (stripos($w, 'encrypted') !== false) {
				$encMessages++;
			}
		}
		$this->assertGreaterThanOrEqual(
			1,
			$encMessages,
			'getPdfUaWarnings() must contain at least one encrypted-source warning after fallback'
		);
	}

	/**
	 * In auto mode an encrypted PDF of three pages reports three pages, and each gives a visible
	 * placeholder with a border.
	 */
	public function testMultiPageEncryptedSourceEmitsOnePlaceholderPerPageInAutoMode()
	{
		$this->encryptedPdf = $this->makeMultiPageEncryptedPdf(3);

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$count = $mpdf->setSourceFile($this->encryptedPdf);

		$this->assertSame(
			3,
			$count,
			'auto-mode setSourceFile() must recover the encrypted source page count, not return a fake 1'
		);

		$mpdf->AddPage();
		for ($i = 1; $i <= $count; $i++) {
			$pageId = $mpdf->importPage($i);
			$this->assertTrue(
				$mpdf->isEncryptedPlaceholder($pageId),
				'every page of an encrypted source must yield a placeholder in auto mode'
			);
			$mpdf->useImportedPage($pageId, 0, ($i - 1) * 60, 100, 50);
		}

		$output = $mpdf->Output(null, 'S');

		$this->assertSame(
			3,
			substr_count($output, '/Artifact <</Type /Layout>> BDC'),
			'a 3-page encrypted source must emit exactly 3 visible Artifact placeholders'
		);

		$this->assertMatchesRegularExpression(
			'/re\s+S/',
			$output,
			'each placeholder must stroke a visible border rectangle (re … S)'
		);

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'encrypted') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue(
			$found,
			'a multi-page encrypted source must record an encrypted-source UA warning'
		);
	}

	/**
	 * In strict mode setSourceFile() on an encrypted PDF of several pages throws before any page
	 * is imported.
	 */
	public function testMultiPageEncryptedSourceThrowsInStrictMode()
	{
		$this->encryptedPdf = $this->makeMultiPageEncryptedPdf(3);

		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);

		try {
			$mpdf->setSourceFile($this->encryptedPdf);
			$this->fail('strict-mode setSourceFile() on a multi-page encrypted source must throw');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertStringContainsString('encrypted', $e->getMessage());
			$this->assertStringContainsString('Matterhorn 01-007', $e->getMessage());
			$this->assertStringContainsString('7.6', $e->getMessage());
		}
	}

	/**
	 * A page imported from a readable source is not made a placeholder because an encrypted
	 * source was set before it: importPage() reads from the source set last.
	 */
	public function testEncryptedSourceDoesNotBlankLaterValidImport()
	{
		$this->encryptedPdf   = $this->makeEncryptedPdf();
		$this->cleanTaggedPdf = $this->makeCleanTaggedPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->encryptedPdf);
		$mpdf->setSourceFile($this->cleanTaggedPdf);
		$pageId = $mpdf->importPage(1);

		$this->assertFalse(
			$mpdf->isEncryptedPlaceholder($pageId),
			'a valid source imported after an encrypted one must not become a placeholder'
		);
		$this->assertStringNotContainsString(
			\Mpdf\Ua\Import\FpdiStructMerger::ENCRYPTED_PAGE_PLACEHOLDER_ID_PREFIX,
			$pageId,
			'the valid import must return a real Form XObject id, not an encrypted placeholder'
		);
		$this->assertTrue(
			$mpdf->getPdfUaFpdiStructMerger()->verifyAndPrepareMerge($pageId),
			'the valid source after an encrypted one must still merge as a real tagged page'
		);
	}

	/**
	 * In strict mode setSourceFile() on an encrypted PDF throws, citing ISO 32000-1 §7.6 and
	 * Matterhorn 01-007.
	 */
	public function testEncryptedImportStrictModeThrowsAtSetSourceFile()
	{
		$this->encryptedPdf = $this->makeEncryptedPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);

		try {
			$mpdf->setSourceFile($this->encryptedPdf);
			$this->fail('setSourceFile() on encrypted source in strict mode must throw');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertStringContainsString('encrypted', $e->getMessage());
			$this->assertStringContainsString('Matterhorn 01-007', $e->getMessage());
			$this->assertStringContainsString('7.6', $e->getMessage());
		}
	}

	/**
	 * A tagged source with readable strings passes verifyAndPrepareMerge(), so its structure is merged.
	 */
	public function testStringSanityCheckPassesForCleanTaggedSource()
	{
		$this->cleanTaggedPdf = $this->makeCleanTaggedPdf();

		$mpdf = $this->makeMpdf();
		$mpdf->setSourceFile($this->cleanTaggedPdf);
		$pageId = $mpdf->importPage(1);

		$merger = $mpdf->getPdfUaFpdiStructMerger();
		$this->assertTrue(
			$merger->verifyAndPrepareMerge($pageId),
			'Clean tagged source must pass the string sanity gauntlet'
		);
		$this->assertFalse(
			$merger->wasSanityCheckFailed($pageId),
			'wasSanityCheckFailed() must be false after a successful verify'
		);
	}

	/**
	 * In auto mode a tagged source whose /Alt is junk bytes fails the check, and its page is drawn
	 * as a Layout artifact with a warning instead of having its structure merged.
	 */
	public function testStringSanityCheckDemotesOnGarbageAltAutoMode()
	{
		$this->garbageAltPdf = $this->makeGarbageAltPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->setSourceFile($this->garbageAltPdf);
		$pageId = $mpdf->importPage(1);

		$merger = $mpdf->getPdfUaFpdiStructMerger();

		$this->assertTrue(
			$merger->sourceIsTagged($mpdf->getImportedPages()[$pageId]['readerId']),
			'Synthetic fixture must report as tagged so the demotion path is exercised'
		);

		$this->assertFalse(
			$merger->verifyAndPrepareMerge($pageId),
			'Garbage /Alt source must fail verifyAndPrepareMerge() in auto mode'
		);
		$this->assertTrue(
			$merger->wasSanityCheckFailed($pageId),
			'wasSanityCheckFailed() must be true after the demotion'
		);

		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString(
			'/Artifact <</Type /Layout>> BDC',
			$output,
			'Demoted tagged page must emit the Artifact wrap'
		);

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'sanity') !== false || stripos($w, 'printable-codepoint') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue(
			$found,
			'Demotion warning must mention the sanity-gauntlet failure'
		);
	}

	/**
	 * In strict mode a tagged source whose /Alt is junk bytes throws instead.
	 */
	public function testStringSanityCheckDemotesOnGarbageAltStrictMode()
	{
		$this->garbageAltPdf = $this->makeGarbageAltPdf();

		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$mpdf->setSourceFile($this->garbageAltPdf);
		$pageId = $mpdf->importPage(1);

		$merger = $mpdf->getPdfUaFpdiStructMerger();

		try {
			$merger->verifyAndPrepareMerge($pageId);
			$this->fail('verifyAndPrepareMerge() must throw in strict mode for garbage /Alt');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertStringContainsString('§7.6.5', $e->getMessage());
			$this->assertStringContainsString('Matterhorn 01-007', $e->getMessage());
		}
	}

	/**
	 * Outside PDF/UA mode an encrypted source still throws FPDI's own CrossReferenceException.
	 */
	public function testNonPdfUaCallerStillReceivesCrossReferenceException()
	{
		$this->encryptedPdf = $this->makeEncryptedPdf();

		$mpdf = new \Mpdf\Mpdf(['mode' => 'c']);

		try {
			$mpdf->setSourceFile($this->encryptedPdf);
			$this->fail('Non-PDFUA setSourceFile() on encrypted source must still throw');
		} catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
			$this->assertSame(
				\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException::ENCRYPTED,
				$e->getCode(),
				'Underlying FPDI exception code must be ENCRYPTED'
			);
		}
	}
}
