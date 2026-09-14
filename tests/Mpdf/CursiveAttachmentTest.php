<?php

namespace Mpdf;

use Mpdf\Output\Destination;

/**
 * GPOS Lookup Type 3 attaches one glyph to the next by anchor: each carries an entry point, an exit
 * point or both, and the shaper lines the exit of one up with the entry of the one after it. Which
 * glyph is "the one after it" depends on the direction the lookup is written for, so Otl walks the run
 * twice, once from each end, and the two walks are otherwise the same.
 *
 * Arabic and Syriac are the scripts that use it, and both are right to left, so the left-to-right walk
 * is only reached by a font for a left-to-right cursive script - Duployan, Mongolian - and none of the
 * fonts that ship with mPDF is one. The fixture is Noto Sans cut down to A and B, with a GPOS of one
 * cursive lookup: A carries an exit anchor, B an entry anchor, and the lookup is written left to
 * right.
 */
class CursiveAttachmentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	private function mpdf()
	{
		return new PositionRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['cursive3' => [
				'R' => 'NotoSans-GPOS3-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'cursive3',
		]);
	}

	/**
	 * The exit of A is joined to the entry of B: B is moved to put its entry point where A's exit is,
	 * and the advance is closed up by the difference.
	 */
	public function testAnExitAnchorIsJoinedToTheEntryAnchorOfTheNextGlyph()
	{
		$mpdf = $this->mpdf();

		$mpdf->WriteHTML('<p>AB</p>');

		$this->assertEquals([[1 => ['YPlacement' => -200, 'XAdvance' => 339]]], $mpdf->drawnPositions);
	}

	/**
	 * A glyph with an exit anchor and nothing after it has nothing to join to. Looking for the next
	 * base to join it to read one past the end of the run, and then kept reading: the character at a
	 * position that does not exist is null, a null is not one of the marks to skip over by any test
	 * that could be made of it, and so the search walked forward for ever, writing a placement at every
	 * position it passed.
	 */
	public function testAnExitAnchorOnTheLastGlyphOfARunIsNotFollowedPastIt()
	{
		$mpdf = $this->mpdf();

		$mpdf->WriteHTML('<p>A</p>');

		$this->assertSame([], $mpdf->drawnPositions);
		$this->assertStringStartsWith('%PDF-', $mpdf->Output('', Destination::STRING_RETURN));
	}

	/**
	 * The same, with the run ending in the pair the lookup does join: the last A is left alone and the
	 * B before it is still joined.
	 */
	public function testTheJoinIsStillMadeWhereTheRunEndsInAnExitAnchor()
	{
		$mpdf = $this->mpdf();

		$mpdf->WriteHTML('<p>ABA</p>');

		$this->assertEquals([[1 => ['YPlacement' => -200, 'XAdvance' => 339]]], $mpdf->drawnPositions);
	}

}
