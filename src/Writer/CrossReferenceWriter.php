<?php

namespace Mpdf\Writer;

use Mpdf\Import\CrossReferenceStreamReader;
use Mpdf\Import\PdfParser;
use Mpdf\Mpdf;
use Mpdf\Strict;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Ends the document with its cross-reference: a classic table, or a compressed cross-reference stream with every
 * object that is not itself a stream packed into compressed object streams (PDF 1.5)
 */
final class CrossReferenceWriter
{

	use Strict;

	/**
	 * How many objects share one object stream. A reader inflates the whole stream to reach any one of them.
	 */
	const OBJECTS_PER_STREAM = 100;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Writer\MetadataWriter
	 */
	private $metadataWriter;

	/**
	 * @param \Mpdf\Mpdf                  $mpdf
	 * @param \Mpdf\Writer\BaseWriter     $writer
	 * @param \Mpdf\Writer\MetadataWriter $metadataWriter
	 */
	public function __construct(Mpdf $mpdf, BaseWriter $writer, MetadataWriter $metadataWriter)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->metadataWriter = $metadataWriter;
	}

	/**
	 * Write the cross-reference, the trailer and the end-of-file marker after the last object, which is the catalog
	 */
	public function writeCrossReference()
	{
		if ($this->usesObjectStreams()) {
			$this->writeStream();
		} else {
			$this->writeTable();
		}

		$this->mpdf->buffer->append('%%EOF');
	}

	/**
	 * Read the cross-reference stream at the end of a document written with object streams, for OverWrite()
	 *
	 * The file is read and closed here: a parser and its cross-reference refer to each other, so dropping the parser
	 * would leave the file open until PHP collects the cycle, and Windows will not delete a file that is open.
	 *
	 * @param string $pdf  The document
	 * @param string $file The path it was read from, which the parser reads rather than a copy of $pdf
	 *
	 * @return array|null With the object 'number' and 'offset' of the cross-reference stream, its 'trailer' entries
	 *                    as written, its 'rows' of [type, field 2, field 3] by object number, and the object number
	 *                    of each of its 'pages' in order, or null for pages when there is no page tree to read. Null
	 *                    when the document does not end with one lone cross-reference stream as streamObject()
	 *                    writes it.
	 */
	public function readStream($pdf, $file)
	{
		if (!preg_match('/startxref\n(\d+)\n%%EOF$/', substr($pdf, -64), $startxref)) {
			return null;
		}

		$offset = (int) $startxref[1];
		if (!preg_match('/\G(\d+) 0 obj\n<<\/Type \/XRef (.*?) \/W \[/', $pdf, $object, 0, $offset)) {
			return null;
		}

		$handle = @fopen($file, 'rb');
		if (!$handle) {
			return null;
		}

		try {
			$parser = new PdfParser(new StreamReader($handle));
			$crossReference = $parser->getCrossReference();

			$readers = $crossReference->getReaders();
			if (count($readers) !== 1 || !$readers[0] instanceof CrossReferenceStreamReader) {
				return null;
			}

			$rows = [[0, 0, 65535]];
			for ($i = 1; $i < $crossReference->getSize(); $i++) {
				$entry = $crossReference->getOffsetFor($i);
				if (is_array($entry)) {
					$rows[] = [2, $entry[0], $entry[1]];
				} else {
					$rows[] = $entry === false ? [0, 0, 0] : [1, $entry, 0];
				}
			}

			return [
				'number' => (int) $object[1],
				'offset' => $offset,
				'trailer' => $object[2],
				'rows' => $rows,
				'pages' => $this->pages($parser),
			];
		} catch (\Exception $e) {
			return null;
		} finally {
			fclose($handle);
		}
	}

	/**
	 * The object number of each page, in order, read through the page tree, which is packed in an object stream
	 *
	 * @param \Mpdf\Import\PdfParser $parser
	 *
	 * @return int[]|null Null when there is no page tree to read
	 */
	private function pages(PdfParser $parser)
	{
		try {
			$reader = new PdfReader($parser);
			$pages = [];
			for ($page = 1; $page <= $reader->getPageCount(); $page++) {
				$pages[] = $reader->getPage($page)->getPageObject()->objectNumber;
			}

			return $pages;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Rewrite a document's cross-reference stream and startxref, moving each offset by how much the objects before
	 * it grew or shrank
	 *
	 * @param string $pdf     The document with its objects already rewritten
	 * @param array  $xref    What readStream() made of the document before they were
	 * @param int[]  $changes How much each rewritten object grew, by its offset before
	 *
	 * @return string
	 */
	public function shiftStream($pdf, array $xref, array $changes)
	{
		$shift = function ($offset) use ($changes) {
			$moved = $offset;
			foreach ($changes as $from => $change) {
				if ($offset > $from) {
					$moved += $change;
				}
			}

			return $moved;
		};

		$rows = $xref['rows'];
		foreach ($rows as $i => $row) {
			if ($row[0] === 1) {
				$rows[$i][1] = $shift($row[1]);
			}
		}

		$offset = $shift($xref['offset']);

		return substr($pdf, 0, $offset) . $this->streamObject($xref['number'], $rows, [$xref['trailer']])
			. "startxref\n" . $offset . "\n%%EOF";
	}

	/**
	 * Whether the document is written with object streams and a cross-reference stream.
	 *
	 * Not when it is uncompressed, since that is asked for to keep the file readable. Not when it is encrypted,
	 * because each string has already been encrypted as it was written, and in an object stream it would have to be
	 * left in the clear for the stream to be encrypted whole. And not for PDF/X-1a or PDF/A-1, which are built on
	 * PDF 1.4 and forbid both.
	 *
	 * @return bool
	 */
	public function usesObjectStreams()
	{
		return $this->mpdf->useObjectStreams
			&& $this->mpdf->compress
			&& !$this->mpdf->encrypted
			&& !$this->mpdf->PDFX
			&& !($this->mpdf->PDFA && strpos((string) $this->mpdf->PDFAversion, '1') === 0);
	}

	/**
	 * The classic cross-reference table and trailer, one fixed-width line per object
	 */
	private function writeTable()
	{
		$offset = $this->mpdf->buffer->getLength();

		$this->writer->write('xref');
		$this->writer->write('0 ' . ($this->mpdf->n + 1));
		$this->writer->write('0000000000 65535 f ');

		for ($i = 1; $i <= $this->mpdf->n; $i++) {
			$this->writer->write(sprintf('%010d 00000 n ', $this->mpdf->offsets[$i]));
		}

		$this->writer->write('trailer');
		$this->writer->write('<<');

		foreach ($this->metadataWriter->trailer($this->mpdf->n) as $entry) {
			$this->writer->write($entry);
		}
		$this->writer->write($this->metadataWriter->fileId());

		$this->writer->write('>>');
		$this->writer->write('startxref');
		$this->writer->write($offset);
	}

	/**
	 * Rewrite the buffered document: streams stay as they are, every other object moves into an object stream, and
	 * a cross-reference stream follows.
	 *
	 * Each object starts on its own append to the buffer, so it spans whole entries, and a stream's data goes back
	 * into the buffer without being copied.
	 */
	private function writeStream()
	{
		$root = $this->mpdf->n;
		$chunks = $this->mpdf->buffer->detach();
		$ranges = $this->ranges($chunks);

		if ($ranges === null) {
			foreach ($chunks as $chunk) {
				$this->mpdf->buffer->append($chunk);
			}
			$this->writeTable();

			return;
		}

		$first = reset($ranges);
		for ($i = 0; $i < $first[0]; $i++) {
			$this->mpdf->buffer->append($chunks[$i]);
		}

		$compressed = [];
		$packed = [];
		foreach ($ranges as $number => $range) {
			$body = $this->isStream($chunks, $range) ? null : $this->body($number, $chunks, $range);

			if ($body === null) {
				$this->mpdf->offsets[$number] = $this->mpdf->buffer->getLength();
				for ($i = $range[0]; $i < $range[1]; $i++) {
					$this->mpdf->buffer->append($chunks[$i]);
				}
			} else {
				$packed[$number] = $body;
				if (count($packed) === self::OBJECTS_PER_STREAM) {
					$compressed += $this->writeObjectStream($packed);
					$packed = [];
				}
			}

			for ($i = $range[0]; $i < $range[1]; $i++) {
				unset($chunks[$i]);
			}
		}

		if ($packed) {
			$compressed += $this->writeObjectStream($packed);
		}

		$number = ++$this->mpdf->n;
		$this->mpdf->offsets[$number] = $this->mpdf->buffer->getLength();

		$rows = self::rows($compressed, $this->mpdf->offsets, $number);

		$trailer = $this->metadataWriter->trailer($root);
		$trailer[] = $this->metadataWriter->fileId();

		$this->mpdf->buffer->append($this->streamObject($number, $rows, $trailer));
		$this->writer->write('startxref');
		$this->writer->write($this->mpdf->offsets[$number]);
	}

	/**
	 * A cross-reference row of [type, field 2, field 3] for each object from 0 to $last; an object number that was
	 * reserved but never written is free
	 *
	 * @param array[] $compressed The row of each object in an object stream, by object number
	 * @param int[]   $offsets    The byte offset of each object written in the file, by object number
	 * @param int     $last
	 *
	 * @return array[]
	 */
	private static function rows(array $compressed, array $offsets, $last)
	{
		$rows = [[0, 0, 65535]];
		for ($i = 1; $i <= $last; $i++) {
			if (isset($compressed[$i])) {
				$rows[] = $compressed[$i];
			} else {
				$rows[] = isset($offsets[$i]) ? [1, $offsets[$i], 0] : [0, 0, 0];
			}
		}

		return $rows;
	}

	/**
	 * The rows with their free entries linked in a list from object 0, each naming the next and the last naming 0
	 * (ISO 32000-1, 7.5.4)
	 *
	 * @param array[] $rows [type, field 2, field 3] for each object from 0
	 *
	 * @return array[]
	 */
	private static function linkFree(array $rows)
	{
		$next = 0;
		for ($i = count($rows) - 1; $i >= 0; $i--) {
			if ($rows[$i][0] === 0) {
				$rows[$i][1] = $next;
				$next = $i;
			}
		}

		return $rows;
	}

	/**
	 * A cross-reference stream object, from "n 0 obj" to "endobj"
	 *
	 * @param int      $number  Its object number
	 * @param array[]  $rows    [type, field 2, field 3] for each object from 0
	 * @param string[] $trailer The trailer's entries, which its dictionary carries
	 *
	 * @return string
	 */
	private function streamObject($number, array $rows, array $trailer)
	{
		list($widths, $data) = self::encode(self::linkFree($rows));
		$data = gzcompress($data);

		return $number . " 0 obj\n<</Type /XRef " . implode(' ', $trailer) . ' /W [' . implode(' ', $widths) . ']'
			. ' /Filter /FlateDecode /DecodeParms <</Columns ' . array_sum($widths) . ' /Predictor 12>>'
			. ' /Length ' . strlen($data) . ">>\nstream\n" . $data . "\nendstream\nendobj\n";
	}

	/**
	 * Pack cross-reference rows into the bytes of a cross-reference stream, each row PNG Up predicted against the one
	 * before it
	 *
	 * @param array[] $rows [type, field 2, field 3] for each object from 0
	 *
	 * @return array The field widths for /W, and the rows, not yet compressed
	 */
	private static function encode(array $rows)
	{
		$largest = max(array_map(function ($row) {
			return $row[1];
		}, $rows));

		// A 64-bit PHP packs offsets past 4 GB in up to 8 bytes (pack's J arrived in 5.6.3); a 32-bit one never holds
		// a document that large
		$wide = PHP_INT_SIZE >= 8 && version_compare(PHP_VERSION, '5.6.3', '>=');
		$format = $wide ? 'J' : 'N';
		$widths = [1, 1, 2];
		while ($widths[1] < ($wide ? 8 : 4) && $largest >> (8 * $widths[1]) > 0) {
			$widths[1]++;
		}

		$data = '';
		$previous = array_fill(1, 1 + $widths[1] + $widths[2], 0);
		foreach ($rows as $row) {
			$bytes = unpack('C*', chr($row[0]) . substr(pack($format, $row[1]), -$widths[1]) . substr(pack('N', $row[2]), -$widths[2]));

			$predicted = [2];
			foreach ($bytes as $i => $byte) {
				$predicted[] = ($byte - $previous[$i]) & 0xFF;
			}

			$data .= call_user_func_array('pack', array_merge(['C*'], $predicted));
			$previous = $bytes;
		}

		return [$widths, $data];
	}

	/**
	 * Which entries of the buffer each object spans, in the order the objects were written
	 *
	 * @param string[] $chunks
	 *
	 * @return array[]|null [first entry, entry after the last] by object number, or null when an object does not
	 *                      start at the beginning of an entry
	 */
	private function ranges(array $chunks)
	{
		$offsets = $this->mpdf->offsets;
		asort($offsets);

		$ranges = [];
		$count = count($chunks);
		$position = 0;
		$index = 0;
		$previous = null;

		foreach ($offsets as $number => $offset) {
			while ($index < $count && $position < $offset) {
				$position += strlen($chunks[$index++]);
			}

			if ($position !== $offset) {
				return null;
			}

			if ($previous !== null) {
				$ranges[$previous][1] = $index;
			}

			$ranges[$number] = [$index, $count];
			$previous = $number;
		}

		return $ranges ?: null;
	}

	/**
	 * Whether the object ends with a stream, reading only the last few bytes of it
	 *
	 * @param string[] $chunks
	 * @param int[]    $range
	 *
	 * @return bool
	 */
	private function isStream(array $chunks, array $range)
	{
		$tail = '';
		for ($i = $range[1] - 1; $i >= $range[0] && strlen($tail) < 32; $i--) {
			$tail = substr($chunks[$i], -32) . $tail;
		}

		return (bool) preg_match('/endstream\s*endobj\s*$/', $tail);
	}

	/**
	 * What an object holds, without the "n 0 obj" and "endobj" around it
	 *
	 * @param int      $number
	 * @param string[] $chunks
	 * @param int[]    $range
	 *
	 * @return string|null Null when it is not shaped as expected, and is left where it is
	 */
	private function body($number, array $chunks, array $range)
	{
		$text = '';
		for ($i = $range[0]; $i < $range[1]; $i++) {
			$text .= $chunks[$i];
		}

		$text = rtrim($text);
		$header = $number . ' 0 obj';

		if (strncmp($text, $header, strlen($header)) !== 0 || substr($text, -6) !== 'endobj') {
			return null;
		}

		$body = trim(substr($text, strlen($header), -6));

		return $body === '' ? null : $body;
	}

	/**
	 * Write objects into one object stream
	 *
	 * @param string[] $objects Bodies by object number
	 *
	 * @return array[] The cross-reference row of each object, by object number
	 */
	private function writeObjectStream(array $objects)
	{
		$this->writer->object();
		$stream = $this->mpdf->n;

		$index = [];
		$rows = [];
		$data = '';
		foreach ($objects as $number => $body) {
			$index[] = $number . ' ' . strlen($data);
			$rows[$number] = [2, $stream, count($rows)];
			$data .= $body . "\n";
		}

		$index = implode(' ', $index) . "\n";
		$data = gzcompress($index . $data);

		$this->writer->write('<</Type /ObjStm /N ' . count($objects) . ' /First ' . strlen($index)
			. ' /Filter /FlateDecode /Length ' . strlen($data) . '>>');
		$this->writer->stream($data);
		$this->writer->write('endobj');

		return $rows;
	}

}
