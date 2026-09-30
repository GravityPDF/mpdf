<?php

namespace Snapshots;

/**
 * At-rules other than @media, unwrapped or removed whole so the rule after each one still applies, and @media blocks
 * nested in them.
 *
 * @group snapshot
 */
class NestedAtRuleSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'nested-at-rules';
	}

	/**
	 * Paragraphs each styled by the rule after an at-rule, or by a rule inside one, under a caption saying what should
	 * be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@charset "UTF-8";
			@namespace svg url(http://www.w3.org/2000/svg);
			@import url(missing.css);
			@layer base, theme;
			p.after-statements { color: #0a7d32; font-weight: bold; }

			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 4mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }

			@keyframes spin { from { color: #ff0000; } to { color: #ff0000; } }
			p.after-keyframes { color: #0a7d32; font-weight: bold; }
			@container (min-width: 1px) { p { color: #ff0000; } }
			p.after-container { color: #0a7d32; font-weight: bold; }
			@font-feature-values Font One { @swash { fancy: 1; } }
			p.after-font-feature-values { color: #0a7d32; font-weight: bold; }
			@page { @top-center { content: "Header"; } }
			p.after-margin-box { color: #0a7d32; font-weight: bold; }

			@supports (display: block) { p.supports { color: #1f3a93; font-style: italic; } }
			p.after-supports { color: #0a7d32; font-weight: bold; }
			@layer base { p.layer { color: #1f3a93; font-style: italic; } }
			p.after-layer { color: #0a7d32; font-weight: bold; }

			@supports not (display: block) { p.supports-not { color: #ff0000; font-size: 16pt; } }
			p.after-supports-not { color: #0a7d32; font-weight: bold; }

			@supports (display: block) {
				@media print { p.nested-print { background-color: #f9e79f; } }
				@media screen { p.nested-screen { color: #ff0000; font-size: 16pt; } }
			}
			p.after-nested { color: #0a7d32; font-weight: bold; }

			@supports (content: "}") { p.string { content: "{"; color: #1f3a93; font-style: italic; } }
			p.after-string { color: #0a7d32; font-weight: bold; }
		</style>

		<h1>At-rules other than @media</h1>

		<h2>Statement at-rules</h2>
		<p class="caption">@charset, @namespace, @import and an @layer statement start the style sheet. The paragraph is green and bold.</p>
		<div class="case">
			<p class="after-statements">After the statement at-rules</p>
		</div>

		<h2>Block at-rules mPDF does not use</h2>
		<p class="caption">@keyframes, @container, @font-feature-values and an @page margin box are removed whole. Each paragraph is green and bold; none is red.</p>
		<div class="case">
			<p class="after-keyframes">After @keyframes</p>
			<p class="after-container">After @container</p>
			<p class="after-font-feature-values">After @font-feature-values</p>
			<p class="after-margin-box">After @page with a margin box</p>
		</div>

		<h2>@supports and @layer</h2>
		<p class="caption">Their rules are unwrapped: the first and third paragraphs are blue and italic, the second and fourth green and bold.</p>
		<div class="case">
			<p class="supports">Inside @supports</p>
			<p class="after-supports">After @supports</p>
			<p class="layer">Inside @layer</p>
			<p class="after-layer">After @layer</p>
		</div>

		<h2>@supports not</h2>
		<p class="caption">The fallback for engines without the feature is dropped: the first paragraph is black at the normal size, the second green and bold.</p>
		<div class="case">
			<p class="supports-not">Inside @supports not</p>
			<p class="after-supports-not">After @supports not</p>
		</div>

		<h2>@media inside @supports</h2>
		<p class="caption">The print block applies and the screen block does not: the first paragraph has a yellow background, the second is black at the normal size, the third green and bold.</p>
		<div class="case">
			<p class="nested-print">Inside @media print</p>
			<p class="nested-screen">Inside @media screen</p>
			<p class="after-nested">After @supports</p>
		</div>

		<h2>A brace inside a string</h2>
		<p class="caption">Braces in quoted strings do not end the block: the first paragraph is blue and italic, the second green and bold.</p>
		<div class="case">
			<p class="string">Inside @supports</p>
			<p class="after-string">After @supports</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
