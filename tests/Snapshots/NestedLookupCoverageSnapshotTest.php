<?php

namespace Snapshots;

use Mpdf\TextRecordingMpdf;

/**
 * Renders the runs that turn on whether a nested lookup is tested against its own Coverage.
 *
 * A contextual or chained lookup matches a sequence on its own Coverages and then names other lookups
 * to run at positions within that match. The glyph handed to one of those is whatever the context
 * matched there, and the subtable it is handed to has a Coverage of its own that nothing compared it
 * with. The handler indexed that Coverage anyway and got null, which is 0 - the record the subtable
 * holds for the first glyph it covers.
 *
 * DejaVu Sans is the first half. It substitutes a shorter, higher form of a combining mark for the
 * one written when it follows a capital, through a chained context that names a single substitution
 * covering the twelve marks it has such a form for. The context presents forty-four, so the other
 * thirty-two were all drawn as the capital grave. Each mark is drawn over a capital A and again over
 * a lowercase a, which the context does not match, so a row shows the mark beside what it was being
 * turned into.
 *
 * Noto Sans Gurmukhi is the second, and the shape GravityPDF/mpdf#102 was reported from: a `dist`
 * chained context names a single adjustment at a Devanagari stress sign, and that lookup's first
 * subtable covers the bindi instead. The stress signs were positioned by the bindi's value record -
 * on the baseline and 150 units to the left, rather than above and below the letter. A subset carries
 * it, since nothing shipped does.
 *
 * @group snapshot
 */
class NestedLookupCoverageSnapshotTest extends Snapshot
{
	/**
	 * Marks DejaVu Sans has a capital form of, so the nested substitution covers them and a capital
	 * gets the shorter, higher form. Over a lowercase letter they stay as written, like any other.
	 */
	const COVERED = [
		'grave' => 0x0300,
		'acute' => 0x0301,
		'circumflex' => 0x0302,
		'tilde' => 0x0303,
		'dieresis' => 0x0308,
	];

	/**
	 * Marks it has no capital form of. Every one of these was drawn as the capital grave, because the
	 * subtable holds that at Coverage Index 0 and a missing index reads as 0.
	 */
	const UNCOVERED = [
		'macron' => 0x0304,
		'overline' => 0x0305,
		'hook above' => 0x0309,
		'comma above' => 0x0313,
		'double overline' => 0x033F,
	];

	/** U+0A15 GURMUKHI LETTER KA */
	const KA = '&#x0A15;';

	/** U+0A02 GURMUKHI SIGN BINDI, the glyph the nested subtable does cover */
	const BINDI = '&#x0A02;';

	/** U+0951 DEVANAGARI STRESS SIGN UDATTA, drawn above the letter */
	const UDATTA = '&#x0951;';

