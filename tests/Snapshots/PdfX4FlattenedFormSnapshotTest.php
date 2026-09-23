<?php

namespace Snapshots;

/**
 * An active form in a PDF/X-4 document under PDFXauto: a field of every kind, drawn on the page as it is with
 * useActiveForms off, showing the values it was given and with nothing interactive left.
 *
 * @group snapshot
 */
class PdfX4FlattenedFormSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'pdfx4-flattened-form';
	}

	/**
	 * Writes a form with a text field, a password, a text area, a combo box, a list box with two options
	 * selected, check boxes, radio buttons, the submit, reset, script and image buttons and a hidden input,
	 * which draws nothing.
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table.fields { border-collapse: collapse; width: 100%; }
			table.fields td { padding: 2mm; border-bottom: 0.3mm solid #cccccc; vertical-align: top; }
			td.label { font-weight: bold; width: 40mm; }
			input, select, textarea { border: 0.2mm solid #404040; }
		</style>

		<h1>PDF/X-4, an active form drawn on the page</h1>

		<form action="https://example.com/submit" method="post">
			<table class="fields">
				<tr>
					<td class="label">Text</td>
					<td><input type="text" name="text" value="Typed value" size="30" onchange="app.alert(1)" /></td>
				</tr>
				<tr>
					<td class="label">Password</td>
					<td><input type="password" name="password" value="secret" size="20" /></td>
				</tr>
				<tr>
					<td class="label">Text area</td>
					<td><textarea name="textarea" rows="3" cols="40">Two lines of text,
in a text area three rows tall.</textarea></td>
				</tr>
				<tr>
					<td class="label">Combo box</td>
					<td>
						<select name="combo">
							<option value="1">First option</option>
							<option value="2" selected="selected">Chosen option</option>
							<option value="3">Third option</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="label">List box</td>
					<td>
						<select name="list" size="4" multiple="multiple">
							<option>Apple</option>
							<option selected="selected">Banana</option>
							<option>Cherry</option>
							<option selected="selected">Damson</option>
							<option>Elder</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="label">Check boxes</td>
					<td>
						<input type="checkbox" name="checked" value="1" checked="checked" /> Checked
						<input type="checkbox" name="unchecked" value="1" /> Not checked
					</td>
				</tr>
				<tr>
					<td class="label">Radio buttons</td>
					<td>
						<input type="radio" name="radio" value="yes" checked="checked" /> Yes
						<input type="radio" name="radio" value="no" /> No
					</td>
				</tr>
				<tr>
					<td class="label">Buttons</td>
					<td>
						<input type="submit" name="submit" value="Send" />
						<input type="reset" name="reset" value="Clear" />
						<input type="button" name="button" value="Run" onclick="app.alert(2)" />
					</td>
				</tr>
				<tr>
					<td class="label">Image button</td>
					<td><input type="image" name="image" src="img/bayeux2.jpg" width="40" onclick="app.alert(3)" /></td>
				</tr>
				<tr>
					<td class="label">Hidden</td>
					<td><input type="hidden" name="hidden" value="Hidden value" />(nothing drawn)</td>
				</tr>
			</table>
		</form>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['PDFX' => '4', 'PDFXauto' => true, 'useActiveForms' => true]);
		// PDF/X needs a title, and would otherwise take the name of the file it is written to
		$this->mpdf->SetTitle('PDF/X-4, an active form drawn on the page');
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}
}
