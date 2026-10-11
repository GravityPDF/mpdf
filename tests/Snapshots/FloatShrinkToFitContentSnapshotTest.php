<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A float with no width is as wide as content that is not a line of text: a floated image, empty blocks, a float with
 * a margin inside it, and content on both sides of a forced page break. Each case is drawn under a caption saying
 * what it should show. Floats are green, the floats inside them blue, and the text that follows them grey.
 *
 * @group snapshot
 */
class FloatShrinkToFitContentSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'float-shrink-to-fit-content';
	}

	/**
	 * The cases, each as the markup and a caption saying what it should show
	 *
	 * @return array
	 */
	protected function cases()
	{
		$image = __DIR__ . '/../data/img/bayeux2.jpg';
		$paragraph = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 4);

		return [
			['<div class="float" style="float: left">' . $paragraph . '<br>short</div><p class="after">bb</p>', 'A float whose text wraps, with a short last line, takes the whole width as in a browser, and "bb" goes below it.'],
			['<div class="float" style="float: left"><img src="' . $image . '" style="float: left; width: 30mm"></div><p class="after">bb</p>', 'A float holding only a 30mm image floated left is 30mm wide, and "bb" sits beside it.'],
			['<div class="float" style="float: left"><img src="' . $image . '" style="float: right; width: 30mm">aa</div><p class="after">bb</p>', 'A float holding "aa" beside a 30mm image floated right is as wide as both, with "aa" and the image on one line, and "bb" beside the float.'],
			['<div class="float" style="float: left; padding: 3mm"><div></div><div></div></div><p class="after">bb</p>', 'A float with 3mm padding holding only empty blocks is 6mm wide, its padding alone, and "bb" sits beside it.'],
			['<div class="float" style="float: left"><div class="inner" style="float: right; width: 20mm; margin-left: 10mm">20mm</div></div><p class="after">bb</p>', 'A float holding a 20mm blue float right with a 10mm margin-left is 30mm wide, the blue box at its right, and "bb" beside it.'],
			['<div class="float" style="float: right"><div class="inner" style="float: left; width: 20mm; margin-right: 10mm">20mm</div></div><p class="after">bb</p>', 'A float right holding a 20mm blue float left with a 10mm margin-right is 30mm wide at the right margin, the blue box at its left, and "bb" at the left margin.'],
			['<div class="float" style="float: left">before the break<div style="page-break-before: always">after</div></div><p class="after">bb</p>', 'A float with a forced page break inside it is as wide as "before the break" here and on the next page, where "bb" sits beside it.'],
		];
	}

	/**
	 * Each case under its caption, cleared of the floats before it
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p { margin: 0; }
			p.caption { margin: 3mm 0 1mm 0; clear: both; color: #333333; }
			p.after { color: #999999; }
			div.float { background-color: #cfc; }
			div.inner { background-color: #cde; }
		</style>
		<h1>Floats with no width around content that is not a line of text</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p class="caption">' . $case[1] . '</p>' . $case[0];
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
