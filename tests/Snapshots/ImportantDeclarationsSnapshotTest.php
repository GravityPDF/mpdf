<?php

namespace Snapshots;

/**
 * Declarations marked !important under the standard cascade: after the inline style, those of the document's
 * stylesheets by specificity and then position, then those of the inline style, then those of the default
 * stylesheet. Each case sets one declaration against another. The one that wins colours its text green; the red one
 * is the one that would win without the flag.
 *
 * @group snapshot
 */
class ImportantDeclarationsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'important-declarations';
	}

	/**
	 * One competing pair of declarations per case, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }
			div.case p { margin: 0.5mm 0; }

			.c1 { color: #0a7d32 !important; }
			#c1 { color: #ff0000; }

			.c2 { color: #0a7d32 !important; }

			#c3 { color: #ff0000 !important; }

			.c4 { color: #0a7d32 !important; color: #ff0000; }

			.c5.win { color: #0a7d32 !important; }
			p.c5 { color: #ff0000 !important; }

			.c6b { color: #ff0000 !important; }
			.c6a { color: #0a7d32 !important; }
			.c6b { color: #ff0000; }

			@media print { .c7 { color: #0a7d32 !important; } }
			@media screen { .c7 { color: #0000ff !important; } }
			#c7 { color: #ff0000; }

			.c8 { color: #0a7d32 ! IMPORTANT; }
			#c8 { color: #ff0000; }

			div.c9 { border: 1mm solid #0a7d32 !important; border-top-color: #ff0000; padding: 1mm 2mm; }
			#c9 { border-left: 3mm dotted #ff0000; border-bottom-color: #ff0000; }

			.c10 { background: #b8e6c4 !important; padding: 1mm 2mm; }
			#c10 { background-color: #ff0000; }

			.c11 { font: 13pt serif !important; }
			#c11 { font-size: 7pt; font-weight: bold; color: #0a7d32; }

			table.c12 { border-collapse: collapse; }
			table.c12 td { border: 0.3mm solid #ff0000; padding: 1mm 3mm; }
			td.win { border: 0.8mm solid #0a7d32 !important; }
			#c12 { border: 0.3mm solid #ff0000; }

			p.ua { color: #ff0000 !important; }
		</style>

		<h1>!important</h1>

		<p class="caption">.class !important beats #id: the line is green.</p>
		<div class="case"><p id="c1" class="c1">An important class against an id</p></div>

		<p class="caption">.class !important beats the inline style: the line is green.</p>
		<div class="case"><p class="c2" style="color: #ff0000">An important class against the inline style</p></div>

		<p class="caption">An inline !important beats #id !important: the line is green.</p>
		<div class="case"><p id="c3" style="color: #0a7d32 !important">An important inline style against an important id</p></div>

		<p class="caption">An !important declaration beats one written after it in the same block: the line is green.</p>
		<div class="case"><p class="c4">An important declaration against a later one</p></div>

		<p class="caption">Of two !important rules, .class.class beats p.class: the line is green.</p>
		<div class="case"><p class="c5 win">Two important rules of different specificity</p></div>

		<p class="caption">Of two !important rules as specific, the one written later wins, and a plain rule after both does not: the line is green.</p>
		<div class="case"><p class="c6a c6b">Two important rules as specific</p></div>

		<p class="caption">An !important rule inside @media print beats #id, and one inside @media screen is left out: the line is green.</p>
		<div class="case"><p id="c7" class="c7">An important rule inside @media</p></div>

		<p class="caption">The flag in capitals, spaced from the bang, still counts: the line is green.</p>
		<div class="case"><p id="c8" class="c8">! IMPORTANT</p></div>

		<p class="caption">border: … !important expands into sides that are all important: a solid green border of even width on every side, no red, no dots.</p>
		<div id="c9" class="c9">An important border shorthand</div>

		<p class="caption">background: … !important beats #id's background-color: a pale green background.</p>
		<div id="c10" class="c10">An important background shorthand</div>

		<p class="caption">font: 13pt serif !important sets the size over #id's 7pt, and resets its bold: the line is large, serif and not bold.</p>
		<div class="case"><p id="c11" class="c11">An important font shorthand</p></div>

		<p class="caption">td.class !important beats td#id on a collapsed border: the middle cell has a thick green border, drawn over its neighbours' thin red ones.</p>
		<table class="c12"><tr><td>Left</td><td id="c12" class="win">Middle</td><td>Right</td></tr></table>

		<p class="caption">The default stylesheet's p.ua { color: green !important } beats the document's p.ua !important and an inline !important: the line is green.</p>
		<div class="case"><p class="ua" style="color: #ff0000 !important">An important rule of the default stylesheet</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD, 'defaultCssFile' => __DIR__ . '/../data/css/important-default.css']);

		$this->mpdf->WriteHTML($html);
	}

}
