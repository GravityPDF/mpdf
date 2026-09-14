<?php

namespace Mpdf\Fonts;

/**
 * A BlobReader that counts how many times each table in the blob was parsed out of it.
 *
 * Every Coverage and ClassDef table the shaper reads is arrived at by seeking to its offset and then
 * reading from there, and a memoised reader that answers from its cache seeks and then reads nothing.
 * So one seek followed by at least one read is one parse of the table at that offset, which is the
 * work the memoising exists to do only once.
 *
 * skip() moves relative to wherever the reader already is, so it is a step through a structure being
 * read rather than an arrival at one.
 */
class TableReadRecordingBlobReader extends BlobReader
{

	/**
	 * @var array offset in the table => parses that started there
	 */
	public $tableReads = [];

	/**
	 * The offset last seeked to, until a read makes it a parse
	 *
	 * @var int|null
	 */
	private $arrivedAt = null;

	public function seek($position)
	{
		$this->arrivedAt = $position;

		parent::seek($position);
	}

	public function skip($delta)
	{
		parent::skip($delta);

		$this->arrivedAt = null;
	}

	public function read($length)
	{
		$this->countArrival();

		return parent::read($length);
	}

	public function readInt16()
	{
		$this->countArrival();

		return parent::readInt16();
	}

	public function readUInt16()
	{
		$this->countArrival();

		return parent::readUInt16();
	}

	private function countArrival()
	{
		if ($this->arrivedAt === null) {
			return;
		}

		$offset = $this->arrivedAt;
		$this->arrivedAt = null;

		$this->tableReads[$offset] = isset($this->tableReads[$offset]) ? $this->tableReads[$offset] + 1 : 1;
	}

}
