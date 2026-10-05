<?php

namespace Snapshots;

/**
 * The CSS-wide keywords in the standard CSS mode. In each case a rule for the element's tag draws it red, and a rule
 * with the keyword, which comes after it, draws it as the caption says.
 *
 * @group snapshot
 */
class CssWideKeywordsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'css-wide-keywords';
	}

	/**
	 * One keyword per case, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { color: #0a7d32; font-size: 12pt; font-weight: bold; border: 0.4mm solid #0a7d32; padding: 1.5mm; background-color: #e3f4e8; }
			div.case p, div.case em, div.case li { color: #ff0000; font-size: 7pt; font-weight: normal; border: 0.2mm dashed #ff0000; padding: 0; background-color: #ffffff; margin: 0.5mm 0; }

			div.case .inherit-color { color: inherit; }
			div.case .unset-color { color: unset; }
			div.case .revert-color { color: revert; }
			div.case .initial-color { color: initial; }
			div.case .inherit-font { font-size: inherit; font-weight: inherit; }
			div.case .initial-font { font: initial; }
			div.case .inherit-border { border: inherit; }
			div.case .inherit-top-colour { border-top-color: inherit; }
			div.case .inherit-padding { padding: inherit; }
			div.case .inherit-background { background: inherit; }
			div.case .unset-box { border: unset; background-color: unset; }
			div.case .list-style { list-style: square inside; }
			div.case .inherit-list-style { list-style: inherit; }

			ul.author { margin: 0; }
			ul.revert { margin: revert; }
			h2.small { font-size: 8pt; margin: 0; }
			h2.revert { font-size: revert; }

			table.cells { color: #0a7d32; background-color: #fff3c4; border-spacing: 1mm; }
			table.cells tr { background-color: #e3f4e8; }
			table.cells td, table.cells th { color: #ff0000; background-color: #ffd6d6; padding: 3mm; font-weight: normal; border: 0.2mm solid #999999; }
			table.cells .inherit-color { color: inherit; }
			table.cells .inherit-background { background-color: inherit; }
			table.cells .revert-padding { padding: revert; }
			table.cells .revert-weight { font-weight: revert; }
		</style>

		<h1>The CSS-wide keywords</h1>

		<p class="caption">color: inherit, unset and revert on a paragraph in a green box: all three lines are green.</p>
		<div class="case"><p class="inherit-color">inherit</p><p class="unset-color">unset</p><p class="revert-color">revert, with no built-in colour for a paragraph</p></div>

		<p class="caption">color: initial on an emphasis in a green box: "initial" is black.</p>
		<div class="case">Green text <em class="initial-color">initial</em> and green again</div>

		<p class="caption">font-size and font-weight: inherit: the line is as large and bold as the box's text.</p>
		<div class="case"><p class="inherit-font">inherit</p></div>

		<p class="caption">font: initial: the line is in the document's default font and size, 9pt, and not bold. It stays red, as font does not set the colour.</p>
		<div class="case"><p class="initial-font">font: initial</p></div>

		<p class="caption">border: inherit: the line has the box's solid green border, not a dashed red one.</p>
		<div class="case"><p class="inherit-border">border: inherit</p></div>

		<p class="caption">border-top-color: inherit: the line's dashed border is green along the top and red on the other sides.</p>
		<div class="case"><p class="inherit-top-colour">border-top-color: inherit</p></div>

		<p class="caption">padding: inherit: the line's text sits 1.5mm inside its dashed red border.</p>
		<div class="case"><p class="inherit-padding">padding: inherit</p></div>

		<p class="caption">background: inherit: the line's background is the box's pale green, not white.</p>
		<div class="case"><p class="inherit-background">background: inherit</p></div>

		<p class="caption">border and background-color: unset, which are not inherited, so take their initial values: no border, and the box's pale green shows through.</p>
		<div class="case"><p class="unset-box">unset</p></div>

		<p class="caption">list-style: inherit on the items of a list with square markers inside: both items have square markers inside the box.</p>
		<div class="case"><ul class="list-style"><li class="inherit-list-style">first item</li><li class="inherit-list-style">second item</li></ul></div>

		<pagebreak />

		<p class="caption">margin: revert on the second list, after a rule that sets every list's margin to 0: the second list has the built-in space above and below it, the first none.</p>
		<ul class="author"><li>A list with no margin</li></ul>
		<ul class="author revert"><li>A list with its built-in margin</li></ul>
		<p>Text after the lists.</p>

		<p class="caption">font-size: revert on the second heading, after a rule that makes headings small: the second heading is its built-in size.</p>
		<h2 class="small">A small heading</h2>
		<h2 class="small revert">A heading at its built-in size</h2>

		<p class="caption">A table with green text on pale yellow, whose row is pale green. The first cell's colour is inherited from the table: green. The second cell's background is inherited from its row: pale green, not the table's yellow. The third cell's padding reverts to the thin built-in padding. The header cell's weight reverts to bold.</p>
		<table class="cells"><tr>
			<td class="inherit-color">color: inherit</td>
			<td class="inherit-background">background-color: inherit</td>
			<td class="revert-padding">padding: revert</td>
			<th class="revert-weight">font-weight: revert</th>
		</tr></table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
