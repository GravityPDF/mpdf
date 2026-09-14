<?php

namespace Mpdf;

/**
 * A GSUB subtable is reached from the lookup that holds it by an Offset16, and the arrays inside the
 * three substitution types that hold a record per covered glyph - Multiple, Alternate and Ligature -
 * are Offset16 as well. Offset16 is unsigned, so a subtable larger than 32,767 bytes has entries at
 * or past 0x8000.
 *
 * The shaper read those arrays with a signed reader, so an entry past the boundary came back negative
 * and it seeked 65,536 bytes short of the record it wanted, decoding whatever the table holds there:
 * another glyph's replacement, and a glyph count belonging to neither.
 *
 * NotoSans-GSUB2-BigSubtable-Synthetic is built for this. It is 1,000 covered glyphs, each replaced
 * by a run of fifteen markers with the glyph itself in the middle - the shape of a font that shapes
 * by expansion, computes on the markers and collapses the run again. The Sequence array is 36,072
 * bytes, so the last 95 entries sit past 0x8000. Only the glyphs a test types carry an outline.
 */
class LargeSubtableTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** The first covered glyph, whose Sequence is 2,006 bytes into the subtable */
	const EARLY = 0xE000;

	/** The first covered glyph whose Sequence sits at or past 0x8000, at 32,776 bytes */
	const FIRST_PAST_BOUNDARY = 0xE389;

	/** The last covered glyph, whose Sequence is 35,982 bytes in */
	const LAST = 0xE3E7;

	/** Glyphs in the run each covered glyph is replaced by */
	const SEQUENCE_LENGTH = 16;

	/** Where the covered glyph itself sits in that run */
	const SELF_POSITION = 8;

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['bigsubtable' => [
				'R' => 'NotoSans-GSUB2-BigSubtable-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'bigsubtable',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The control: a glyph whose Sequence is well inside the first 32 KB was always read correctly.
	 * The markers have no codepoints of their own, so the run is read by its shape rather than by
	 * naming them.
	 */
	public function testAGlyphBeforeTheBoundaryExpandsIntoItsOwnRun()
	{
		$drawn = $this->drawn([self::EARLY]);

		$this->assertCount(self::SEQUENCE_LENGTH, $drawn);
		$this->assertSame(self::EARLY, $drawn[self::SELF_POSITION]);
	}

	/**
	 * The first glyph past the boundary. Read signed, its offset was negative: the shaper seeked
	 * before the subtable, read a glyph count of its own and replaced the character with eight glyphs
	 * belonging to another - the character typed was not on the page at all.
	 */
	public function testAGlyphPastTheBoundaryExpandsIntoItsOwnRun()
	{
		$drawn = $this->drawn([self::FIRST_PAST_BOUNDARY]);

		$this->assertCount(self::SEQUENCE_LENGTH, $drawn);
		$this->assertSame(self::FIRST_PAST_BOUNDARY, $drawn[self::SELF_POSITION]);
	}

	/**
	 * Every covered glyph is given the same markers, so the two runs have to agree everywhere but the
	 * middle. Run against the last glyph of the subtable, 3 KB further past the boundary, so that a
	 * glyph past it is read from its own Sequence rather than from one that happens to be as long.
	 */
	public function testTheRunPastTheBoundaryIsTheSameRunAsTheOneBeforeIt()
	{
		$early = $this->drawn([self::EARLY]);
		$late = $this->drawn([self::LAST]);

		unset($early[self::SELF_POSITION], $late[self::SELF_POSITION]);

		$this->assertSame($early, $late);
	}

	/**
	 * Four of them in one run. A font that shapes this way feeds the markers to chaining rules that
	 * compute on them, so a Sequence read from the wrong place is not one wrong glyph but a run those
	 * rules then match against and expand again: Noto Sans Duployan turned four characters into
	 * 102,109 glyphs and 52 seconds of shaping.
	 */
	public function testEachGlyphOfARunExpandsOnce()
	{
		$run = [self::LAST - 3, self::LAST - 2, self::LAST - 1, self::LAST];
		$drawn = $this->drawn($run);

		$this->assertCount(count($run) * self::SEQUENCE_LENGTH, $drawn);
		foreach ($run as $i => $codepoint) {
			$this->assertSame(
				$codepoint,
				$drawn[$i * self::SEQUENCE_LENGTH + self::SELF_POSITION],
				sprintf('the character at position %d is not in the run it expanded into', $i)
			);
		}
	}

}
