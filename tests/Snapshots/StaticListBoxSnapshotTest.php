<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class StaticListBoxSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'static-list-box';
	}

	/**
	 * Static list boxes drawn as rows of options beside a drop-down, which keeps its one option and arrow
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			td { border: 0.2mm solid #808080; padding: 2mm; vertical-align: top; }
			p.note { color: #606060; }
		</style>

		<h1>mPDF</h1>
		<h2>Static list boxes</h2>

		<p class="note">With forms inactive, a select that is multiple or two or more rows tall is drawn
			as a list box: a row for each option that fits, the selected ones highlighted, and no arrow.
			It is as wide as its widest option.</p>

		<form>
			<h3>Multiple, two selected</h3>

			<p>Fruit <select name="fruit" size="4" multiple>
				<option>Apple</option>
				<option selected>Banana</option>
				<option>Cherry</option>
				<option selected>Damson</option>
				<option>Elderberry</option>
			</select> after</p>

			<h3>Scrolled to a selection below the last row</h3>

			<p>Number <select name="number" size="2">
				<option>One</option>
				<option>Two</option>
				<option>Three</option>
				<option selected>Four</option>
				<option>Five</option>
			</select> and a bare multiple, four rows tall <select name="letters" multiple>
				<option>gypsy quay</option>
				<option>jolly</option>
			</select></p>

			<h3>Beside a drop-down</h3>

			<p>List <select name="list" size="3">
				<option selected>Short</option>
				<option>A much longer option</option>
			</select> drop-down <select name="drop">
				<option selected>Short</option>
				<option>A much longer option</option>
			</select></p>

			<h3>Right to left</h3>

			<p dir="rtl" style="font-family: dejavusans"><select name="greeting" size="3">
				<option>مرحبا بالعالم</option>
				<option selected>سلام</option>
				<option>שלום עולם</option>
			</select></p>

			<h3>Disabled, and in a table</h3>

			<table>
				<tr>
					<td><select name="disabled" size="3" disabled>
						<option>First</option>
						<option selected>Second</option>
					</select></td>
					<td><select name="cell" size="3">
						<option>Table one</option>
						<option selected>Table two</option>
						<option>Table three</option>
					</select></td>
				</tr>
			</table>
		</form>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['useActiveForms' => false]);
		$this->mpdf->WriteHTML($html);
	}
}
