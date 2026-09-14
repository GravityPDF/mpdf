<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the Indic clusters that leave initial_reordering_syllable() before it reorders anything: a
 * cluster of characters the shaper has nothing to say about, and a broken cluster whose last glyph is
 * the dotted circle inserted into it. Both are still offered rphf, pref, blwf, half, abvf, pstf and
 * init, all of which read ['mask'] off every glyph they are offered, and the masks were set up after
 * both ways out.
 *
 * A missing key read as 0 wherever it was tested and as 0 wherever it was or'd into, so setting the
 * masks before the two exits draws nothing differently - and that is what this document is for. Its
 * fixture is byte-identical to the one the code before the change writes; what it holds is that the
 * clusters which take those exits are drawn the way they were, so moving the masks again, or moving
 * an exit past them, shows up as a page that moved.
 *
 * What did change is that a page of these no longer writes a warning per glyph per feature. That is
 * not in a PDF, so the test beside the snapshot asserts it instead.
 *
 * Kaputa Unicode reaches both exits; FreeSerif reaches the one in the 'init' pass of the final
 * reordering, which reads the same key from the other side.
 *
 * @group snapshot
 */
class IndicClusterMaskSnapshotTest extends Snapshot
{
	/** U+25CC DOTTED CIRCLE, which the shaper inserts into a cluster with no base of its own */
	const DOTTED_CIRCLE = '&#x25CC;';

	/** U+0DCA SINHALA SIGN AL-LAKUNA, the virama */
	const AL_LAKUNA = '&#x0DCA;';

	/** U+0DD9 SINHALA VOWEL SIGN KOMBUVA, a matra written before the consonant it follows */
	const KOMBUVA = '&#x0DD9;';

	/** Sinhala for "Sinhala", a cluster the shaper does reorder */
	const SINHALA = '&#x0DC3;&#x0DD2;&#x0D82;&#x0DC4;&#x0DBD;';

	/** KA, virama, zero-width joiner, SHA: a conjunct, so the same characters through the other exit */
	const KA_SHA = '&#x0D9A;&#x0DCA;&#x200D;&#x0DC2;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'indic-cluster-mask';
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
			p.sample { font-size: 28pt; margin-top: 0; margin-bottom: 3mm; }
			p.kaputa { font-family: kaputaunicode; }
			p.freeserif { font-family: freeserif; }
		</style>

		<h1>mPDF</h1>
		<h2>Indic clusters the shaper leaves alone</h2>
		<p class="note">A mark with no consonant to attach to, and a matra with nothing to sit before,
			are clusters the Indic shaper returns without reordering. They are still offered every
			feature it masks, so every glyph of them needs a mask - and asking for none of those
			features is what a mask of 0 says, which is what a missing one already read as. Nothing
			here is drawn differently for having one; the samples are held so that a change to where
			the masks are set, or to where the shaper returns, has to move a page to get through.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return '<h2>Kaputa Unicode</h2>'
			. $this->sample('kaputa', 'Dotted circle, Al-Lakuna - a broken cluster ending in its dotted circle', self::DOTTED_CIRCLE . self::AL_LAKUNA)
			. $this->sample('kaputa', 'Al-Lakuna alone - a mark with no consonant to attach to', self::AL_LAKUNA)
			. $this->sample('kaputa', 'Kombuva alone - a matra with no consonant to be written before', self::KOMBUVA)
			. $this->sample('kaputa', 'Sinhala for "Sinhala" - clusters the shaper does reorder', self::SINHALA)
			. $this->sample('kaputa', 'KA, Al-Lakuna, ZWJ, SHA - a conjunct, reordered the same way', self::KA_SHA)
			. '<h2>FreeSerif</h2>'
			. $this->sample('freeserif', 'Kombuva alone - the same matra through the init pass of another font', self::KOMBUVA)
			. $this->sample('freeserif', 'Sinhala for "Sinhala"', self::SINHALA);
	}

	private function sample($font, $label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample ' . $font . '">' . $text . '</p>';
	}

	/**
	 * Every glyph of every cluster the document draws carries a mask, so no feature reads a key that
	 * is not there. A warning per glyph per feature is not something a fixture can hold: it goes to
	 * the error handler, and out ahead of the document for anything writing one to stdout.
	 */
	public function testTheDocumentIsDrawnWithoutReadingAMaskThatIsNotThere()
	{
		$diagnostics = [];
		set_error_handler(function ($no, $message, $file) use (&$diagnostics) {
			if (false !== strpos($file, 'Indic.php') || false !== strpos($file, 'Otl.php')) {
				$diagnostics[] = basename($file) . ': ' . $message;
			}

			return true;
		});

		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($this->style() . $this->samples());

		restore_error_handler();

		$this->assertSame([], array_values(array_unique($diagnostics)));
	}
}
