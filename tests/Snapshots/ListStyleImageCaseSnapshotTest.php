<?php

namespace Snapshots;

/**
 * List marker images whose file name has capitals, found by the name as written rather than a lowercased one,
 * which only a case-insensitive disk would find.
 *
 * @group snapshot
 */
class ListStyleImageCaseSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'list-style-image-case';
	}

	/**
	 * Lists whose marker image is named in the longhand, the shorthand and a URL() in capitals, under a caption
	 * saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			ul { margin: 0; font-size: 12pt; }

			ul.longhand { list-style-image: url(img/List-Bullet.png); }
			ul.shorthand { list-style: square url(img/List-Bullet.png) inside; }
			ul.capitals { list-style-image: URL(img/List-Bullet.png); }
		</style>

		<h1>List marker images with capitals in the file name</h1>

		<p class="caption">list-style-image: url(img/List-Bullet.png). Each item's marker is a small blue square with a red centre, not a disc.</p>
		<ul class="longhand">
			<li>First item</li>
			<li>Second item</li>
		</ul>

		<p class="caption">list-style: square url(img/List-Bullet.png) inside. The same marker, inside the text, not a black square.</p>
		<ul class="shorthand">
			<li>First item</li>
			<li>Second item</li>
		</ul>

		<p class="caption">list-style-image: URL(img/List-Bullet.png), with URL in capitals. The same marker as the first list.</p>
		<ul class="capitals">
			<li>First item</li>
			<li>Second item</li>
		</ul>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML($html);
	}

}
