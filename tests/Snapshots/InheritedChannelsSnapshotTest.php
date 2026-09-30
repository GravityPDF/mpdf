<?php

namespace Snapshots;

/**
 * An inherited property carried by each channel that used to drop it, one case per caption, under the standard
 * cascade: a block to its child blocks, a positioned block to its content, a table to its cells and a cell to a table
 * nested in it. Last, text-transform: none on an inline element.
 *
 * @group snapshot
 */
class InheritedChannelsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inherited-channels';
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
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; text-shadow: none; }
			td { border: 0.2mm solid #999999; padding: 1mm 2mm; }
		</style>

		<h1>Inherited properties reach every descendant</h1>

		<div style="text-shadow: 0.4mm 0.4mm #6c9bd8">
			<p class="caption">text-shadow on a div. The paragraph inside it has a blue shadow.</p>
			<p>A paragraph in a shadowed div</p>
		</div>

		<ul style="text-shadow: 0.4mm 0.4mm #6c9bd8">
			<li><p class="caption">text-shadow on a list. The paragraph in its item has a blue shadow.</p><p>A paragraph in a shadowed list</p></li>
		</ul>

		<p class="caption">word-spacing: 8mm on a positioned block, below. The words of the paragraph inside it are 8mm apart.</p>
		<div style="position: absolute; top: 88mm; left: 15mm; width: 180mm; word-spacing: 8mm"><p>Words spaced out in a positioned block</p></div>
		<div style="height: 16mm"></div>

		<p class="caption">text-transform: uppercase on a table. The cells are in capitals.</p>
		<table style="text-transform: uppercase"><tr><td>first cell</td><td>second cell</td></tr></table>

		<p class="caption">font-variant: small-caps on a table. The cells are in small capitals.</p>
		<table style="font-variant: small-caps"><tr><td>First cell</td><td>Second cell</td></tr></table>

		<p class="caption">text-shadow on a table. The cells have a red shadow.</p>
		<table style="text-shadow: 0.4mm 0.4mm #e07070"><tr><td>First cell</td><td>Second cell</td></tr></table>

		<p class="caption">color, font-family, font-weight and font-size on a cell. The cells of the table nested in it are red, bold, 13pt and monospaced like the cell's own text.</p>
		<table><tr><td style="color: #c00000; font-family: monospace; font-weight: bold; font-size: 13pt">
			Outer cell
			<table><tr><td>Nested cell<br>on two lines</td><td>Another nested cell</td></tr></table>
		</td></tr></table>

		<p class="caption">text-transform: none on a span in an uppercase paragraph. The words in the span stay as written, in lower case, and those around it are in capitals.</p>
		<p style="text-transform: uppercase">before the span <span style="text-transform: none">inside the span</span> after the span</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
