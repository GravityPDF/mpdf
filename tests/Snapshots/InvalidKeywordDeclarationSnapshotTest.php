<?php

namespace Snapshots;

/**
 * Declarations with a keyword the property does not take, such as width: banana, dropped so the earlier declaration
 * of the property still applies
 *
 * @group snapshot
 */
class InvalidKeywordDeclarationSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'invalid-keyword-declaration';
	}

	/**
	 * Sections each styled with a valid declaration and then one with a keyword the property does not take, under a
	 * caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.bar { background-color: #aed6f1; height: 4mm; margin-bottom: 1mm; font-size: 7pt; }
			div.reference { background-color: #f9e79f; }
			p.line { background-color: #d6eaf8; margin: 0 0 1mm 0; }

			div.w { width: 60mm; width: banana; }
			p.indent { margin-left: 40mm; margin-left: banana; }
		</style>

		<h1>Declarations with a keyword the property does not take</h1>

		<h2>width</h2>
		<p class="caption">Both blue bars are 60mm wide, as the yellow one is, not the width of the page.</p>
		<div class="bar reference" style="width: 60mm">60mm</div>
		<div class="bar" style="width: 60mm; width: banana">width: 60mm; width: banana</div>
		<div class="bar w">width: 60mm; width: banana, in a stylesheet</div>

		<h2>margin-left</h2>
		<p class="caption">Both blue lines start 40mm in, under the right end of the 40mm yellow bar.</p>
		<div class="bar reference" style="width: 40mm">40mm</div>
		<p class="line" style="margin-left: 40mm; margin-left: banana">margin-left: 40mm; margin-left: banana</p>
		<p class="line indent">margin-left: 40mm; margin-left: banana, in a stylesheet</p>

		<h2>font-size and font</h2>
		<p class="caption">Both lines are large, 20pt, rather than 9pt.</p>
		<p style="font-size: 20pt; font-size: banana">font-size: banana</p>
		<p style="font: 20pt serif; font: banana">font: banana</p>

		<h2>text-align</h2>
		<p class="caption">The line is aligned right.</p>
		<p class="line" style="text-align: right; text-align: banana">text-align: right; text-align: banana</p>

		<h2>font-weight</h2>
		<p class="caption">The line is bold.</p>
		<p style="font-weight: bold; font-weight: heavy">font-weight: bold; font-weight: heavy</p>

		<h2>display</h2>
		<p class="caption">Nothing is drawn between the two yellow bars.</p>
		<div class="bar reference" style="width: 40mm"></div>
		<p class="line" style="display: none; display: banana">display: none; display: banana - this line should not be drawn</p>
		<div class="bar reference" style="width: 40mm"></div>

		<h2>border and background</h2>
		<p class="caption">The box has a 1mm red border and a blue background, not a blue border and no background.</p>
		<div style="border: 1mm solid #ff0000; border: 2mm banana #0000ff; background: #aed6f1; background: banana; padding: 2mm">border: 2mm banana #0000ff; background: banana</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
