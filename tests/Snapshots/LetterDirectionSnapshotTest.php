<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders a right-to-left letter added to Unicode after 6.1, in a paragraph that runs the other way.
 *
 * A letter of a right-to-left script is a strong character: it sets the direction of the run it is
 * in, whatever the paragraph around it does. While Ucdn's tables stopped at 6.1, a letter added since
 * read as an unassigned codepoint, whose bidi class is the neutral ON - so it set no direction, took
 * one from its neighbours, and a run of such letters was laid out in the order it was typed. See
 * GravityPDF/mpdf#101.
 *
 * U+08AD ARABIC LETTER LOW ALEF was added in Unicode 7.0. Beside the Beh, in a left-to-right
 * paragraph, it used to be placed to the right of it rather than to the left. The Alef beneath it is
 * the control: it has been a strong letter throughout, and is where the Low Alef should have been all
 * along.
 *
 * The two are drawn in the Noto subset built for the joining tables of #94, which is the only font
 * here with a glyph for U+08AD.
 *
 * @group snapshot
 */
class LetterDirectionSnapshotTest extends Snapshot
{
	/** U+0628 ARABIC LETTER BEH, dual-joining, strong right-to-left throughout */
	const BEH = '&#x0628;';

	/** U+0627 ARABIC LETTER ALEF, strong right-to-left throughout */
	const ALEF = '&#x0627;';

	/** U+08AD ARABIC LETTER LOW ALEF, added in Unicode 7.0 */
	const LOW_ALEF = '&#x08AD;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'letter-direction';
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
		$this->mpdf->WriteHTML($this->style() . $this->samples());
	}

	private function config()
	{
		return [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [
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
			p.sample { font-family: arabicsubset; font-size: 28pt; margin-top: 0; margin-bottom: 3mm; text-align: left; }
		</style>

		<h1>mPDF</h1>
		<h2>A right-to-left letter in a left-to-right paragraph</h2>
		<p class="note">Each pair reads right to left, so the first letter typed is the one on the right.
			The paragraphs are not marked with a direction - the letters carry their own. Compare the first
			sample with the second: the letter after the Beh belongs on the left of it, and the two
			samples have to agree on that.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return $this->sample('Beh, Low Alef - the Low Alef left of the Beh', self::BEH . self::LOW_ALEF)
			. $this->sample('Beh, Alef - a letter strong throughout, the form to match', self::BEH . self::ALEF)
			. $this->sample('Beh alone', self::BEH);
	}

	private function sample($label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample">' . $text . '</p>';
	}

	/**
	 * @param string $dir the direction to mark the paragraph with, or '' to leave it unmarked
	 *
	 * @return string[] the glyphs the sample was drawn with, left to right
	 */
	private function glyphsOf($text, $dir = '')
	{
		$mpdf = new TextRecordingMpdf($this->config());
		$mpdf->WriteHTML($this->style() . '<p class="sample"' . ($dir ? ' dir="' . $dir . '"' : '') . '>' . $text . '</p>');

		return preg_split('//u', end($mpdf->drawnText), -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * A strong letter is laid out the same way whichever direction the paragraph around it runs,
	 * because the run takes its direction from the letter. A neutral one does not: it is left where
	 * the paragraph puts it, which for the Low Alef was the wrong side of the Beh.
	 */
	public function testALetterAddedSinceUnicodeSixOneSetsTheDirectionOfItsOwnRun()
	{
		$unmarked = $this->glyphsOf(self::BEH . self::LOW_ALEF);

		$this->assertSame(
			$this->glyphsOf(self::BEH . self::LOW_ALEF, 'rtl'),
			$unmarked,
			'the paragraph decided the order rather than the letters'
		);
		$this->assertSame(
			$this->glyphsOf(self::BEH . self::ALEF, 'rtl'),
			$this->glyphsOf(self::BEH . self::ALEF),
			'the control, which has been strong all along, does not depend on the paragraph either'
		);
	}

	/**
	 * Read right to left, the Beh is typed first and drawn last, so whatever follows it is drawn
	 * before it - and that is what used to come out the other way round.
	 */
	public function testTheLowAlefIsDrawnToTheLeftOfTheBeh()
	{
		$sample = $this->glyphsOf(self::BEH . self::LOW_ALEF);
		$control = $this->glyphsOf(self::BEH . self::ALEF);

		$this->assertCount(3, $sample, 'the Beh is drawn as two glyphs beside the letter after it');
		$this->assertSame(count($control), count($sample), 'the sample and the control are drawn with the same number of glyphs');

		$alone = $this->glyphsOf(self::BEH);
		$this->assertSame(array_slice($alone, 0, 1), array_slice($sample, 1, 1), 'the Beh is not where the control puts it');
	}

}
