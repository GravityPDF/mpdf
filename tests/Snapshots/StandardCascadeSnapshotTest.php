<?php

namespace Snapshots;

/**
 * The standard cascade: rules that compete for an element applied by specificity and then by the order they are
 * written in, after the built-in defaults and the presentational attributes and before the inline style. Each case
 * sets one rule against another. The rule that wins colours its text green; the red rule is the one the legacy
 * cascade lets win, where the two differ.
 *
 * @group snapshot
 */
class StandardCascadeSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'standard-cascade';
	}

	/**
	 * One competing pair of rules per case, under a caption saying what should be seen
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

			#c1 { color: #0a7d32; }
			p.c1 { color: #ff0000; }

			#c2 { color: #0a7d32; }
			div.c2 div p { color: #ff0000; }

			body p.c3 { color: #ff0000; }
			.c3.win { color: #0a7d32; }

			.c4 p { color: #0a7d32; }
			div div div p { color: #ff0000; }

			.c5b { color: #ff0000; }
			.c5a { color: #0a7d32; }

			.c6a { color: #ff0000; }
			.c6b { color: #ff0000; }
			.c6a { color: #0a7d32; }

			.c7b { color: #ff0000; }

			:where(#c8) p { color: #ff0000; }
			div.c8 p { color: #0a7d32; }

			#c9 { color: #ff0000; }

			ul.c10 { margin: 0 0 4mm 0; }

			table.c12 { border-collapse: collapse; }
			table.c12 tr td { border: 0.3mm solid #ff0000; padding: 1mm 3mm; }
			td#win { border: 0.8mm solid #0a7d32; }
		</style>
		<style>
			.c7a { color: #0a7d32; }
		</style>

		<h1>The standard cascade</h1>

		<p class="caption">#id beats p.class: the line is green.</p>
		<div class="case"><p id="c1" class="c1">An id against a tag with a class</p></div>

		<p class="caption">#id beats div.class div p: the line is green.</p>
		<div class="case c2"><div><p id="c2">An id against a descendant rule</p></div></div>

		<p class="caption">.class.class beats body p.class: the line is green.</p>
		<div class="case"><p class="c3 win">Two classes against body and a tag with a class</p></div>

		<p class="caption">.class p beats div div div p, although the divs are nearer: the line is green.</p>
		<div class="case c4"><div><div><p>A class ancestor against three tag ancestors</p></div></div></div>

		<p class="caption">.c5a is written after .c5b, so it wins whichever order the classes are in: both lines are green.</p>
		<div class="case"><p class="c5a c5b">Classes a then b</p><p class="c5b c5a">Classes b then a</p></div>

		<p class="caption">.c6a, then .c6b, then .c6a again: the second .c6a comes last, and the line is green.</p>
		<div class="case"><p class="c6a c6b">A selector written twice</p></div>

		<p class="caption">A rule in a later style block beats one as heavy in an earlier block: the line is green.</p>
		<div class="case"><p class="c7a c7b">A rule in a later stylesheet</p></div>

		<p class="caption">:where() counts nothing, so div.class p beats :where(#id) p: the line is green.</p>
		<div class="case c8" id="c8"><p>:where() against a class</p></div>

		<p class="caption">The inline style beats #id: the line is green.</p>
		<div class="case"><p id="c9" style="color: #0a7d32">Inline style against an id</p></div>

		<p class="caption">ul.class beats the default stylesheet's ul ul, which is the user agent's: the nested list keeps the 4mm gap below it, so "Outer two" is well below "Inner two".</p>
		<div class="case">
			<ul class="c10"><li>Outer one<ul class="c10"><li>Inner one</li><li>Inner two</li></ul></li><li>Outer two</li></ul>
		</div>

		<p class="caption">&lt;hr color&gt; beats the grey of the built-in defaults: the rule is green.</p>
		<div class="case"><hr color="#0a7d32" /></div>

		<p class="caption">td#id beats table.class tr td: the middle cell has a thick green border, drawn over its neighbours' thin red ones.</p>
		<table class="c12"><tr><td>Left</td><td id="win">Middle</td><td>Right</td></tr></table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssCascade' => 'standard']);

		$this->mpdf->WriteHTML($html);
	}

}
