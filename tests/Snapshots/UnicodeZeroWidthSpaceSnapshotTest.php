<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class UnicodeZeroWidthSpaceSnapshotTest extends Snapshot
{
	const ZWSP = "\xe2\x80\x8b";

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'unicode-zero-width-space';
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
		ob_start();
		?>
		<style>
			p.note { color: #606060; }
		</style>

		<h1>mPDF</h1>
		<h2>A zero-width space in MultiCell() with a Unicode font</h2>

		<p class="note">Each row below writes the same words three times with MultiCell(), in DejaVu Sans, in
			boxes 40mm wide. On the left they run together with nothing between them, so the only place a line
			can end is wherever the box runs out, in the middle of a word. In the middle they are joined by
			zero-width spaces (U+200B), and on the right by ordinary spaces. The middle box has to break at the
			same places as the right one and keep every word whole, while drawing nothing between the words.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans']);
		$this->mpdf->WriteHTML($html);

		$this->mpdf->SetFont('dejavusans', '', 11);

		$this->compare('Left to right', ['Internationalisation', 'documentation', 'specification', 'configuration', 'implementation']);
		$this->compare('Right to left', ['ובאוניברסיטאות', 'הטלקומוניקציה', 'האנציקלופדיות', 'והפילוסופיה'], 'rtl');

		$this->writeOtherwise();
	}

	/**
	 * A row of three MultiCell() boxes of $words: run together, joined by zero-width spaces, and joined by spaces
	 *
	 * @param string $label
	 * @param string[] $words
	 * @param string $directionality
	 *
	 * @return void
	 */
	private function compare($label, array $words, $directionality = 'ltr')
	{
		$mpdf = $this->mpdf;
		$columns = [
			'Run together' => implode('', $words),
			'Zero-width spaces' => implode(self::ZWSP, $words),
			'Spaces' => implode(' ', $words),
		];

		$mpdf->Ln(4);
		$mpdf->SetFont('dejavusans', 'B', 11);
		$mpdf->Cell(0, 6, $label, 0, 1);
		$mpdf->SetFont('dejavusans', '', 9);

		$top = $mpdf->y;
		$x = $mpdf->lMargin;
		foreach (array_keys($columns) as $heading) {
			$mpdf->SetXY($x, $top);
			$mpdf->Cell(40, 5, $heading, 0, 0, 'C');
			$x += 45;
		}

		$mpdf->SetFont('dejavusans', '', 11);
		$mpdf->SetDirectionality($directionality);

		$bottom = $top;
		$x = $mpdf->lMargin;
		foreach ($columns as $text) {
			$mpdf->SetXY($x, $top + 6);
			$mpdf->MultiCell(40, 5, $text, 1);
			$bottom = max($bottom, $mpdf->y);
			$x += 45;
		}

		$mpdf->SetDirectionality('ltr');
		$mpdf->SetXY($mpdf->lMargin, $bottom);
	}

	/**
	 * The other ways of writing text straight to the page, none of which may draw anything for the zero-width space
	 *
	 * @return void
	 */
	private function writeOtherwise()
	{
		$mpdf = $this->mpdf;

		$mpdf->Ln(6);
		$mpdf->WriteHTML('<h3>Written other ways</h3><p class="note">WriteText(), WriteCell(), AutosizeText(), the shaded box, SVG text, the text around the circle and the textarea each hold a zero-width space between two halves, and draw nothing between them. The textarea draws in DejaVu Sans Mono, which has no glyph for U+200B and would draw a box for it. The Hebrew WriteText() on the right reads שלום then עולם from the right, in the order it was written.</p>');

		$mpdf->SetFont('dejavusans', '', 11);
		$y = $mpdf->y + 5;
		$mpdf->WriteText(15, $y, 'Write' . self::ZWSP . 'Text');

		$mpdf->SetDirectionality('rtl');
		$mpdf->WriteText(110, $y, 'שלום' . self::ZWSP . 'עולם');
		$mpdf->SetDirectionality('ltr');

		$mpdf->SetXY(15, $y + 5);
		$mpdf->WriteCell(80, 8, 'Write' . self::ZWSP . 'Cell, centred', 1, 1, 'C');
		$mpdf->Ln(6);

		$mpdf->SetX(15);
		$mpdf->AutosizeText('Autosize' . self::ZWSP . 'Text', 80, 'dejavusans', '', 24);
		$mpdf->Ln(14);

		$mpdf->Shaded_box('Shaded' . self::ZWSP . 'box', 'dejavusans', '', 14, '80mm');
		$mpdf->Ln(2);

		$mpdf->WriteHTML('<table><tr>'
			. '<td style="vertical-align: middle"><svg xmlns="http://www.w3.org/2000/svg" width="80mm" height="12mm" viewBox="0 0 300 45"><text x="0" y="30" font-family="dejavusans" font-size="28">SVG' . self::ZWSP . 'text</text></svg></td>'
			. '<td><textcircle r="16mm" top-text="Circular' . self::ZWSP . 'Text" bottom-text="Bottom' . self::ZWSP . 'Text" style="font-size: 12pt" /></td>'
			. '<td style="vertical-align: middle"><textarea name="notes" cols="14" rows="1">Zero' . self::ZWSP . 'width</textarea></td>'
			. '</tr></table>');
	}
}
