<?php

namespace Snapshots;

/**
 * Active form fields whose appearances show text that has to be shaped or reordered, each beside the same text written
 * on the page in the same font, which it should match (#408)
 *
 * @group snapshot
 */
class FormFieldsShapedSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'form-fields-shaped';
	}

	/**
	 * Arabic, Arabic with vowel marks, Hebrew, a value mixing directions, Kannada and Thai in text fields; a value
	 * shrunk to fit; wrapped and auto-sized text areas; and a combo box, a list box and a button
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$rows = [
			'Arabic' => ['dejavusans', 'مرحبا بالعالم', '<input type="text" name="arabic" value="مرحبا بالعالم" style="font-family: %1$s; width: 60mm" />'],
			'Arabic, right-aligned' => ['dejavusans', 'مرحبا بالعالم', '<input type="text" name="arabic_right" value="مرحبا بالعالم" style="font-family: %1$s; width: 60mm; text-align: right" />'],
			'Arabic with marks' => ['xbriyaz', 'بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ', '<input type="text" name="marks" value="بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ" style="font-family: %1$s; width: 60mm" />'],
			'Hebrew' => ['dejavusans', 'שלום עולם', '<input type="text" name="hebrew" value="שלום עולם" style="font-family: %1$s; width: 60mm" />'],
			'Mixed directions' => ['dejavusans', 'Order 1234 שלום עולם', '<input type="text" name="mixed" value="Order 1234 שלום עולם" style="font-family: %1$s; width: 60mm" />'],
			'Kannada' => ['lohitkannada', 'ಕನ್ನಡ ಭಾಷೆ', '<input type="text" name="kannada" value="ಕನ್ನಡ ಭಾಷೆ" style="font-family: %1$s; width: 60mm" />'],
			'Thai' => ['garuda', 'สวัสดีครับ ที่นี่', '<input type="text" name="thai" value="สวัสดีครับ ที่นี่" style="font-family: %1$s; width: 60mm" />'],
			'Shrunk to fit' => ['dejavusans', 'مرحبا بالعالم', '<input type="text" name="shrunk" value="مرحبا بالعالم" style="font-family: %1$s; width: 15mm" />'],
			'Text area' => ['dejavusans', 'مرحبا بالعالم، هذا نص أطول يلتف على عدة أسطر داخل الحقل', '<textarea name="area" rows="3" style="font-family: %1$s; width: 40mm">مرحبا بالعالم، هذا نص أطول يلتف على عدة أسطر داخل الحقل</textarea>'],
			'Auto-sized text area' => ['dejavusans', '', '<textarea name="auto" rows="3" style="font-family: %1$s; width: 60mm; font-size: auto">' . str_repeat('A few more words to fit ', 12) . '</textarea>'],
			'Combo box' => ['dejavusans', 'שלום עולם', '<select name="combo" style="font-family: %1$s"><option value="1">Hello</option><option value="2" selected="selected">שלום עולם</option></select>'],
			'List box' => ['dejavusans', 'مرحبا / שלום', '<select name="list" size="3" style="font-family: %1$s"><option value="1">مرحبا بالعالم</option><option value="2" selected="selected">שלום עולם</option><option value="3">Hello</option></select>'],
			'Button' => ['garuda', 'สวัสดี', '<input type="button" name="button" value="สวัสดี" onclick="app.alert(1)" style="font-family: %1$s" />'],
		];

		ob_start();
		?>
		<style>
			table.fields { border-collapse: collapse; width: 100%; }
			table.fields td { padding: 2mm; border-bottom: 0.3mm solid #cccccc; vertical-align: top; }
			td.label { font-weight: bold; width: 35mm; }
			td.page { width: 50mm; }
		</style>

		<h1>Shaped text in active form fields</h1>

		<p>Each field's appearance is drawn by mPDF. Beside it is the same text written on the page in the same font, which
			the field should match: Arabic joined, right-to-left text in visual order, and Kannada and Thai marks in
			place.</p>

		<form>
			<table class="fields">
				<tr>
					<td class="label"></td>
					<td class="page"><b>On the page</b></td>
					<td><b>Active field</b></td>
				</tr>
				<?php foreach ($rows as $label => $row) { ?>
				<tr>
					<td class="label"><?php echo $label; ?></td>
					<td class="page" style="font-family: <?php echo $row[0]; ?>"><?php echo $row[1]; ?></td>
					<td><?php echo sprintf($row[2], $row[0]); ?></td>
				</tr>
				<?php } ?>
			</table>
		</form>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['mode' => 'utf-8', 'useActiveForms' => true]);
		$this->mpdf->WriteHTML($html);
	}
}
