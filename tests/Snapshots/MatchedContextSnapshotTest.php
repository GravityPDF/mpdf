<?php

namespace Snapshots;

/**
 * Renders the runs that turn on whether a matched context ends its lookup.
 *
 * A lookup applies at most one of its subtables at a glyph, and a subtable says it applied by
 * reporting how far to advance. A contextual subtable reported whatever the lookups it names
 * shifted, so one that matched and named nothing - a rule meaning "stop here" - read as not having
 * matched, and the glyph went on to a later subtable that matched a shorter context and acted on it.
 *
 * Manjari is the half a shipped font reaches. Its `akhn` lookup ligates BA + VIRAMA + DA, behind two
 * subtables that match the same three letters and name no lookups: one where a vowel sign follows,
 * one where a VIRAMA and an RA or DA do. Those are the spellings where the DA has to keep its own
 * shape to carry what comes after it, and the ligature was being formed over them - the vowel sign
 * then hangs off a letter that is no longer there.
 *
 * The synthetic font is the positioning half, which nothing shipped reaches: Noto Sans cut down to
 * A, B and C, with a `dist` chained context of two subtables over the B. The first asks for a C
 * after it and names no lookups; the second shifts the B 400 units left. Only the B before a C
 * should stay where it is.
 *
 * @group snapshot
 */
class MatchedContextSnapshotTest extends Snapshot
{

	/** U+0D2C MALAYALAM LETTER BA, U+0D4D VIRAMA, U+0D26 DA - the three letters `akhn` ligates */
	const BA_VIRAMA_DA = '&#x0D2C;&#x0D4D;&#x0D26;';

	/** The six vowel signs the first guard subtable looks ahead for, and one it does not */
	const VOWEL_SIGNS = [
		'U+0D41 sign u' => 0x0D41,
		'U+0D42 sign uu' => 0x0D42,
		'U+0D43 sign vocalic r' => 0x0D43,
		'U+0D44 sign vocalic rr' => 0x0D44,
		'U+0D62 sign vocalic l' => 0x0D62,
		'U+0D63 sign vocalic ll' => 0x0D63,
		'U+0D3E sign aa - not guarded' => 0x0D3E,
	];

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'matched-context';
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
			'fontdata' => [
				'manjari' => [
					'R' => 'Manjari-Regular.ttf',
					'useOTL' => 0xFF,
				],
				'guard' => [
					'R' => 'NotoSans-GPOS83-Guard-Synthetic.ttf',
					'useOTL' => 0xFF,
				],
			],
		]);

		$this->mpdf->WriteHTML($this->style() . $this->malayalamSamples() . $this->guardSamples());
	}

	private function style()
	{
		ob_start();
		?>
		<style>
			h2 { font-size: 12pt; margin-bottom: 1mm; }
			p.note { font-size: 9pt; color: #606060; margin-top: 0; }
			td.label { font-size: 9pt; color: #606060; width: 60mm; }
			td.sample { font-size: 28pt; }
			td.malayalam { font-family: manjari; }
			td.guard { font-family: guard; }
		</style>

		<h1>mPDF</h1>
		<?php

		return ob_get_clean();
	}

	/**
	 * Each row is the three letters with something after them, beside the three on their own - which
	 * is the ligature, and what the guarded rows were being drawn as with the mark hung off it.
	 */
	private function malayalamSamples()
	{
		$html = '<h2>Malayalam akhn ligature</h2>'
			. '<p class="note">BA + VIRAMA + DA ligates only where nothing follows that the DA has to'
			. ' carry. Read the first column against the second: every row but the sign aa, which no'
			. ' subtable guards, should keep the DA as its own letter rather than repeat the ligature.'
			. '</p><table>';

		foreach (self::VOWEL_SIGNS as $label => $sign) {
			$html .= '<tr><td class="label">' . $label . '</td>'
				. '<td class="sample malayalam">' . self::BA_VIRAMA_DA . sprintf('&#x%04X;', $sign) . '</td>'
				. '<td class="sample malayalam">' . self::BA_VIRAMA_DA . '</td></tr>';
		}

		// The second guard: a VIRAMA and then an RA, which is the RA's subscript form
		$html .= '<tr><td class="label">U+0D4D U+0D30 virama and ra</td>'
			. '<td class="sample malayalam">' . self::BA_VIRAMA_DA . '&#x0D4D;&#x0D30;</td>'
			. '<td class="sample malayalam">' . self::BA_VIRAMA_DA . '</td></tr>';

		return $html . '</table>';
	}

	/**
	 * The B before a C is the one the guard matches, and the only one that should sit where it was
	 * typed. The others are drawn 400 units left of it.
	 */
	private function guardSamples()
	{
		$html = '<h2>A guarded chained context positioning</h2>'
			. '<p class="note">Only the B in the first row is left where it is. Every other B is drawn 400'
			. ' units to the left of where it was typed, which over a C puts it on top of the letter.</p>'
			. '<table>';

		foreach (['BC', 'BA', 'CB', 'B'] as $sample) {
			$html .= '<tr><td class="label">' . $sample . '</td>'
				. '<td class="sample guard">' . $sample . '</td></tr>';
		}

		return $html . '</table>';
	}
}
