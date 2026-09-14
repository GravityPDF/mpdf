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
 * No shipped font carries U+08AD, and of the 85 only FreeSans has U+074F, whose Syriac feature list is
 * empty and which therefore joins nothing. Both samples are drawn in a Noto subset instead, so that the
 * character the run turns on has a glyph of its own and the reader can see the pair join.
 *
 * The samples are marked dir="rtl". U+08AD was added in Unicode 7.0 and Ucdn's tables stop at 6.1, so
 * it reads as an unassigned codepoint with no direction of its own; in a left-to-right paragraph that
 * lands it to the right of the Beh instead of the left. A right-to-left paragraph resolves the neutral
 * the other way and puts it where it belongs. The stale data is GravityPDF/mpdf#101, not this fix.
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
		$this->mpdf = $this->createMpdf($this->config());
		$this->mpdf->WriteHTML($this->style() . $this->syriacSamples() . $this->arabicSamples());
	}

	/**
	 * Two Noto subsets, each holding the three letters its half of the document draws and every joining
	 * form of them.
	 */
	private function config()
	{
		return [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [
				'syriacsubset' => [
					'R' => 'NotoSansSyriac-Joining-Subset.ttf',
					'useOTL' => 0xFF,
				],
				'arabicsubset' => [
					'R' => 'NotoSansArabic-Joining-Subset.ttf',
					'useOTL' => 0xFF,
				],
			],
		];
	}

	private function style()
	{
		ob_start();
		?>
		<style>
			h2 { font-size: 12pt; margin-bottom: 1mm; }
			p.note { font-size: 9pt; color: #606060; margin-top: 0; }
			p.label { font-size: 9pt; color: #606060; margin-bottom: 0; }
			p.sample { font-size: 28pt; margin-top: 0; margin-bottom: 3mm; text-align: left; }
			p.syriac { font-family: syriacsubset; }
			p.arabic { font-family: arabicsubset; }
		</style>

		<h1>mPDF</h1>
		<h2>Cursive joining</h2>
		<p class="note">A letter of a cursive script takes its form from the letters either side of it, so
			a character read as joining on the wrong side is drawn correctly itself and misshapes its
			neighbour. Every sample runs right to left; read the letter on the right of each, and compare
			it with the two samples under it.</p>
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
		return '<p class="label">' . $label . '</p>'
			. '<p dir="rtl" class="sample ' . $script . '">' . $text . '</p>';
	}

	/**
	 * The document only shows anything if the letter before each of the two characters is drawn as the
	 * neighbour it is sampled against draws it, and not as the form the wrong key gave it. Every glyph
	 * is a single character of a subset built as the document is written, so the samples are compared
	 * with each other rather than by naming any of them.
	 *
	 * @return string[] the glyphs the sample was drawn with, left to right
	 */
	private function glyphsOf($font, $text)
	{
		$mpdf = new TextRecordingMpdf($this->config());
		$mpdf->WriteHTML($this->style() . '<p dir="rtl" class="sample ' . $font . '">' . $text . '</p>');

		return preg_split('//u', end($mpdf->drawnText), -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * Sogdian Fe joins on both sides, so the Beth in front of it is the initial Beth that stands in
	 * front of any other dual-joining letter, and not the isolated one. The Beth is the rightmost
	 * letter of a run that reads right to left, so it is the last glyph drawn.
	 */
	public function testTheSyriacSampleDrawsTheBethSogdianFeAsksForAndNotTheIsolatedOne()
	{
		$beforeSogdianFe = $this->glyphsOf('syriac', self::BETH . self::SOGDIAN_FE);
		$beforeSemkath = $this->glyphsOf('syriac', self::BETH . self::SEMKATH);
		$alone = $this->glyphsOf('syriac', self::BETH);

		$this->assertSame(end($beforeSemkath), end($beforeSogdianFe), 'the sample draws a Beth the dual-joining neighbour does not');
		$this->assertNotSame(end($alone), end($beforeSogdianFe), 'the sample draws the isolated Beth');
	}

	/**
	 * Low Alef joins nothing, so it leaves the Beh in front of it exactly as the Beh stands on its own -
	 * the whole run is the two of them drawn unchanged, and none of it is the Beh that joins an Alef.
	 */
	public function testTheArabicSampleDrawsTheBehLowAlefAsksForAndNotTheJoinedOne()
	{
		$sample = $this->glyphsOf('arabic', self::BEH . self::LOW_ALEF);
		$unjoined = array_merge($this->glyphsOf('arabic', self::LOW_ALEF), $this->glyphsOf('arabic', self::BEH));

		$this->assertSame($unjoined, $sample, 'the sample draws something other than the Low Alef and the isolated Beh');
		$this->assertNotSame($this->glyphsOf('arabic', self::BEH . self::ALEF), $sample, 'the sample draws the joined Beh');
	}
}
