<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Fonts\BlobReader;

class SequenceRuleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * glyphCount 3, seqLookupCount 2, inputSequence from position 1, then the records
	 */
	public function testAPlainRuleLeavesTheReaderAtItsRecords()
	{
		$reader = new BlobReader(pack('n*', 3, 2, 40, 41, 1, 7, 0, 9));

		$this->assertSame([[40, 41], 2], SequenceRule::plain($reader));
		$this->assertSame(
			[
				['SequenceIndex' => 1, 'LookupListIndex' => 7],
				['SequenceIndex' => 0, 'LookupListIndex' => 9],
			],
			SequenceRule::lookupRecords($reader, 2)
		);
	}

	/**
	 * A glyphCount of 1 is a rule that matches position 0 alone, so there is nothing to list
	 */
	public function testARuleOfOnePositionListsNoInput()
	{
		$this->assertSame([[], 1], SequenceRule::plain(new BlobReader(pack('n*', 1, 1))));
		$this->assertSame([[], [], []], SequenceRule::chained(new BlobReader(pack('n*', 0, 1, 0))));
	}

	/**
	 * The spec stores backtrack nearest-first, the reverse of the order it appears in the text. The
	 * parser and the shaper both expect it that way, so it has to come back exactly as stored.
	 */
	public function testAChainedRuleKeepsEachSequenceInTheOrderItIsStored()
	{
		$reader = new BlobReader(pack('n*', 2, 30, 31, 2, 40, 2, 50, 51, 1, 0, 3));

		$this->assertSame([[30, 31], [40], [50, 51]], SequenceRule::chained($reader));
		$this->assertSame(1, $reader->readUInt16(), 'the reader is left at seqLookupCount');
	}

	public function testCoverageOffsetsAreMadeAbsoluteFromTheSubtable()
	{
		$reader = new BlobReader(pack('n*', 12, 20));

		$this->assertSame([112, 120], SequenceRule::coverageOffsets($reader, 100, 2));
	}
}
