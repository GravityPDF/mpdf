<?php

namespace Snapshots;

/**
 * currentColor, and the borders and shadows that name no colour and so take it, one case under each caption, in the
 * standard CSS mode. Colours that convert to nothing draw nothing: transparent and hidden borders, transparent text
 * and a transparent shadow.
 *
 * @group snapshot
 */
class CurrentColorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'current-color';
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
			body { font-family: sans-serif; font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { padding: 1mm 2mm; }
		</style>

		<h1>currentColor, and colours that draw nothing</h1>

		<p class="caption">A border in currentColor is green, the colour of the text.</p>
		<div class="case" style="color: #0a7d32; border: 0.6mm solid currentColor">Green text in a green border</div>

		<p class="caption">A border that names no colour is green too.</p>
		<div class="case" style="color: #0a7d32; border: 0.6mm solid">Green text in a green border</div>

		<p class="caption">A dashed border with no colour on a block inside a blue one is blue.</p>
		<div style="color: #1f3a93"><div class="case" style="border: 0.6mm dashed">Blue text in a blue dashed border</div></div>

		<p class="caption">A span's border with no colour is red, around the red word only.</p>
		<p>Black text with <span style="color: #b03a2e; border: 0.4mm solid">a red boxed word</span> in it.</p>

		<p class="caption">The cells of a purple table have purple borders that name no colour.</p>
		<table style="color: #6c3483; border-collapse: collapse"><tr><td style="border: 0.4mm solid; padding: 1mm 3mm">First cell</td><td style="border: 0.4mm solid; padding: 1mm 3mm">Second cell</td></tr></table>

		<p class="caption">The items of an amber list are underlined in amber by a border-bottom with no colour.</p>
		<ul style="color: #b9770e"><li style="border-bottom: 0.3mm solid">First item</li><li style="border-bottom: 0.3mm solid">Second item</li></ul>

		<p class="caption">An image in green text has a green frame that names no colour.</p>
		<p style="color: #0a7d32">An image <img style="border: 0.6mm solid" width="8mm" height="8mm" src="data:image/gif;base64,R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs="> in green text.</p>

		<p class="caption">A transparent border takes its width and draws nothing: only the red bottom side shows, and the text is set in 3mm from the left.</p>
		<div style="border: 3mm solid transparent; border-bottom-color: #c0392b">Text inside a transparent border</div>

		<p class="caption">A hidden border draws nothing, not even a hairline.</p>
		<div style="border: 3mm hidden #3366cc">Text inside a hidden border</div>

		<p class="caption">Transparent text takes its place and is not seen: the brackets hold a gap.</p>
		<p>Between the brackets [<span style="color: transparent">these words are hidden</span>] there is only space.</p>

		<p class="caption">A box-shadow with no colour is green, and a text-shadow with no colour is red.</p>
		<div class="case" style="color: #0a7d32; box-shadow: 1.5mm 1.5mm; background-color: #ffffff; border: 0.2mm solid #999999">A block with a green shadow</div>
		<p style="color: #c0392b; font-size: 14pt; text-shadow: 0.6mm 0.6mm">Red text with a red shadow</p>

		<p class="caption">A transparent text-shadow draws nothing: only the blue shadow shows.</p>
		<p style="font-size: 14pt; text-shadow: 0.6mm 0.6mm transparent, 1.2mm 1.2mm #1f3a93">Black text with a blue shadow</p>

		<p class="caption">A background in currentColor is peach, the block's colour, behind black text.</p>
		<div class="case" style="color: #f5cba7; background-color: currentColor"><span style="color: #000000">Black text on peach</span></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
