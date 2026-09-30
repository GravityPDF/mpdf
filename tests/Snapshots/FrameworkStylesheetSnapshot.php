<?php

namespace Snapshots;

/**
 * A page styled by a stylesheet written the way frameworks ship them: a licence comment, @charset and @import, custom
 * properties, @font-face and @keyframes, @layer, utilities with escaped class names inside nested @media and @supports
 * blocks, content strings with braces and semicolons, data URIs, and comments everywhere. Written under each value of
 * cssMode, which draw it the same but for the escaped class names, which only standard matches.
 *
 * @group snapshot
 */
abstract class FrameworkStylesheetSnapshot extends Snapshot
{

	/**
	 * @return string A CssMode value
	 */
	abstract protected function mode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'framework-stylesheet-' . $this->mode();
	}

	/**
	 * The components, each under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@charset "UTF-8";
			/*! Framework v1.0.0 | MIT License | "quotes", {braces}; and semicolons */
			@import url("missing-theme.css") screen;
			@import url(missing-print.css?v=1;2) print and (min-width: 1000px);
			@layer reset, components, utilities;
			<!--
			:root { --brand: #0d6efd; --font: "Inter;var", sans-serif; --empty: { }; }
			@font-face { font-family: "Brand Icons"; src: url("brand-icons.woff2?v=3#iefix") format("woff2"), url(data:font/woff2;base64,d09GMgABAAAAAA==) format("woff2"); font-display: swap; }
			@keyframes spinner-border { to { transform: rotate(360deg) /* a full turn } */; } }
			@-webkit-keyframes pulse { 50% { opacity: .5; } }

			@layer reset {
				body { font-size: 9pt; color: #212529; /* the default text */ }
				h1, /* one */ h2 /* two */ { margin: 0 0 1mm 0; }
				h1 { font-size: 15pt; }
				h2 { font-size: 10pt; margin-top: 4mm; }
			}

			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }

			@layer components {
				/* Alerts: an icon from a data URI, and a close button drawn with a content string */
				.alert { padding: 2mm 3mm; border: 0.3mm solid #badbcc; margin-bottom: 2mm; }
				.alert-success { color: #0f5132; background-color: #d1e7dd; }
				.alert-icon { padding-left: 8mm; background-image: url(data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAA0JCgsKCA0LCgsODg0PEyAVExISEyccHhcgLikxMC4pLSwzOko+MzZGNywtQFdBRkxOUlNSMj5aYVpQYEpRUk//2wBDAQ4ODhMREyYVFSZPNS01T09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0//wAARCAAIAAgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAABAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCACkA//9k=); background-repeat: no-repeat; background-position: 2mm 50%; background-size: 4mm 4mm; }
				.btn-close::before { content: "}"; }
				.alert-dismissible { padding-right: 10mm; font-weight: bold; }

				/* Quotations, with the marks in strings */
				blockquote.quote { quotes: "\201C" "\201D" "{" "}"; color: #495057; font-style: italic; border-left: 1mm solid #dee2e6; padding-left: 3mm; margin: 0 0 2mm 0; }
				.breadcrumb-item + .breadcrumb-item::before { content: "/\00a0;"; }
				.breadcrumb-item { color: #0d6efd; }

				/* Code, in a font whose name has a semicolon in it */
				code, .font-mono { font-family: "Source Code;Pro", 'Menlo{Regular}', monospace; color: #d63384; }

				/* A theme switch that matches nothing here */
				[data-theme="dark{mode}"] .card, .card[data-x='};'] { background-color: #212529; color: #ff0000; }
				.card { border: 0.3mm solid #ced4da; padding: 2mm 3mm; margin-bottom: 2mm; }
				.card-title { font-size: 11pt; color: #0d6efd; /* after a string with a brace */ }
			}

			@layer utilities {
				.text-center { text-align: center !important; }
				.fw-bold { font-weight: bold !important; }
				.text-muted { color: #6c757d; }
				.hover\:underline:hover { text-decoration: underline; }

				@media (min-width: 640px) {
					.sm\:hidden { display: none; }
					.sm\:text-end { text-align: right; }
				}
				@media (min-width: 1024px) {
					.lg\:hidden { display: none; }
				}
				@supports (display: grid) {
					@media print {
						.print\:bg-warning { background-color: #fff3cd; }
						.md\:w-1\/2 { width: 50%; }
					}
					@media screen { .print\:bg-warning { background-color: #f8d7da; color: #ff0000; } }
				}
				@supports not (display: grid) {
					.grid-fallback { color: #ff0000; }
				}
			}
			-->
		</style>

		<h1>A framework's stylesheet</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->mode(); ?>. No text on the page is red.</p>

		<h2>Alert</h2>
		<p class="caption">Dark green bold text on light green, with a small blue square from a JPEG data URI at its left, and a border. The rule after the close button's content string applies.</p>
		<div class="alert alert-success alert-icon alert-dismissible">A saved record, from a data URI icon</div>

		<h2>Quotation and breadcrumb</h2>
		<p class="caption">The quotation is grey and italic with a grey rule at its left, and the breadcrumb items are blue: rules after strings with braces, semicolons and escapes.</p>
		<blockquote class="quote">A quotation, with its marks set in the stylesheet.</blockquote>
		<p><span class="breadcrumb-item">Home</span> <span class="breadcrumb-item">Library</span> <span class="breadcrumb-item">Data</span></p>

		<h2>Code</h2>
		<p class="caption">Pink, in a monospace font: the font names with a semicolon and braces in them are skipped for the next in the list.</p>
		<p>Call <code>render()</code>, or set <span class="font-mono">font-mono</span> on any element.</p>

		<h2>Card</h2>
		<p class="caption">A bordered card with a blue title, not white on black: the dark theme selectors, with a brace and a semicolon in their strings, match nothing here.</p>
		<div class="card" data-x="plain"><p class="card-title">Card title</p><p class="text-muted">Muted text in the card.</p></div>

		<h2>Utilities in nested @media and @supports</h2>
		<p class="caption">An A4 page is about 794px wide, so the min-width: 640px block applies and the min-width: 1024px block does not. Standard: the line marked sm:hidden is not shown, the line marked lg:hidden is; sm:text-end is right-aligned; print:bg-warning has a pale yellow background and md:w-1/2 is half the width. Legacy: escaped class names such as .sm\:hidden match nothing, so those lines are all shown, plain and left-aligned. In both, the fallback in @supports not is black, and the last line is centred and bold.</p>
		<p class="sm:hidden">sm:hidden, which should not be shown</p>
		<p class="lg:hidden">lg:hidden, which is shown</p>
		<p class="sm:text-end">sm:text-end</p>
		<p class="print:bg-warning md:w-1/2">print:bg-warning md:w-1/2</p>
		<p class="grid-fallback">grid-fallback</p>
		<p class="text-center fw-bold hover:underline">text-center fw-bold, not underlined</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);

		$this->mpdf->WriteHTML($html);
	}

}
