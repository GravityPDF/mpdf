<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A heading with page-break-after: avoid is kept on the page of what follows it: the first line of the next paragraph,
 * or the whole of a table or block kept together. Each case takes a spread of two pages, with a caption on the first
 * saying what the second should show
 *
 * @group snapshot
 */
class PageBreakAfterAvoidSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'page-break-after-avoid';
	}

	/**
	 * The cases: how many one-line paragraphs fill the page ahead of the heading, the heading and what follows it, and
	 * the caption. Fifty-one of the paragraphs fill a page
	 *
	 * @return array
	 */
	protected function cases()
	{
		$heading = '<h2 style="page-break-after: avoid">Heading</h2>';
		$line = '<p>First line of the paragraph.</p>';
		$rows = '<tr><td>Row 1</td></tr><tr><td>Row 2</td></tr><tr><td>Row 3</td></tr><tr><td>Row 4</td></tr>';

		return [
			[48, $heading . '<table style="page-break-inside: avoid">' . $rows . '</table>', 'A table kept together follows the heading: the heading and the table both stand at the top of the next page, and this page ends with filler.'],
			[44, $heading . '<div style="page-break-inside: avoid"><p>Line 1</p><p>Line 2</p><p>Line 3</p><p>Line 4</p><p>Line 5</p></div>', 'A block kept together follows the heading: the heading and the five lines all stand at the top of the next page.'],
			[50, $heading . $line, 'The paragraph\'s first line does not fit under the heading: the heading and the line both stand at the top of the next page.'],
			[46, $heading . $line, 'The heading and the paragraph\'s first line both fit at the foot of this page, so both stay there and the next case starts the next page.'],
			[49, $heading . '<p style="margin-top: 10mm">First line of the paragraph.</p>', 'The paragraph\'s top margin pushes its first line off this page: the heading goes with it to the top of the next page.'],
			[50, $heading . '<h3 style="page-break-after: avoid">Subheading</h3>' . $line, 'A heading, a subheading and a paragraph in a row: all three stand at the top of the next page.'],
			[49, $heading . '<table>' . $rows . '</table>', 'A table not kept together starts on the next page: the heading goes with its first row.'],
			[46, $heading . '<table>' . $rows . '</table>', 'The table\'s first row fits under the heading: the heading and the first rows stay at the foot of this page, and the rest of the table starts the next page.'],
			[0, '<pagebreak /><div style="page-break-after: avoid"><img style="width: 100mm; height: 262mm" src="' . __DIR__ . '/../data/img/bg.jpg" />Tall block</div>' . $line, 'A block almost a page tall could not share a page with the line after it either: it fills the next page and the line starts the one after.'],
			[52, '<table><tr><td>' . $heading . $line . '</td></tr></table>', 'Inside a table cell the heading is left alone: the row moves whole to the next page.'],
		];
	}

	/**
	 * Each case on a fresh page: its caption, filler to the foot of the page, then the heading and what follows it
	 */
	public function generatePdf()
	{
		$html = '<style>
			p { margin: 0; }
			p.caption { margin: 0 0 2mm 0; padding: 1mm; border: 0.2mm solid #999999; font-size: 8pt; color: #555555; }
			p.caption b { color: #000000; }
		</style>';

		foreach ($this->cases() as $n => $case) {
			$html .= ($n ? '<pagebreak />' : '') . '<p class="caption"><b>Case ' . ($n + 1) . '.</b> ' . $case[2] . '</p>'
				. str_repeat('<p>Filler</p>', $case[0]) . $case[1];
		}

		$this->mpdf = $this->createMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
