<?php

namespace Snapshots;

/**
 * A tagged PDF/UA-1 document: headings, a list, a table with headers, a link, an image with alternative text,
 * a table of contents and a running header.
 *
 * @group snapshot
 */
class PdfUaSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'pdfua';
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
		$this->mpdf = $this->createMpdf(['PDFUA' => true, 'title' => 'Quarterly report', 'mode' => 'en-GB']);
		$this->mpdf->h2toc = ['H2' => 0];
		$this->mpdf->SetHTMLHeader('<div style="text-align: right">Quarterly report</div>');

		$this->mpdf->WriteHTML('
			<h1>Quarterly report</h1>
			<p>The quarter in figures, with <a href="https://example.com/report">the full report</a> online.</p>
			<tocpagebreak />
			<h2>Highlights</h2>
			<ul>
				<li>Revenue rose</li>
				<li>Costs fell</li>
			</ul>
			<ol>
				<li>First step</li>
				<li>Second step</li>
			</ol>
			<h2>Figures</h2>
			<table border="1">
				<thead><tr><th>Region</th><th scope="col">Revenue</th></tr></thead>
				<tbody>
					<tr><th scope="row">North</th><td>120</td></tr>
					<tr><th scope="row">South</th><td>95</td></tr>
				</tbody>
			</table>
			<p><img src="' . __DIR__ . '/../data/img/bayeux2.jpg" alt="A panel of the Bayeux Tapestry" width="60" /></p>
			<p lang="fr">Le trimestre en chiffres.</p>
			<div style="page-break-inside: avoid"><p>Kept together</p><p>and read once</p></div>
		');
	}
}
