<?php

namespace Snapshots;

/**
 * A table of contents with its own @page, in front of an index long enough to run over a page and a closing page.
 * The index paints the contents ahead of time to number its entries; only the contents should end up with the wide
 * margins and the shaded footer of that @page
 *
 * @group snapshot
 */
class IndexAfterSelectedContentsSnapshotTest extends Snapshot
{
	use IndexedPages;

	/**
	 * {@inheritdoc}
	 */
	public function getId()
	{
		return 'index-after-selected-contents';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generatePdf()
	{
		$terms = [];
		for ($i = 1; $i <= 90; $i++) {
			$terms[] = sprintf('Term %02d', $i);
		}

		ob_start();
		?>
		<style>
			@page contents {
				margin-left: 70mm;
				margin-top: 60mm;
				odd-footer-name: html_contentsFooter;
				even-footer-name: html_contentsFooter;
			}
		</style>

		<htmlpagefooter name="contentsFooter">
			<div style="background: #fde0a0; text-align: center;">Contents footer, on the contents page only</div>
		</htmlpagefooter>

		<h1>mPDF</h1>
		<h2>A contents with its own @page, and an index behind it</h2>

		<p>The next page is the contents, with wide margins and a shaded footer from its own @page. The index and
			the closing page after it should have the document's ordinary margins and no shaded footer.</p>

		<tocpagebreak toc-page-selector="contents" toc-preHTML="&lt;h2&gt;Contents&lt;/h2&gt;" />

		<?= $this->heading('The terms') ?>
		<?= $this->covers($terms) ?>
		<pagebreak />

		<?= $this->heading('Index') ?>
		<indexinsert />

		<pagebreak />
		<?= $this->heading('Closing page') ?>
		<p>This page comes after the index, in the document's ordinary margins.</p>
		<?php
		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML(ob_get_clean());
	}
}
