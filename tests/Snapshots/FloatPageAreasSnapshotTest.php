<?php

namespace Snapshots;

/**
 * Right pages have a narrower page area than left pages. A shaded panel holds a right float that runs over three pages
 * with text beside it, and a left float follows on the fourth. On every page the floats, the text beside them and
 * the panel sit between that page's own margins
 *
 * @group snapshot
 */
class FloatPageAreasSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'float-page-areas';
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
		$paragraphs = function ($count) {
			return str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>', $count);
		};

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(
			'<style>'
			. '@page { margin: 20mm 40mm 20mm 30mm; } @page :left { margin-left: 15mm; margin-right: 15mm; }'
			. 'body { font-family: dejavusans; font-size: 10pt; } p { text-align: justify; margin: 0 0 2mm; }'
			. '.panel { background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; }'
			. '.float { width: 45mm; background-color: #fbe3d6; border: 0.5mm solid #cc5500; padding: 2mm; }'
			. '</style>'
			. '<div class="panel">'
			. '<div class="float" style="float: right; margin-left: 3mm">' . $paragraphs(14) . '</div>'
			. $paragraphs(22)
			. '</div>'
			. '<div class="float" style="float: left; margin-right: 3mm">' . $paragraphs(4) . '</div>'
			. $paragraphs(6)
		);
	}
}
