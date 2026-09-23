<?php

namespace Mpdf\Writer;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\Import\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * Object streams and the cross-reference stream (PDF 1.5), read back through Mpdf\Import\PdfParser
 */
class CrossReferenceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A few pages with links, bookmarks and a table of contents: many small objects that are not streams
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function document(array $config = [])
	{
		$mpdf = new Mpdf(array_merge(['mode' => 'c', 'useObjectStreams' => true], $config));
		$mpdf->SetTitle('Object streams (test)');

		$html = '<tocpagebreak />';
		for ($i = 0; $i < 150; $i++) {
			$html .= '<h3><tocentry content="Entry ' . $i . '" /><bookmark content="Entry ' . $i . '" />Entry ' . $i . '</h3>'
				. '<p><a href="https://example.com/' . $i . '">Link ' . $i . '</a></p>';
		}
		$mpdf->WriteHTML($html);

		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * Every object is where the cross-reference stream says: streams at their offset in the file, everything else
	 * in an object stream, the catalog among them
	 */
	public function testEveryObjectIsWhereTheCrossReferenceStreamSays()
	{
		$pdf = $this->document();
		$parser = new PdfParser(StreamReader::createByString($pdf));
		$crossReference = $parser->getCrossReference();
		$trailer = $crossReference->getTrailer();

		$this->assertStringStartsWith('%PDF-1.5', $pdf);
		$this->assertStringNotContainsString("\nxref\n", $pdf);
		$this->assertSame('XRef', $trailer->value['Type']->value);
		$this->assertSame(12, $trailer->value['DecodeParms']->value['Predictor']->value);

		$packed = 0;
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			$offset = $crossReference->getOffsetFor($number);
			$this->assertNotFalse($offset, 'Object ' . $number);

			if (is_array($offset)) {
				$this->assertNotInstanceOf(PdfStream::class, $parser->getIndirectObject($number)->value);
				$packed++;
			} else {
				$this->assertSame($number . ' 0 obj', substr($pdf, $offset, strlen($number . ' 0 obj')), 'Object ' . $number);
				$this->assertInstanceOf(PdfStream::class, $parser->getIndirectObject($number)->value);
			}
		}

		$this->assertGreaterThan(300, $packed);

		$root = $trailer->value['Root']->value;
		$this->assertIsArray($crossReference->getOffsetFor($root));
		$catalog = $parser->getIndirectObject($root)->value;
		$this->assertEquals(PdfName::create('Catalog'), $catalog->value['Type']);
	}

	/**
	 * Each document that has to keep to the classic table, and why
	 */
	public function classicTableProvider()
	{
		return [
			'not asked for, which is the default' => [[], true, false],
			'not compressed, so readable' => [['useObjectStreams' => true], false, false],
			'encrypted, as each string is encrypted where it is written' => [['useObjectStreams' => true], true, true],
			'PDF/X-1a, built on PDF 1.4' => [['useObjectStreams' => true, 'mode' => '', 'PDFX' => true, 'PDFXauto' => true], true, false],
			'PDF/A-1b, built on PDF 1.4' => [['useObjectStreams' => true, 'mode' => '', 'PDFA' => true, 'PDFAauto' => true], true, false],
		];
	}

	/**
	 * @dataProvider classicTableProvider
	 *
	 * @param array $config
	 * @param bool  $compress
	 * @param bool  $protect
	 */
	public function testSomeDocumentsKeepTheClassicTable(array $config, $compress, $protect)
	{
		$mpdf = new Mpdf(array_merge(['mode' => 'c'], $config));
		$mpdf->SetCompression($compress);
		if ($protect) {
			$mpdf->SetProtection(['print']);
		}
		$mpdf->WriteHTML('<p><a href="https://example.com">Link</a></p>');
		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		$this->assertStringContainsString("\nxref\n0 ", $pdf);
		$this->assertStringNotContainsString('/Type /ObjStm', $pdf);
		$this->assertStringNotContainsString('/Type /XRef', $pdf);
	}

	/**
	 * PDF/A-3 is built on PDF 1.7, so it gets object streams like any other document
	 */
	public function testPdfA3UsesObjectStreams()
	{
		$pdf = $this->document(['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B']);

		$this->assertStringContainsString('/Type /ObjStm', $pdf);
		$this->assertStringNotContainsString("\nxref\n", $pdf);
	}

	/**
	 * Call one of the cross-reference writer's private static methods
	 *
	 * @param string $method
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	private function call($method, array $arguments)
	{
		$call = \Closure::bind(function () use ($method, $arguments) {
			return call_user_func_array([CrossReferenceWriter::class, $method], $arguments);
		}, null, CrossReferenceWriter::class);

		return $call();
	}

	/**
	 * An object number reserved but never written is free, and the free entries are linked in a list from object 0,
	 * each naming the next and the last naming 0 (ISO 32000-1, 7.5.4)
	 */
	public function testObjectNumbersNeverWrittenAreLinkedAsFree()
	{
		$rows = $this->call('linkFree', [$this->call('rows', [[5 => [2, 1, 0]], [1 => 15, 4 => 90], 6])]);

		$this->assertSame([
			0 => [0, 2, 65535],
			1 => [1, 15, 0],
			2 => [0, 3, 0],
			3 => [0, 6, 0],
			4 => [1, 90, 0],
			5 => [2, 1, 0],
			6 => [0, 0, 0],
		], $rows);
	}

	/**
	 * An offset past 4 GB gets as many bytes as it needs rather than being cut short
	 */
	public function testAnOffsetPastFourGigabytesIsWrittenWhole()
	{
		if (PHP_INT_SIZE < 8) {
			$this->markTestSkipped('A 32-bit PHP cannot hold an offset past 4 GB');
		}

		list($widths, $data) = $this->call('encode', [[[0, 0, 65535], [1, 0x123456789A, 0]]]);

		$this->assertSame([1, 5, 2], $widths);
		// The second row, PNG Up predicted against the first: type 1, then the offset in five bytes, then generation 0
		// less the first row's 65535
		$this->assertSame("\x02\x01\x12\x34\x56\x78\x9A\x01\x01", substr($data, 9));
	}

	/**
	 * A document that asks for a later version than 1.5 keeps it
	 */
	public function testALaterVersionIsKept()
	{
		$pdf = $this->document(['pdf_version' => '1.7']);

		$this->assertStringStartsWith('%PDF-1.7', $pdf);
	}

}
