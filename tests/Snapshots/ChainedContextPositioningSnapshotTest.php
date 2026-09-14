<?php

namespace Snapshots;

use Mpdf\PositionRecordingMpdf;

/**
 * Renders the marks placed by a GPOS Lookup Type 8 Format 3 subtable - a chained context matched
 * against a Coverage table per position, which is how most fonts write chained kerning and mark
 * positioning, and how the shipped ones write dozens of subtables into a single lookup.
 *
 * A lookup applies at most one of its subtables at a glyph, and the caller decides that by whether
 * the subtable it just offered the glyph to reports a shift. This format reported none, so a glyph
 * matched by two subtables of one lookup was positioned by both and the mark ended up twice as far
 * from the letter as the font asks.
 *
 * The document pairs each sample with the same mark placed by a subtable nothing else in the lookup
 * matches, so the two have to agree - a mark under the letter it belongs to beside one out from under
 * it. The contextualpositioning snapshot holds the other seven contextual and chaining formats, all of
 * which have always reported their shift.
 *
 * @group snapshot
 */
class ChainedContextPositioningSnapshotTest extends Snapshot
{
	/** U+05D0 HEBREW LETTER ALEF */
	const ALEF = '&#x05D0;';

	/** U+05DE HEBREW LETTER MEM */
	const MEM = '&#x05DE;';

	/** U+05B9 HEBREW POINT HOLAM, the mark the two subtables both place */
	const HOLAM = '&#x05B9;';

	/** U+05AF HEBREW MARK MASORA CIRCLE, written QARNEY PARA in the lookup's rules */
	const QARNEY_PARA = '&#x05AF;';

	/** U+0599 HEBREW ACCENT PASHTA, written MUNAH in the lookup's rules */
	const MUNAH = '&#x0599;';

	/** U+1000 MYANMAR LETTER KA */
	const KA = '&#x1000;';

	/** U+1004 MYANMAR LETTER NGA */
	const NGA = '&#x1004;';

	/** U+1037 MYANMAR SIGN DOT BELOW */
	const DOT_BELOW = '&#x1037;';

	/** U+103A MYANMAR SIGN ASAT */
	const ASAT = '&#x103A;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'chained-context-positioning';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf(['mode' => 'utf-8']);
		$this->mpdf->WriteHTML($this->style() . $this->samples());
	}

	private function style()
	{
		ob_start();
		?>
		<style>
			h2 { font-size: 12pt; margin-bottom: 1mm; }
			p.note { font-size: 9pt; color: #606060; margin-top: 0; }
			p.label { font-size: 9pt; color: #606060; margin-bottom: 0; }
			p.sample { font-size: 40pt; margin-top: 0; margin-bottom: 3mm; }
			p.hebrew { font-family: taameydavidclm; }
			p.myanmar { font-family: padaukbook; }
		</style>

		<h1>mPDF</h1>
		<h2>Chained context positioning</h2>
		<p class="note">A mark is placed by whichever subtable of a lookup matches first, and by that
			one only. Each pair below asks for the same mark twice: once where two subtables of the
			lookup match the glyph, and once where one does. The mark has to land in the same place
			both times.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return '<h2>Taamey David CLM: the Holam under an Alef</h2>'
			. $this->sample('hebrew', 'Alef, Holam, Qarney Para, Munah - two subtables of the mark lookup match', self::ALEF . self::HOLAM . self::QARNEY_PARA . self::MUNAH)
			. $this->sample('hebrew', 'Alef, Holam, Munah - one does, and places the Holam where it belongs', self::ALEF . self::HOLAM . self::MUNAH)
			. $this->sample('hebrew', 'Alef, Holam, Qarney Para - the marks without the Munah that closes the chain', self::ALEF . self::HOLAM . self::QARNEY_PARA)
			. $this->sample('hebrew', 'Mem, Holam, Qarney Para, Munah - the same three marks on another letter', self::MEM . self::HOLAM . self::QARNEY_PARA . self::MUNAH)
			. '<h2>Padauk Book: the Dot Below under a Ka</h2>'
			. $this->sample('myanmar', 'Ka, Dot Below, Asat - two subtables of the mark lookup match', self::KA . self::DOT_BELOW . self::ASAT)
			. $this->sample('myanmar', 'Ka, Dot Below - one does, and places the dot where it belongs', self::KA . self::DOT_BELOW)
			. $this->sample('myanmar', 'Nga, Dot Below, Asat - the same marks on a letter the chain does not match twice', self::NGA . self::DOT_BELOW . self::ASAT);
	}

	private function sample($script, $label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample ' . $script . '">' . $text . '</p>';
	}

	/**
	 * @return array the adjustment each glyph of the sample was given, keyed by its position in the line
	 */
	private function positionsOf($font, $text)
	{
		$mpdf = new PositionRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($this->style() . '<p class="sample ' . $font . '">' . $text . '</p>');

		return end($mpdf->drawnPositions);
	}

	/**
	 * The document only shows anything if each pair of samples places its mark in the same place, and
	 * the offsets are the font's rather than anything this repository chose, so the pairs are compared
	 * with each other rather than against a number.
	 *
	 * The Holam is the last of the marks to be placed in both of the Hebrew samples.
	 */
	public function testTheHolamIsPlacedTheSameWithAndWithoutTheQarneyParaBetweenTheMarks()
	{
		$chained = $this->positionsOf('hebrew', self::ALEF . self::HOLAM . self::QARNEY_PARA . self::MUNAH);
		$plain = $this->positionsOf('hebrew', self::ALEF . self::HOLAM . self::MUNAH);
		$holamOfChained = end($chained);
		$holamOfPlain = end($plain);

		$this->assertSame($holamOfPlain['XPlacement'], $holamOfChained['XPlacement']);
	}

	public function testTheDotBelowIsPlacedTheSameWithAndWithoutTheAsatAfterIt()
	{
		$chained = $this->positionsOf('myanmar', self::KA . self::DOT_BELOW . self::ASAT);
		$plain = $this->positionsOf('myanmar', self::KA . self::DOT_BELOW);

		$this->assertSame($plain[1]['XPlacement'], $chained[1]['XPlacement']);
	}
}
