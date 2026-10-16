<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A unitless 0 beside another word of a gradient's position, as in "10px 0", is the coordinate 0, and the words after
 * "at" are the position. A bare 0 is still the angle of a linear gradient and leaves a radial one centred.
 *
 * @group snapshot
 */
class GradientPositionZeroSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'gradient-position-zero';
	}

	/**
	 * The CSS mode the document is drawn in
	 *
	 * @return string
	 */
	protected function cssMode()
	{
		return CssMode::STANDARD;
	}

	/**
	 * The gradients in sections by heading, each keyed by its background with a caption saying what should be drawn
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			'radial-gradient() with at' => [
				'radial-gradient(at 10px 0, red, blue)' => 'at 10px 0: red at the top edge, 10px in from the left, fading to blue.',
				'radial-gradient(at left 0, red, blue)' => 'at left 0: red in the top left corner.',
				'radial-gradient(at 100% 0, red, blue)' => 'at 100% 0: red in the top right corner.',
				'radial-gradient(at 0 100%, red, blue)' => 'at 0 100%: red in the bottom left corner.',
				'radial-gradient(at 0 50%, red, blue)' => 'at 0 50%: red halfway down the left edge.',
				'radial-gradient(ellipse farthest-corner at 0 0, red, blue)' => 'ellipse farthest-corner at 0 0: red in the top left corner.',
				'radial-gradient(circle at 75% 0, red, blue)' => 'circle at 75% 0: red at the top edge, three quarters across, in round rings.',
				'radial-gradient(circle at 10px 0, red, blue)' => 'circle at 10px 0: red at the top edge, 10px in from the left, in round rings.',
				'repeating-radial-gradient(at 10px 0, red, blue 10mm)' => 'repeating, at 10px 0: rings of red to blue every 10 mm, centred on the top edge 10px in from the left.',
			],
			'Prefixed gradients' => [
				'-moz-radial-gradient(10px 0, red, blue)' => '-moz- radial 10px 0: red at the top edge, 10px in from the left.',
				'-webkit-radial-gradient(0 100%, red, blue)' => '-webkit- radial 0 100%: red in the bottom left corner.',
				'-moz-linear-gradient(10px 0, red, blue)' => '-moz- linear 10px 0: from red at the top left to blue at the bottom right, the bands leaning, not upright.',
				'-webkit-linear-gradient(0 100%, red, blue)' => '-webkit- linear 0 100%: from red at the bottom left to blue at the top right, the bands leaning.',
				'-moz-linear-gradient(left 0, red, blue)' => '-moz- linear left 0: from red at the top left to blue at the bottom right, the bands leaning.',
			],
			'A bare 0' => [
				'linear-gradient(0, red, blue)' => 'linear: still the angle, red at the bottom, blue at the top.',
				'radial-gradient(0, red, blue)' => 'radial: still centred, a red centre fading to blue.',
			],
		];
	}

	/**
	 * A box for each gradient under a caption saying what it should show, the prefixed gradients starting a page
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 0 0; }
			p { margin: 2mm 0 0.5mm 0; }
			div.box { width: 60mm; height: 18mm; border: 0.2mm solid #999999; }
		</style>
		<h1>A gradient position ending in 0, cssMode ' . $this->cssMode() . '</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2' . ($heading === 'Prefixed gradients' ? ' style="page-break-before: always"' : '') . '>' . $heading . '</h2>';
			foreach ($cases as $background => $caption) {
				$html .= '<p>' . $caption . '</p><div class="box" style="background: ' . $background . '"></div>';
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
