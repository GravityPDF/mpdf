<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\BlobReader;

class AnchorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * An anchor at byte 4: anchorFormat 1, then xCoordinate -549 and yCoordinate 1548. The reader is
	 * taken there from wherever it was left.
	 */
	public function testTheCoordinatesAreReadSignedFromWhereTheTableStarts()
	{
		$reader = new BlobReader(pack('n*', 99, 99, 1, 0xFDDB, 1548));
		$reader->readUInt16();

		$this->assertSame([-549, 1548], Anchor::coordinates($reader, 4));
	}

	/**
	 * Format 2 adds anchorPoint and Format 3 two device offsets, after the coordinates. Neither moves
	 * them, so every format reads alike.
	 */
	public function testFormats2And3ReadAsTheirCoordinates()
	{
		$this->assertSame([10, -20], Anchor::coordinates(new BlobReader(pack('n*', 2, 10, 0xFFEC, 7)), 0));
		$this->assertSame([10, -20], Anchor::coordinates(new BlobReader(pack('n*', 3, 10, 0xFFEC, 6, 0)), 0));
	}

}
