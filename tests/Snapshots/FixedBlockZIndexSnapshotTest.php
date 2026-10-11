<?php

namespace Snapshots;

/**
 * A position: fixed block is drawn on every page, and positioned blocks are stacked by z-index with their
 * backgrounds: higher over lower, the same in document order, auto and 0 in document order, and below 0 under the
 * page's content.
 *
 * @group snapshot
 */
class FixedBlockZIndexSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'fixed-block-z-index';
	}

	/**
	 * Pairs of overlapping squares, a green one written before a red one, each with the z-index of each and a caption
	 * saying which should be on top
	 *
	 * @return string[][]
	 */
	protected function pairs()
	{
		return [
			['z-index: 5', '', 'Green z-index: 5, red none: green and its G are over red.'],
			['z-index: 0', '', 'Green z-index: 0, red none: in document order, red and its R over green.'],
			['z-index: auto', '', 'Green z-index: auto, red none: in document order, red and its R over green.'],
			['z-index: 5', 'z-index: 5', 'Both z-index: 5: in document order, red and its R over green.'],
			['z-index: 6', 'z-index: 5', 'Green z-index: 6, red z-index: 5: green and its G are over red.'],
		];
	}

	/**
	 * A green and a red square overlapping by half, under a caption, the green one's text in its overlapping corner
	 *
	 * @param float $top Where the pair goes, in mm from the top of the page
	 * @param string $green The green square's z-index declaration
	 * @param string $red The red square's z-index declaration
	 * @param string $caption
	 *
	 * @return string
	 */
	protected function pair($top, $green, $red, $caption)
	{
		$square = 'position: absolute; width: 24mm; height: 24mm; font-size: 20pt; font-weight: bold;';

		return '<div style="position: absolute; top: ' . $top . 'mm; left: 70mm; width: 120mm">' . $caption . '</div>'
			. '<div style="' . $square . ' top: ' . $top . 'mm; left: 20mm; background: #3c3; text-align: right; padding-top: 12mm; height: 12mm; ' . $green . '">G</div>'
			. '<div style="' . $square . ' top: ' . ($top + 12) . 'mm; left: 32mm; background: #e33; ' . $red . '">R</div>';
	}

	/**
	 * The pairs on the first page, a block with a negative z-index under the paragraphs of the second, and a third
	 * page with the fixed banner alone
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 10pt; }
			h1 { font-size: 14pt; margin: 0 0 2mm 0; }
			p.under { background: #ff6; margin: 0 0 2.5mm 0; padding: 1mm; }
		</style>
		<div style="position: fixed; top: -12mm; right: 0; width: 60mm; padding: 1.5mm; background: #ccf; border: 0.3mm solid #66c">Fixed: drawn at the top right of every page</div>
		<h1>position: fixed and z-index</h1>
		<p>Each page should have the lilac banner "Fixed: drawn at the top right of every page" in its top margin, at the right.</p>';

		$top = 40;
		foreach ($this->pairs() as $pair) {
			$html .= $this->pair($top, $pair[0], $pair[1], $pair[2]);
			$top += 45;
		}

		$html .= '<pagebreak /><h1>z-index below 0</h1>
		<p>Green z-index: -1, red none: the yellow paragraphs and their text are over green, which shows between them, and red is over them.</p>'
			. str_repeat('<p class="under">This paragraph is in the flow of the page, and its yellow background is over the green square.</p>', 12)
			. $this->pair(40, 'z-index: -1', '', '');

		$html .= '<pagebreak /><h1>A page of its own</h1>
		<p>This page should have the banner too, and nothing else.</p>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
