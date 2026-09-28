<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class SvgTextColorAlphaSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'svg-text-color-alpha';
	}

	/**
	 * Stripes for the text to be drawn over, so what shows through it can be seen
	 *
	 * @return string
	 */
	private function stripes()
	{
		return '<rect x="0" y="0" width="400" height="60" fill="#ffffff" />'
			. '<rect x="0" y="0" width="100" height="60" fill="#14386b" />'
			. '<rect x="200" y="0" width="100" height="60" fill="#f0d878" />';
	}

	/**
	 * An SVG of text drawn over the stripes
	 *
	 * @param string $attributes The attributes on the text
	 * @param string $defs Anything the text needs defined first, such as an SVG font
	 *
	 * @return string
	 */
	private function sample($attributes, $defs = '')
	{
		return '<p><svg width="400" height="60" viewBox="0 0 400 60">' . $defs . $this->stripes()
			. '<text x="10" y="46" font-size="48" ' . $attributes . '>Translucent</text>'
			. '</svg></p>';
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
		$font = '<defs><font horiz-adv-x="500"><font-face font-family="Boxes" units-per-em="1000" />'
			. '<missing-glyph horiz-adv-x="500" d="M50 0 L450 0 L450 700 L50 700 Z" /></font></defs>';

		ob_start();
		?>
		<style>
			p.caption { margin: 5mm 0 2mm 0; color: #606060; }
		</style>

		<h1>mPDF</h1>
		<h2>SVG text in an rgba() or cmyka() colour</h2>

		<p class="caption">The alpha of a fill or stroke colour is drawn on text as it is on shapes. Each line is set
			over blue, white and yellow stripes, which should show through the letters.</p>

		<p class="caption">fill="#d02020" - opaque, for comparison</p>
		<?= $this->sample('fill="#d02020"') ?>

		<p class="caption">fill="rgba(208,32,32,0.4)"</p>
		<?= $this->sample('fill="rgba(208,32,32,0.4)"') ?>

		<p class="caption">fill="cmyka(0,85,85,0,0.4)" stroke="rgba(0,0,0,0.6)"</p>
		<?= $this->sample('fill="cmyka(0,85,85,0,0.4)" stroke="rgba(0,0,0,0.6)" stroke-width="2"') ?>

		<p class="caption">fill="rgba(208,32,32,0.8)" fill-opacity="0.5" - the two multiplied, 0.4</p>
		<?= $this->sample('fill="rgba(208,32,32,0.8)" fill-opacity="0.5"') ?>

		<p class="caption">An SVG font, fill="rgba(208,32,32,0.4)" stroke="cmyka(0,0,0,100,0.6)"</p>
		<?= $this->sample('font-family="Boxes" fill="rgba(208,32,32,0.4)" stroke="cmyka(0,0,0,100,0.6)" stroke-width="2"', $font) ?>

		<p class="caption">A rect with fill="rgba(208,32,32,0.4)", which the text above should match</p>
		<p><svg width="400" height="60" viewBox="0 0 400 60"><?= $this->stripes() ?><rect x="10" y="10" width="380" height="40" fill="rgba(208,32,32,0.4)" /></svg></p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
