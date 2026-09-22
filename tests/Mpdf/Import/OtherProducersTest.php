<?php

namespace Mpdf\Import;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Importing PDFs other producers wrote with cross-reference streams and object streams, standard and hybrid.
 * Where each file came from is in tests/data/pdfs/README.md.
 */
class OtherProducersTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const DIR = __DIR__ . '/../../data/pdfs/';

	/**
	 * Each file, its page count, and whether it is a hybrid
	 */
	public function documentProvider()
	{
		return [
			'Acrobat 11: linearized, two cross-reference streams' => ['compressed-xref.pdf', 1, false],
			'qpdf: a cross-reference stream with a PNG predictor' => ['object-streams-png-predictor.pdf', 2, false],
			'Ghostscript: a cross-reference stream without a predictor' => ['object-streams-no-predictor.pdf', 2, false],
			'hybrid: a classic table, and /XRefStm for the objects in object streams' => ['hybrid-xref.pdf', 2, true],
		];
	}

	/**
	 * The file is shaped the way its case says, so the tests below go on testing what they claim to
	 *
	 * @dataProvider documentProvider
	 *
	 * @param string $file
	 * @param int    $pages
	 * @param bool   $hybrid
	 */
	public function testTheDocumentIsShapedAsDescribed($file, $pages, $hybrid)
	{
		$pdf = file_get_contents(self::DIR . $file);

		$this->assertStringNotContainsString('mPDF', $pdf);
		$this->assertMatchesRegularExpression('/\/Type\s*\/ObjStm/', $pdf);
		$this->assertMatchesRegularExpression('/\/Type\s*\/XRef\b/', $pdf);

		if ($hybrid) {
			$this->assertStringContainsString('/XRefStm', $pdf);
			$this->assertMatchesRegularExpression('/(^|\n)xref\s/', $pdf);
		} else {
			$this->assertStringNotContainsString('/XRefStm', $pdf);
			$this->assertDoesNotMatchRegularExpression('/(^|\n)xref\s/', $pdf);
		}
	}

	/**
	 * Every object the cross-reference lists is read, those in object streams among them
	 *
	 * @dataProvider documentProvider
	 *
	 * @param string $file
	 */
	public function testEveryObjectIsRead($file)
	{
		$parser = new PdfParser(StreamReader::createByFile(self::DIR . $file));
		$crossReference = $parser->getCrossReference();

		$read = 0;
		$compressed = 0;
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			$offset = $crossReference->getOffsetFor($number);
			if ($offset === false) {
				continue;
			}

			$this->assertSame($number, $parser->getIndirectObject($number)->objectNumber);
			$read++;
			$compressed += is_array($offset) ? 1 : 0;
		}

		$this->assertGreaterThan(0, $compressed, 'Some objects are in object streams');
		$this->assertGreaterThan($compressed, $read, 'Some objects are not');
	}

	/**
	 * mPDF imports every page, and each draws what it drew in the document it came from
	 *
	 * @dataProvider documentProvider
	 *
	 * @param string $file
	 * @param int    $pages
	 */
	public function testEveryPageIsImported($file, $pages)
	{
		$source = new PdfReader(new PdfParser(StreamReader::createByFile(self::DIR . $file)));
		$expected = [];
		for ($page = 1; $page <= $pages; $page++) {
			$expected[] = $source->getPage($page)->getContentStream();
		}

		$mpdf = new Mpdf(['mode' => 'c']);
		$this->assertSame($pages, $mpdf->setSourceFile(self::DIR . $file));
		for ($page = 1; $page <= $pages; $page++) {
			$mpdf->AddPage();
			$mpdf->useTemplate($mpdf->importPage($page));
		}
		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		$this->assertSame($pages, (new PdfReader(new PdfParser(StreamReader::createByString($pdf))))->getPageCount());
		$this->assertSame($expected, $this->importedPages($pdf));
	}

	/**
	 * What each page imported into the document draws: its form XObject, in the order they were written
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function importedPages($pdf)
	{
		$parser = new PdfParser(StreamReader::createByString($pdf));
		$crossReference = $parser->getCrossReference();

		$forms = [];
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			$value = $parser->getIndirectObject($number)->value;
			if ($value instanceof PdfStream && PdfDictionary::get($value->value, 'Subtype') == PdfName::create('Form')) {
				$forms[] = $value->getUnfilteredStream();
			}
		}

		return $forms;
	}

}
