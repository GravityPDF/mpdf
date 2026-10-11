<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A block with page-break-after: avoid is kept with the first line after it as that line is drawn: text loose in the
 * parent block counts as that line, and a line taller than the block's line height is measured at its height. Each case
 * starts a page, with a caption saying what that page and the next should show
 *
 * @group snapshot
 */
class PageBreakAfterAvoidLineSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'page-break-after-avoid-line';
	}

	/**
	 * The cases: how many one-line paragraphs fill the page ahead of the heading, the heading and what follows it, and
	 * the caption. Fifty-one of the paragraphs fill a page
	 *
	 * @return array
	 */
	private function cases()
	{
		$heading = '<h2 style="page-break-after: avoid">Heading</h2>';
		$loose = 'Text loose after the heading, in no paragraph of its own.';
		$image = '<img style="width: 15mm; height: 15mm" src="' . __DIR__ . '/../data/img/bg.jpg" />';

		return [
			[50, $heading . $loose . '<p>Next paragraph.</p>', 'The loose text does not fit under the heading: the heading, the loose text and the next paragraph all stand at the top of the next page.'],
			[46, $heading . $loose . '<p>Next paragraph.</p>', 'The loose text fits under the heading, the next paragraph does not: the heading and the loose text stay at the foot of this page, and the next paragraph starts the next page alone.'],
			[50, '<div>' . $heading . $loose . '</div>', 'The loose text ends the block the heading is in, and does not fit under it: the heading and the loose text both stand at the top of the next page.'],
			[0, '<div style="page-break-after: avoid; height: 252mm; border: 0.2mm solid #999999">Tall block</div><p>' . $image . ' A line with a 15mm image.</p>', 'A block nearly a page tall, kept with a line holding a 15mm image: the two cannot share a page, so the block stays on this page and the line starts the next, with no blank page between.'],
			[0, '<div style="page-break-after: avoid; height: 250mm; border: 0.2mm solid #999999">Tall block</div><p>A line with a <span style="line-height: 5">span with line-height: 5</span>.</p>', 'A block nearly a page tall, kept with a line made tall by a span with line-height: 5: the block stays on this page and the line starts the next, with no blank page between.'],
		];
	}

	/**
	 * The page each case starts: its caption, then filler to the foot of the page, the heading and what follows it
	 *
	 * @param int $n
	 * @param array $case
	 *
	 * @return string
	 */
	private function casePage($n, array $case)
	{
		return '<p class="caption"><b>Case ' . ($n + 1) . '.</b> ' . $case[2] . '</p>' . str_repeat('<p>Filler</p>', $case[0]) . $case[1];
	}

	/**
	 * Each case on a fresh page. The last case is written by a call of its own and ends it with loose text, which the
	 * end of the HTML draws
	 */
	public function generatePdf()
	{
		$html = '<style>
			p { margin: 0; }
			p.caption { margin: 0 0 2mm 0; padding: 1mm; border: 0.2mm solid #999999; font-size: 8pt; color: #555555; }
			p.caption b { color: #000000; }
		</style>';

		$cases = $this->cases();
		foreach ($cases as $n => $case) {
			$html .= ($n ? '<pagebreak />' : '') . $this->casePage($n, $case);
		}

		$this->mpdf = $this->createMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
		$last = [50, '<h2 style="page-break-after: avoid">Heading</h2>Text loose at the end of the HTML.', 'The HTML ends with loose text after the heading, which does not fit under it: the heading and the text both stand at the top of the next page.'];
		$this->mpdf->WriteHTML('<pagebreak />' . $this->casePage(count($cases), $last));
	}

}
