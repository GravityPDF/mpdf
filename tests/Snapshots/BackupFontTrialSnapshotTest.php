<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class BackupFontTrialSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'backup-font-trial';
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
		$backupFonts = ['dejavusanscondensed', 'freesans', 'garuda', 'khmeros'];

		$this->mpdf = new \Mpdf\Mpdf([
			'useSubstitutions' => true,
			'default_font' => 'dejavusans',
			'backupSubsFont' => $backupFonts,
		]);

		// Render with each backup font for the first time
		foreach ($backupFonts as $font) {
			foreach ((array) glob($this->mpdf->tempDir . '/mpdf/ttfontdata/' . $font . '.*') as $file) {
				unlink($file);
			}
		}

		$this->mpdf->WriteHTML('
			<p>This document is set in DejaVu Sans and rendered with the font cache empty for its backup fonts:
			DejaVu Sans Condensed, FreeSans, Garuda and Khmer OS, tried in that order.
			The Thai below is drawn in Garuda and the Khmer in Khmer OS; DejaVu Sans Condensed and FreeSans have neither and draw nothing.
			The last line lists the fonts the document holds, which should be DejaVu Sans, Garuda and Khmer OS only.
			Before the fix, every backup font tried was added to the document on a cold font cache.</p>
			<p style="font-size: 24pt">Thai: ภาษาไทย</p>
			<p style="font-size: 24pt">Khmer: ភាសាខ្មែរ</p>
		');

		$this->mpdf->WriteHTML('<p>Fonts in the document: ' . implode(', ', array_keys($this->mpdf->fonts)) . '</p>');
	}
}
