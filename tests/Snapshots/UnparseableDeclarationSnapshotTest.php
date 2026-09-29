<?php

namespace Snapshots;

/**
 * Declarations whose value mPDF cannot read, dropped so the value they would have replaced still applies, and the
 * CSS units mPDF used to read as pixels.
 *
 * @group snapshot
 */
class UnparseableDeclarationSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'unparseable-declaration';
	}

	/**
	 * Sections each styled with a value mPDF cannot read, or with a unit it resolves, under a caption saying what
	 * should be seen
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
			div.case { border: 0.2mm solid #999999; padding: 0 2mm; }
			blockquote { background-color: #d6eaf8; }
			div.bar { background-color: #aed6f1; height: 4mm; margin-bottom: 1mm; font-size: 7pt; }
			div.reference { background-color: #f9e79f; }

			blockquote.calc { margin: calc(5mm + 5mm); }
			blockquote.var { margin: 1mm 2mm var(--gap) 4mm; }
			blockquote.comma { margin-left: 1,5mm; }

			div.green { color: #0a7d32; }
			div.green p.bogus { color: bogus; }
			div.green p.var { color: var(--brand); }
			div.green p.oklch { color: oklch(0.7 0.1 120); }
		</style>

		<h1>Declarations mPDF cannot read</h1>

		<h2>Lengths mPDF cannot read keep the default margins</h2>
		<p class="caption">Each blue box is indented 40px from both sides and has space above and below it, as the first one does with no margin declared.</p>
		<div class="case">
			<blockquote>Default margins</blockquote>
			<blockquote class="calc">margin: calc(5mm + 5mm)</blockquote>
			<blockquote class="var">margin: 1mm 2mm var(--gap) 4mm</blockquote>
			<blockquote class="comma">margin-left: 1,5mm</blockquote>
			<blockquote style="margin: min(1mm, 2mm)">margin: min(1mm, 2mm), in a style attribute</blockquote>
		</div>

		<h2>An earlier declaration still applies</h2>
		<p class="caption">Both bars are the same width as the yellow one, 60mm, rather than the width of the page.</p>
		<div class="bar reference" style="width: 60mm">60mm</div>
		<div class="bar" style="width: 60mm; width: calc(100% - 10mm)">width: 60mm; width: calc(100% - 10mm)</div>
		<div class="bar" style="width: 60mm; width: 50dvw">width: 60mm; width: 50dvw</div>

		<h2>Colours mPDF cannot read keep the inherited colour</h2>
		<p class="caption">Every line is green; none is black.</p>
		<div class="case green">
			<p>Inherited green</p>
			<p class="bogus">color: bogus</p>
			<p class="var">color: var(--brand)</p>
			<p class="oklch">color: oklch(0.7 0.1 120)</p>
			<p style="color: #zzzzzz">color: #zzzzzz, in a style attribute</p>
		</div>

		<h2>Units relative to the page, and quarter-millimetres</h2>
		<p class="caption">Each blue bar is as wide as the yellow bar above it, not a few millimetres wide.</p>
		<div class="bar reference" style="width: 105mm">105mm, half the width of the A4 page</div>
		<div class="bar" style="width: 50vw">width: 50vw</div>
		<div class="bar" style="width: 50vmin">width: 50vmin</div>
		<div class="bar reference" style="width: 148.5mm">148.5mm, half the height of the A4 page</div>
		<div class="bar" style="width: 50vh">width: 50vh</div>
		<div class="bar" style="width: 50vmax">width: 50vmax</div>
		<div class="bar reference" style="width: 100mm">100mm</div>
		<div class="bar" style="width: 400Q">width: 400Q</div>
		<p class="caption">The blue bar starts 5vw, 10.5mm, in from the yellow one.</p>
		<div class="bar reference" style="width: 40mm">No margin</div>
		<div class="bar" style="width: 40mm; margin-left: 5vw">margin-left: 5vw</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
