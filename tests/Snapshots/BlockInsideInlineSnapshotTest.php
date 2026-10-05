<?php

namespace Snapshots;

/**
 * A block opened inside inline elements, one case per caption, under the standard cascade: the block and the text
 * after it take the inline elements' style, and a link around a block keeps its colour and underline on the block
 *
 * @group snapshot
 */
class BlockInsideInlineSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-inside-inline';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			body { font-size: 10pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 5mm 0 1mm 0; font-size: 8pt; color: #606060; font-weight: normal; font-style: normal; text-decoration: none; }
			.a { color: #008000; font-weight: bold; }
			.big { font-size: 16pt; color: #7030a0; }
			td { border: 0.2mm solid #999999; padding: 1mm 2mm; }
		</style>

		<h1>A block inside an inline element</h1>

		<p class="caption">A div inside a green, bold span. All three lines are green and bold.</p>
		<span class="a">Before the div<div>Inside the div</div>After the div</span>

		<p class="caption">A div inside a link. All three lines are blue, underlined and link to example.com.</p>
		<a href="https://example.com/">Before the div<div>Inside the div</div>After the div</a>

		<p class="caption">A div inside an i inside a b. The text inside the i is bold italic, "after the i" is bold and "after the b" is plain.</p>
		<b><i>Before the div<div>Inside the div</div>After the div</i> after the i</b> after the b

		<p class="caption">Two divs in a row inside a green, bold span. The four lines in the span are green and bold; "Outside the span" is plain.</p>
		<span class="a">Before<div>First div</div><div>Second div</div>After</span>
		<div>Outside the span</div>

		<p class="caption">A div in a 16pt purple span, with font-size: 50%. It is 8pt and purple; the lines around it are 16pt.</p>
		<span class="big">Before the div<div style="font-size: 50%">Half the span's size</div>After the div</span>

		<p class="caption">Text, then a green span holding a div, on one line. "Plain text" is black; the rest is green and bold.</p>
		<div>Plain text <span class="a">in the span<div>Inside the div</div>After the div</span></div>

		<p class="caption">A div inside a green, bold span in a cell. The three lines are green and bold; "After the span" is plain.</p>
		<table><tr><td><span class="a">Before the div<div>Inside the div</div>After the div</span> After the span</td></tr></table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
