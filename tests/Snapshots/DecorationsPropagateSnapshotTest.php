<?php

namespace Snapshots;

/**
 * Text decorations propagating under the standard cascade, one case per caption: to child blocks and inline elements
 * in the colour and size of the element that set them, past text-decoration: none, stacking with a descendant's own,
 * and stopping at floats, inline blocks and tables. Last, vertical-align staying off a child block.
 *
 * @group snapshot
 */
class DecorationsPropagateSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'decorations-propagate';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 10pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; }
			td { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			a { color: #1f5fbf; text-decoration: none; }
		</style>

		<h1>Text decorations propagate</h1>

		<p class="caption">underline on a red div. The paragraph inside is blue and 16pt, and has the div's thin red underline.</p>
		<div style="color: #c00000; text-decoration: underline">
			<p style="color: #1f5fbf; font-size: 16pt">A large blue paragraph in an underlined div</p>
		</div>

		<p class="caption">A link set to text-decoration: none in an underlined red paragraph. The link is blue, with the paragraph's red underline under it.</p>
		<p style="color: #c00000; text-decoration: underline">Read the <a href="https://example.com/">terms of service</a> before you sign</p>

		<p class="caption">text-decoration: none on a paragraph in an underlined div. The paragraph is still underlined.</p>
		<div style="text-decoration: underline">
			<p style="text-decoration: none">A paragraph set to none in an underlined div</p>
		</div>

		<p class="caption">underline on a red div and line-through on a blue paragraph in it. The paragraph has a red underline and a blue line through it.</p>
		<div style="color: #c00000; text-decoration: underline">
			<p style="color: #1f5fbf; text-decoration: line-through">Underlined by the div, struck through by itself</p>
		</div>

		<p class="caption">overline on a list. Each item has a line over it.</p>
		<ul style="text-decoration: overline">
			<li>First item</li>
			<li>Second item</li>
		</ul>

		<p class="caption">A float in an underlined div. The text beside the float is underlined; the float's text, in the grey box, is not.</p>
		<div style="text-decoration: underline">
			<div style="float: right; width: 60mm; background-color: #eeeeee; padding: 1mm">Floated text, not underlined</div>
			Text in the flow, underlined
		</div>
		<div style="clear: both"></div>

		<p class="caption">An inline block in an underlined paragraph. The words around it are underlined; the inline block's are not.</p>
		<p style="text-decoration: underline">Before the inline block <span style="display: inline-block">inside the inline block</span> after it</p>

		<p class="caption">A table in an underlined div. The cells are not underlined.</p>
		<div style="text-decoration: underline">
			<table><tr><td>First cell</td><td>Second cell</td></tr></table>
		</div>

		<p class="caption">vertical-align: super on a div. The paragraph inside it sits on the baseline, not raised.</p>
		<div style="vertical-align: super">
			<p>A paragraph on the baseline</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
