<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A block at the top level with column-count, column-width or columns is laid out in columns, with column-gap between
 * them, column-rule drawn down the gaps, column-span: all on a child and column-fill. A block inside a bordered block
 * stays in one column. The last block runs from the second page onto a third in both columns.
 *
 * @group snapshot
 */
class CssColumnsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'css-columns';
	}

	/**
	 * The columned blocks, each with a caption saying how its text should be laid out
	 *
	 * @return string[][]
	 */
	protected function cases()
	{
		$words = $this->words(5);

		return [
			['column-count: 2; column-gap: 10mm: two columns 10mm apart, about as tall as each other.', '<div style="column-count: 2; column-gap: 10mm">' . $words . '</div>'],
			['columns: 40mm: four columns, as many 40mm columns as fit.', '<div style="columns: 40mm">' . $words . '</div>'],
			['columns: 3 30mm: three columns, though five of 30mm would fit.', '<div style="columns: 3 30mm">' . $words . '</div>'],
			['column-rule: 0.3mm solid: three columns, a black line down the middle of each gap.', '<div style="column-count: 3; column-gap: 8mm; column-rule: 0.3mm solid">' . $words . '</div>'],
			['column-rule: 0.6mm dashed red, then 0.8mm dotted blue, then 1.2mm double green: two columns, each with a line of that kind down the gap.', '<div style="column-count: 2; column-gap: 8mm; column-rule: 0.6mm dashed red">' . $words . '</div>'
				. '<div style="column-count: 2; column-gap: 8mm; column-rule: 0.8mm dotted blue; margin-top: 2mm">' . $words . '</div>'
				. '<div style="column-count: 2; column-gap: 8mm; column-rule: 1.2mm double green; margin-top: 2mm">' . $words . '</div>'],
			['column-span: all: two columns, a heading across the page, then two columns again. The grey line runs down each part, not across the heading.', '<div style="column-count: 2; column-gap: 8mm; column-rule: 0.3mm solid #999999">' . $words . '<h3 style="column-span: all; text-align: center; margin: 1mm 0">A heading across both columns</h3>' . $words . '</div>'],
			['column-fill: auto: one column holding all the text, the second left empty.', '<div style="column-count: 2; column-fill: auto">' . $this->words(2) . '</div>'],
			['Inside a bordered block: one column across the box, as before.', '<div style="border: 0.3mm solid #999999; padding: 1mm"><div style="column-count: 2">' . $this->words(2) . '</div></div>'],
		];
	}

	/**
	 * Sentences of filler text
	 *
	 * @param int $sentences
	 *
	 * @return string
	 */
	private function words($sentences)
	{
		return str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore. ', $sentences);
	}

	/**
	 * Each columned block under its caption, then on a page of its own a long one with a rule that carries on in both
	 * columns of the next page
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h3 { font-size: 10pt; }
			p.caption { margin: 3mm 0 1mm 0; font-weight: bold; color: #555555; }
		</style>
		<h1>CSS columns</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p class="caption">' . $case[0] . '</p>' . $case[1];
		}

		$html .= '<pagebreak /><p class="caption">A long block, column-count: 2 with a rule: both columns fill this page and carry on in both columns of the next, with the line down the gap on each page, then a line of text across the page below them.</p>'
			. '<div style="column-count: 2; column-gap: 10mm; column-rule: 0.3mm solid">' . $this->words(130) . '</div>'
			. '<p>After the columns: one line across the page.</p>';

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
