<?php

namespace Mpdf;

/**
 * A lookup applies at most one of its subtables at a glyph: the loop over them stops at the first
 * that reports having applied, and a subtable reports that by returning the number of glyphs to
 * advance by. GravityPDF/mpdf#97 was one format that never returned its; this is the rest of the
 * same invariant.
 *
 * A contextual or chaining subtable returned whatever the lookups it names shifted, so a matched
 * context whose nested lookups did nothing - or that names no lookups at all, which is how a font
 * writes a rule meaning "stop here" - read as not having matched. The glyph went on to the rest of
 * the lookup, and where a later subtable matched a shorter context over it, that one substituted or
 * positioned instead of the guard that was meant to turn it away.
 */
class MatchedContextTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0D2C MALAYALAM LETTER BA, the glyph all three `akhn` subtables below begin at */
	const BA = 0x0D2C;

	/** U+0D4D MALAYALAM SIGN VIRAMA */
	const VIRAMA = 0x0D4D;

	/** U+0D26 MALAYALAM LETTER DA */
	const DA = 0x0D26;

	/** U+0D41 MALAYALAM VOWEL SIGN U, one of the six the first guard subtable looks ahead for */
	const SIGN_U = 0x0D41;

	/** U+0D30 MALAYALAM LETTER RA, which the second looks ahead for after a VIRAMA */
	const RA = 0x0D30;

	/**
	 * The DA carrying the vowel sign. None of these three has a codepoint of its own, so mPDF maps
	 * each into the Private Use Area as it reads the font.
	 */
	const DA_U = 0xE0BD;

	/** The DA carrying the subscript RA */
	const DA_RA = 0xE0C6;

	/** The BA + DA ligature `akhn` forms where nothing follows it */
	const BA_DA = 0xE1CE;

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
			'fontdata' => ['manjari' => [
				'R' => 'Manjari-Regular.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'manjari',
		]);
		$mpdf->WriteHTML('<p>' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * Manjari's `akhn` lookup 17 has sixty-eight subtables, three of which match BA + VIRAMA + DA.
	 * The first two name no lookups and differ only in what has to follow - one of six vowel signs,
	 * or a VIRAMA and an RA or DA - and the third, which matches the three letters with nothing
	 * after them, ligates them.
	 *
	 * With a vowel sign after it the first subtable matches, so the letters stay as written and the
	 * DA takes the vowel. They were being ligated and the vowel left to hang off the ligature.
	 */
	public function testAGuardSubtableTurnsTheGlyphAwayFromTheRestOfItsLookup()
	{
		$this->assertSame(
			[self::BA, self::VIRAMA, self::DA_U],
			$this->drawn([self::BA, self::VIRAMA, self::DA, self::SIGN_U])
		);
	}

	/**
	 * The second guard subtable, which asks for a VIRAMA and then an RA or a DA. The RA is drawn
	 * under the DA, so the two become one glyph and the run is three rather than five.
	 */
	public function testTheSecondGuardSubtableTurnsItAwayToo()
	{
		$this->assertSame(
			[self::BA, self::VIRAMA, self::DA_RA],
			$this->drawn([self::BA, self::VIRAMA, self::DA, self::VIRAMA, self::RA])
		);
	}

	/**
	 * With nothing after them the two guards do not match, the third subtable does, and the three
	 * letters are ligated - which is what tells the guards apart from turning the whole lookup off.
	 */
	public function testTheSubtableThatDoesNameALookupStillApplies()
	{
		$this->assertSame([self::BA_DA], $this->drawn([self::BA, self::VIRAMA, self::DA]));
	}

	/**
	 * The positioning half. No font in `packages/` or `tests/data/ttf` carries a contextual GPOS
	 * subtable that matches and moves nothing, so the fixture is written by hand: Noto Sans cut down
	 * to A, B and C, with a `dist` chained context of two subtables over the B - the first asking for
	 * a C after it and naming no lookups, the second naming a single positioning that shifts the B
	 * 400 units left.
	 *
	 * @return array the adjustments the line was given, keyed by position
	 */
	private function positions($text)
	{
		$mpdf = new PositionRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['guard' => [
				'R' => 'NotoSans-GPOS83-Guard-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'guard',
		]);
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		return $mpdf->drawnPositions;
	}

	/**
	 * The B before a C is matched by the guard and left where it is. It was being moved.
	 */
	public function testAGuardSubtableLeavesTheGlyphTheRestOfItsLookupWouldMove()
	{
		$this->assertSame([], $this->positions('BC'));
	}

	/**
	 * Any other B is reached by the second subtable and moved, so the guard turns away only what it
	 * matches.
	 */
	public function testTheSubtableThatDoesNameALookupStillPositions()
	{
		$this->assertEquals([[0 => ['XPlacement' => -400]]], $this->positions('BA'));
	}

}
