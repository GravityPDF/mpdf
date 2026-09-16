<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\BlobReader;

class MarkArrayTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A MarkArray at byte 2 of two records - class 0 anchored 10 bytes into the array, class 3
	 * anchored 16 bytes in - and the two anchors after them.
	 */
	public function testARecordIsFoundByCoverageIndexAndItsAnchorFromTheArray()
	{
		$table = pack('n*', 99, 2, 0, 10, 3, 16, 1, 100, 200, 1, 0xFFCE, 0xFF9C);

		$this->assertSame(
			['Class' => 0, 'AnchorX' => 100, 'AnchorY' => 200],
			MarkArray::record(new BlobReader($table), 2, 0)
		);
		$this->assertSame(
			['Class' => 3, 'AnchorX' => -50, 'AnchorY' => -100],
			MarkArray::record(new BlobReader($table), 2, 1)
		);
	}

}
