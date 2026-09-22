<?php

namespace Mpdf\Ua;

/**
 * The XMP metadata of an encrypted PDF/UA document, which must stay readable without the key.
 *
 * The Identity crypt filter that leaves the metadata stream in plain text only exists under
 * a /V 4 security handler (ISO 32000-1 §7.6.5), so a PDF/UA document is encrypted with one.
 *
 * @group pdfua
 */
class EncryptedUaMetadataTest extends PdfUaTestCase
{

	/**
	 * An encrypted PDF/UA document writes its pdfuaid:part and dc:title as plain text under a /V 4 handler.
	 *
	 * @return void
	 */
	public function testEncryptedXmpIsReadablePlaintext()
	{
		// PDFUAauto adds the 'extract' permission PDF/UA requires rather than throwing
		$mpdf = $this->makeMpdf(['PDFUAauto' => true, 'title' => 'Encrypted UA Title']);
		$mpdf->SetProtection(['print']);
		$output = $this->getOutput($mpdf, '<h1>Encrypted UA</h1><p>Body text.</p>');

		$this->assertStringContainsString('/Encrypt ', $output, 'Document must be encrypted');

		$this->assertStringContainsString('/V 4', $output, 'Encrypted PDF/UA must use a /V 4 handler');
		$this->assertStringContainsString('/R 4', $output);
		$this->assertStringContainsString('/EncryptMetadata false', $output);
		$this->assertStringContainsString('/CFM /V2', $output, 'StdCF must define the RC4 (V2) crypt filter');
		$this->assertStringContainsString('/StmF /StdCF', $output);
		$this->assertStringContainsString('/StrF /StdCF', $output);

		$this->assertStringContainsString('/Filter[/Crypt]', $output, 'Metadata stream must carry the Identity crypt filter');
		$this->assertStringContainsString('/Name/Identity', $output);

		$this->assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $output, 'pdfuaid:part must be readable plaintext');
		$this->assertStringContainsString('Encrypted UA Title', $output, 'dc:title must be readable plaintext');
		$this->assertMatchesRegularExpression(
			'#<dc:title>.*Encrypted UA Title.*</dc:title>#s',
			$output,
			'dc:title element must contain the document title verbatim'
		);
	}

	/**
	 * An encrypted document that is not PDF/UA keeps the /V 1 or /V 2 handler and encrypts its metadata.
	 *
	 * @return void
	 */
	public function testNonUaEncryptedDocumentKeepsLegacyHandler()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'c']);
		$mpdf->compress = false;
		$mpdf->SetProtection(['print']);
		$mpdf->WriteHTML('<p>Not a UA document.</p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('/Encrypt ', $output);
		$this->assertStringNotContainsString('/V 4', $output, 'Non-UA docs must not upgrade to a /V 4 handler');
		$this->assertStringNotContainsString('/EncryptMetadata', $output);
		$this->assertStringNotContainsString('/Filter[/Crypt]', $output);
	}

	/**
	 * Only the metadata stream of an encrypted PDF/UA document is left in plain text; its content is encrypted.
	 *
	 * @return void
	 */
	public function testContentStreamsRemainEncrypted()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection(['print']);
		$marker = 'UniqueEncryptionProbe1234567';
		$output = $this->getOutput($mpdf, '<h1>Heading</h1><p>' . $marker . '</p>');

		// Uncompressed, the text would show verbatim in the content stream were it not encrypted
		$this->assertStringNotContainsString($marker, $output, 'Body content must be encrypted, not plaintext');
		$this->assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $output);
	}
}
