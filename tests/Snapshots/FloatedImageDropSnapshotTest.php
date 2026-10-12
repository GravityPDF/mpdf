<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A left and a right floated image too wide to sit side by side: the second goes below the first, against its own
 * side and at its own size, and the text runs beside the first and then beside the second.
 *
 * @group snapshot
 */
class FloatedImageDropSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'floated-image-drop';
	}

	/**
	 * Each case's caption, keyed by the side its first image floats to
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			'left' => 'Left float first: the red image at the top left, the blue one below it at the right, both 120mm by 30mm. Text beside the red image on its right, then beside the blue image on its left, never over either.',
			'right' => 'Right float first: the red image at the top right, the blue one below it at the left, both 120mm by 30mm. Text beside the red image on its left, then beside the blue image on its right, never over either.',
		];
	}

	/**
	 * A paragraph with a red then a blue floated image for each order, under a caption saying where they should be
	 */
	public function generatePdf()
	{
		$red = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAFElEQVQI12O8a2zMAANMDEgANwcAQMQBS3YePV8AAAAASUVORK5CYII=';
		$blue = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAFElEQVQI12M0TjvDAANMDEgANwcARiUBbVuuICoAAAAASUVORK5CYII=';

		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			p.caption { margin: 6mm 0 1mm 0; font-style: italic; }
			p.case { margin: 0; border: 0.2mm solid #999999; }
		</style>
		<h1>Floated images too wide to sit side by side</h1>';

		foreach ($this->cases() as $first => $caption) {
			$second = $first === 'left' ? 'right' : 'left';
			$html .= '<p class="caption">' . $caption . '</p><p class="case">'
				. '<img src="' . $red . '" style="float: ' . $first . '; width: 120mm; height: 30mm" />'
				. '<img src="' . $blue . '" style="float: ' . $second . '; width: 120mm; height: 30mm" />'
				. str_repeat('Text beside the floats. ', 28)
				. '</p>';
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
