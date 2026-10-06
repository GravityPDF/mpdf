<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A set height fixes a block's box: a percentage is taken of the parent's height, shorter content leaves space,
 * taller content overflows the box, and overflow other than visible clips the content, and the backgrounds and
 * borders of the blocks inside, to the padding box (#587)
 *
 * @group snapshot
 */
class BlockHeightSnapshotTest extends Snapshot
{

	/**
	 * Six single-line blocks, about 20mm tall together
	 */
	const SIX_LINES = '<div>one</div><div>two</div><div>three</div><div>four</div><div>five</div><div>six</div>';

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'block-height';
	}

	/**
	 * The CSS mode the document is drawn in
	 *
	 * @return string
	 */
	protected function cssMode()
	{
		return CssMode::STANDARD;
	}

	/**
	 * The document's title
	 *
	 * @return string
	 */
	protected function title()
	{
		return 'Block height and overflow';
	}

	/**
	 * The blocks, each with a caption saying what it should show, grouped under headings
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			'Percentage heights' => [
				['<div class="box" style="height: 30mm"><div class="inner" style="height: 50%">50% of 30mm</div></div>', 'A 30mm grey box; the red box inside it is 15mm tall, half of it.'],
				['<div class="inner" style="height: 50%">50% of no height</div>', 'A red box one line tall: with no height on its parent, 50% is auto.'],
			],
			'Fixed boxes' => [
				['<div class="box" style="height: 12mm">short</div>', 'A 12mm grey box with one line of text at the top and space below it.'],
				['<div class="box room" style="height: 10mm">' . self::SIX_LINES . '</div>', 'A 10mm grey box; the six lines run on below its bottom border, over the gap before the next caption.'],
				['<div class="box" style="height: 10mm; overflow: hidden">' . self::SIX_LINES . '</div>', 'A 10mm grey box showing only its first two lines and the top of the third; the rest is clipped.'],
				['<div class="box" style="height: 10mm; padding: 3mm; overflow: hidden">' . self::SIX_LINES . '</div>', 'A 16mm grey box, 3mm of padding inside its border, showing its first three lines; the clip is the padding box, so the text starts 3mm inside the border.'],
			],
			'Nested blocks' => [
				['<div class="box" style="height: 15mm; overflow: hidden"><div class="inner" style="height: 30mm; border: 1mm solid #0000cc">nested</div></div>', 'A 15mm grey box; the red box inside it, with a blue border, is cut off at the grey box\'s bottom edge, with no bottom border.'],
				['<div class="box room" style="height: 15mm"><div class="inner" style="height: 30mm; border: 1mm solid #0000cc">nested, visible</div></div>', 'A 15mm grey box; the red box inside it runs on 32mm below the grey box\'s top, over the gap below.'],
			],
		];
	}

	/**
	 * A box for each case, under a caption saying what it should show. The gap after each box leaves room for what
	 * overflows it
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1mm 0 0.5mm 0; }
			div.box { width: 60mm; border: 0.5mm solid #999999; background-color: #e6e6e6; margin-bottom: 4mm; }
			div.room { margin-bottom: 30mm; }
			div.inner { width: 40mm; background-color: #ff9999; }
		</style>
		<h1>' . $this->title() . '</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p>' . $case[1] . '</p>' . $case[0];
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
