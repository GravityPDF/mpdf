<?php

namespace Snapshots;

/**
 * A page styled the way a CSS framework styles one, with utility classes marked !important, as Bootstrap writes them,
 * fighting component rules, ids and inline styles. Written under each value of cssMode, with captions saying what
 * each cascade draws.
 *
 * @group snapshot
 */
abstract class FrameworkImportantSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cascade();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'framework-important-' . $this->cascade();
	}

	/**
	 * The components, each under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			/* Base */
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 1mm 0; color: #000000; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }

			/* Components */
			#sidebar p { color: #6c757d; font-weight: bold; }
			.card { border: 0.3mm solid #ced4da; background-color: #f8f9fa; padding: 0 3mm; margin-bottom: 2mm; }
			.card p { margin: 4mm 0; background-color: #e9ecef; }
			.alert { padding: 2mm 3mm; margin-bottom: 2mm; border: 0.3mm solid #badbcc; color: #0f5132; background-color: #d1e7dd; }
			#promo { display: block; }
			#notice { border: 1mm solid #842029; background-color: #f8d7da; color: #842029; }
			.btn { padding: 1mm 3mm; color: #ffffff; background-color: #0d6efd; }
			#checkout .btn { background-color: #6f42c1; }
			table.table { border-collapse: collapse; width: 100%; }
			.table td, .table th { padding: 1mm 2mm; border-bottom: 0.2mm solid #dee2e6; text-align: left; }

			/* Utilities, last and marked !important as Bootstrap writes them */
			.text-success { color: #198754 !important; }
			.text-primary { color: #0d6efd !important; }
			.text-muted { color: #6c757d !important; }
			.fw-normal { font-weight: normal !important; }
			.m-0 { margin: 0 !important; }
			.d-none { display: none !important; }
			.border-0 { border: 0 !important; }
			.bg-light { background: #f8f9fa !important; }
			.bg-success { background-color: #198754 !important; }
			.text-end { text-align: right !important; }
		</style>

		<h1>Framework utilities marked !important</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>

		<h2>A utility against an id</h2>
		<p class="caption">.text-success and .fw-normal against #sidebar p. Standard: the first line is green and not bold, the utilities beating the id. Legacy: it is grey and bold like the second, the descendant rule coming last.</p>
		<div id="sidebar"><p class="text-success fw-normal">With the utilities</p><p>Without them</p></div>

		<h2>A utility against the inline style</h2>
		<p class="caption">.text-success against style="color: grey". Standard: the first line is green. Legacy: it is grey like the second, the inline style coming last.</p>
		<p class="text-success" style="color: #6c757d">With the utility</p>
		<p style="color: #6c757d">Without it</p>

		<h2>An inline !important against a utility</h2>
		<p class="caption">style="color: green" against .text-muted, with and without !important. Both: the first line is green. Standard: the second line is grey, the utility beating the inline style that is not important. Legacy: it is green too.</p>
		<p class="text-muted" style="color: #198754 !important">Styled inline with !important</p>
		<p class="text-muted" style="color: #198754">Styled inline without it</p>

		<h2>Two utilities</h2>
		<p class="caption">.text-success and .text-primary, both !important and as specific, .text-primary written later. Standard: the line is blue, the later rule winning. Legacy: it is green, classes applying in alphabetical order.</p>
		<p class="text-primary text-success">Both colour utilities</p>

		<h2>A spacing utility against a component</h2>
		<p class="caption">.m-0 against .card p. Standard: the first paragraph sits against the top of the card, with no margin. Legacy: it has the 4mm margin of the second, the descendant rule coming last.</p>
		<div class="card"><p class="m-0">With .m-0</p><p>Without it</p></div>

		<h2>Hidden by a utility</h2>
		<p class="caption">.d-none against #promo { display: block }. Standard: only "Visible alert" is drawn. Legacy: "Hidden alert" is drawn above it, the id coming last.</p>
		<div id="promo" class="alert d-none">Hidden alert</div>
		<div class="alert">Visible alert</div>

		<h2>Border and background utilities against an id</h2>
		<p class="caption">.border-0 and .bg-light (a background shorthand) against #notice. Standard: the notice has no border and a light grey background. Legacy: it has a thick dark red border and a pink background, the id coming last.</p>
		<div id="notice" class="alert border-0 bg-light">A notice</div>

		<h2>A utility in a table</h2>
		<p class="caption">.text-end against .table td. Standard: the amounts are right-aligned. Legacy: they are left-aligned, the descendant rule coming last.</p>
		<table class="table">
			<tr><th>Item</th><th class="text-end">Amount</th></tr>
			<tr><td>Consulting</td><td class="text-end">1,200.00</td></tr>
			<tr><td>Hosting</td><td class="text-end">300.00</td></tr>
		</table>

		<h2>A utility against a component inside an id</h2>
		<p class="caption">.bg-success against #checkout .btn. Standard: the first button is green. Legacy: it is purple like the second, the descendant rule coming last.</p>
		<p id="checkout"><span class="btn bg-success">With .bg-success</span> <span class="btn">Without it</span></p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
