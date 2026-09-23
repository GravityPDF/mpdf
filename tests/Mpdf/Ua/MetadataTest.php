<?php

namespace Mpdf\Ua;

/**
 * The document-level entries PDF/UA requires: the XMP identifier, MarkInfo, /Lang, the
 * title, embedded fonts, and /StructParents and /Tabs on each page.
 *
 * @group pdfua
 */
class MetadataTest extends PdfUaTestCase
{

	/**
	 * The XMP metadata identifies the document as PDF/UA part 1.
	 */
	public function testXmpContainsPdfuaidPart()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>Hello PDF/UA</p>');
		$this->assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $output);
	}

	/**
	 * A document that is PDF/A as well declares the pdfuaid schema in a PDF/A extension schema, as
	 * PDF/A allows no other; one that is only PDF/UA has no need to.
	 */
	public function testPdfaDocumentDeclaresThePdfuaidSchema()
	{
		$output = $this->getOutput($this->makeMpdf(['PDFA' => true]), '<p>Hello PDF/UA</p>');
		$this->assertStringContainsString('<pdfaSchema:namespaceURI>http://www.aiim.org/pdfua/ns/id/</pdfaSchema:namespaceURI>', $output);
		$this->assertStringContainsString('<pdfaProperty:name>part</pdfaProperty:name>', $output);

		$output = $this->getOutput($this->makeMpdf(), '<p>Hello PDF/UA</p>');
		$this->assertStringNotContainsString('pdfaExtension', $output);
	}

	/**
	 * The catalog marks the document as tagged.
	 */
	public function testCatalogContainsMarkInfo()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>Hello PDF/UA</p>');
		$this->assertStringContainsString('/MarkInfo <</Marked true /Suspects false>>', $output);
	}

	/**
	 * The catalog refers to the XMP metadata stream.
	 */
	public function testCatalogContainsMetadataRef()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>Hello PDF/UA</p>');
		$this->assertMatchesRegularExpression('/\/Metadata \d+ 0 R/', $output);
	}

	/**
	 * Viewers are told to show the document title rather than the file name.
	 */
	public function testViewerPreferencesDisplayDocTitle()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>Hello PDF/UA</p>');
		$this->assertStringContainsString('/DisplayDocTitle true', $output);
	}

	/**
	 * Strict mode refuses a document without a title (Matterhorn 06-003).
	 */
	public function testThrowsWhenTitleMissing()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		// A language is given so only the title is missing
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'PDFUAauto' => false, 'mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>no title</p>');
		$mpdf->Output(null, 'S');
	}

	/**
	 * Auto mode writes a document without a title and records a warning.
	 */
	public function testWarnsWhenTitleMissingWithAuto()
	{
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'PDFUAauto' => true, 'mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>no title auto</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertNotEmpty($output);

		$this->assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $output);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty($warnings);
	}

	/**
	 * A title of "0" is a title, and is written.
	 */
	public function testZeroTitleIsNotTreatedAsMissing()
	{
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'PDFUAauto' => false, 'mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->SetTitle('0');
		$mpdf->WriteHTML('<p>zero title</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('<dc:title>', $output);
		$this->assertStringContainsString('/Title ', $output);
	}

	/**
	 * Core fonts cannot be embedded, so writing a document that uses one throws (Matterhorn 14-002).
	 */
	public function testCoreFontsNotAllowed()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'mode' => 'c', 'title' => 'Core Font Test']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>x</p>');
		$mpdf->Output(null, 'S');
	}

	/**
	 * Every page sets /Tabs /S, not only pages with annotations (Matterhorn 28-001).
	 */
	public function testTabsSOnEveryPage()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p>Page one</p><pagebreak /><p>Page two</p><pagebreak /><p>Page three</p>';
		$output = $this->getOutput($mpdf, $html);
		$tabsCount = substr_count($output, '/Tabs /S');
		$this->assertGreaterThanOrEqual(3, $tabsCount);
	}

	/**
	 * Every page has its own /StructParents key, numbered in page order.
	 */
	public function testStructParentsOnEveryPage()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p>Page one</p><pagebreak /><p>Page two</p><pagebreak /><p>Page three</p>';
		$output = $this->getOutput($mpdf, $html);
		$this->assertMatchesRegularExpression('/\/StructParents 0\b/', $output);
		$this->assertMatchesRegularExpression('/\/StructParents 1\b/', $output);
		$this->assertMatchesRegularExpression('/\/StructParents 2\b/', $output);
	}

	/**
	 * A PDF/UA document is written as PDF 1.7, the version PDF/UA-1 is based on.
	 */
	public function testPdfVersionForcedTo17()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>version test</p>');
		$this->assertStringContainsString('%PDF-1.7', $output);
	}

	/**
	 * The catalog carries the document language.
	 */
	public function testCatalogContainsLang()
	{
		$mpdf = $this->makeMpdf(['mode' => 'en-GB']);
		$output = $this->getOutput($mpdf, '<p>language test</p>');
		$this->assertMatchesRegularExpression('/\/Lang \(\S+\)/', $output);
	}

	/**
	 * Strict mode refuses a document without a language (Matterhorn 04-001).
	 *
	 * The language comes from the mode, so a document given no mode has none.
	 */
	public function testThrowsWhenLangMissingStrict()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'PDFUAauto' => false, 'title' => 'Lang Test']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>no lang</p>');
		$mpdf->Output(null, 'S');
	}

	/**
	 * A document without PDFUA does not mark itself as tagged.
	 */
	public function testPdfuaFalseByDefaultNoMarkInfo()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'c']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>no pdfua</p>');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringNotContainsString('/MarkInfo', $output);
	}

	/**
	 * A PDF/A document can also be PDF/UA, and its XMP carries both identifiers.
	 */
	public function testPdfuaPdfaCoexistenceXmp()
	{
		$mpdf = $this->makeMpdf(['PDFA' => true, 'PDFAauto' => true]);
		$output = $this->getOutput($mpdf, '<p>coexistence test</p>');
		$this->assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $output);
		$this->assertStringContainsString('<pdfaid:part>', $output);
	}

	/**
	 * An encrypted document leaves its XMP metadata readable through the Identity crypt
	 * filter, as ISO 32000-1 §14.3.2 requires.
	 *
	 * @group pdfua
	 */
	public function testEncryptedOutputHasXmpNotEncrypted()
	{
		// Auto mode adds the extract permission PDF/UA needs instead of throwing
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection(['extract'], 'user', 'owner_pass');
		$output = $this->getOutput($mpdf, '<p>Encrypted PDF/UA-1 test</p>');

		$this->assertStringContainsString(
			'/Filter[/Crypt]',
			$output,
			'Metadata stream dict must carry /Filter [/Crypt] when document is encrypted'
		);
		$this->assertStringContainsString(
			'/Name/Identity',
			$output,
			'Metadata stream dict must carry /Name /Identity to bypass document encryption'
		);

		$this->assertStringContainsString(
			'pdfuaid:part',
			$output,
			'XMP pdfuaid:part must be plaintext in the raw PDF output (not RC4-encrypted)'
		);
	}

	/**
	 * The catalog refers to the structure tree root.
	 */
	public function testStructTreeRootInCatalog()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>struct tree test</p>');
		$this->assertMatchesRegularExpression('/\/StructTreeRoot \d+ 0 R/', $output);
	}

	/**
	 * The structure tree root is written.
	 */
	public function testStructTreeRootObjectExists()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p>struct tree object test</p>');
		$this->assertStringContainsString('/Type /StructTreeRoot', $output);
	}
}
