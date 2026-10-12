<?php

namespace Snapshots;

/**
 * A header and footer set with SetHeader() and SetFooter() stay on the pages of an @page rule that names none, give way
 * on a named page whose rule names its own, and come back on the unnamed page after it
 *
 * @group snapshot
 */
class PageRuleHeaderSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'page-rule-header';
	}

	/**
	 * Four pages, each captioned with the header and footer it should draw
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 11pt; }
			@page { margin-top: 30mm; }
			@page chapter { header: html_chapter; }
			@page nofooter { footer: _blank; }
		</style>
		<htmlpageheader name="chapter"><div style="text-align: center; color: #cc0000; font-weight: bold;">CHAPTER HEADER</div></htmlpageheader>
		<p>The @page rule only sets margin-top: 30mm. This page should show the header "Header from SetHeader()" and the footer "Footer from SetFooter() 1".</p>
		<div style="page: chapter"><p>The named page "chapter" has header: html_chapter. This page should show the red "CHAPTER HEADER" in place of the header from SetHeader(), and keep the footer "Footer from SetFooter() 2".</p></div>
		<div style="page: auto"><p>Back on the unnamed page. This page should show the header "Header from SetHeader()" again and the footer "Footer from SetFooter() 3".</p></div>
		<div style="page: nofooter"><p>The named page "nofooter" has footer: _blank. This page should show the header "Header from SetHeader()" and no footer.</p></div>';

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetHeader('Header from SetHeader()');
		$this->mpdf->SetFooter('Footer from SetFooter() {PAGENO}');

		$this->mpdf->WriteHTML($html);
	}

}