	/** U+0952 DEVANAGARI STRESS SIGN ANUDATTA, drawn below it */
	const ANUDATTA = '&#x0952;';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'nested-lookup-coverage';
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
		$this->mpdf->WriteHTML($this->style() . $this->markSamples() . $this->gurmukhiSamples());
	}

	/**
	 * DejaVu Sans is shipped and needs no registering. The Gurmukhi subset is 5 glyphs of Noto Sans
	 * Gurmukhi 2.005 (OFL 1.1) - the KA, the bindi, the two stress signs and the conjunct that keeps
	 * the nested lookup's first subtable at the two entries that make it index its Coverage.
	 */
	private function config()
	{
		return [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [
				'gurmukhinested' => [
					'R' => 'NotoSansGurmukhi-NestedCoverage-Subset.ttf',
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
			td.label { font-size: 9pt; color: #606060; width: 40mm; }
			td.sample { font-size: 28pt; }
			td.dejavu { font-family: dejavusans; }
			td.gurmukhi { font-family: gurmukhinested; }
		</style>

		<h1>mPDF</h1>
		<?php

		return ob_get_clean();
	}

	/**
	 * Every mark in the second table used to draw the same accent as the first row of the first.
	 */
	private function markSamples()
	{
		$html = '<h2>Combining marks after a capital</h2>'
			. '<p class="note">DejaVu Sans has a capital form of some combining marks and not of others.'
			. ' The lowercase column is the mark as written; the capital column is the form drawn over a'
			. ' capital letter. Read down the second table: each mark should be its own, not the grave of'
			. ' the first row above.</p>';

		foreach (['a capital form' => self::COVERED, 'no capital form' => self::UNCOVERED] as $heading => $marks) {
			$html .= '<h2>Marks with ' . $heading . '</h2><table>';
			foreach ($marks as $label => $mark) {
				$html .= '<tr><td class="label">' . $label . ' U+' . sprintf('%04X', $mark) . '</td>'
					. '<td class="sample dejavu">' . sprintf('&#x0041;&#x%04X;', $mark) . '</td>'
					. '<td class="sample dejavu">' . sprintf('&#x0061;&#x%04X;', $mark) . '</td></tr>';
			}
			$html .= '</table>';
		}

		return $html;
	}

	private function gurmukhiSamples()
	{
		return '<h2>Gurmukhi stress signs</h2>'
			. '<p class="note">The udatta sits above the letter and the anudatta below it. Both were given'
			. ' the bindi\'s adjustment instead - on the baseline, and to the left of where the bindi'
			. ' itself is drawn.</p>'
			. '<table>'
			. $this->gurmukhiRow('udatta U+0951 - above', self::UDATTA)
			. $this->gurmukhiRow('anudatta U+0952 - below', self::ANUDATTA)
			. $this->gurmukhiRow('bindi U+0A02 - the record they were given', self::BINDI)
			. '</table>';
	}

	private function gurmukhiRow($label, $mark)
	{
		return '<tr><td class="label">' . $label . '</td>'
			. '<td class="sample gurmukhi">' . self::KA . $mark . '</td>'
			. '<td class="sample gurmukhi">' . self::KA . '</td></tr>';
	}

	/**
	 * The font comes from the paragraph's own inline style rather than from the stylesheet, so the
	 * sample stands on its own and the run is the first thing drawn.
	 *
	 * @return string[] the glyphs the sample was drawn with, left to right
	 */
	private function glyphsOf($text)
	{
		$mpdf = new TextRecordingMpdf($this->config());
		$mpdf->WriteHTML('<p style="font-family:dejavusans">' . $text . '</p>');

		return preg_split('//u', $mpdf->drawnText[0], -1, PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * The document only shows anything if the marks the nested substitution does not cover are drawn
	 * as themselves rather than as the capital grave. The capital forms have no codepoints of their
	 * own - they are mapped into the Private Use Area as the subset is built - so the samples are
	 * compared with each other rather than named.
	 */
	public function testTheMarksWithNoCapitalFormAreNotDrawnAsTheGrave()
	{
		$grave = $this->glyphsOf('&#x0041;&#x0300;');

		foreach (self::UNCOVERED as $label => $mark) {
			$sample = $this->glyphsOf(sprintf('&#x0041;&#x%04X;', $mark));
			$written = $this->glyphsOf(sprintf('&#x0061;&#x%04X;', $mark));

			$this->assertNotSame($grave[1], $sample[1], $label . ' is drawn as the capital grave');
			$this->assertSame($written[1], $sample[1], $label . ' over a capital is not the mark written');
		}
	}

	/**
	 * The marks that do have a capital form still get it, which is what tells the guard apart from
	 * turning the rule off. Each is drawn as something other than the character written, and each as
	 * something other than the others.
	 */
	public function testTheMarksWithACapitalFormStillGetIt()
	{
		$drawn = [];
		foreach (self::COVERED as $label => $mark) {
			$sample = $this->glyphsOf(sprintf('&#x0041;&#x%04X;', $mark));
			$written = $this->glyphsOf(sprintf('&#x0061;&#x%04X;', $mark));

			$this->assertNotSame($written[1], $sample[1], $label . ' was not given its capital form');
			$drawn[] = $sample[1];
		}

		$this->assertSame($drawn, array_unique($drawn), 'two capital forms are drawn as the same glyph');
	}
}
