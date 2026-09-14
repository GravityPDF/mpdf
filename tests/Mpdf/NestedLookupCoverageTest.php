<?php

namespace Mpdf;

/**
 * A contextual or chained lookup matches a sequence of glyphs on its own Coverages, then names other
 * lookups to run at positions within that match. The glyph handed to a nested lookup is whatever the
 * context matched there; the subtables of that lookup each carry a Coverage of their own, and nothing
 * compared the two.
 *
 * The loops that apply a lookup from the top of the list test the glyph against each subtable's
 * Coverage before entering it. The two methods that apply the lookups a matched context names did
 * not, so every subtable of a nested lookup was entered for every glyph its context could present,
 * and every handler indexes that Coverage with no check of its own.
 *
 * A missing index reads as null, which is 0, so the handler applies whatever the subtable holds for
 * the first glyph it covers. It then reports having applied, so the loop stops and the subtable that
 * does cover the glyph, further down the same lookup, is never reached.
 */
class NestedLookupCoverageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+0300 COMBINING GRAVE ACCENT, which DejaVu Sans has a capital form of */
	const GRAVE = 0x0300;

	/** U+0304 COMBINING MACRON, which it has not */
	const MACRON = 0x0304;

	/** U+0041 LATIN CAPITAL LETTER A, which the context needs in front of the mark */
	const CAPITAL_A = 0x0041;

	/** U+0061 LATIN SMALL LETTER A, which it does not match */
	const SMALL_A = 0x0061;

	/** U+0A15 GURMUKHI LETTER KA, the base each Gurmukhi mark is drawn on */
	const KA = '&#x0A15;';

	/** U+0A02 GURMUKHI SIGN BINDI, the first glyph the nested GPOS subtable covers */
	const BINDI = '&#x0A02;';

	/** U+0951 DEVANAGARI STRESS SIGN UDATTA, drawn above the letter */
	const UDATTA = '&#x0951;';

	/** U+0952 DEVANAGARI STRESS SIGN ANUDATTA, drawn below it */
	const ANUDATTA = '&#x0952;';

	/**
	 * DejaVu Sans substitutes a shorter, higher form of a combining mark for the one written when it
	 * follows a capital, through a chained context that names a single substitution covering the
	 * twelve marks it has such a form for. The context presents forty-four.
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML('<p style="font-family:dejavusans">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The mark the nested subtable does cover is still substituted, which is what the rule is for. The
	 * capital form has no codepoint of its own and is mapped into the Private Use Area as the subset
	 * is built, so it is read as "not the character written" rather than named.
	 */
	public function testAMarkTheNestedSubtableCoversIsStillSubstituted()
	{
		$drawn = $this->drawn([self::CAPITAL_A, self::GRAVE]);

		$this->assertCount(2, $drawn);
		$this->assertSame(self::CAPITAL_A, $drawn[0]);
		$this->assertNotSame(self::GRAVE, $drawn[1], 'the capital form of the grave was not substituted');
	}

	/**
	 * A mark it does not cover is left as itself. It was being given the record at Coverage Index 0 -
	 * the capital grave - so a macron written over a capital A was drawn as a grave accent.
	 */
	public function testAMarkTheNestedSubtableDoesNotCoverIsLeftAlone()
	{
		$this->assertSame([self::CAPITAL_A, self::MACRON], $this->drawn([self::CAPITAL_A, self::MACRON]));
	}

	/**
	 * With a lowercase letter in front of it the context does not match at all, so even the mark the
	 * nested subtable does cover stays as written.
	 */
	public function testAMarkAfterALowercaseLetterIsLeftAlone()
	{
		$this->assertSame([self::SMALL_A, self::GRAVE], $this->drawn([self::SMALL_A, self::GRAVE]));
	}

	/**
	 * The positioning half, and the shape GravityPDF/mpdf#102 was reported from.
	 *
	 * NotoSansGurmukhi-NestedCoverage-Subset is 5 glyphs of Noto Sans Gurmukhi 2.005 (OFL 1.1) whose
	 * `dist` chained context names a single adjustment at a Devanagari stress sign. That lookup has
	 * two subtables - the first covers the bindi and a conjunct, the second the two stress signs - and
	 * the bindi's value record was what the stress signs were positioned by.
	 *
	 * @return array the adjustment the mark of a two-character run was given
	 */
	private function markPosition($mark)
	{
		$mpdf = new PositionRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['gurmukhinested' => [
				'R' => 'NotoSansGurmukhi-NestedCoverage-Subset.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'gurmukhinested',
		]);
		$mpdf->WriteHTML('<p>' . self::KA . $mark . '</p>');

		return $mpdf->drawnPositions[0][1];
	}

	/**
	 * The udatta is raised above the letter by its own record in the second subtable, where the first
	 * subtable's record left it on the baseline and 150 units to the left.
	 */
	public function testAMarkIsPositionedByTheSubtableThatCoversIt()
	{
		$this->assertSame(
			['BaseWidth' => 622, 'XPlacement' => 602, 'YPlacement' => 275, 'XAdvanceL' => 602, 'XAdvanceR' => 602],
			$this->markPosition(self::UDATTA)
		);
	}

	/**
	 * The anudatta is the same the other way: its own record drops it below the letter, where the
	 * first subtable's put it on the baseline.
	 */
	public function testTheSecondMarkOfTheSameSubtableIsPositionedByItToo()
	{
		$this->assertSame(
			['BaseWidth' => 622, 'XPlacement' => 255, 'YPlacement' => -355, 'XAdvanceL' => 255, 'XAdvanceR' => 255],
			$this->markPosition(self::ANUDATTA)
		);
	}

	/**
	 * The bindi is the glyph the first subtable does cover, and the one whose record the other two
	 * were being given. It is positioned exactly as it was, so the guard turns away the glyphs the
	 * subtable does not cover without turning away the one it does.
	 */
	public function testTheGlyphTheFirstSubtableCoversIsPositionedAsItWas()
	{
		$this->assertSame(
			['BaseWidth' => 622, 'XPlacement' => 603, 'YPlacement' => 0, 'XAdvanceL' => 603, 'XAdvanceR' => 603],
			$this->markPosition(self::BINDI)
		);
	}

	/**
	 * Reading a Coverage at a glyph it does not hold is a warning per glyph per subtable under E_ALL,
	 * and the value it yields is an offset, so what follows it can be a deprecation as well. Asserted
	 * beside the positions, since an install running at production's error_reporting sees none of it -
	 * only a mark in the wrong place.
	 */
	public function testANestedLookupReadsNoCoverageItHasNot()
	{
		$raised = [];

		set_error_handler(function ($number, $message, $file, $line) use (&$raised) {
			// Filtered to the shaper so a notice raised elsewhere in the render cannot fail this
			if (false !== strpos($file, 'Otl.php')) {
				$raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			}

			return true;
		});

		try {
			foreach ([self::UDATTA, self::ANUDATTA, self::BINDI] as $mark) {
				$this->markPosition($mark);
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

}
