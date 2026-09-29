<?php

namespace Snapshots;

/**
 * Descendant rules whose last part names a language, such as `div :lang(fr)`, `div [lang=fr]` and `div p:lang(fr)`,
 * in block content, inline content and tables, and a regional language falling back to the rule for its short code.
 *
 * @group snapshot
 */
class DescendantLangSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'descendant-lang-selector';
	}

	/**
	 * Sections each styled by descendant lang rules, under a caption saying what should be seen
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
			p.gap { margin-top: 3mm; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 1mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 1mm 3mm; }

			div.pseudo :lang(fr) { color: #0a7d32; font-weight: bold; }
			div.attribute [lang=de] { color: #1f3a93; font-style: italic; }
			div.tagged p:lang(es) { background-color: #f9e79f; }
			div.inline :lang(fr) { color: #0a7d32; font-weight: bold; }
			table.cells :lang(fr) { background-color: #aed6f1; }
			table.cells td:lang(de) { color: #1f3a93; font-weight: bold; }
			table.content td :lang(fr) { color: #0a7d32; font-weight: bold; }
			div.regional :lang(fr) { color: #0a7d32; font-weight: bold; }
			div.regional :lang(pt-br) { color: #1f3a93; font-style: italic; }
			div.regional :lang(pt) { color: #ff0000; }
			div.order .note { color: #ff0000; } div.order p:lang(fr) { color: #0a7d32; font-weight: bold; }
		</style>

		<h1>Descendant rules naming a language</h1>

		<h2>:lang()</h2>
		<p class="caption">div.pseudo :lang(fr): the French paragraph is green and bold, the English one black.</p>
		<div class="case pseudo">
			<p lang="fr">Un paragraphe en français</p>
			<p lang="en">A paragraph in English</p>
		</div>

		<h2>[lang]</h2>
		<p class="caption">div.attribute [lang=de]: the German paragraph is blue and italic, the English one black.</p>
		<div class="case attribute">
			<p lang="de">Ein Absatz auf Deutsch</p>
			<p lang="en">A paragraph in English</p>
		</div>

		<h2>A tag with :lang()</h2>
		<p class="caption">div.tagged p:lang(es): the Spanish paragraph has a yellow background, the English one none.</p>
		<div class="case tagged">
			<p lang="es">Un párrafo en español</p>
			<p lang="en">A paragraph in English</p>
		</div>

		<h2>Inline content</h2>
		<p class="caption">div.inline :lang(fr): only the French words are green and bold.</p>
		<div class="case inline">
			<p>An English sentence with <span lang="fr">quelques mots en français</span> in it.</p>
		</div>

		<h2>Table cells</h2>
		<p class="caption">table.cells :lang(fr) and table.cells td:lang(de): the French cell has a blue background, the German cell is blue and bold, the English cell is plain.</p>
		<table class="cells">
			<tr><td lang="fr">Français</td><td lang="de">Deutsch</td><td lang="en">English</td></tr>
		</table>

		<p class="caption gap">table.content td :lang(fr): the French words in the cell are green and bold.</p>
		<table class="content">
			<tr><td>English with <span lang="fr">des mots en français</span></td></tr>
		</table>

		<h2>Regional languages</h2>
		<p class="caption">fr-CA falls back to :lang(fr) and is green and bold. pt-BR is named in full and is blue and italic, not red.</p>
		<div class="case regional">
			<p lang="fr-CA">Un paragraphe en français canadien</p>
			<p lang="pt-BR">Um parágrafo em português do Brasil</p>
		</div>

		<h2>Order</h2>
		<p class="caption">div.order p:lang(fr) is applied after div.order .note: the paragraph is green and bold, not red.</p>
		<div class="case order">
			<p class="note" lang="fr">Un paragraphe en français</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
