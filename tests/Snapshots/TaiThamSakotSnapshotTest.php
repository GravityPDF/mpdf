<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the Tai Tham clusters that turn on where a glyph gets its South East Asian category. The
 * shaper categorises every character of a run, groups them into clusters, then applies 'locl' and
 * 'ccmp' before it reorders each cluster - and the reorderer reads the category of the last glyph of
 * a broken cluster to decide whether to reorder it at all.
 *
 * A substitution in that pass makes glyphs the categorising pass never saw. Otl carries the Indic and
 * Myanmar categories onto them and carried nothing onto these, so a Sakot ligated with the consonant
 * after it reached the reorderer with no category, and reading it was a warning per run.
 *
 * Lanna Alif is the font throughout. Its 'ccmp' ligates a Sakot with the consonant after it into a
 * subscript form whose glyph has no codepoint of its own, so it is mapped into the Private Use Area
 * as the subset is built and the samples are read off the page and compared with each other rather
 * than named.
 *
 * Nothing here is drawn differently: a missing category read as null matched nothing at the one test
 * that reads it, and the fixture is byte-identical to the one the code before the fix writes. What it
 * pins is a cluster that reaches the reorderer through a substitution, so moving where the category
 * comes from has to move a page to get through.
 *
 * @group snapshot
 */
class TaiThamSakotSnapshotTest extends Snapshot
{
	/** U+1A20 TAI THAM LETTER HIGH KA */
	const HIGH_KA = '&#x1A20;';

	/** U+1A21 TAI THAM LETTER HIGH XA */
	const HIGH_XA = '&#x1A21;';

	/** U+1A60 TAI THAM SIGN SAKOT, which subscripts the consonant after it */
	const SAKOT = '&#x1A60;';

	/** U+1A63 TAI THAM VOWEL SIGN AA */
	const VOWEL_AA = '&#x1A63;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'tai-tham-sakot';
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
			p.sample { font-family: lannaalif; font-size: 28pt; margin-top: 0; margin-bottom: 3mm; }
		</style>

		<h1>mPDF</h1>
		<h2>Tai Tham clusters joined by a Sakot</h2>
		<p class="note">A Sakot subscripts the consonant after it, and the two are drawn as one glyph
			the font substitutes for the pair. Written with no consonant in front of them they are a
			broken cluster, and a dotted circle is inserted to stand in for the consonant that is
			missing. Read the subscript form under each: the broken cluster draws the same one the
			well-formed cluster does.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return $this->sample('Sakot, High Ka - no base, so a dotted circle carries the pair', self::SAKOT . self::HIGH_KA)
			. $this->sample('High Ka, Sakot, High Ka - the same pair under a base, the form to match', self::HIGH_KA . self::SAKOT . self::HIGH_KA)
			. $this->sample('High Ka alone - the base by itself', self::HIGH_KA)
			. $this->sample('Sakot alone - nothing to subscript, drawn as it was written', self::SAKOT)
			. $this->sample('Sakot, High Xa - the same rule one consonant over', self::SAKOT . self::HIGH_XA)
			. $this->sample('Sakot, High Ka, Vowel Sign Aa - a vowel after the broken cluster', self::SAKOT . self::HIGH_KA . self::VOWEL_AA)
			. $this->sample('High Ka, Sakot, High Ka, Vowel Sign Aa - the same vowel after the base', self::HIGH_KA . self::SAKOT . self::HIGH_KA . self::VOWEL_AA);
	}

	private function sample($label, $text)
	{
		return '<p class="label">' . $label . '</p>'
			. '<p class="sample">' . $text . '</p>';
	}

	/**
	 * The document only shows anything if the broken cluster draws the subscript form the well-formed
	 * cluster draws, rather than the two characters it was written as. The glyph has no codepoint of
	 * its own, so the samples are compared with each other rather than by naming it.
	 *
	 * @return int[] the glyphs the sample was drawn with, left to right
	 */
	private function glyphsOf($text)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($this->style() . '<p class="sample">' . $text . '</p>');

		return array_values(unpack('N*', mb_convert_encoding(end($mpdf->drawnText), 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The Sakot and the consonant after it become one glyph either way. In the broken cluster a dotted
	 * circle stands where the base would be, and the subscript form is the same one the base takes.
	 */
	public function testTheBrokenClusterDrawsTheSubscriptFormTheWellFormedClusterDraws()
	{
		$broken = $this->glyphsOf(self::SAKOT . self::HIGH_KA);
		$wellFormed = $this->glyphsOf(self::HIGH_KA . self::SAKOT . self::HIGH_KA);

		$this->assertCount(2, $broken, 'the broken cluster draws something other than a stand-in and one subscript form');
		$this->assertSame(end($wellFormed), end($broken), 'the broken cluster draws a subscript form the well-formed one does not');
		$this->assertNotSame($this->glyphsOf(self::HIGH_KA)[0], $broken[0], 'the stand-in is the consonant rather than a dotted circle');
	}

	/**
	 * The Sakot is genuinely replaced rather than drawn alongside the form that replaces it.
	 */
	public function testTheSakotIsNotDrawnBesideTheFormThatReplacesIt()
	{
		$alone = $this->glyphsOf(self::SAKOT);
		$subscripted = $this->glyphsOf(self::SAKOT . self::HIGH_KA);

		$this->assertNotContains($alone[0], $subscripted, 'the pair draws the Sakot rather than the form that replaces it');
	}
}
