<?php

namespace Snapshots;

/**
 * Active submit, reset and script buttons with an empty value, no value, a value and a value of "0". A button with an
 * empty value is drawn blank, as a browser draws it, rather than captioned with its field name (#459).
 *
 * @group snapshot
 */
class EmptyButtonCaptionSnapshotTest extends Snapshot
{

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'empty-button-caption';
	}

	/**
	 * A table with a row per button type and a column per value
	 */
	public function generatePdf()
	{
		$values = [
			'value=""' => ' value=""',
			'No value' => '',
			'value="Send"' => ' value="Send"',
			'value="0"' => ' value="0"',
		];

		$html = '<style>td, th { border: 0.2mm solid #808080; padding: 2mm; }</style>'
			. '<h2>Active push buttons</h2>'
			. '<p>A button with an empty value has no caption. Its field name is never shown.</p>'
			. '<form><table><tr><th></th>';

		foreach (array_keys($values) as $label) {
			$html .= '<th>' . htmlspecialchars($label) . '</th>';
		}
		$html .= '</tr>';

		foreach (['submit', 'reset', 'button'] as $type) {
			$html .= '<tr><th>' . $type . '</th>';
			$i = 0;
			foreach ($values as $attribute) {
				$html .= '<td><input type="' . $type . '" name="' . $type . '_name_' . $i++ . '"' . $attribute . ' /></td>';
			}
			$html .= '</tr>';
		}

		$html .= '<tr><th>unnamed submit</th><td><input type="submit" value="" /></td><td><input type="submit" /></td>'
			. '<td></td><td></td></tr></table></form>';

		$this->mpdf = $this->createMpdf(['useActiveForms' => true]);
		$this->mpdf->WriteHTML($html);
	}

}
