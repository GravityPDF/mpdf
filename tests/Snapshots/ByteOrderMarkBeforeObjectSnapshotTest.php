<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class ByteOrderMarkBeforeObjectSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'byte-order-mark-before-object';
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
		$bom = "\xEF\xBB\xBF";
		$image = '<img src="' . __DIR__ . '/../data/img/grey-gradient.jpg" style="width: 30mm; height: 10mm" />';

		$samples = [
			'An image' => $bom . $image,
			'A barcode' => $bom . '<barcode code="1234" />',
			'A text input' => $bom . '<input type="text" value="Filled in" />',
			'A dotted leader before a word' => '<div style="width: 80mm">' . $bom . '<dottab />after</div>',
			'A rule' => $bom . '<hr style="width: 80mm; margin: 0" />',
			'A meter, half full' => $bom . '<meter value="1" max="2" style="width: 40mm; height: 5mm"></meter>',
			'A text circle' => $bom . '<textcircle r="8mm" top-text="Circle" />',
			'An image in a table cell' => '<table><tr><td style="border: 0.2mm solid #808080">' . $bom . $image . '</td></tr></table>',
			'An image after a paragraph holding only the mark' => '<p>' . $bom . '</p><p>' . $image . '</p>',
		];

		ob_start();
		?>
		<style>
			p { margin: 0; }
			p.note { color: #606060; margin-bottom: 3mm; }
			p.caption { font-weight: bold; margin-top: 4mm; }
		</style>

		<h1>mPDF</h1>
		<h2>Objects after a byte order mark</h2>

		<p class="note">Each sample below starts with U+FEFF, as a template saved with a byte order mark would
			output after a style block. The mark draws nothing and no text is left around it. The object named
			by each caption has to appear under that caption.</p>

		<?php foreach ($samples as $caption => $sample) { ?>
			<p class="caption"><?php echo $caption; ?></p><?php echo $sample; ?>
		<?php } ?>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['default_font' => 'dejavusanscondensed']);
		$this->mpdf->WriteHTML($html);
	}
}
