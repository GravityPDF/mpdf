<?php

namespace Snapshots;

use Mpdf\PositionRecordingMpdf;
use Mpdf\TextRecordingMpdf;

/**
 * Renders a run of a script added to Unicode after 6.1, which is where Ucdn's tables used to stop.
 * Text of such a script had no script of its own to report, so Otl asked the font for DFLT and got
 * whatever the font had filed there - for most fonts of a recent script, nothing at all.
 *
 * The font is a subset of Noto Sans Wancho holding three letters and the two brackets, and its whole
 * layout is filed under wcho: a locl substitution that swaps the opening bracket for the Wancho one,
 * and a dist lookup that spaces the letters. There is no DFLT script in it, so before the tables were
 * regenerated none of that was reachable and the run was drawn exactly as it was typed.
 *
 * Wancho is an alphabet, so the tag alone is the whole of the shaping - no shaper was added for it,
 * or for any of the other scripts the regeneration named. See GravityPDF/mpdf#99.
 *
 * @group snapshot
 */
class ScriptTagSnapshotTest extends Snapshot
{
	/** U+1E2C0 WANCHO LETTER AA */
	const AA = '&#x1E2C0;';

	/** U+1E2C1 WANCHO LETTER A */
	const A = '&#x1E2C1;';

	/** U+1E2C2 WANCHO LETTER BA */
	const BA = '&#x1E2C2;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'script-tag';
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
				'wanchosubset' => [
					'R' => 'NotoSansWancho-ScriptTag-Subset.ttf',
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
			p.sample { font-family: wanchosubset; font-size: 28pt; margin-top: 0; margin-bottom: 3mm; }
		</style>

		<h1>mPDF</h1>
		<h2>A script added after Unicode 6.1</h2>
		<p class="note">The font files every lookup it has under its own script tag, wcho, and carries no
			DFLT script at all. Compare the opening bracket of the first sample with the one of the second:
			the first is the bracket the font draws for Wancho, which it can only be asked for by name.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		return $this->sample('Bracketed Wancho - the Wancho opening bracket', '(' . self::AA . ')')
			. $this->sample('The brackets alone - the Latin ones, the form the run was given', '()')
			. $this->sample('Three letters - spaced by the font rather than by their own widths', self::AA . self::A . self::BA);
	}

	private function sample($label, $text)
	{
		return '<p class="label">' . $label . '</p><p class="sample">' . $text . '</p>';
	}

	/**
	 * @return string[] the glyphs the sample was drawn with, left to right
	 */
	private function glyphsOf($text)
	{
		$mpdf = new TextRecordingMpdf($this->config());
		$mpdf->WriteHTML($this->style() . '<p class="sample">' . $text . '</p>');

		return preg_split('//u', end($mpdf->drawnText), -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * The font's locl lookup, which lives under wcho and nowhere else, replaces the opening bracket
	 * with the Wancho one. Standing beside a Wancho letter the bracket is part of a Wancho run and gets
	 * that form; standing on its own it is a run of common characters, and keeps the Latin one.
	 */
	public function testTheBracketBesideAWanchoLetterIsTheOneTheFontFilesUnderWancho()
	{
		$bracketed = $this->glyphsOf('(' . self::AA . ')');
		$alone = $this->glyphsOf('()');

		$this->assertCount(3, $bracketed);
		$this->assertNotSame($alone[0], $bracketed[0], 'the run was drawn with the bracket the font gives any other script');
		$this->assertSame($alone[1], $bracketed[2], 'the closing bracket, which the font has no Wancho form of, was changed');
	}

	/**
	 * The dist lookup lives under wcho too, so a pair of Wancho letters is only spaced by the font once
	 * the run is asked for by that name.
	 */
	public function testTheLettersAreSpacedByTheFontsOwnPositioning()
	{
		$mpdf = new PositionRecordingMpdf($this->config());
		$mpdf->WriteHTML($this->style() . '<p class="sample">' . self::AA . self::A . self::BA . '</p>');

		$this->assertNotEmpty($mpdf->drawnPositions, 'nothing in the run was positioned by the font');
	}

}
