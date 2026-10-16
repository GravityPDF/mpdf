<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class SvgNestedViewportSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'svg-nested-viewport';
	}

	/**
	 * A 120px square SVG on grey, drawn 96px square, holding the nested SVG, with the nested viewport's box outlined in red on top
	 *
	 * @param string $nested The nested <svg> element
	 * @param string $box    x, y, width and height of the nested viewport, to outline it
	 *
	 * @return string An img of the SVG
	 */
	private function sample($nested, $box)
	{
		list($x, $y, $w, $h) = explode(' ', $box);

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
			. '<rect width="120" height="120" fill="#dddddd" />'
			. $nested
			. '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" fill="none" stroke="#d02020" stroke-width="1" stroke-dasharray="3 2" />'
			. '</svg>';

		return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="96" /> ';
	}

	/**
	 * A 10 by 10 picture to fit into a viewBox: a blue square with a yellow circle in it
	 *
	 * @return string
	 */
	private function badge()
	{
		return '<rect width="10" height="10" fill="#14386b" /><circle cx="5" cy="5" r="4" fill="#f0d878" />';
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
		$big = '<rect width="120" height="120" fill="#14386b" /><circle cx="60" cy="60" r="50" fill="#f0d878" />';

		ob_start();
		?>
		<style>
			p { margin: 0; }
			p.caption { margin: 4mm 0 1.5mm 0; color: #606060; }
		</style>

		<h1>mPDF</h1>
		<h2>An &lt;svg&gt; inside an SVG</h2>

		<p class="caption">A nested &lt;svg&gt; sets up a viewport of its own: moved by x and y, clipped to its width
			and height, with its viewBox fitted into it. Each picture is 120px square on grey, and the nested
			viewport is outlined with red dashes.</p>

		<p class="caption">viewBox scaling - a 10 by 10 viewBox in a 60px viewport, top left; then the same at
			x="30" y="30" with width and height of 75%</p>
		<p>
			<?= $this->sample('<svg width="60" height="60" viewBox="0 0 10 10">' . $this->badge() . '</svg>', '0 0 60 60') ?>
			<?= $this->sample('<svg x="30" y="30" width="75%" height="75%" viewBox="0 0 10 10">' . $this->badge() . '</svg>', '30 30 90 90') ?>
		</p>

		<p class="caption">Clipping without a viewBox - a 120px picture in a 60px viewport shows its top left
			quarter; with overflow="visible" it is all drawn</p>
		<p>
			<?= $this->sample('<svg width="60" height="60">' . $big . '</svg>', '0 0 60 60') ?>
			<?= $this->sample('<svg width="60" height="60" overflow="visible">' . $big . '</svg>', '0 0 60 60') ?>
		</p>

		<p class="caption">x and y offset - x="60" y="60", x="60" y="0", and x="0" y="60", each 60px square</p>
		<p>
			<?= $this->sample('<svg x="60" y="60" width="60" height="60" viewBox="0 0 10 10">' . $this->badge() . '</svg>', '60 60 60 60') ?>
			<?= $this->sample('<svg x="60" width="60" height="60" viewBox="0 0 10 10">' . $this->badge() . '</svg>', '60 0 60 60') ?>
			<?= $this->sample('<svg y="60" width="60" height="60" viewBox="0 0 10 10">' . $this->badge() . '</svg>', '0 60 60 60') ?>
		</p>

		<p class="caption">preserveAspectRatio in a 120 by 60 viewport at y="30" - xMinYMid meet, xMidYMid meet
			(the default), xMaxYMid meet, xMidYMid slice, none</p>
		<p>
			<?php foreach (['xMinYMid meet', 'xMidYMid meet', 'xMaxYMid meet', 'xMidYMid slice', 'none'] as $par) { ?>
				<?= $this->sample('<svg y="30" width="120" height="60" viewBox="0 0 10 10" preserveAspectRatio="' . $par . '">' . $this->badge() . '</svg>', '0 30 120 60') ?>
			<?php } ?>
		</p>

		<p class="caption">Two levels of nesting - a viewport at 20,20 with an 8 by 8 viewBox; inside it a 4 by 4
			viewport at 2,2 holding a 20 by 20 red square, clipped to 4 by 4; a green square after the inner
			&lt;svg&gt; is drawn in the outer viewport again</p>
		<p>
			<?= $this->sample(
				'<svg x="20" y="20" width="80" height="80" viewBox="0 0 8 8">'
				. '<rect width="8" height="8" fill="#14386b" />'
				. '<svg x="2" y="2" width="4" height="4"><rect width="20" height="20" fill="#d02020" /></svg>'
				. '<rect x="6" y="6" width="2" height="2" fill="#2a8a2a" />'
				. '</svg>',
				'20 20 80 80'
			) ?>
		</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
