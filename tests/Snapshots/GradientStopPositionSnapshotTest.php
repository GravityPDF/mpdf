<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Gradient stops and positions written as fractional percentages or zero lengths are drawn where they say, and a stop
 * placed before an earlier one meets it in a hard edge. A repeating gradient whose period has no length is a solid
 * fill of its average colour.
 *
 * @group snapshot
 */
class GradientStopPositionSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'gradient-stop-position';
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
			'Stops' => [
				'linear-gradient(to right, red 12.5%, blue 50%)' => 'red 12.5%, blue 50%: solid red for the first eighth, fading to blue by halfway, solid blue after.',
				'linear-gradient(to right, red 20%, green, blue 80%)' => 'red 20%, green, blue 80%: stops in order are untouched, red to a fifth, green in the middle, blue from four fifths.',
				'linear-gradient(to right, red 50%, blue 0px)' => 'red 50%, blue 0px: red on the left half, blue on the right half, a hard edge at the middle.',
				'linear-gradient(to right, red 50%, blue 0%)' => 'red 50%, blue 0%: the same, a hard edge at the middle.',
				'linear-gradient(to right, red, green 60%, blue 20%, yellow)' => 'red, green 60%, blue 20%, yellow: red fading to green at 60%, a hard edge to blue there, fading to yellow at the right.',
				'radial-gradient(red 12.5%, blue 0px)' => 'radial, red 12.5%, blue 0px: a small red centre with a hard edge to blue around it.',
			],
			'Positions' => [
				'radial-gradient(at 12.5% 50%, red, blue)' => 'radial at 12.5% 50%: red an eighth of the way across, halfway down.',
				'radial-gradient(12.5% 50%, circle, red, blue)' => 'radial 12.5% 50%, circle: red an eighth of the way across, halfway down, in round rings.',
				'radial-gradient(at 0px 0px, red, blue)' => 'radial at 0px 0px: red in the top left corner.',
				'radial-gradient(0px 0px, circle, red, blue)' => 'radial 0px 0px, circle: red in the top left corner, in round rings.',
				'-moz-linear-gradient(12.5% 50%, red, blue)' => '-moz- linear 12.5% 50%: from red an eighth of the way across to blue an eighth in from the right, upright bands.',
				'linear-gradient(10px 0px, red, blue)' => 'linear 10px 0px: from red at the top left to blue at the bottom right, the bands leaning.',
			],
			'Repeating gradients with a period of no length' => [
				'repeating-linear-gradient(red 0px, blue 0px)' => 'linear, red 0px, blue 0px: solid purple, the average of red and blue.',
				'repeating-linear-gradient(red 50%, blue 0%)' => 'linear, red 50%, blue 0%: blue moves up to 50%, a period of no length again, so solid purple.',
				'repeating-radial-gradient(red 0px, blue 0px)' => 'radial, red 0px, blue 0px: solid purple.',
				'repeating-linear-gradient(to right, red 0, blue 10%)' => 'linear, red 0, blue 10%, a period with length: red to blue ten times across.',
			],
		];
	}

	/**
	 * A box for each gradient under a caption saying what it should show, a section to a page
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
		<h1>Gradient stops and positions at fractions and zero lengths, cssMode ' . $this->cssMode() . '</h1>';

		$first = true;
		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2' . ($first ? '' : ' style="page-break-before: always"') . '>' . $heading . '</h2>';
			foreach ($cases as $background => $caption) {
				$html .= '<p>' . $caption . '</p><div class="box" style="background: ' . $background . '"></div>';
			}
			$first = false;
		}

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
