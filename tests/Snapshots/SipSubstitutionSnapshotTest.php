<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class SipSubstitutionSnapshotTest extends Snapshot
{
	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'sip-substitution';
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
			'backupSIPFont' => 'sun-extb',
			'backupSubsFont' => ['dejavusanscondensed'],
		]);

		// Render with each font for the first time
		foreach (['garuda', 'sun-extb', 'dejavusanscondensed'] as $font) {
			foreach ((array) glob($this->mpdf->tempDir . '/mpdf/ttfontdata/' . $font . '.*') as $file) {
				unlink($file);
			}
		}

		$this->mpdf->WriteHTML('
			<p>The Garuda paragraph below is rendered with the font cache empty for Garuda, Sun-ExtB and DejaVu Sans Condensed.
			The CJK Extension B character U+20000 is set in Sun-ExtB (backupSIPFont), and the characters after it that Garuda lacks are still substituted:
			the double arrow U+21D2 from the core Symbol font, and a-macron U+0101 from DejaVu Sans Condensed (backupSubsFont).
			Before the fix, all three were missing glyphs.</p>
			<p style="font-family: garuda; font-size: 24pt">Next &#x20000; then &#x21D2; &#x101;</p>
		');
	}
}
