<?php

namespace Snapshots;

/**
 * An overline is drawn at the thickness the font gives its underline, so the two lines over and under the same text
 * match, and a table shrunk to fit thins its overline with its text
 *
 * @group snapshot
 */
class OverlineThicknessSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'overline-thickness';
	}

	/**
	 * Large text in two fonts with an underline, an overline and both, each under a caption saying how thick its lines
	 * should be, and a table shrunk to fit with both lines
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			p.caption { margin: 3mm 0 0 0; }
			div.sample { font-size: 40pt; margin: 0; }
		</style>
		<h1>text-decoration: overline thickness</h1>';

		foreach (['DejaVu Serif Condensed' => 'dejavuserifcondensed', 'DejaVu Sans Condensed' => 'dejavusanscondensed'] as $name => $family) {
			$html .= '<p class="caption">' . $name . ', underline: a line under the text.</p>
			<div class="sample" style="font-family: ' . $family . '; text-decoration: underline">Decorated</div>
			<p class="caption">' . $name . ', overline: a line over the text, as thick as the underline above.</p>
			<div class="sample" style="font-family: ' . $family . '; text-decoration: overline">Decorated</div>
			<p class="caption">' . $name . ', underline overline: the two lines the same thickness.</p>
			<div class="sample" style="font-family: ' . $family . '; text-decoration: underline overline">Decorated</div>';
		}

		$html .= '<p class="caption">A table shrunk to fit the page: the line over the text as thin as the line under it.</p>
		<table autosize="4"><tr>
			<td style="font-size: 40pt; text-decoration: underline overline">DecoratedDecoratedDecoratedDecorated</td>
		</tr></table>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
