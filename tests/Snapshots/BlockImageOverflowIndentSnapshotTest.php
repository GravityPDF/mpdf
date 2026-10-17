<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A block-level image too wide for its line is narrowed in proportion, its margin kept, and text-indent does not move
 * a block-level image on a paragraph's first line (#703)
 *
 * @group snapshot
 */
class BlockImageOverflowIndentSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-image-overflow-indent';
	}

	/**
	 * The HTML of each case under a caption saying where the image should sit and how big it should be. The picture is
	 * 100 by 116 pixels.
	 *
	 * @param string $image The image's path
	 *
	 * @return array[] Caption and HTML
	 */
	protected function cases($image)
	{
		$img = '<img src="' . $image . '" style="%s">';

		return [
			[
				'A 60mm box holding a 50mm block image with margin-left: 20mm: the image is narrowed to the 40mm left, 20mm in from the left edge.',
				'<div class="box" style="width: 60mm">' . sprintf($img, 'display: block; width: 50mm; margin-left: 20mm') . '</div>',
			],
			[
				'A 100mm box with a 20mm left float, then a 60mm block image with margin-left: 30mm: the image is narrowed to 50mm, 30mm in from the float, and keeps its proportions (taller than wide).',
				'<div class="box" style="width: 100mm">' . sprintf($img, 'float: left; width: 20mm') . sprintf($img, 'display: block; width: 60mm; margin-left: 30mm') . '</div>',
			],
			[
				'text-indent: 20mm, a block image first in the paragraph: the image is at the left edge of the box, not 20mm in; "after" below it is not indented either.',
				'<div class="box"><p style="text-indent: 20mm">' . sprintf($img, 'display: block; width: 15mm') . 'after</p></div>',
			],
			[
				'text-indent: 20mm, a block image with margin: 0 auto first in the paragraph: the image is centred in the box.',
				'<div class="box"><p style="text-indent: 20mm">' . sprintf($img, 'display: block; width: 15mm; margin: 0 auto') . 'after</p></div>',
			],
			[
				'text-indent: 20mm in a right-to-left paragraph, a block image first in it: the image is at the right edge of the box.',
				'<div class="box"><p dir="rtl" style="text-indent: 20mm">' . sprintf($img, 'display: block; width: 15mm') . 'after</p></div>',
			],
			[
				'text-indent: 20mm with text before the block image: "before" is indented 20mm, the image below it is at the left edge.',
				'<div class="box"><p style="text-indent: 20mm">before ' . sprintf($img, 'display: block; width: 15mm') . ' after</p></div>',
			],
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
			p.caption { margin: 1mm 0 0.5mm 0; }
			div.box { border: 0.2mm solid #999999; margin-bottom: 2mm; }
			div.box p { margin: 0; }
		</style>
		<h1>Block-level images narrowed to their line, and not moved by text-indent</h1>';

		foreach ($this->cases(__DIR__ . '/../data/img/bg.jpg') as $case) {
			$html .= '<p class="caption">' . $case[0] . '</p>' . $case[1];
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
