<?php

namespace Snapshots;

/**
 * The first call writes a short introduction left to right; the second turns the document right to left and writes
 * a shaded panel that runs over three pages. The @page rule's margins are 20mm on the left and 50mm on the right, and
 * every page, the first included, keeps them
 *
 * @group snapshot
 */
class RightToLeftAfterFirstPageSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'rtl-after-first-page';
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
		$paragraphs = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>', 30);

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(
			'<style>'
			. '@page { margin-left: 20mm; margin-right: 50mm; }'
			. 'body { font-family: dejavusans; font-size: 10pt; } p { text-align: justify; margin: 0 0 2mm; }'
			. '.panel { background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; }'
			. '</style>'
			. '<p>An introduction written left to right, before the document turns right to left.</p>'
		);
		$this->mpdf->WriteHTML('<html dir="rtl"><div class="panel">' . $paragraphs . '</div>');
	}
}
