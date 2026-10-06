<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A float with no width is as wide as its content, and only a block that starts a block formatting context contains
 * its floats, as in a browser. Each case is drawn under a caption saying what it should show. Floats are green, the
 * blocks around them yellow, and the text that follows them grey.
 *
 * @group snapshot
 */
class FloatShrinkToFitSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'float-shrink-to-fit';
	}

	/**
	 * The document's title
	 *
	 * @return string
	 */
	protected function title()
	{
		return 'Floats with no width, and the blocks that contain floats';
	}

	/**
	 * The cases, each as the markup and a caption saying what it should show, grouped under headings
	 *
	 * @return array
	 */
	protected function cases()
	{
		$paragraph = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 6);
		$float = '<div class="float" style="float: left; width: 30mm; height: 12mm">30mm by 12mm</div>';

		return [
			'A float with no width' => [
				['<div class="float" style="float: left">aa</div><p class="after">bb</p>', 'A left float holding "aa" is as wide as that word, and "bb" sits beside it on the same line.'],
				['<div class="float" style="float: right">aa</div><p class="after">bb</p>', 'A right float holding "aa" is as wide as that word and sits at the right margin; "bb" is at the left margin on the same line.'],
				['<div class="float" style="float: left">' . $paragraph . '</div><p class="after">bb</p>', 'A left float holding a paragraph that wraps takes the whole width, and "bb" goes below it.'],
				['<div class="float" style="float: left; width: 30mm; background-color: #cde">30mm wide</div><div class="float" style="float: left">aa bb</div><p class="after">cc</p>', 'A float with no width beside a 30mm blue float is as wide as "aa bb", and "cc" sits beside both.'],
				['<div class="float" style="float: left"><div style="width: 50mm; background-color: #cde">a 50mm block</div></div><p class="after">bb</p>', 'A float holding a 50mm blue block is 50mm wide.'],
				['<div class="float" style="float: right"><table><tr><td>cell one</td><td>cell two</td></tr></table></div><p class="after">bb</p>', 'A right float holding a table is as wide as the table.'],
			],
			'The blocks that contain floats' => [
				['<div class="parent">' . $float . '</div><p class="after">zz</p>', 'A plain yellow parent does not contain its float: it is a line with no height, and "zz" sits beside the float.'],
				['<div class="parent" style="overflow: hidden">' . $float . '</div><p class="after">zz</p>', 'A parent with overflow: hidden contains its float: the yellow box is 12mm tall, and "zz" goes below.'],
				['<div class="parent" style="display: flow-root">' . $float . '</div><p class="after">zz</p>', 'A parent with display: flow-root contains its float: the yellow box is 12mm tall, and "zz" goes below.'],
				['<div class="parent">' . $float . '<div style="clear: both"></div></div><p class="after">zz</p>', 'A clear at the end of the parent makes it contain the float, as a clearfix does: the yellow box is 12mm tall, and "zz" goes below.'],
				['<div class="parent" style="float: left; width: 80mm">' . $float . '</div><p class="after">zz</p>', 'A yellow float 80mm wide contains its float: it is 12mm tall, and "zz" sits beside it.'],
			],
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
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; clear: both; }
			p { margin: 0; }
			p.caption { margin: 2mm 0 1mm 0; clear: both; color: #333333; }
			p.after { color: #999999; }
			div.float { background-color: #cfc; }
			div.parent { background-color: #ffc; }
			td { border: 0.2mm solid #999999; padding: 0.5mm; }
		</style>
		<h1>' . $this->title() . '</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p class="caption">' . $case[1] . '</p>' . $case[0];
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
