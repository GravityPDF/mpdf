<?php

namespace Snapshots;

/**
 * Families named by the font shorthand and <font face> that are unregistered, have spaces in their name, or come
 * after unregistered names in a list, drawn in the family named or inherited rather than the first registered font.
 *
 * @group snapshot
 */
class FontShorthandFamilySnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'font-shorthand-family';
	}

	/**
	 * Paragraphs each styled with a family, under a caption saying which font the text should be drawn in
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
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; font-family: dejavuserif; }
			div.case p { margin: 1mm 0; }

			p.unregistered { font: 14pt Roboto; }
			p.quoted { font: 14pt "DejaVu Sans Mono"; }
			p.unquoted { font: 14pt DejaVu Sans Mono; }
			p.mapped { font: 14pt Times New Roman; }
			p.list { font: italic bold 14pt/1.6 Roboto, "Segoe UI", "DejaVu Sans Mono", serif; }
			p.none { font: 14pt Roboto, "Segoe UI", Lato; }
		</style>

		<h1>Families in the font shorthand and font face</h1>
		<p class="caption">Every box sets font-family: dejavuserif. No text on this page is drawn as empty boxes.</p>

		<h2>font shorthand</h2>
		<p class="caption">An unregistered family: 14pt, in the serif font the box sets.</p>
		<div class="case"><p class="unregistered">Roboto is not installed</p></div>

		<p class="caption">A quoted name with spaces: 14pt monospace.</p>
		<div class="case"><p class="quoted">"DejaVu Sans Mono" is read whole</p></div>

		<p class="caption">An unquoted name with spaces: 14pt monospace.</p>
		<div class="case"><p class="unquoted">DejaVu Sans Mono is read whole</p></div>

		<p class="caption">Times New Roman, which mPDF maps to its serif font: 14pt serif.</p>
		<div class="case"><p class="mapped">Times New Roman is mapped</p></div>

		<p class="caption">A list whose first two names are unregistered: bold italic 14pt monospace on widely spaced lines.</p>
		<div class="case"><p class="list">Roboto and Segoe UI are skipped, so this falls back to DejaVu Sans Mono and wraps onto a second line to show the spacing</p></div>

		<p class="caption">A list with no registered name: 14pt, in the serif font the box sets.</p>
		<div class="case"><p class="none">Nothing in this list is installed</p></div>

		<h2>font face</h2>
		<p class="caption">An unregistered face: in the serif font the box sets.</p>
		<div class="case"><p><font face="Segoe UI">Segoe UI is not installed</font></p></div>

		<p class="caption">A face with spaces: monospace.</p>
		<div class="case"><p><font face="DejaVu Sans Mono">DejaVu Sans Mono is read whole</font></p></div>

		<p class="caption">A list whose first name is unregistered: monospace.</p>
		<div class="case"><p><font face="Roboto, DejaVu Sans Mono">Roboto is skipped</font></p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
