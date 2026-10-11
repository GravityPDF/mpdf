<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * object-fit, object-position and aspect-ratio on images: the box keeps the size its width and height give it, shown
 * by a grey background and border, and the picture is scaled and placed inside it and cut off at its edge.
 *
 * @group snapshot
 */
class ObjectFitSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'object-fit';
	}

	/**
	 * The images, each with a caption saying what it should show, grouped under headings
	 *
	 * @return array
	 */
	private function cases()
	{
		$wide = 'ratio-16x9.png';
		$tall = 'ratio-9x16.png';
		$photo = 'bayeux2.jpg';
		$box = 'width: 40mm; height: 25mm; ';

		return [
			'object-fit in a 40 x 25 mm box' => [
				[$photo, $box . 'object-fit: fill', 'fill: the photo stretched to the box.'],
				[$photo, $box . 'object-fit: contain', 'contain: the whole photo, centred, grey bars above and below it.'],
				[$photo, $box . 'object-fit: cover', 'cover: the box filled, the photo cut off at both sides.'],
				[$wide, $box . 'object-fit: none', 'none: the middle of the 16:9 picture at its natural size.'],
				[$wide, 'width: 100mm; height: 60mm; object-fit: scale-down', 'scale-down in a 100 x 60 mm box: the 16:9 picture at its natural size, centred.'],
				[$tall, $box . 'object-fit: contain', 'contain on a tall picture: grey bars at the sides.'],
				[$wide, $box . 'object-fit: cover', 'cover on a wide picture: cut off at the sides.'],
			],
			'object-position' => [
				[$tall, $box . 'object-fit: contain; object-position: left top', 'left top: the picture against the left of the box.'],
				[$tall, $box . 'object-fit: contain; object-position: right', 'right: the picture against the right of the box.'],
				[$tall, $box . 'object-fit: contain; object-position: 25% 0', '25% 0: a quarter of the grey on the left.'],
				[$tall, $box . 'object-fit: contain; object-position: 5mm 0', '5mm 0: 5 mm of grey on the left.'],
				[$photo, $box . 'object-fit: cover; object-position: left center', 'cover, left center: the left end of the photo kept, its right end cut off.'],
			],
			'aspect-ratio' => [
				[$photo, 'width: 40mm; aspect-ratio: 1 / 1', 'width 40mm, 1 / 1: a 40 mm square, the photo stretched to it.'],
				[$photo, 'height: 20mm; aspect-ratio: 3 / 1', 'height 20mm, 3 / 1: 60 x 20 mm, the photo stretched to it.'],
				[$photo, 'width: 60mm; aspect-ratio: 3 / 1; object-fit: cover', 'width 60mm, 3 / 1, cover: 60 x 20 mm, the photo cut off a little at both sides.'],
				[$photo, 'width: 40mm; aspect-ratio: auto 1 / 1', 'auto 1 / 1: the photo in its own proportions.'],
			],
			'Rounded, padded, in a table, and SVG' => [
				[$photo, $box . 'object-fit: cover; border-radius: 6mm', 'cover with border-radius: the photo filling the rounded box.'],
				[$tall, $box . 'object-fit: contain; padding: 2mm; border: 1mm solid #c00', 'contain with padding and a red border: the border round the 40 x 25 mm box, not the picture.'],
				[$tall, $box . 'object-fit: contain; object-position: right', 'in a table cell, contain, right: the picture against the right.', '<table border="1"><tr><td>%s</td></tr></table>'],
				['demo.svg', $box . 'object-fit: contain', 'an SVG with contain: the whole drawing, in its own proportions.'],
			],
		];
	}

	/**
	 * Each image under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1.5mm 0 0.5mm 0; }
			img { background-color: #cccccc; border: 0.3mm solid #666666; }
		</style>
		<h1>object-fit, object-position and aspect-ratio</h1>';

		$dir = __DIR__ . '/../data/img/';
		foreach ($this->cases() as $heading => $images) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($images as $case) {
				$img = '<img src="' . $dir . $case[0] . '" style="' . $case[1] . '">';
				$html .= '<p>' . $case[2] . '</p>' . (isset($case[3]) ? sprintf($case[3], $img) : '<div>' . $img . '</div>');
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
