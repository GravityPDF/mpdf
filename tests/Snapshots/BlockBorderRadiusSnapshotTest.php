<?php

namespace Snapshots;

/**
 * A percentage border-radius on a block is of the block's own border box, horizontal of its width and vertical of its
 * height, so 50% on a box that is not square is an ellipse rather than a pill.
 *
 * @group snapshot
 */
class BlockBorderRadiusSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-border-radius';
	}

	/**
	 * The radii, each with the size of its box and a caption saying what shape it should be
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['border-radius: 50%', '40mm', '12mm', '50% on 40 x 12 mm: an ellipse, 20 x 6 mm at each corner, not a pill.'],
			['border-radius: 10%', '40mm', '12mm', '10% on 40 x 12 mm: corners 4 mm across and 1.2 mm tall.'],
			['border-radius: 50%', '40mm', '40mm', '50% on 40 x 40 mm: a circle.'],
			['border-radius: 50% / 10%', '40mm', '12mm', '50% / 10% on 40 x 12 mm: corners 20 mm across and 1.2 mm tall.'],
			['border-top-left-radius: 50% 10%', '40mm', '12mm', 'border-top-left-radius: 50% 10%: only the top left corner is rounded, 20 mm across and 1.2 mm tall.'],
			['border-radius: 3mm', '40mm', '12mm', '3mm: a 3 mm circle at each corner, whatever the box.'],
		];
	}

	/**
	 * A filled box for each case under its caption, then a block that runs on to the next page: its top corners on the
	 * first page and its bottom corners on the second are each half the height of the part on that page
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 2mm 0 0.5mm 0; }
			div.box { background: #4a90d9; border: 0.3mm solid #1c4f82; }
			div.tall { width: 60mm; background: #cccccc; border: 0.3mm solid #666666; border-radius: 50%; }
			div.tall p { margin: 0; padding: 2mm 0; text-align: center; }
		</style>
		<h1>Percentage border-radius on blocks</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[3] . '</p><div class="box" style="width: ' . $case[1] . '; height: ' . $case[2] . '; ' . $case[0] . '"></div>';
		}

		$html .= '<p>50% on a block that breaks across the page: the top corners here and the bottom corners on the next page are each half the height of their part.</p>';
		$html .= '<div class="tall">' . str_repeat('<p>Filler</p>', 30) . '</div>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
