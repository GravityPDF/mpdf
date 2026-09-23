<?php

namespace Mpdf\Import;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

/**
 * Reading cross-reference streams and object streams when a PDF is imported
 */
class ObjectStreamCrossReferenceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Three pages with links and bookmarks, written with or without object streams
	 *
	 * @param bool $objectStreams
	 *
	 * @return string
	 */
	private function document($objectStreams)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'useObjectStreams' => $objectStreams, 'creationDate' => 0]);
		$mpdf->SetTitle('Read back');

		for ($i = 1; $i <= 3; $i++) {
			$mpdf->WriteHTML(($i > 1 ? '<pagebreak />' : '') . '<h2><bookmark content="Page ' . $i . '" />Page ' . $i . '</h2>'
				. '<p><a href="https://example.com/' . $i . '">Link ' . $i . '</a></p>');
		}

		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * Every object in the document, as the parser resolves it, streams inflated, less the object and
	 * cross-reference streams that only carry the others
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function objects($pdf)
	{
		$parser = new PdfParser(StreamReader::createByString($pdf));
		$crossReference = $parser->getCrossReference();

		$objects = [];
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			if ($crossReference->getOffsetFor($number) === false) {
				continue;
			}

			$value = $parser->getIndirectObject($number)->value;
			if ($value instanceof PdfStream) {
				$type = PdfDictionary::get($value->value, 'Type');
				if (in_array($type->value, ['ObjStm', 'XRef'], true)) {
					continue;
				}
				$value = $value->getUnfilteredStream();
			}

			$objects[] = $this->flatten($value);
		}

		return $objects;
	}

	/**
	 * A parsed value as plain PHP, so two can be compared
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function flatten($value)
	{
		if ($value instanceof PdfType) {
			$value = $value->value;
		}

		return is_array($value) ? array_map([$this, 'flatten'], $value) : $value;
	}

	/**
	 * A document written with object streams holds the same objects, in the same order, as one written with a
	 * classic table, once the parser has taken them out of their object streams
	 */
	public function testObjectsReadTheSameOutOfObjectStreams()
	{
		$classic = $this->document(false);
		$compressed = $this->document(true);

		$this->assertStringContainsString('/Type /ObjStm', $compressed);
		$this->assertSame($this->objects($classic), $this->objects($compressed));
	}

	/**
	 * mPDF imports the pages of a document it wrote with object streams
	 */
	public function testMpdfImportsWhatItWritesWithObjectStreams()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$this->assertSame(3, $mpdf->setSourceFile(StreamReader::createByString($this->document(true))));

		for ($page = 1; $page <= 3; $page++) {
			$mpdf->AddPage();
			$mpdf->useTemplate($mpdf->importPage($page));
		}

		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		$this->assertStringContainsString('/URI (https://example.com/3)', $pdf);
	}

	/**
	 * The header and the objects, each given as its number and what follows "n 0 obj"
	 *
	 * @param string[] $objects Bodies by object number
	 * @param string   $pdf     What comes before them
	 *
	 * @return array [the document so far, the offset of each object by number]
	 */
	private function objectsFrom(array $objects, $pdf = "%PDF-1.5\n")
	{
		$offsets = [];
		foreach ($objects as $number => $body) {
			$offsets[$number] = strlen($pdf);
			$pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
		}

		return [$pdf, $offsets];
	}

	/**
	 * A hybrid file: the objects, a cross-reference table listing all of them, and a trailer whose /XRefStm points at
	 * the object numbered $xrefStm
	 *
	 * @param string[] $objects Bodies by object number
	 * @param int      $xrefStm
	 *
	 * @return string
	 */
	private function hybrid(array $objects, $xrefStm)
	{
		list($pdf, $offsets) = $this->objectsFrom($objects);

		$size = max(array_keys($objects)) + 1;
		$table = strlen($pdf);
		$pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
		for ($i = 1; $i < $size; $i++) {
			$pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 00000 f \n";
		}

		return $pdf . "trailer\n<</Size " . $size . ' /Root 1 0 R /XRefStm ' . $offsets[$xrefStm] . ">>\nstartxref\n" . $table . "\n%%EOF";
	}

	/**
	 * A stream object's body
	 *
	 * @param string $dictionary Entries besides /Length
	 * @param string $data
	 *
	 * @return string
	 */
	private function stream($dictionary, $data)
	{
		return '<<' . $dictionary . ' /Length ' . strlen($data) . ">>\nstream\n" . $data . "\nendstream";
	}

	/**
	 * A hybrid file: a classic table for readers of PDF 1.4, whose /XRefStm lists the objects it keeps in object
	 * streams. The table is read first, then the stream for what it leaves out.
	 */
	public function testAHybridFileReadsTheObjectsItsTableLeavesOut()
	{
		$pdf = $this->hybrid([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [3 0 R] /Count 1>>',
			3 => '<</Type /Page /Parent 2 0 R /MediaBox [0 0 100 100]>>',
			4 => $this->stream('/Type /ObjStm /N 1 /First 4 /Filter /FlateDecode', gzcompress("5 0\n<</Hidden true>>")),
			6 => '<</Tabled true>>',
			7 => $this->stream('/Type /XRef /Size 8 /Index [5 2] /W [1 1 1]', "\x02\x04\x00\x02\x04\x00"),
		], 7);

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertTrue($parser->getIndirectObject(5)->value->value['Hidden']->value);
		// The stream says object 6 is compressed too, but the table is read first
		$this->assertTrue($parser->getIndirectObject(6)->value->value['Tabled']->value);
	}

	/**
	 * A hybrid file whose stream cannot be read opens as a reader of PDF 1.4 would open it, from its table
	 */
	public function testAHybridFileWithABrokenStreamStillReadsItsTable()
	{
		$pdf = $this->hybrid([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
		], 2);

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertSame('Catalog', $parser->getIndirectObject(1)->value->value['Type']->value);
	}

	/**
	 * A cross-reference stream with a type field of width 0, which stands for type 1, and three-byte offsets, as
	 * other producers write them
	 */
	public function testACrossReferenceStreamWithADefaultTypeFieldIsRead()
	{
		list($pdf, $offsets) = $this->objectsFrom([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
		]);
		$offsets[3] = strlen($pdf);

		$rows = '';
		foreach ($offsets as $offset) {
			$rows .= substr(pack('N', $offset), 1) . "\0";
		}

		$pdf .= "3 0 obj\n" . $this->stream('/Type /XRef /Size 4 /Index [1 3] /W [0 3 1] /Root 1 0 R', $rows)
			. "\nendobj\nstartxref\n" . $offsets[3] . "\n%%EOF";

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertSame('Pages', $parser->getIndirectObject(2)->value->value['Type']->value);
	}

	/**
	 * An object that is not where its object stream's header says is reported missing rather than misread
	 */
	public function testAnObjectMissingFromItsObjectStreamIsNotFound()
	{
		$this->expectException(CrossReferenceException::class);
		$this->expectExceptionCode(CrossReferenceException::OBJECT_NOT_FOUND);

		$pdf = $this->hybrid([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			3 => $this->stream('/Type /ObjStm /N 1 /First 4', "9 0\n<<>>"),
			5 => $this->stream('/Type /XRef /Size 6 /Index [4 1] /W [1 1 1]', "\x02\x03\x00"),
		], 5);

		(new PdfParser(StreamReader::createByString($pdf)))->getIndirectObject(4);
	}

	/**
	 * One row of a cross-reference stream with /W [1 2 1]
	 *
	 * @param int $type
	 * @param int $second
	 * @param int $third
	 *
	 * @return string
	 */
	private function row($type, $second, $third = 0)
	{
		return chr($type) . pack('n', $second) . chr($third);
	}

	/**
	 * A section of a file ending in a cross-reference stream: the objects, then an uncompressed cross-reference stream
	 * with /W [1 2 1] listing them, the other $rows given and itself, whose catalog is object 1, then startxref
	 *
	 * @param string[] $objects Bodies by object number
	 * @param int      $number  The cross-reference stream's object number
	 * @param array[]  $rows    [type, field 2, field 3] of objects not among $objects, by object number
	 * @param string   $pdf     What comes before it
	 * @param string   $entries More of its dictionary, such as /Prev for an update
	 *
	 * @return array [the document so far, the offset of the cross-reference stream]
	 */
	private function section(array $objects, $number, array $rows = [], $pdf = "%PDF-1.5\n", $entries = '')
	{
		list($pdf, $offsets) = $this->objectsFrom($objects, $pdf);
		foreach ($offsets as $object => $offset) {
			$rows[$object] = [1, $offset, 0];
		}

		$offset = strlen($pdf);
		$rows[$number] = [1, $offset, 0];
		ksort($rows);

		$index = [];
		$data = '';
		foreach ($rows as $object => $row) {
			$index[] = $object . ' 1';
			$data .= $this->row($row[0], $row[1], $row[2]);
		}

		$dictionary = '/Type /XRef /Size ' . (max(array_keys($rows)) + 1) . ' /Index [' . implode(' ', $index) . '] /W [1 2 1] /Root 1 0 R ' . $entries;

		return [
			$pdf . $number . " 0 obj\n" . $this->stream($dictionary, $data) . "\nendobj\nstartxref\n" . $offset . "\n%%EOF\n",
			$offset,
		];
	}

	/**
	 * A cross-reference stream may give its /Length as an indirect object (ISO 32000-1, 7.5.8.2 only asks the entries
	 * of its own table to be direct). Looking that up would go through the cross-reference being read, so the stream
	 * is read to endstream instead.
	 */
	public function testACrossReferenceStreamWithAnIndirectLengthIsRead()
	{
		list($pdf, $offsets) = $this->objectsFrom([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
		]);

		// Four rows of four bytes: the length is known before the offsets it holds are
		$offsets[3] = strlen($pdf);
		$pdf .= "3 0 obj\n16\nendobj\n";
		$offsets[4] = strlen($pdf);

		$rows = '';
		foreach ($offsets as $offset) {
			$rows .= $this->row(1, $offset);
		}

		$pdf .= "4 0 obj\n<</Type /XRef /Size 5 /Index [1 4] /W [1 2 1] /Root 1 0 R /Length 3 0 R>>\nstream\n" . $rows
			. "\nendstream\nendobj\nstartxref\n" . $offsets[4] . "\n%%EOF";

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertSame('Pages', $parser->getIndirectObject(2)->value->value['Type']->value);
	}

	/**
	 * An object stream whose /Length is an object inside it cannot be read, which 7.5.7 forbids; it is refused
	 * rather than looked up without end
	 */
	public function testAnObjectStreamThatNeedsItselfToBeReadIsRefused()
	{
		list($pdf) = $this->section([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			6 => "<</Type /ObjStm /N 1 /First 4 /Length 5 0 R>>\nstream\n5 0\n6\nendstream",
		], 7, [5 => [2, 6, 0]]);

		try {
			$object = (new PdfParser(StreamReader::createByString($pdf)))->getIndirectObject(5);
		} catch (CrossReferenceException $e) {
			$this->assertStringContainsString('needs itself', $e->getMessage());

			return;
		}

		// FPDI before 2.2 does not look an indirect /Length up, and reads the stream to endstream instead
		$this->assertSame(6, $object->value->value);
	}

	/**
	 * The entry types an update can give an object that stop it being found in an older section: free, and a type
	 * no reader knows, which is a reference to the null object
	 */
	public function freedTypeProvider()
	{
		return [
			'free' => [0],
			'unknown' => [7],
		];
	}

	/**
	 * An object freed in an update is gone, however an older section listed it (ISO 32000-1, 7.5.6 and 7.5.8.3)
	 *
	 * @dataProvider freedTypeProvider
	 *
	 * @param int $type
	 */
	public function testAnObjectFreedInAnUpdateIsGone($type)
	{
		list($pdf, $offset) = $this->section([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			3 => '<</Old true>>',
		], 4);
		list($pdf) = $this->section([], 5, [3 => [$type, 0, 0]], $pdf, '/Prev ' . $offset);

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertFalse($parser->getCrossReference()->getOffsetFor(3));
		$this->assertSame('Pages', $parser->getIndirectObject(2)->value->value['Type']->value);
	}

	/**
	 * A section of a file ending in a classic cross-reference table: the objects, then a table listing them in
	 * subsections of one, the objects numbered in $free marked free, then a trailer whose catalog is object 1
	 *
	 * @param string[] $objects Bodies by object number
	 * @param int[]    $free
	 * @param string   $pdf     What comes before it
	 * @param string   $entries More of the trailer, such as /Prev for an update
	 *
	 * @return array [the document so far, the offset of the table]
	 */
	private function classic(array $objects, array $free = [], $pdf = "%PDF-1.5\n", $entries = '')
	{
		list($pdf, $offsets) = $this->objectsFrom($objects, $pdf);

		$lines = [0 => "0000000000 65535 f \n"];
		foreach ($offsets as $object => $offset) {
			$lines[$object] = sprintf("%010d 00000 n \n", $offset);
		}
		foreach ($free as $object) {
			$lines[$object] = "0000000000 00001 f \n";
		}
		ksort($lines);

		$table = strlen($pdf);
		$pdf .= "xref\n";
		foreach ($lines as $object => $line) {
			$pdf .= $object . " 1\n" . $line;
		}

		return [
			$pdf . 'trailer' . "\n<</Size " . (max(array_keys($lines)) + 1) . ' /Root 1 0 R ' . $entries . ">>\nstartxref\n" . $table . "\n%%EOF\n",
			$table,
		];
	}

	/**
	 * An object an update's classic table marks free is gone, however an older section listed it (ISO 32000-1, 7.5.6)
	 */
	public function testAnObjectAClassicUpdateFreesIsGone()
	{
		list($pdf, $offset) = $this->classic([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			3 => '<</Old true>>',
		]);
		list($pdf) = $this->classic([], [3], $pdf, '/Prev ' . $offset);

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertFalse($parser->getCrossReference()->getOffsetFor(3));
		$this->assertSame('Pages', $parser->getIndirectObject(2)->value->value['Type']->value);
	}

	/**
	 * The generation of an object is the one its cross-reference lists: from a classic table, from a cross-reference
	 * stream's third field, and 0 for an object in an object stream
	 */
	public function testTheGenerationIsTheOneTheCrossReferenceLists()
	{
		list($pdf) = $this->section([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			3 => $this->stream('/Type /ObjStm /N 1 /First 4', "8 0\n<<>>"),
		], 4, [5 => [1, 9, 2], 8 => [2, 3, 0]]);
		$crossReference = (new PdfParser(StreamReader::createByString($pdf)))->getCrossReference();

		$this->assertSame(2, $crossReference->getGenerationFor(5));
		$this->assertSame(0, $crossReference->getGenerationFor(8));
		$this->assertSame(0, $crossReference->getGenerationFor(2));
		$this->assertNull($crossReference->getGenerationFor(6));

		list($pdf) = $this->classic([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
		]);

		$this->assertSame(0, (new PdfParser(StreamReader::createByString($pdf)))->getCrossReference()->getGenerationFor(2));
	}

	/**
	 * A reference to a generation of an object other than the one the cross-reference lists is to the null object
	 * (ISO 32000-1, 7.3.10 and 7.5.4): an imported page copies it as null, and a reference to the current one as it is
	 */
	public function testAReferenceToAnOldGenerationIsCopiedAsNull()
	{
		list($pdf) = $this->classic([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [3 0 R] /Count 1>>',
			3 => '<</Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 6 0 R'
				. ' /Resources <</Font <</Current 4 0 R /Old 5 1 R>>>>>>',
			4 => '<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>',
			5 => '<</Type /Font /Subtype /Type1 /BaseFont /Courier>>',
			6 => $this->stream('', 'BT /Current 12 Tf (x) Tj ET'),
		]);

		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->setSourceFile(StreamReader::createByString($pdf));
		$mpdf->AddPage();
		$mpdf->useTemplate($mpdf->importPage(1));
		$output = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		$parser = new PdfParser(StreamReader::createByString($output));
		$crossReference = $parser->getCrossReference();
		$fonts = null;
		for ($number = 1; $number < $crossReference->getSize() && $fonts === null; $number++) {
			$value = $parser->getIndirectObject($number)->value;
			if ($value instanceof PdfStream && PdfDictionary::get($value->value, 'Subtype')->value === 'Form') {
				$resources = PdfType::resolve(PdfDictionary::get($value->value, 'Resources'), $parser);
				$fonts = PdfType::resolve(PdfDictionary::get($resources, 'Font'), $parser);
			}
		}

		$this->assertInstanceOf(\setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference::class, $fonts->value['Current']);
		// Written as "/Old null", which reads the same as no entry at all (7.3.7)
		$this->assertFalse(isset($fonts->value['Old']) && !$fonts->value['Old'] instanceof \setasign\Fpdi\PdfParser\Type\PdfNull);
	}

	/**
	 * An object whose index in its object stream is wrong is still found, by the number the stream's header gives it
	 */
	public function testAnObjectAtTheWrongIndexIsFoundByItsNumber()
	{
		list($pdf) = $this->section([
			1 => '<</Type /Catalog /Pages 2 0 R>>',
			2 => '<</Type /Pages /Kids [] /Count 0>>',
			3 => $this->stream('/Type /ObjStm /N 2 /First 9', "8 0 9 16\n<</Eight true>> <</Nine true>>"),
		], 4, [8 => [2, 3, 1], 9 => [2, 3, 0]]);

		$parser = new PdfParser(StreamReader::createByString($pdf));

		$this->assertTrue($parser->getIndirectObject(9)->value->value['Nine']->value);
		$this->assertTrue($parser->getIndirectObject(8)->value->value['Eight']->value);
	}

}
