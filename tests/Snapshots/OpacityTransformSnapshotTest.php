<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Opacity and transform on blocks and inline elements. A block with opacity is drawn as one group, so its own
 * background does not show through its text and boxes inside it do not show through each other; a transform turns,
 * scales or moves the block about its centre or its transform-origin.
 *
 * @group snapshot
 */
class OpacityTransformSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'opacity-transform';
	}

	/**
	 * Each case under a caption saying what it should show, then a faded block that runs onto a second page
	 */
	public function generatePdf()
	{
		$outline = 'border: 0.2mm dashed #999999; height: 12mm; width: 50mm;';
		$box = 'height: 12mm; width: 50mm; background: #3366cc; color: #ffffff;';

		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1mm 0 0.5mm 0; }
		</style>
		<h1>opacity and transform</h1>
		<h2>Opacity on a block</h2>
		<p>opacity: 0.3 on a red block: pale red, with grey text that has no red behind its letters.</p>
		<div style="opacity: 0.3; background: #ff0000; color: #000000; font-size: 14pt; font-weight: bold; width: 80mm">FADED BLOCK</div>
		<p>opacity: 0.5 on a block holding a red box and a blue box that overlaps it: where they overlap is the same pale blue as the rest of the blue box.</p>
		<div style="opacity: 0.5; width: 80mm">
			<div style="background: #ff0000; height: 8mm; width: 40mm"></div>
			<div style="background: #0000ff; height: 8mm; width: 40mm; margin: -4mm 0 0 20mm"></div>
		</div>
		<p>opacity: 0.5 on a block holding two black boxes, the lower with opacity: 0.5 of its own: the upper box is mid grey, the lower a paler grey, a quarter as dark as black.</p>
		<div style="opacity: 0.5; width: 80mm"><div style="background: #000000; height: 6mm"></div><div style="opacity: 0.5; background: #000000; height: 6mm"></div></div>
		<h2>Opacity on inline elements</h2>
		<p style="font-size: 12pt">Opaque, <span style="opacity: 0.5">half <b style="opacity: 0.5">quarter</b></span> and opaque again.</p>
		<h2>Transform on a block</h2>
		<p>Each blue box is drawn over a dashed outline of where it is laid out.</p>';

		$cases = [
			['rotate(30deg)', '', 'rotate(30deg): turned clockwise about the centre of the outline.'],
			['scale(0.5)', 'left top', 'scale(0.5) with transform-origin: left top: half size, in the top left corner of the outline.'],
			['translate(60mm, 0)', '', 'translate(60mm, 0): beside the outline, to its right.'],
			['translate(60mm) rotate(-15deg) skewX(20deg)', '', 'translate(60mm) rotate(-15deg) skewX(20deg): to the right of the outline, slanted and turned anticlockwise.'],
		];

		foreach ($cases as $case) {
			$style = $box . ' transform: ' . $case[0] . ';' . ($case[1] ? ' transform-origin: ' . $case[1] . ';' : '');
			$html .= '<p style="margin-top: 14mm">' . $case[2] . '</p>
				<div style="' . $outline . ' margin-top: 18mm; margin-bottom: -12.4mm"></div>
				<div style="' . $style . '">' . $case[0] . '</div>';
		}

		$html .= '<h2 style="margin-top: 14mm">Opacity across a page break</h2>
			<p>opacity: 0.4 on a green block that runs onto the next page: pale green on both pages, its text grey.</p>
			<div style="opacity: 0.4; background: #00aa00; color: #000000; padding: 2mm">'
			. str_repeat('<p>A line of the faded block.</p>', 60) . '</div>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
