<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A blurred text-shadow fades out from the glyphs over the blur radius, as translucent copies of the text stroked at
 * growing widths, and a shadow without blur stays one sharp copy
 *
 * @group snapshot
 */
class TextShadowBlurSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'text-shadow-blur';
	}

	/**
	 * The shadows, each with a caption saying what it should look like
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['1mm 1mm #ff0000', 'No blur: one sharp red copy, down and to the right.'],
			['1mm 1mm 0.5mm #ff0000', '0.5mm blur: a red copy down and to the right with a narrow soft edge.'],
			['1mm 1mm 1mm #ff0000', '1mm blur: the same copy, softer and spreading further.'],
			['1mm 1mm 2mm #ff0000', '2mm blur: a soft red haze, with no hard edge anywhere.'],
			['1.5mm 1.5mm 4mm #000000', '4mm blur: a wide grey cloud around the text, reaching furthest down and to the right.'],
			['0 0 1.5mm #0000ff', 'No offset: a blue glow evenly all round the text.'],
			['1mm 1mm 1.5mm rgba(255, 0, 0, 0.4)', 'A 40% red: a paler haze than the 1mm and 2mm ones above.'],
			['-1mm -1mm #0000ff, 1mm 1mm 1.5mm #008000', 'Two shadows: a sharp blue copy up and to the left, and a soft green haze down and to the right.'],
		];
	}

	/**
	 * Each shadow on a line of large text under its caption, then a blurred shadow on a paragraph that wraps over
	 * several lines
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			p { margin: 2mm 0 1mm 0; }
			div.text { font-size: 20pt; margin: 0 0 3mm 4mm; }
		</style>
		<h1>Blurred text-shadow</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[1] . '</p><div class="text" style="text-shadow: ' . $case[0] . '">Shadowed text</div>';
		}

		$html .= '<p>A paragraph that wraps over three lines: every line has the same soft red haze, the bold word too.</p>'
			. '<div class="text" style="width: 90mm; text-shadow: 1mm 1mm 1.5mm #ff0000">A blurred shadow on text that <b>wraps</b> over several lines</div>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
