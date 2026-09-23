<?php

namespace Snapshots;

/**
 * Print-only, screen-only and hidden content in a PDF/A-1b document under PDFAauto, which allows no optional content:
 * print-only content is drawn, and screen-only and hidden content is left out with its space kept (#397)
 *
 * @group snapshot
 */
class AutoVisibilitySnapshotTest extends Snapshot
{

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'auto-visibility';
	}

	/**
	 * A block, a span and an image of each visibility, between visible text that shows where they sit
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf(['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B']);

		$image = __DIR__ . '/../data/img/bg.jpg';
		$html = '';
		foreach (['visible', 'printonly', 'screenonly', 'hidden'] as $visibility) {
			$style = 'visibility: ' . $visibility;
			$html .= '<h3>' . $visibility . '</h3>'
				. '<div style="' . $style . '; border: 1mm solid #336; background: #ccd; padding: 2mm">Block</div>'
				. '<p>Before <span style="' . $style . '; background: #fc6">span</span> after</p>'
				. '<p>Before <img src="' . $image . '" style="' . $style . '; width: 15mm; height: 8mm" /> after</p>';
		}

		$this->mpdf->WriteHTML($html);
	}

}
