<?php

namespace Mpdf\Import;

use setasign\Fpdi\PdfParser\CrossReference\CrossReference as FpdiCrossReference;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\FixedReader;
use setasign\Fpdi\PdfParser\CrossReference\LineReader;
use setasign\Fpdi\PdfParser\PdfParser as BasePdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

/**
 * The cross-reference of an imported PDF, read from classic tables and from cross-reference streams, with the
 * objects they place in object streams (PDF 1.5)
 *
 * Part of mPDF, written from the PDF specification (ISO 32000-1, 7.5.7 and 7.5.8).
 */
class ObjectStreamCrossReference extends FpdiCrossReference
{

	/**
	 * How many object streams are kept read. Objects are copied in no particular order, so a page's resources can
	 * alternate between a few of them.
	 */
	const OBJECT_STREAMS_KEPT = 8;

	/**
	 * @var \SplObjectStorage|null The /XRefStm of a hybrid file's table, by that table's reader
	 */
	private $hybridStreams;

	/**
	 * @var array[] The object streams read last, as objectStream() returns them, by object number
	 */
	private $objectStreams = [];

	/**
	 * @var bool[] The object streams being read, by object number, so one that needs itself to be read is refused
	 */
	private $loading = [];

	/**
	 * @param int $objectNumber
	 *
	 * @return int|int[]|false A byte offset, [object stream number, index in it], or false when it is not listed
	 */
	public function getOffsetFor($objectNumber)
	{
		$entry = $this->entry($objectNumber);

		return $entry ? $entry[0] : false;
	}

	/**
	 * The generation of the object the cross-reference lists: a reference with another generation is to an object
	 * that no longer exists, and so to the null object (ISO 32000-1, 7.3.10 and 7.5.4)
	 *
	 * @param int $objectNumber
	 *
	 * @return int|null Null when the object is not listed, or the section listing it does not say
	 */
	public function getGenerationFor($objectNumber)
	{
		$entry = $this->entry($objectNumber);
		if (!$entry) {
			return null;
		}

		list(, $reader) = $entry;
		if ($reader instanceof FixedReader) {
			return (int) substr($this->tableLine($reader, $objectNumber), 11, 5);
		}
		if ($reader instanceof LineReader) {
			$offsets = $reader->getOffsets();

			return isset($offsets[$objectNumber][1]) ? $offsets[$objectNumber][1] : null;
		}

		return $reader instanceof CrossReferenceStreamReader ? $reader->getGenerationFor($objectNumber) : null;
	}

	/**
	 * Where an object is and which section says so, by the newest section that does: a hybrid file's table, then its
	 * /XRefStm, then the section before (ISO 32000-1, 7.5.8.4). A section that frees the object ends the search, as it
	 * deletes the object from that section on (7.5.6), though a hybrid file's table frees what its /XRefStm lists.
	 *
	 * @param int $objectNumber
	 *
	 * @return array|null [the offset getOffsetFor() gives, the reader that gave it], or null when none does
	 */
	private function entry($objectNumber)
	{
		foreach ($this->getReaders() as $reader) {
			$offset = $reader->getOffsetFor($objectNumber);
			if ($offset !== false) {
				return [$offset, $reader];
			}

			$stream = $this->hybridStreams && isset($this->hybridStreams[$reader]) ? $this->hybridStreams[$reader] : null;
			if ($stream !== null) {
				$offset = $stream->getOffsetFor($objectNumber);
				if ($offset !== false) {
					return [$offset, $stream];
				}
			}

			foreach ([$reader, $stream] as $section) {
				if ($section instanceof CrossReferenceStreamReader && $section->isFree($objectNumber)) {
					return null;
				}
			}

			if ($reader instanceof FixedReader && substr((string) $this->tableLine($reader, $objectNumber), 17, 1) === 'f') {
				return null;
			}
		}

		return null;
	}

	/**
	 * An object's 20-byte entry in a classic table, where the reader's subsections, corrected for a faulty one, put it
	 *
	 * @param \setasign\Fpdi\PdfParser\CrossReference\FixedReader $reader
	 * @param int                                                     $objectNumber
	 *
	 * @return string|null Null when the table does not cover the object
	 */
	private function tableLine(FixedReader $reader, $objectNumber)
	{
		foreach ($reader->getSubSections() as $position => $section) {
			if ($objectNumber >= $section[0] && $objectNumber < $section[0] + $section[1]) {
				$streamReader = $this->parser->getStreamReader();
				$position += 20 * ($objectNumber - $section[0]);
				$streamReader->ensure($position, 20);
				$line = $streamReader->readBytes(20);

				return $line === false ? null : $line;
			}
		}

		return null;
	}

	/**
	 * @param int $objectNumber
	 *
	 * @return \setasign\Fpdi\PdfParser\Type\PdfIndirectObject
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
	 */
	public function getIndirectObject($objectNumber)
	{
		$offset = $this->getOffsetFor($objectNumber);
		if (!is_array($offset)) {
			return parent::getIndirectObject($objectNumber);
		}

		// The stream's header says where each object in it is, by number, so an index that disagrees does not matter
		list($streamNumber) = $offset;
		$objectStream = $this->objectStream($streamNumber);

		if (!isset($objectStream['objects'][(int) $objectNumber])) {
			throw new CrossReferenceException(
				sprintf('Object (id:%s) not found in object stream (id:%s).', $objectNumber, $streamNumber),
				CrossReferenceException::OBJECT_NOT_FOUND
			);
		}

		$parser = $objectStream['parser'];
		$parser->getTokenizer()->clearStack();
		$parser->getStreamReader()->reset($objectStream['first'] + $objectStream['objects'][(int) $objectNumber]);

		return PdfIndirectObject::create((int) $objectNumber, 0, $parser->readValue());
	}

