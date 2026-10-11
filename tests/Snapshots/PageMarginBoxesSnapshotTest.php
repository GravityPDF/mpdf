<?php

namespace Snapshots;

/**
 * The margin boxes of @page rules drawn as each page's header and footer: the plain rule's on every page, a :first
 * rule's content: none and counter style on the first page, and a named page's own boxes. Each page's caption says what
 * its header and footer show.
 *
 * @group snapshot
 */
class PageMarginBoxesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'page-margin-boxes';
	}

	/**
	 * Three pages: the first page, a plain page and a named page
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@page {
				margin: 30mm 20mm;
				@top-left { content: "Margin boxes"; color: #336699; font-weight: bold; }
				@top-center { content: "Running header"; }
				@top-right { content: "Page " counter(page) " of " counter(pages); }
				@bottom-center { content: "- " counter(page) " -"; font-size: 8pt; color: #6c757d; }
			}
			@page :first {
				@top-center { content: none; }
				@bottom-center { content: "Page " counter(page, upper-roman) " in upper-roman"; }
			}
			@page appendix {
				@top-center { content: "Appendix"; font-style: italic; color: #a02020; }
				@bottom-right { content: "Appendix footer"; }
			}

			body { font-family: dejavusans; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p { margin: 0 0 1.5mm 0; }
			p.caption { font-size: 7.5pt; color: #6c757d; }
		</style>

		<h1>@page margin boxes</h1>
		<p>Page 1, the first page. The @page :first rule takes the top-center box's content away and numbers the bottom-center box in upper-roman.</p>
		<p class="caption">Header: "Margin boxes" in bold blue at the left, nothing in the middle, "Page 1 of 3" at the right. Footer: "Page I in upper-roman" small and grey, centred.</p>

		<pagebreak />
		<p>Page 2, a plain page, with every box of the plain @page rule.</p>
		<p class="caption">Header: "Margin boxes" in bold blue at the left, "Running header" in the middle, "Page 2 of 3" at the right. Footer: "- 2 -" small and grey, centred.</p>

		<div style="page: appendix">
			<p>Page 3, the named page appendix, whose rule gives the top-center box its own content and style and adds a bottom-right box.</p>
			<p class="caption">Header: "Margin boxes" in bold blue at the left, "Appendix" in italic red in the middle, "Page 3 of 3" at the right. Footer: "Page III in upper-roman" small and grey in the middle, as mPDF takes the first page of a named page for a :first page too, and "Appendix footer" at the right.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
