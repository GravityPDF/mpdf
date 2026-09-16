<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\BlobReader;

class ValueRecordTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * xPlacement, yPlacement and xAdvance are signed: a kern pulls a glyph back as often as it pushes
	 */
	public function testTheThreeFieldsMpdfUsesAreReadSigned()
	{
		$reader = new BlobReader(pack('n*', 0xFF38, 12, 0xFF4C));

		$this->assertSame(
			['XPlacement' => -200, 'YPlacement' => 12, 'XAdvance' => -180],
			ValueRecord::read($reader, 0x0007)
		);
	}

	/**
	 * A field the format leaves out is not in the record at all. The shaper replaces a mark's own width
	 * with an XAdvance that is stated, zero or not, so a missing one must not read as 0.
	 */
	public function testAFieldTheFormatLeavesOutIsAbsentRatherThanZero()
	{
		$this->assertSame(['XAdvance' => 0], ValueRecord::read(new BlobReader(pack('n*', 0)), 0x0004));
		$this->assertSame([], ValueRecord::read(new BlobReader(''), 0));
	}

	/**
	 * The fields are written in bit order whichever are present, so each one mPDF drops still has to be
	 * stepped over to reach the next it keeps - and past the last, so an array of records lines up.
	 */
	public function testEveryFieldTheFormatStatesIsSteppedOver()
	{
		// yPlacement, xAdvance, yAdvance, then all four device offsets, then the next record
		$reader = new BlobReader(pack('n*', 7, 9, 99, 1, 2, 3, 4, 0xBEEF));

		$this->assertSame(['YPlacement' => 7, 'XAdvance' => 9], ValueRecord::read($reader, 0x00FE));
		$this->assertSame(14, $reader->tell());
		$this->assertSame(0xBEEF, $reader->readUInt16());
	}

	/**
	 * Two bytes a field. A pair adjustment steps through an array of pairs by this, so a size off by
	 * one field reads every pair after the first from the wrong place.
	 */
	public function testTheSizeIsTwoBytesForEachFieldTheFormatStates()
	{
		$this->assertSame(0, ValueRecord::size(0));
		$this->assertSame(2, ValueRecord::size(0x0004));
		$this->assertSame(6, ValueRecord::size(0x0007));
		$this->assertSame(8, ValueRecord::size(0x0055));
		$this->assertSame(16, ValueRecord::size(0x00FF));
	}

}
