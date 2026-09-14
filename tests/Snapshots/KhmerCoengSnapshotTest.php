<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the Khmer runs that turn on how far into the cluster KHMER_FIX_2 reads. The block looks
 * through the cluster for a Coeng followed by a Ra so it can move the pair in front of the base
 * consonant, and it read one position past the end of the cluster to do it.
 *
 * The Khmer syllable pattern takes at most four Coeng groups, so a fifth leaves the Coeng as the last
 * character of its cluster and the character tested is the first character of the next one. Where that
 * is a Ro, a Coeng was moved in front of a base consonant belonging to a cluster the Ro is not in - it
 * was drawn ahead of the KA it subscripts, in a run written with nothing ahead of that KA at all. On
 * the last cluster of a run the character tested is not in the array, which is a Coeng with nothing
 * after it: two warnings and a deprecation per run rather than a mark drawn on its dotted circle.
 *
 * KhmerOS is the font throughout. The glyphs its 'blwf' and 'pref' substitute in have no codepoints of
 * their own and are mapped into the Private Use Area as the subset is built, so the samples are read
 * off the page and compared with each other rather than named.
 *
 * @group snapshot
 */
class KhmerCoengSnapshotTest extends Snapshot
{
	/** U+1780 KHMER LETTER KA */
	const KA = '&#x1780;';

	/** U+179A KHMER LETTER RO */
	const RO = '&#x179A;';

	/** U+17D2 KHMER SIGN COENG, which subscripts the consonant after it */
	const COENG = '&#x17D2;';

	/**
	 * Five Coeng groups: one more than the syllable pattern takes, so the fifth Coeng is the last
	 * character of its cluster and whatever follows belongs to the next one
	 */
	const FIVE_COENG_GROUPS = '&#x1780;&#x17D2;&#x1780;&#x17D2;&#x1780;&#x17D2;&#x1780;&#x17D2;&#x1780;&#x17D2;';

	/** Khmer for "Khmer language", whose one Coeng sits inside its cluster */
	const PHASA_KHMER = '&#x1797;&#x17B6;&#x179F;&#x17B6;&#x1781;&#x17D2;&#x1798;&#x17C2;&#x179A;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'khmer-coeng';
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
			p.sample { font-family: khmeros; font-size: 28pt; margin-top: 0; margin-bottom: 3mm; }
		</style>

		<h1>mPDF</h1>
		<h2>Khmer Coeng clusters</h2>
		<p class="note">A Coeng subscripts the consonant after it, and a Coeng and a Ro together are
			drawn in front of the consonant they were written after. Both belong to one cluster: a Coeng
			that ends its cluster subscripts nothing and moves nowhere, whatever the next cluster
			begins with. Every sample below is written left to right in the order it is listed.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return $this->sample('KA, five Coeng groups, RO - the KA first, the trailing Coeng where it was written', self::FIVE_COENG_GROUPS . self::RO)
			. $this->sample('KA, five Coeng groups - the same run without the RO after it', self::FIVE_COENG_GROUPS)
			. $this->sample('KA, Coeng, RO - one cluster, so the pair is drawn in front of the KA', self::KA . self::COENG . self::RO)
			. $this->sample('Coeng alone - a Coeng with nothing after it, on its dotted circle', self::COENG)
			. $this->sample('Khmer for "Khmer language", whose Coeng sits inside its cluster', self::PHASA_KHMER);
	}

	private function sample($label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample">' . $text . '</p>';
	}

	/**
	 * @return array the codepoints of the line the sample drew, and the diagnostics the shaper raised
	 * writing it
	 */
	private function drawn($text)
	{
		$diagnostics = [];
		set_error_handler(function ($no, $message, $file) use (&$diagnostics) {
			if (false !== strpos($file, 'Indic.php')) {
				$diagnostics[] = $message;
			}

			return true;
		});

		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($this->style() . '<p class="sample">' . $text . '</p>');

		restore_error_handler();

		return [
			'text' => array_values(unpack('N*', mb_convert_encoding(end($mpdf->drawnText), 'UTF-32BE', 'UTF-8'))),
			'diagnostics' => array_values(array_unique($diagnostics)),
		];
	}

	/**
	 * The document only shows anything if the run with the RO after it is still drawn in the order it
	 * was written, which is the order the same run without the RO is drawn in, with the RO after it.
	 */
	public function testTheRunEndingInACoengIsDrawnTheSameWayWhateverFollowsIt()
	{
		$withRo = $this->drawn(self::FIVE_COENG_GROUPS . self::RO);
		$withoutRo = $this->drawn(self::FIVE_COENG_GROUPS);

		$this->assertSame(array_merge($withoutRo['text'], [0x179A]), $withRo['text']);
	}

	/**
	 * And the sample that ends the document is a Coeng with nothing after it at all, which is the read
	 * off the end of the array.
	 */
	public function testTheLoneCoengIsShapedWithoutReadingPastIt()
	{
		$this->assertSame([], $this->drawn(self::COENG)['diagnostics']);
	}

	/**
	 * A Coeng and a Ro inside one cluster are still moved in front of the base, which is what the block
	 * is for and what the third sample is there to show.
	 */
	public function testTheClusterWhoseCoengAndRoBelongTogetherStillMovesThem()
	{
		$drawn = $this->drawn(self::KA . self::COENG . self::RO)['text'];

		$this->assertSame(0x1780, end($drawn));
		$this->assertNotSame(0x1780, $drawn[0]);
	}
}
