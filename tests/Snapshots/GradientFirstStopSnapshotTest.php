<?php

namespace Snapshots;

/**
 * A gradient whose first colour stop is written at a unitless 0 keeps that stop, in linear and radial gradients
 * alike, and a bare 0 before the stops is still the angle or position.
 *
 * @group snapshot
 */
class GradientFirstStopSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'gradient-first-stop';
	}

	/**
	 * The gradients, each with a caption saying what should be drawn
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['repeating-radial-gradient(red 0, blue 10px)', 'red 0, blue 10px, repeating radial: red and blue rings from the centre.'],
			['repeating-radial-gradient(red 0%, blue 10%)', 'red 0%, blue 10%, repeating radial: red and blue rings from the centre.'],
			['repeating-radial-gradient(circle, red 0, blue 10px)', 'circle, red 0, blue 10px: round red and blue rings from the centre.'],
			['radial-gradient(red 0, blue)', 'red 0, blue, radial: a red centre fading to blue at the edges.'],
			['linear-gradient(red 0, blue)', 'red 0, blue, linear: red at the top fading to blue at the bottom.'],
			['linear-gradient(rgb(255, 0, 0) 0, blue)', 'rgb() 0, blue, linear: red at the top fading to blue at the bottom.'],
			['repeating-linear-gradient(red 0, blue 10px)', 'red 0, blue 10px, repeating linear: red and blue bands from the top.'],
			['linear-gradient(0, red, blue)', 'a bare 0 then red, blue: red at the bottom, blue at the top.'],
			['radial-gradient(0, red, blue)', 'a bare 0 then red, blue, radial: a red centre fading to blue.'],
		];
	}

	/**
	 * A box for each gradient under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 2mm 0 0.5mm 0; }
			div.box { width: 60mm; height: 20mm; border: 0.2mm solid #999999; }
		</style>
		<h1>A first colour stop at 0</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[1] . '</p><div class="box" style="background: ' . $case[0] . '"></div>';
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
