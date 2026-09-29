<?php

namespace Snapshots;

/**
 * Left pages have a wider page area than right pages. Three panels 90mm wide each run over a page break: one centred,
 * one pushed against the right margin by `margin-left: auto`, and one right to left. Each keeps its place in the page
 * area of every page
 *
 * @group snapshot
 */
class SetWidthPageAreasSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'set-width-page-areas';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$paragraphs = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>', 12);

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(
			'<style>'
			. '@page { margin-top: 20mm; margin-bottom: 20mm; } @page :right { margin-left: 20mm; margin-right: 60mm; } @page :left { margin-left: 30mm; margin-right: 15mm; }'
			. 'body { font-family: dejavusans; font-size: 10pt; } p { text-align: justify; margin: 0 0 2mm; }'
			. '.panel { width: 90mm; background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; margin-bottom: 6mm; }'
			. '</style>'
			. '<div class="panel" style="margin-left: auto; margin-right: auto">' . $paragraphs . '</div>'
			. '<div class="panel" style="margin-left: auto">' . $paragraphs . '</div>'
			. '<div class="panel" style="direction: rtl">' . $paragraphs . '</div>'
		);
	}
}