	/**
	 * Read a cross-reference stream as well as a classic table, and the /XRefStm of a hybrid file's table
	 *
	 * @param mixed $initValue What the parser found at the cross-reference offset
	 *
	 * @return \setasign\Fpdi\PdfParser\CrossReference\ReaderInterface
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
	 */
	protected function initReaderInstance($initValue)
	{
		if (!$initValue instanceof PdfIndirectObject) {
			$reader = parent::initReaderInstance($initValue);

			// A reader of PDF 1.4 only sees the table, so a file whose stream cannot be read still opens from it
			$stream = PdfDictionary::get($reader->getTrailer(), 'XRefStm');
			if ($stream instanceof PdfNumeric) {
				try {
					$streamReader = $this->readXref($stream->value + $this->fileHeaderOffset);
					$this->hybridStreams = $this->hybridStreams ?: new \SplObjectStorage();
					$this->hybridStreams[$reader] = $streamReader;
				} catch (\Exception $e) {
					// keep the table alone
				}
			}

			return $reader;
		}

		try {
			$stream = PdfStream::ensure($initValue->value);
		} catch (PdfTypeException $e) {
			throw new CrossReferenceException('Invalid object type at xref reference offset.', CrossReferenceException::INVALID_DATA, $e);
		}

		$type = PdfDictionary::get($stream->value, 'Type');
		if (!$type instanceof PdfName || $type->value !== 'XRef') {
			throw new CrossReferenceException('The xref position points to an incorrect object type.', CrossReferenceException::INVALID_DATA);
		}

		$this->checkForEncryption($stream->value);

		// An indirect /Length would be looked up through the cross-reference this stream is still building, so the
		// stream is read to its endstream keyword instead
		if (!PdfDictionary::get($stream->value, 'Length') instanceof PdfNumeric) {
			unset($stream->value->value['Length']);
		}

		return new CrossReferenceStreamReader($stream);
	}

	/**
	 * An object stream, inflated and its header read
	 *
	 * @param int $number
	 *
	 * @return array A parser over its inflated data, its /First, and the object number and offset of each object in it
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException When it is not an object stream
	 */
	private function objectStream($number)
	{
		$number = (int) $number;
		if (isset($this->objectStreams[$number])) {
			return $this->objectStreams[$number];
		}

		// Its /Length may be an object inside it, which cannot be read before it is (ISO 32000-1, 7.5.7)
		if (isset($this->loading[$number])) {
			throw new CrossReferenceException(sprintf('Object stream (id:%s) needs itself to be read.', $number), CrossReferenceException::INVALID_DATA);
		}

		$this->loading[$number] = true;
		try {
			return $this->readObjectStream($number);
		} finally {
			unset($this->loading[$number]);
		}
	}

	/**
	 * Read an object stream: inflate it, read its header, and keep it with the few read last
	 *
	 * @param int $number
	 *
	 * @return array As objectStream() returns it
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException When it is not an object stream
	 */
	private function readObjectStream($number)
	{
		// An object stream cannot itself be in an object stream
		$object = is_array($this->getOffsetFor($number)) ? null : parent::getIndirectObject($number);
		$type = $object && $object->value instanceof PdfStream ? PdfDictionary::get($object->value->value, 'Type') : null;
		if (!$type instanceof PdfName || $type->value !== 'ObjStm') {
			throw new CrossReferenceException(sprintf('Object stream (id:%s) not found.', $number), CrossReferenceException::OBJECT_NOT_FOUND);
		}

		$first = PdfDictionary::get($object->value->value, 'First');
		$count = PdfDictionary::get($object->value->value, 'N');
		if (!$first instanceof PdfNumeric || !$count instanceof PdfNumeric) {
			throw new CrossReferenceException(sprintf('Object stream (id:%s) has no /First or /N.', $number), CrossReferenceException::INVALID_DATA);
		}

		$data = PredictorDecoder::unfilteredStream($object->value);
		$header = preg_split('/\s+/', trim(substr($data, 0, (int) $first->value)), -1, PREG_SPLIT_NO_EMPTY);

		// The first of an object number the header gives twice is the one kept
		$objects = [];
		for ($i = 0; $i < (int) $count->value && isset($header[2 * $i + 1]); $i++) {
			if (!isset($objects[(int) $header[2 * $i]])) {
				$objects[(int) $header[2 * $i]] = (int) $header[2 * $i + 1];
			}
		}

		if (count($this->objectStreams) === self::OBJECT_STREAMS_KEPT) {
			reset($this->objectStreams);
			unset($this->objectStreams[key($this->objectStreams)]);
		}

		return $this->objectStreams[$number] = [
			'parser' => new BasePdfParser(StreamReader::createByString($data)),
			'first' => (int) $first->value,
			'objects' => $objects,
		];
	}

}
