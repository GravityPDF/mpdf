<?php

namespace Snapshots;

/**
 * Renders the runs that turn on a subtable saying whether it applied separately from how far the
 * cursor then moves.
 *
 * Every subtable used to return one number for both. A matched context returned whatever the lookups
 * it named shifted, counted from the position the record named rather than from the context's own
 * start, and a Multiple Substitution to the empty sequence - a font deleting a glyph - returned the
 * nothing it had put there, which read as not having applied.
 *
 * Nothing shipped reaches any of that, so the font is written by hand:
 * NotoSans-GSUB53-NestedAdvance-Synthetic is Noto Sans cut down to the capitals, with a `ccmp` of
 * three lookups - a context over A B C naming an expansion at its second glyph, a deletion of the D
 * standing in front of a rule that draws a Q, and a context over G H I whose first record deletes
 * the G and whose second names a glyph one place past what is left.
 *
 * @group snapshot
 */
class NestedAdvanceSnapshotTest extends Snapshot
{

	/** Each run beside what it should draw, which is what hb-shape draws for it */
	const SAMPLES = [
		'ABCE' => 'AXYCE',
		'ABC' => 'AXYC',
		'DE' => 'E',
		'D' => 'nothing',
		'GHIJ' => 'HIJ',
		'GHI' => 'HI',
	];

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'nested-advance';
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
		$this->mpdf = $this->createMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['nestedadvance' => [
				'R' => 'NotoSans-GSUB53-NestedAdvance-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
		]);

		$this->mpdf->WriteHTML($this->style() . $this->samples());
	}

	private function style()
	{
		ob_start();
		?>
		<style>
			h2 { font-size: 12pt; margin-bottom: 1mm; }
			p.note { font-size: 9pt; color: #606060; margin-top: 0; }
			td.label { font-size: 9pt; color: #606060; width: 60mm; }
			td.sample { font-size: 28pt; font-family: nestedadvance; }
		</style>

		<h1>mPDF</h1>
		<?php

		return ob_get_clean();
	}

	private function samples()
	{
		$html = '<h2>A nested lookup\'s advance and a deletion</h2>'
			. '<p class="note">Read each row against its label. The Y is the second glyph the A B C'
			. ' context expands the B into, and should be left as itself rather than drawn as a Z. The'
			. ' D should be gone rather than drawn as a Q, and the J left as itself rather than drawn'
			. ' as an R.</p><table>';

		foreach (self::SAMPLES as $sample => $expected) {
			$html .= '<tr><td class="label">' . $sample . ' &rarr; ' . $expected . '</td>'
				. '<td class="sample">' . $sample . '</td></tr>';
		}

		return $html . '</table>';
	}
}
