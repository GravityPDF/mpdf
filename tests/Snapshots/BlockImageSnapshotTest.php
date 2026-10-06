<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * An image with display: block has a line of its own and is placed by its margins, as a browser places it: auto on
 * both sides centres it, auto on one side pushes it to the other, and text-align does not move it (#589)
 *
 * @group snapshot
 */
class BlockImageSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-image';
	}

	/**
	 * The HTML of each case under a caption saying where the image should sit. The boxes are 180mm wide and the
	 * image 20mm, so a centred image starts 80mm in and one at the right edge 160mm in.
	 *
	 * @param string $image The image's path
	 *
	 * @return array[] Caption and HTML
	 */
	protected function cases($image)
	{
		$img = '<img src="' . $image . '" style="width: 20mm; %s">';

		return [
			['display: block; margin: 0 auto: the image is centred, 80mm from the left edge of the box.', sprintf($img, 'display: block; margin: 0 auto')],
			['display: block; margin-left: auto: the image is at the right edge of the box.', sprintf($img, 'display: block; margin-left: auto')],
			['A block image between text: "before" on a line of its own, the image centred on the next, "after" on the line below it.', '<p>before ' . sprintf($img, 'display: block; margin: 0 auto') . ' after</p>'],
			['An inline image in a text-align: center block, for contrast: centred by the text alignment, with the text beside it.', '<div style="text-align: center">before ' . sprintf($img, '') . ' after</div>'],
			['A block image in a right-to-left block, with no auto margin: at the right edge of the box.', '<div dir="rtl">' . sprintf($img, 'display: block') . '</div>'],
			['display: block; margin: 0 auto with 2mm padding and a 1mm red border: the border box is centred, the picture inside it.', sprintf($img, 'display: block; margin: 0 auto; padding: 2mm; border: 1mm solid red')],
			['A block image with margin: 0 auto beside a 40mm left float: centred in the 140mm beside the float, 60mm in from it.', sprintf($img, 'float: left; width: 40mm') . sprintf($img, 'display: block; margin: 0 auto')],
		];
	}

	/**
	 * Each case in a bordered box under its caption
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 1mm 0 0.5mm 0; }
			div.box { border: 0.2mm solid #999999; margin-bottom: 2mm; }
			div.box p { margin: 0; }
		</style>
		<h1>Block-level images placed by their margins</h1>';

		foreach ($this->cases(__DIR__ . '/../data/img/bg.jpg') as $case) {
			$html .= '<p>' . $case[0] . '</p><div class="box">' . $case[1] . '</div>';
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
