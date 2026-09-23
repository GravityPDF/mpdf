<?php

namespace Mpdf;

use Mpdf\Output\Destination;
use Mpdf\Import\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;

class OverWriteTest extends BaseMpdfTest
{

	/**
	 * @var string[]
	 */
	private $files = [];

	protected function tear_down()
	{
		parent::tear_down();

		foreach ($this->files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		$this->files = [];
	}

	private function file($contents)
	{
		$file = tempnam(sys_get_temp_dir(), 'OverWrite');
		file_put_contents($file, $contents);
		$this->files[] = $file;

		return $file;
	}

	/**
	 * A two page document, written the way OverWrite() expects to find one
	 */
	private function source($compress = false, $objectStreams = false)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'useObjectStreams' => $objectStreams]);
		$mpdf->compress = $compress;
		$mpdf->WriteHTML('<p>MAIN HEADING one</p><pagebreak /><p>MAIN HEADING two</p>');

		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * The same document with its line endings changed in transit, as mpdf/mpdf#626 had it
	 */
	private function crlfSource()
	{
		return $this->file(str_replace("\n", "\r\n", $this->source()));
	}

	private function overWrite($file, $compress = false)
	{
		$this->mpdf->compress = $compress;

		return $this->mpdf->OverWrite($file, ['MAIN HEADING'], ['replacement'], Destination::STRING_RETURN);
	}

	/**
	 * Every object the cross-reference places in the file is at the offset it gives
	 *
	 * @param string $pdf
	 *
	 * @return int How many objects were checked
	 */
	private function assertEachOffsetLandsOnItsObject($pdf)
	{
		$crossReference = (new PdfParser(StreamReader::createByString($pdf)))->getCrossReference();
		$checked = 0;
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			$offset = $crossReference->getOffsetFor($number);
			if (is_int($offset)) {
				$this->assertSame($number . ' 0 obj', substr($pdf, $offset, strlen($number . ' 0 obj')), 'Object ' . $number);
				$checked++;
			}
		}

		return $checked;
	}

	/**
	 * The [filter, text] of each content stream in $pdf, inflating the ones that say they are compressed
	 */
	private function streams($pdf)
	{
		preg_match_all("/<<(\/Filter \/FlateDecode )?\/Length \d+>>\nstream\n(.*?)\nendstream/s", $pdf, $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			return [$match[1], $match[1] ? gzuncompress($match[2]) : $match[2]];
		}, $matches);
	}

	public function compressionProvider()
	{
		return [
			'uncompressed document, uncompressed instance' => [false, false],
			'compressed document, compressed instance' => [true, true],
			'uncompressed document, compressed instance' => [false, true],
			'compressed document, uncompressed instance' => [true, false],
		];
	}

	/**
	 * Whether a stream is compressed is read from the document, not from the instance doing the overwriting
	 *
	 * @dataProvider compressionProvider
	 */
	public function testTextIsReplacedHoweverTheDocumentAndInstanceAreCompressed($documentCompressed, $instanceCompressed)
	{
		$pdf = $this->overWrite($this->file($this->source($documentCompressed)), $instanceCompressed);
		$streams = $this->streams($pdf);
		$text = implode("\n", array_column($streams, 1));

		$this->assertSame(2, substr_count($text, 'replacement'));
		$this->assertStringNotContainsString('MAIN HEADING', $text);

		$filter = $documentCompressed ? '/Filter /FlateDecode ' : '';
		$this->assertSame([$filter, $filter], array_column($streams, 0));
	}

	/**
	 * The offsets the method keeps have to still add up, or the reader has to repair the file
	 */
	public function testTheCrossReferenceTableStillPointsAtItself()
	{
		$pdf = $this->overWrite($this->file($this->source()));

		$matches = [];
		preg_match("/startxref\n(\d+)\n%%EOF/", $pdf, $matches);

		$this->assertNotEmpty($matches);
		$this->assertSame("xref\n0 ", substr($pdf, (int) $matches[1], 7));
	}

	/**
	 * The document is read through a parser, which is not freed as soon as it is dropped. The file has to be closed
	 * all the same, or Windows refuses to delete it.
	 */
	public function testADocumentWithObjectStreamsIsLeftClosed()
	{
		if (!function_exists('get_resources')) {
			$this->markTestSkipped('get_resources() needs PHP 7');
		}

		$file = $this->file($this->source(true, true));
		$this->overWrite($file, true);

		foreach (get_resources('stream') as $stream) {
			$meta = stream_get_meta_data($stream);
			if (isset($meta['uri'])) {
				$this->assertNotSame(realpath($file), realpath($meta['uri']), 'The document is still open');
			}
		}
	}

	/**
	 * A document written with object streams has its page tree packed into one and its offsets in a cross-reference
	 * stream: the text is still replaced, and every offset in the stream still lands on its object
	 */
	public function testTheCrossReferenceStreamStillPointsAtEachObject()
	{
		$pdf = $this->overWrite($this->file($this->source(true, true)), true);
		$text = implode("\n", array_column($this->streams($pdf), 1));

		$this->assertSame(2, substr_count($text, 'replacement'));
		$this->assertStringNotContainsString('MAIN HEADING', $text);

		$this->assertGreaterThan(0, $this->assertEachOffsetLandsOnItsObject($pdf));
	}

	/**
	 * Each kind of cross-reference a document can end with
	 */
	public function crossReferenceProvider()
	{
		return [
			'classic table' => [false],
			'cross-reference stream' => [true],
		];
	}

	/**
	 * When the replacement is longer, each page's content grows and every object after it moves: each offset moves by
	 * what grew before where it was, however many pages grew
	 *
	 * @dataProvider crossReferenceProvider
	 *
	 * @param bool $objectStreams
	 */
	public function testAReplacementLongerThanTheTextMovesEveryObjectAfterIt($objectStreams)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'useObjectStreams' => $objectStreams]);
		for ($page = 1; $page <= 6; $page++) {
			$mpdf->WriteHTML(($page > 1 ? '<pagebreak />' : '') . '<p>PLACEHOLDER on page ' . $page . '</p>');
		}
		$source = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		$pdf = $this->mpdf->OverWrite($this->file($source), ['PLACEHOLDER'], ['A replacement a good deal longer than the text'], Destination::STRING_RETURN);

		$this->assertSame(6, substr_count(implode("\n", array_column($this->streams($pdf), 1)), 'A replacement a good deal longer'));

		$this->assertGreaterThan(6, $this->assertEachOffsetLandsOnItsObject($pdf));
	}

	/**
	 * The file ID's second part names this version of the document, so it changes when the document does, and the
	 * first stays the document's own (ISO 32000-1, 14.4)
	 *
	 * @dataProvider crossReferenceProvider
	 *
	 * @param bool $objectStreams
	 */
	public function testTheFileIdNamesTheNewVersion($objectStreams)
	{
		$source = $this->source(true, $objectStreams);
		$pdf = $this->overWrite($this->file($source), true);

		$id = function ($pdf) {
			$id = (new PdfParser(StreamReader::createByString($pdf)))->getCrossReference()->getTrailer()->value['ID']->value;

			return [$id[0]->value, $id[1]->value];
		};
		$before = $id($source);
		$after = $id($pdf);

		$this->assertSame($before[0], $after[0]);
		$this->assertNotSame($before[1], $after[1]);
	}

	/**
	 * mpdf/mpdf#626: none of the patterns matched, every $m[1] and $m[2] read raised an undefined
	 * key, and what came back was a document whose cross-reference table had been rewritten from
	 * nothing - readers report it as damaged and repair it, and none of the text was replaced.
	 */
	public function testADocumentWrittenWithOtherLineEndingsIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('no cross-reference table of the kind mPDF writes was found in it');

		$this->overWrite($this->crlfSource());
	}

	public function testADocumentFromSomewhereElseIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Cannot overwrite');

		$this->overWrite(__DIR__ . '/../data/pdfs/compressed-xref.pdf');
	}

	public function testRefusingRaisesNothingOfItsOwn()
	{
		$crlf = $this->crlfSource();

		$raised = [];
		set_error_handler(function ($number, $message) use (&$raised) {
			$raised[] = $message;

			return true;
		});

		try {
			$this->overWrite($crlf);
		} catch (MpdfException $e) {
			// the point of the test is what was raised on the way, not that it was
		}

		restore_error_handler();

		$this->assertSame([], $raised);
	}

}
