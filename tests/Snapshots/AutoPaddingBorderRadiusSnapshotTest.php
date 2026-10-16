<?php

namespace Snapshots;

/**
 * With autoPadding, a rounded block is padded so the corner of its text lands on each curve where it runs at 45
 * degrees, which adds the least padding. A percentage border-radius is padded as a length is, with the vertical
 * percentage taken of the block's width, as its height is not known when the padding is set.
 *
 * @group snapshot
 */
class AutoPaddingBorderRadiusSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'auto-padding-border-radius';
	}

	/**
	 * The blocks in sections by heading, each block's style with a caption saying where its text should sit, and the
	 * block's own text where it has one
	 *
	 * @return array
	 */
	protected function sections()
	{
		return [
			'Percentages, on a block 40 mm wide' => [
				['width: 40mm; border-radius: 50%', '50%: the text starts on the curve, 5.9 mm in from the left and down from the top, the same on the right and bottom.'],
				['width: 40mm; border-radius: 20mm', '20mm, the length 50% comes to: the text sits where it does in the box above.'],
				['width: 40mm; border-radius: 10%', '10%: the text is 1.2 mm in from the left and down from the top, just clear of the small corner.'],
				['width: 40mm; border-radius: 5mm', '5mm: the text is 1.5 mm in from each side.'],
				['width: 40mm; border-radius: 50% / 10%', '50% / 10%, a flat corner: padded mostly down, 0.4 mm in from the left and 3.2 mm down from the top.'],
				['width: 40mm', 'No radius: no padding, the text against the top left of the box.'],
			],
			'Radii from small to large, on a block 60 mm wide' => [
				['width: 60mm; border-radius: 2mm', '2mm: the text is 0.6 mm in from each side.'],
				['width: 60mm; border-radius: 10mm', '10mm: 2.9 mm in from each side.'],
				['width: 60mm; border-radius: 15mm', '15mm: 4.4 mm in from each side.'],
				['width: 60mm; border-radius: 30mm', '30mm, half the width: 8.8 mm in from each side.'],
				['width: 60mm; border-radius: 30mm / 8mm', '30mm / 8mm, wide and flat: 1.0 mm in from the left and right, 5.9 mm in from the top and bottom.'],
				['width: 60mm; border-radius: 8mm / 30mm', '8mm / 30mm, narrow and tall: 5.9 mm in from the left and right, 1.0 mm in from the top and bottom.'],
			],
			'Blocks of different widths' => [
				['width: 25mm; border-radius: 10mm', '10mm on a block 25 mm wide: 2.9 mm in from each side, as on any width.'],
				['width: 60mm; border-radius: 10mm', '10mm on a block 60 mm wide: the same 2.9 mm.'],
				['width: 120mm; border-radius: 10mm', '10mm on a block 120 mm wide: the same 2.9 mm.'],
				['width: 25mm; border-radius: 25%', '25% on a block 25 mm wide, a radius of 6.25 mm: 1.8 mm in from each side.'],
				['width: 60mm; border-radius: 25%', '25% on a block 60 mm wide, 15 mm: 4.4 mm in from each side.'],
				['width: 120mm; border-radius: 25%', '25% on a block 120 mm wide, 30 mm: 8.8 mm in from each side.'],
			],
			'Font sizes, on a block 60 mm wide' => [
				['width: 60mm; border-radius: 10mm; font-size: 6pt', '10mm at 6pt: 2.9 mm in from each side; the padding does not depend on the text.'],
				['width: 60mm; border-radius: 10mm; font-size: 14pt', '10mm at 14pt: the same 2.9 mm, the larger lines still clear of the curve.'],
				['width: 60mm; border-radius: 10mm; font-size: 20pt', '10mm at 20pt: the same 2.9 mm.'],
				['width: 60mm; border-radius: 2em; font-size: 6pt', '2em at 6pt, a radius of 4.2 mm: 1.2 mm in from each side.'],
				['width: 60mm; border-radius: 2em; font-size: 14pt', '2em at 14pt, 9.9 mm: 2.9 mm in from each side.'],
				['width: 60mm; border-radius: 2em; font-size: 20pt', '2em at 20pt, 14.1 mm: 4.1 mm in from each side.'],
			],
			'40pt text, on a block 120 mm wide' => [
				['width: 120mm; border-radius: 10mm; font-size: 40pt', '10mm at 40pt: the same 2.9 mm in from each side as at any size.', 'Text clears each curve.'],
				['width: 120mm; border-radius: 2em; font-size: 40pt', '2em at 40pt, a radius of 28.2 mm: 8.3 mm in from each side.', 'Text clears each curve.'],
			],
			'Borders and padding, a 10mm radius on a block 60 mm wide' => [
				['width: 60mm; border-radius: 10mm; border-width: 2mm', 'A 2 mm border: the text is 2.9 mm inside the border, as with a thin one. The padding is measured from the outside of the border, so the text sits further in than the curve needs.'],
				['width: 60mm; border-radius: 10mm; border-width: 5mm', 'A 5 mm border: still 2.9 mm inside the border, well clear of the curve.'],
				['width: 60mm; border-radius: 10mm; padding: 1mm', 'padding: 1mm, short of the curve: raised to 2.9 mm on each side.'],
				['width: 60mm; border-radius: 10mm; padding: 3mm', 'padding: 3mm, already clear of the curve: kept at 3 mm.'],
				['width: 60mm; border-radius: 20mm / 4mm; padding: 2mm', '20mm / 4mm with padding: 2mm: 2 mm across is clear, so only the top and bottom are raised, to 2.3 mm.'],
			],
			'Mixed corners, on a block 60 mm wide and 25 mm tall' => [
				['width: 60mm; height: 25mm; border-top-left-radius: 20mm', 'Only the top left rounded, 20mm: the text is 5.9 mm in from the left and down from the top, with no padding on the right or bottom.'],
				['width: 60mm; height: 25mm; border-top-left-radius: 20mm 4mm; border-top-right-radius: 5mm', 'Top left 20mm / 4mm, top right 5mm: 3.2 mm down from the top, 0.4 mm in from the left; the right corner reuses that top padding and only needs 0.3 mm in from the right.'],
				['width: 60mm; height: 25mm; border-top-left-radius: 15mm; border-bottom-right-radius: 15mm', 'Top left and bottom right 15mm: 4.4 mm in from every side, as each side touches one rounded corner.'],
				['width: 60mm; height: 25mm; border-radius: 2mm 6mm 12mm 20mm', '2mm, 6mm, 12mm and 20mm clockwise from the top left: 1.8 mm down from the top, 3.5 mm in from the right, 5.9 mm in from the bottom and left.'],
				['width: 60mm; height: 25mm; border-radius: 30mm 30mm 0 0 / 8mm 8mm 0 0', 'Only the top corners, 30mm / 8mm: 5.9 mm down from the top and 1.0 mm in from each side, with no padding at the bottom.'],
			],
		];
	}

	/**
	 * A block of text for each case under its caption, in sections
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 5mm 0 0 0; }
			p { margin: 3mm 0 0.5mm 0; }
			div.box { background: #cfe0f3; border: 0.3mm solid #1c4f82; font-size: 9pt; }
		</style>
		<h1>autoPadding with a border-radius</h1>';

		$html .= '<p class="note">A block shorter than its two radii has its corners drawn smaller to fit, as CSS does, but is padded for the full radius.</p>';

		foreach ($this->sections() as $heading => $cases) {
			$html .= '<h2' . ($heading === 'Percentages, on a block 40 mm wide' ? '' : ' style="page-break-before: always"') . '>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$text = isset($case[2]) ? $case[2] : 'Text clears the curve of each corner, top and bottom, on every line it fills.';
				$html .= '<p>' . $case[1] . '</p><div class="box" style="' . $case[0] . '">' . $text . '</div>';
			}
		}

		$this->mpdf = $this->createMpdf(['autoPadding' => true]);

		$this->mpdf->WriteHTML($html);
	}

}
