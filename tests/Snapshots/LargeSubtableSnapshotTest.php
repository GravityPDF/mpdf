<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the glyphs of a GSUB subtable that runs past 32 KB.
 *
 * The record for each covered glyph is reached through an array of Offset16 from the start of the
 * subtable, and Offset16 is unsigned, so a subtable larger than 32,767 bytes has entries at or past
 * 0x8000. Read signed, those came back negative and the shaper seeked 65,536 bytes short of the
 * record it wanted, decoding whatever the table holds there: another glyph's replacement, and a
 * glyph count belonging to neither.
 *
 * The font is built for this and draws nothing else. It is 1,000 covered glyphs, each replaced by a
 * run of fifteen markers with the glyph itself in the middle - the shape of a font that shapes by
 * expansion, computes on the markers and collapses the run again. Its Sequence array comes to 36,072
 * bytes, so the last 95 entries sit past the boundary. Four glyphs carry an outline and the markers
 * draw nothing, so a row shows the character typed, or shows nothing where it was replaced out of
 * another glyph's record. Before the fix the last two rows were blank.
 *
 * @group snapshot
 */
class LargeSubtableSnapshotTest extends Snapshot
{
	/**
	 * The four glyphs the font draws, and where each one's Sequence sits in the subtable.
	 */
	const SAMPLES = [
		['square', 0xE000, 'Sequence 2,006 bytes in'],
		['triangle', 0xE001, 'Sequence 2,040 bytes in'],
		['diamond', 0xE3E6, 'Sequence 35,948 bytes in - past 0x8000'],
		['bar', 0xE3E7, 'Sequence 35,982 bytes in - past 0x8000'],
	];

	/** Glyphs in the run each covered glyph is replaced by */
	const SEQUENCE_LENGTH = 16;

	/** Where the covered glyph itself sits in that run */
	const SELF_POSITION = 8;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'large-subtable';
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
				'bigsubtable' => [
					'R' => 'NotoSans-GSUB2-BigSubtable-Synthetic.ttf',
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
			td.label { font-size: 9pt; color: #606060; width: 70mm; }
			td.sample { font-family: bigsubtable; font-size: 28pt; }
		</style>

		<h1>mPDF</h1>
		<h2>A substitution subtable over 32 KB</h2>
		<p class="note">Each of these characters is replaced by a run of fifteen markers with the
			character itself in the middle. The markers draw nothing, so a row shows the shape of the
			character that was typed - unless the record was read from the wrong place, in which case the
			character is gone and the row is blank.</p>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		$html = '<table>';
		foreach (self::SAMPLES as $sample) {
			list($name, $codepoint, $where) = $sample;
			$html .= '<tr><td class="label">' . $name . ' U+' . sprintf('%04X', $codepoint)
				. ' - ' . $where . '</td>'
				. '<td class="sample">' . sprintf('&#x%04X;', $codepoint) . '</td></tr>';
		}

		return $html . '</table>';
	}

	/**
	 * The font comes from the inline style rather than from the stylesheet, so the sample stands on
	 * its own and the run is the first thing drawn.
	 *
	 * @return int[] the codepoints the sample was drawn with
	 */
	private function drawn($codepoint)
	{
		$mpdf = new TextRecordingMpdf($this->config());
		$mpdf->WriteHTML(sprintf('<p style="font-family:bigsubtable">&#x%04X;</p>', $codepoint));

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

	/**
	 * The document only shows anything if each row draws the character that was typed. The markers
	 * around it have no codepoints of their own, so the run is read by where the character sits in it
	 * rather than by naming them.
	 */
	public function testEveryRowDrawsTheCharacterThatWasTyped()
	{
		foreach (self::SAMPLES as $sample) {
			list($name, $codepoint) = $sample;
			$drawn = $this->drawn($codepoint);

			$this->assertCount(self::SEQUENCE_LENGTH, $drawn, $name . ' was not replaced by a run of sixteen');
			$this->assertSame($codepoint, $drawn[self::SELF_POSITION], $name . ' is not in the run it expanded into');
		}
	}
}
