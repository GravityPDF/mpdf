<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the runs whose shaping turns on the two characters the right-joining table filed under the
 * wrong key: U+074F SYRIAC LETTER SOGDIAN FE, which joins on both sides and was read as joining on
 * neither, and U+08AD ARABIC LETTER LOW ALEF, which joins on neither and was read as joining on the
 * right.
 *
 * Neither character decides its own shape here - a cursive letter takes its form from its neighbours,
 * so what the table got wrong is the letter before it. Each sample is therefore drawn beside the same
 * letter before a neighbour of the same joining type, which is the form it has to match, and before
 * nothing, which is the form it was given.
 *
 * Neither font carries a glyph for the character that closes the sample: no shipped font has U+08AD,
 * and of the 85 only FreeSans has U+074F, whose Syriac script list is empty. The blank each leaves is
 * beside the point - it is the letter before it that moves. U+08AD is drawn to the right of the Beh
 * rather than the left of it because the bidirectional data stops at Unicode 6.1 and the character was
 * added in 7.0, so it has no direction of its own; that is GravityPDF/mpdf#99 and not this fix.
 *
 * @group snapshot
 */
class JoiningTablesSnapshotTest extends Snapshot
{
	/** U+0712 SYRIAC LETTER BETH, dual-joining */
	const BETH = '&#x0712;';

	/** U+0723 SYRIAC LETTER SEMKATH, dual-joining */
	const SEMKATH = '&#x0723;';

	/** U+074F SYRIAC LETTER SOGDIAN FE, dual-joining */
	const SOGDIAN_FE = '&#x074F;';

	/** U+0628 ARABIC LETTER BEH, dual-joining */
	const BEH = '&#x0628;';

	/** U+0627 ARABIC LETTER ALEF, right-joining */
	const ALEF = '&#x0627;';

	/** U+08AD ARABIC LETTER LOW ALEF, non-joining */
	const LOW_ALEF = '&#x08AD;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'joining-tables';
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
		$this->mpdf->WriteHTML($this->style() . $this->syriacSamples() . $this->arabicSamples());
	}

	private function style()
	{
		ob_start();
		?>
		<style>
			h2 { font-size: 12pt; margin-bottom: 1mm; }
			p.note { font-size: 9pt; color: #606060; margin-top: 0; }
			p.label { font-size: 9pt; color: #606060; margin-bottom: 0; }
			p.sample { font-size: 28pt; margin-top: 0; margin-bottom: 3mm; }
			p.syriac { font-family: estrangeloedessa; }
			p.arabic { font-family: lateef; }
		</style>

		<h1>mPDF</h1>
		<h2>Cursive joining</h2>
		<p class="note">A letter of a cursive script takes its form from the letters either side of it, so
			a character read as joining on the wrong side is drawn correctly itself and misshapes its
			neighbour. Each sample below ends in a character with no glyph in the font; read the letter
			before it, and compare it with the two samples under it.</p>
		<?php

		return ob_get_clean();
	}

	/**
	 * U+074F joins on both sides, so the Beth before it is initial - the same Beth as before any other
	 * dual-joining letter, and not the isolated one it was drawn as.
	 */
	private function syriacSamples()
	{
		return '<h2>Syriac: Beth before Sogdian Fe</h2>'
			. $this->sample('syriac', 'Beth, Sogdian Fe - initial Beth', self::BETH . self::SOGDIAN_FE)
			. $this->sample('syriac', 'Beth, Semkath - initial Beth, the form to match', self::BETH . self::SEMKATH)
			. $this->sample('syriac', 'Beth alone - isolated Beth, the form it was given', self::BETH)
			. $this->sample('syriac', 'Beth, Sogdian Fe, Beth - initial and final Beth', self::BETH . self::SOGDIAN_FE . self::BETH);
	}

	/**
	 * U+08AD joins on neither side, so the Beh before it is isolated - and not the initial one the
	 * table's spare key gave it.
	 */
	private function arabicSamples()
	{
		return '<h2>Arabic: Beh before Low Alef</h2>'
			. $this->sample('arabic', 'Beh, Low Alef - isolated Beh', self::BEH . self::LOW_ALEF)
			. $this->sample('arabic', 'Beh alone - isolated Beh, the form to match', self::BEH)
			. $this->sample('arabic', 'Beh, Alef - initial Beh, the form it was given', self::BEH . self::ALEF);
	}

	private function sample($script, $label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample ' . $script . '">' . $text . '</p>';
	}

	/**
	 * The document only shows anything if the letter before each of the two characters is drawn as the
	 * neighbour it is sampled against draws it, and not as the form the wrong key gave it. Both are
	 * single glyphs out of a subset built as the document is written, so the samples are compared with
	 * each other by the glyphs they have in common rather than by naming any of them.
	 *
	 * @return string[] the glyphs the sample was drawn with, in the order they are drawn
	 */
	private function glyphsOf($font, $text)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($this->style() . '<p class="sample ' . $font . '">' . $text . '</p>');

		return preg_split('//u', end($mpdf->drawnText), -1, PREG_SPLIT_NO_EMPTY);
	}

	private function glyphsInCommon($font, $one, $other)
	{
		return array_values(array_intersect($this->glyphsOf($font, $one), $this->glyphsOf($font, $other)));
	}

	public function testTheSyriacSampleDrawsTheBethSogdianFeAsksForAndNotTheIsolatedOne()
	{
		$sample = self::BETH . self::SOGDIAN_FE;

		$this->assertNotSame([], $this->glyphsInCommon('syriac', $sample, self::BETH . self::SEMKATH), 'the sample draws no letter the dual-joining neighbour draws');
		$this->assertSame([], $this->glyphsInCommon('syriac', $sample, self::BETH), 'the sample draws the isolated Beth');
	}

	public function testTheArabicSampleDrawsTheBehLowAlefAsksForAndNotTheJoinedOne()
	{
		$sample = self::BEH . self::LOW_ALEF;

		$this->assertNotSame([], $this->glyphsInCommon('arabic', $sample, self::BEH), 'the sample draws no letter the Beh on its own draws');
		$this->assertSame([], $this->glyphsInCommon('arabic', $sample, self::BEH . self::ALEF), 'the sample draws the joined Beh');
	}
}
