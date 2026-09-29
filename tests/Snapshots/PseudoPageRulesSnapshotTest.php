<?php

namespace Snapshots;

/**
 * The style sheet has :left and :right page rules and no plain @page rule. Right pages leave a deep top margin and a
 * wide outer margin on the right; left pages mirror them at the bottom and on the left. Text runs over three pages
 * inside a shaded panel, so each page's margins show
 *
 * @group snapshot
 */
class PseudoPageRulesSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'pseudo-page-rules';
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
		$paragraphs = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>', 24);

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(
			'<style>'
			. '@page :right { margin: 50mm 60mm 20mm 20mm; } @page :left { margin: 20mm 20mm 50mm 60mm; }'
			. 'body { font-family: dejavusans; font-size: 10pt; } p { text-align: justify; margin: 0 0 2mm; }'
			. '.panel { background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; }'
			. '</style>'
			. '<div class="panel">' . $paragraphs . '</div>'
		);
	}
}
