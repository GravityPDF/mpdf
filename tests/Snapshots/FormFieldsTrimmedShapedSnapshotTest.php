<?php

namespace Snapshots;

/**
 * Shaped values too wide for their fields even at the smallest size, trimmed to the longest start that fits and cut
 * between whole clusters, each beside that start written on the page in the same font (#460, #458)
 *
 * @group snapshot
 */
class FormFieldsTrimmedShapedSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'form-fields-trimmed-shaped';
	}

	/**
	 * Arabic, whose first 8 characters fit though its first 7 do not, and Devanagari and Telugu, which would otherwise
	 * be cut at the virama of a conjunct, drawn into the page and then as active fields. Devanagari is left out of the
	 * active fields, as its one bundled font, FreeSerif, has characters past the BMP, which an active field refuses.
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$rows = [
			'Arabic' => ['dejavusans', 'مرحبا بالعالم', 8, '12mm'],
			'Devanagari' => ['freeserif', 'नमस्ते दुनिया', 2, '8mm'],
			'Telugu' => ['pothana2000', 'సత్యమేవ జయతే', 1, '5mm'],
		];

		$this->mpdf = $this->createMpdf(['mode' => 'utf-8']);
		$this->mpdf->WriteHTML('
			<style>
				table.fields { border-collapse: collapse; width: 100%; }
				table.fields td { padding: 2mm; border-bottom: 0.3mm solid #cccccc; vertical-align: top; }
				td.label { font-weight: bold; width: 30mm; }
				td.page { width: 45mm; font-size: 12pt; }
				input { font-size: 20pt; }
			</style>

			<h1>Shaped values trimmed to fit</h1>

			<p>Each field is 20pt and too narrow for its value even at the smallest size it is drawn at, 10pt, so the
				value loses what does not fit from its end. What it keeps is the longest start that fits once shaped,
				and it is never cut inside a cluster: the Arabic keeps 8 characters though its first 7 are wider, and
				the Devanagari and Telugu are cut before a conjunct rather than at its virama. Beside each field is the
				start it should show, written on the page in the same font.</p>
		');

		foreach (['Drawn into the page' => false, 'Active fields' => true] as $heading => $active) {
			$this->mpdf->useActiveForms = $active;

			$html = '<h2>' . $heading . '</h2><form><table class="fields"><tr><td class="label"></td><td class="page"><b>Should show</b></td><td><b>Field</b></td></tr>';
			foreach ($rows as $label => $row) {
				list($font, $value, $length, $width) = $row;
				if ($active && $font === 'freeserif') {
					continue;
				}

				$html .= '<tr>'
					. '<td class="label">' . $label . '</td>'
					. '<td class="page" style="font-family: ' . $font . '">' . mb_substr($value, 0, $length, 'UTF-8') . '</td>'
					. '<td><input type="text" name="' . strtolower($label) . '" value="' . $value . '" style="font-family: ' . $font . '; width: ' . $width . '" /></td>'
					. '</tr>';
			}

			$this->mpdf->WriteHTML($html . '</table></form>');
		}
	}
}
