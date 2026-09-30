<?php

namespace Snapshots;

/**
 * A page styled the way a framework styles one, written in legacy mode, which parses and applies CSS as mPDF v7 did.
 * Each rule below that uses a selector mPDF v7 could not read would colour its text red in standard mode, and does
 * nothing here. The shorthands leave the longhands given before them in place.
 *
 * @group snapshot
 */
class LegacyCssModeSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'legacy-css-mode';
	}

	/**
	 * Each selector and shorthand under a caption saying what legacy mode draws
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1mm 0; }
			div.case { border: 0.2mm solid #ced4da; padding: 1mm 2mm; }
			div.case p { margin: 0.5mm 0; }

			.nav > li { color: #ff0000; }
			.card-title + p { color: #ff0000; }
			.card-title ~ .card-text { color: #ff0000; }
			.list-group li:first-child, .list-group li:nth-child(3) { color: #ff0000; }
			.list-group li:first-of-type { font-weight: bold; }
			a[href^="https"] { color: #ff0000; }
			p:lang(fr) { color: #ff0000; }
			.badge b { color: #ff0000; }
			.alert:not(.alert-info) { color: #ff0000; }
			:is(.btn, .badge) strong { color: #ff0000; }
			:where(.card) .muted { color: #ff0000; }

			.btn { border-top-color: #cc0000; }
			.btn { border: 0.5mm solid #0000cc; padding: 1mm 2mm; }
			.hero { background-repeat: no-repeat; background-position: right top; }
			.hero { background: url(img/tiger.jpg) #f0f0f0; height: 20mm; }
			.lead-wrap { line-height: 2; font-variant: small-caps; }
			.lead { font: 10pt sans-serif; }
		</style>

		<h1>Legacy mode</h1>
		<p class="caption">Written with cssMode set to legacy. No text on this page is red.</p>

		<h2>Child and sibling combinators</h2>
		<p class="caption">.nav &gt; li, .card-title + p and .card-title ~ .card-text are dropped: the items and paragraphs are the colour of the body text.</p>
		<div class="case">
			<ul class="nav"><li>Home</li><li>Docs</li></ul>
			<p class="card-title">Card title</p><p>Next paragraph</p><p class="card-text">Card text</p>
		</div>

		<h2>Structural pseudo-classes outside tables</h2>
		<p class="caption">li:first-child, li:nth-child(3) and li:first-of-type are dropped: no item is red or bold.</p>
		<div class="case">
			<ul class="list-group"><li>First</li><li>Second</li><li>Third</li></ul>
		</div>

		<h2>Attribute selectors and an inherited language</h2>
		<p class="caption">a[href^="https"] is dropped, and p:lang(fr) matches only a paragraph with a lang attribute of its own, not one that inherits it: the link keeps its default blue, and the paragraph is the colour of the body text.</p>
		<div class="case" lang="fr">
			<p><a href="https://example.com/">A secure link</a></p>
			<p>A paragraph that inherits French</p>
		</div>

		<h2>An inline ancestor, and :not(), :is() and :where()</h2>
		<p class="caption">.badge b through a span, .alert:not(.alert-info), :is(.btn, .badge) strong and :where(.card) .muted are dropped: nothing here is red.</p>
		<div class="case card">
			<p><span class="badge"><b>Badge</b> <strong>strong</strong></span></p>
			<p class="alert alert-warning">A warning alert</p>
			<p class="muted">Muted text</p>
		</div>

		<h2>Shorthands</h2>
		<p class="caption">The border shorthand leaves the red top colour written before it: the button's top border is red, the others blue. The background shorthand leaves the repeat and position written before it: the tiger is drawn once, at the top right. The font shorthand leaves the line height and the small capitals the paragraph inherits: the lead paragraph is in small capitals, with double line spacing.</p>
		<div class="case">
			<p><span class="btn">Button</span></p>
			<div class="hero"></div>
			<div class="lead-wrap"><p class="lead">A lead paragraph that runs over two lines, to show the space between them, as it inherits the line height the shorthand leaves in place.</p></div>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::LEGACY]);

		$this->mpdf->SetBasePath(__DIR__ . '/../data');
		$this->mpdf->WriteHTML($html);
	}

}
