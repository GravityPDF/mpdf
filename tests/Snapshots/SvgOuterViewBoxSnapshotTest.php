<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class SvgOuterViewBoxSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'svg-outer-viewbox';
	}

	/**
	 * An SVG with a 10 by 10 viewBox holding a blue square with a yellow circle in it, a red corner at its top left
	 * and a green one at its bottom right, drawn on its own with a red border round the image
	 *
	 * @param string $attributes The <svg> element's width, height and preserveAspectRatio
	 *
	 * @return string An img of the SVG
	 */
	private function sample($attributes)
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" ' . $attributes . '>'
			. '<rect width="10" height="10" fill="#14386b" />'
			. '<circle cx="5" cy="5" r="4" fill="#f0d878" />'
			. '<rect width="2" height="2" fill="#d02020" />'
			. '<rect x="8" y="8" width="2" height="2" fill="#2a8a2a" />'
			. '</svg>';

		return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" style="border: 1px solid #d02020; margin-right: 3mm;" />';
	}

	/**
	 * A row of samples, one for each preserveAspectRatio, all in a box of one size
	 *
	 * @param string   $size  The <svg> element's width and height
	 * @param string[] $fits  preserveAspectRatio values
	 *
	 * @return string
	 */
	private function row($size, $fits)
	{
		$html = '<p>';
		foreach ($fits as $fit) {
			$html .= $this->sample($size . ' preserveAspectRatio="' . $fit . '"') . ' ';
		}

		return $html . '</p>';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$wide = 'width="120" height="60"';
		$tall = 'width="60" height="120"';

		ob_start();
		?>
		<style>
			p { margin: 0; }
			p.caption { margin: 4mm 0 1.5mm 0; color: #606060; }
		</style>

		<h1>mPDF</h1>
		<h2>An SVG whose width and height are not the shape of its viewBox</h2>

		<p class="caption">Each picture is a 10 by 10 viewBox - a blue square with a yellow circle, a red corner top
			left and a green one bottom right - in an image outlined in red. The image is the SVG's width and
			height, and the viewBox is fitted into it as preserveAspectRatio says.</p>

		<p class="caption">120 by 60, meet - xMinYMid, xMidYMid (the default), xMaxYMid</p>
		<?= $this->row($wide, ['xMinYMid meet', 'xMidYMid meet', 'xMaxYMid meet']) ?>

		<p class="caption">120 by 60, slice - xMidYMin, xMidYMid, xMidYMax; then none, stretched</p>
		<?= $this->row($wide, ['xMidYMin slice', 'xMidYMid slice', 'xMidYMax slice', 'none']) ?>

		<p class="caption">60 by 120, meet - xMidYMin, xMidYMid, xMidYMax; slice - xMinYMid, xMaxYMid; then none</p>
		<?= $this->row($tall, ['xMidYMin meet', 'xMidYMid meet', 'xMidYMax meet', 'xMinYMid slice', 'xMaxYMid slice', 'none']) ?>

		<p class="caption">A width alone, 120, and a height alone, 60 - each a square, the viewBox's shape</p>
		<p>
			<?= $this->sample('width="120"') ?>
			<?= $this->sample('height="60"') ?>
		</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
