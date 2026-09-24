<?php

namespace Snapshots;

/**
 * A translucent panel in two columns over a coloured page, with a translucent box of another colour inside it. Each
 * shows one even tint, with no darker band where the lines it was laid down in meet
 *
 * @group snapshot
 */
class ColumnTranslucentBackgroundSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'column-translucent-background';
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
		$paragraphs = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>', 5);

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(
			'<style>body { background-color: #cc0033; font-family: dejavusans; font-size: 10pt; }</style>'
			. '<columns column-count="2" />'
			. '<div style="background-color: rgba(255, 255, 255, 0.5); padding: 3mm">'
			. $paragraphs
			. '<div style="background-color: rgba(0, 0, 255, 0.3); margin: 0 4mm; padding: 2mm">' . $paragraphs . '</div>'
			. $paragraphs
			. '</div>'
			. '<columns column-count="0" />'
		);
	}
}
