<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class CoreFontSubstitutionSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'core-font-substitution';
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
		$this->mpdf = new \Mpdf\Mpdf([
			'useSubstitutions' => true,
			'default_font' => 'chelvetica',
			'backupSubsFont' => ['dejavusanscondensed'],
		]);

		foreach ((array) glob($this->mpdf->tempDir . '/mpdf/ttfontdata/dejavusanscondensed.*') as $file) {
			unlink($file);
		}

		$this->mpdf->WriteHTML('
			<p>This document is set in the core Helvetica font and rendered with the font cache empty for DejaVu Sans Condensed.
			The Cyrillic word below, which Helvetica lacks, is substituted from DejaVu Sans Condensed (backupSubsFont).
			Before the fix, it was missing on the first render.</p>
			<p style="font-size: 24pt">Hello Привет</p>
		');
	}
}
