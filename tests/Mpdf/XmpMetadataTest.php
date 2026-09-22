<?php

namespace Mpdf;

use Mpdf\Output\Destination;

/**
 * The XMP packet a PDF/A, PDF/X or PDF/UA document carries is written with the same bytes
 * whatever the line endings of the checkout the library is running from.
 */
class XmpMetadataTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A document carrying a title, an author, a subject and keywords, so every Dublin Core
	 * property the metadata writer knows how to write is written.
	 *
	 * @param array $config Configuration merged over the reproducible defaults
	 *
	 * @return string
	 */
	private function describedDocument(array $config)
	{
		$mpdf = new Mpdf($config + [
			'creationDate' => 946684800,
			'exposeVersion' => false,
			'title' => 'Quarterly report',
		]);
		$mpdf->compress = false;
		$mpdf->SetAuthor('A. Author');
		$mpdf->SetSubject('What the report is about');
		$mpdf->SetKeywords('quarter, report');
		$mpdf->WriteHTML('<p>Described</p>');

		return $mpdf->Output(null, Destination::STRING_RETURN);
	}

	/**
	 * The XMP packet out of a written document.
	 *
	 * @param string $pdf The document
	 *
	 * @return string
	 */
	private function xmpPacket($pdf)
	{
		$this->assertSame(1, preg_match('/<\?xpacket begin=.*?<\?xpacket end="w"\?>/s', $pdf, $matches), 'the document has an XMP packet');

		return $matches[0];
	}

	/**
	 * The standards that make mPDF write an XMP packet.
	 *
	 * @return array
	 */
	public function standardProvider()
	{
		return [
			'PDF/A' => [['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => true]],
			'PDF/X' => [['mode' => 'utf-8', 'PDFX' => true, 'PDFXauto' => true]],
			'PDF/UA' => [['mode' => 'en-GB', 'PDFUA' => true, 'PDFUAauto' => true]],
		];
	}

	/**
	 * A Dublin Core property is written with "\n" between its lines, never "\r\n". Git hands a
	 * Windows user a checkout with CRLF endings, so a string literal spanning several lines of
	 * the source would otherwise put a carriage return into the packet.
	 *
	 * @dataProvider standardProvider
	 *
	 * @param array $config The standard to write the document to
	 */
	public function testTheXmpPacketHasNoCarriageReturns(array $config)
	{
		$packet = $this->xmpPacket($this->describedDocument($config));

		$this->assertStringContainsString('<dc:title>', $packet, 'the title is in the packet');
		$this->assertStringContainsString('<dc:creator>', $packet, 'the author is in the packet');
		$this->assertStringContainsString('<dc:description>', $packet, 'the subject is in the packet');
		$this->assertStringContainsString('<dc:subject>', $packet, 'the keywords are in the packet');

		$this->assertStringNotContainsString("\r", $packet);
	}

	/**
	 * The classes that serialise PDF bytes. A string literal spanning several lines of one of
	 * these is written out with whatever line endings the checkout has, which differs between
	 * platforms; this is what let the carriage returns above into the packet in the first place,
	 * and it is invisible to a test run on a checkout with "\n" endings.
	 *
	 * @return array
	 */
	public function byteWritingFileProvider()
	{
		$files = ['src/Form.php'];

		foreach (glob(__DIR__ . '/../../src/Writer/*.php') as $file) {
			$files[] = 'src/Writer/' . basename($file);
		}

		$cases = [];
		foreach ($files as $file) {
			$cases[$file] = [$file];
		}

		return $cases;
	}

	/**
	 * No literal in those classes spans a line of the source.
	 *
	 * @dataProvider byteWritingFileProvider
	 *
	 * @param string $file The file, relative to the package root
	 */
	public function testNoWrittenLiteralSpansALineOfTheSource($file)
	{
		$spanning = [];

		foreach (token_get_all(file_get_contents(__DIR__ . '/../../' . $file)) as $token) {
			if (!is_array($token) || strpos($token[1], "\n") === false) {
				continue;
			}

			if ($token[0] === T_CONSTANT_ENCAPSED_STRING || $token[0] === T_ENCAPSED_AND_WHITESPACE) {
				$spanning[] = $file . ' line ' . $token[2];
			}
		}

		$this->assertSame([], $spanning, 'write "\n" rather than a newline in the source');
	}
}
