<?php

namespace Snapshots;

/**
 * A two-column article with a right-floated and a left-floated image, each wrapping its column's text and kept with
 * those lines when the columns are balanced
 *
 * @group snapshot
 */
class FloatImagesInColumnsSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'float-images-in-columns';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and loading it with content
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$lorem = 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Sed auctor viverra diam. In lacinia lectus. Praesent tincidunt massa in dolor. Morbi viverra leo quis ipsum. In vitae velit. In aliquam nulla nec mi. Sed accumsan, justo id congue fringilla, diam mauris volutpat ligula, sed aliquet elit diam at felis. ';

		$this->mpdf = $this->createMpdf(['mode' => 'c']);
		$this->mpdf->SetBasePath(__DIR__ . '/../data');

		$this->mpdf->WriteHTML('
			<style>
				body { font-family: serif; font-size: 10pt; }
				p { text-align: justify; margin: 0 0 3mm 0; }
				img.right { float: right; width: 32mm; margin: 1mm 0 2mm 3mm; }
				img.left { float: left; width: 26mm; margin: 1mm 3mm 2mm 0; }
			</style>
			<h1>Floated images in columns</h1>
			<columns column-count="2" />
			<p>' . str_repeat($lorem, 3) . '</p>
			<p>Mid-paragraph, ' . $lorem . '<img class="right" src="img/tiger.jpg">' . str_repeat($lorem, 3) . '</p>
			<p><img class="left" src="img/bayeux2.jpg" style="height: 34mm">' . str_repeat($lorem, 3) . '</p>
			<p>' . str_repeat($lorem, 2) . '</p>
			<columns column-count="1" />
			<p>After the columns.</p>
		');
	}
}
