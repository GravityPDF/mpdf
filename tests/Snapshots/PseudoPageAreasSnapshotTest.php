<?php

namespace Snapshots;

/**
 * A story on a named page starts in a narrow page area on its first page, then carries on over the wider page areas of
 * its left and right pages, which have different side margins. Two columns follow and are laid out across the page area
 * of each page
 *
 * @group snapshot
 */
class PseudoPageAreasSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'pseudo-page-areas';
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
			. '@page story { margin: 20mm; }'
			. '@page story :first { margin-left: 100mm; }'
			. '@page story :right { margin-left: 30mm; margin-right: 15mm; }'
			. '@page story :left { margin-left: 15mm; margin-right: 30mm; }'
			. 'body { font-family: dejavusans; font-size: 10pt; } p { text-align: justify; margin: 0 0 2mm; }'
			. 'h1 { font-size: 20pt; color: #336699; margin: 0 0 4mm; }'
			. '.panel { background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; margin-bottom: 4mm; }'
			. '</style>'
			. '<div style="page: story">'
			. '<h1>A story that starts in a narrow frame</h1>'
			. '<div class="panel">' . $paragraphs(22) . '</div>'
			. '<columns column-count="2" column-gap="8" />' . $paragraphs(24)
			. '</div>'
		);
	}
}
