<?php

namespace Snapshots;

/**
 * Descendants inherit their parent's computed values under the standard cascade, one case per caption: em spacing
 * handed on as the length it came to, inherit on an em padding, a font size three steps from its first ancestor with
 * one, the content of a positioned block drawn at line-height: normal, a bidirectional embedding opened again after a
 * block, the text before a block drawn in its own style, and a table in an inline element.
 *
 * @group snapshot
 */
class ComputedValuesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'computed-values';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: dejavusans; font-size: 10pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; letter-spacing: normal; word-spacing: normal; }
			.box { border: 0.2mm solid #999999; }
		</style>

		<h1>Descendants inherit computed values</h1>

		<div style="font-size: 20pt; letter-spacing: 0.1em">
			<p class="caption">letter-spacing: 0.1em on a 20pt div. The 10pt paragraph inside it has its letters 2pt apart, like the one set to 2pt below it, not 1pt.</p>
			<p style="font-size: 10pt">Spaced by the div's em</p>
		</div>
		<p style="letter-spacing: 2pt">Spaced by 2pt of its own</p>

		<p class="caption">word-spacing: 1em on a 20pt paragraph. The words of the 10pt span in it are as far apart as the paragraph's own.</p>
		<p style="font-size: 20pt; word-spacing: 1em">Big words <span style="font-size: 10pt">and small words in a span</span></p>

		<div style="font-size: 20pt; padding-left: 1em" class="box">
			<p class="caption">padding-left: 1em on a 20pt div, and padding-left: inherit on the 10pt paragraph in it. The paragraph's text starts two div paddings in, 14mm from the div's border.</p>
			<p style="font-size: 10pt; padding-left: inherit" class="box">Padded by what the div's em came to</p>
		</div>

		<p class="caption">font-size: 20pt, then 1.5em, 50% and 2em. The words are 30pt, 15pt and 30pt.</p>
		<div style="font-size: 20pt"><div style="font-size: 1.5em">Thirty <span style="font-size: 50%">fifteen <span style="font-size: 2em">thirty</span></span></div></div>

		<p class="caption">A span set to dir="rtl" around a block. Before and after the block, the Hebrew word is to the right of the Latin one. The block inherits the span's direction and is aligned right.</p>
		<div><span dir="rtl">&#x5e9;&#x5dc;&#x5d5;&#x5dd; before<div style="color: #606060">A block in the span</div>&#x5e9;&#x5dc;&#x5d5;&#x5dd; after</span></div>

		<p class="caption">A block in an italic, shadowed span. The words before the span are upright and unshadowed; the span's words before, in and after the block are italic and shadowed.</p>
		<div>Upright words <span style="font-style: italic; text-shadow: 0.4mm 0.4mm #6c9bd8">in the span<div>A block in the span</div>after the block</span> and upright again</div>

		<p class="caption">A table in an italic, green span. The cell and the span's words before and after the table are italic and green; the words outside the span are not.</p>
		<div>Plain words <span style="font-style: italic; color: #2e8b57">in the span<table><tr><td style="border: 0.2mm solid #999999">A cell in the span</td></tr></table>after the table</span> and plain again</div>

		<p class="caption">A positioned block at the foot of the page, and a paragraph beside it in the flow. Both have line-height: normal, and their lines are the same distance apart.</p>
		<div style="position: absolute; top: 240mm; left: 110mm; width: 80mm" class="box"><p>A positioned block<br>at line-height: normal<br>three lines</p></div>
		<div style="width: 80mm" class="box"><p>A paragraph in the flow<br>at line-height: normal<br>three lines</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}

}
