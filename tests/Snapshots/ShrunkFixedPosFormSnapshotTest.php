<?php

namespace Snapshots;

/**
 * Active form fields in fixed-position blocks that shrink to fit their box, each beside the same block at full size.
 * The shrunk fields draw their appearance at the widget's scaled size (#457).
 *
 * @group snapshot
 */
class ShrunkFixedPosFormSnapshotTest extends Snapshot
{

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'shrunk-fixed-pos-form';
	}

	/**
	 * Each block twice: in a box it fits, and in a box too short for it, which shrinks it
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf(['useActiveForms' => true]);

		$fields = [
			'Text field' => '<input type="text" name="text" value="Typed value" size="30" />',
			'Text area' => '<textarea name="area" rows="3" cols="30">A value that wraps over more than one line of the text area.</textarea>',
			'List box' => '<select name="list" size="3"><option value="1">One</option><option value="2" selected="selected">Two, selected</option>'
				. '<option value="3">Three</option><option value="4">Four</option></select>',
			'Combo box' => '<select name="combo"><option value="1">First</option><option value="2" selected="selected">Second, selected</option></select>',
			'Buttons' => '<input type="checkbox" name="check" value="yes" checked="checked" /> Checked'
				. ' <input type="button" name="button" value="Push button" />',
		];

		$y = 20;
		foreach ($fields as $label => $field) {
			$html = '<p style="font-weight: bold; margin: 0 0 1mm 0">' . $label . '</p><form>' . $field . '</form>';

			$this->mpdf->WriteFixedPosHTML($this->rename($html, 'full'), 15, $y, 85, 40, 'auto');
			$this->mpdf->WriteFixedPosHTML($this->rename($html, 'shrunk'), 110, $y, 85, 9, 'auto');
			$this->mpdf->Rect(15, $y, 85, 40);
			$this->mpdf->Rect(110, $y, 85, 9);

			$y += 50;
		}
	}

	/**
	 * The block's fields with the suffix added to their names, as a form has one field per name
	 *
	 * @param string $html
	 * @param string $suffix
	 *
	 * @return string
	 */
	private function rename($html, $suffix)
	{
		return preg_replace('/name="(\w+)"/', 'name="$1_' . $suffix . '"', $html);
	}

}
