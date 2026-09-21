<?php

namespace Mpdf;

/**
 * Every GSUB and GPOS subtable used to return one number carrying two facts: whether it applied, and
 * how many glyphs the cursor should move on by. The two are now separate - null for a subtable that
 * did not apply, and otherwise the advance - which is what GravityPDF/mpdf#110 is about.
 *
 * Fusing them was wrong in three ways, and NotoSans-GSUB53-NestedAdvance-Synthetic holds one shape
 * for each. It is Noto Sans cut down to the capitals, with a hand-written `ccmp`:
 *
 *   lookup 2  context A B C, naming the expansion B -> X Y at sequence index 1
 *             context Y, naming Y -> Z
 *   lookup 3  D -> nothing, and behind it in the same lookup, D -> Q
 *   lookup 6  context G H I, naming G -> nothing at index 0 and J -> R at index 2
 *
 * Over the 107 fonts in `packages/` and `tests/data/ttf`, both shapes occur only in the two NotoEmoji
 * faces - two records naming a nested lookup at a sequence index other than 0 whose return the font
 * fixes at something other than 1, and 62 Multiple Substitutions to the empty sequence in each.
 * Everywhere else the records name sequence index 0, where the borrowed advance is accidentally the
 * right one.
 *
 * Each expectation below is what hb-shape draws for the same string.
 */
class NestedAdvanceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @return string the text of the line as it is handed to the drawing code, '' where the line has
	 *                nothing left in it
	 */
	private function drawn($text)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['nestedadvance' => [
				'R' => 'NotoSans-GSUB53-NestedAdvance-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'nestedadvance',
		]);
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		return isset($mpdf->drawnText[0]) ? $mpdf->drawnText[0] : '';
	}

	/**
	 * The context over A B C names the expansion at its second glyph, so the two glyphs that lookup
	 * reports are counted from the B and not from the A the context started at. Added to the cursor
	 * they left it on the Y the expansion had just made, where the second subtable of the same lookup
	 * matched it and substituted the Z.
	 *
	 * The cursor belongs at the end of the matched input, carried through what the expansion added,
	 * which is past the C.
	 */
	public function testAContextAdvancesPastItsOwnMatchRatherThanByWhatItNamed()
	{
		$this->assertSame('AXYCE', $this->drawn('ABCE'));
	}

	/**
	 * A Multiple Substitution to the empty sequence is how a font deletes a glyph, and it reported the
	 * number of glyphs it had put there - none - which read as not having applied. The lookup went on
	 * to its second subtable, still holding the D's glyph id, and substituted the Q over the E that
	 * had moved up into the D's place.
	 */
	public function testADeletionEndsItsLookupRatherThanReadingAsNothingDone()
	{
		$this->assertSame('E', $this->drawn('DE'));
	}

	/**
	 * The same deletion with nothing after it. The lookup's second subtable was reading the position
	 * the D had stood at, which no longer exists, and drawing the Q from whatever that read as.
	 */
	public function testTheLastGlyphOfALineCanBeDeleted()
	{
		$this->assertSame('', $this->drawn('D'));
	}

	/**
	 * Two records, the first deleting the G at the front of the match and the second naming the third
	 * glyph of it. Nothing moved the matched positions to account for the deletion, so the second
	 * record read the position the I had stood at and found the J that had moved up into it, and
	 * substituted the R there. The match is one glyph shorter than the record's index, so there is no
	 * position for it to name.
	 */
	public function testAMatchedPositionMovesWithWhatAnEarlierRecordDeleted()
	{
		$this->assertSame('HIJ', $this->drawn('GHIJ'));
	}

	/**
	 * A substitution to the empty sequence left the replacement array unassigned, so array_splice()
	 * was handed an undefined variable, and reading the position the deleted glyph had stood at is a
	 * warning of its own with an offset read behind it. Asserted beside the text, since an install
	 * running at production's error_reporting sees none of it - only the wrong letters.
	 */
	public function testADeletionReadsNothingItHasNot()
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
			foreach (['DE', 'D', 'GHIJ'] as $text) {
				$this->drawn($text);
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

}
