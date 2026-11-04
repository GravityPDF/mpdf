<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class SipSubstitutionSnapshotTest extends Snapshot
{
	/**
	 * Fonts the document substitutes into, whose cached metrics are removed so it renders with each
	 * of them for the first time.
	 *
	 * @var string[]
	 */
	private $uncachedFonts = ['garuda', 'sun-extb', 'dejavusanscondensed'];

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

		$this->removeCachedMetrics($this->mpdf->tempDir . '/mpdf/ttfontdata');

		$this->mpdf->WriteHTML('
			<p>The Garuda paragraph below is rendered with the font cache empty for Garuda, Sun-ExtB and DejaVu Sans Condensed.
			The CJK Extension B character U+20000 is set in Sun-ExtB (backupSIPFont), and the characters after it that Garuda lacks are still substituted:
			the double arrow U+21D2 from the core Symbol font, and a-macron U+0101 from DejaVu Sans Condensed (backupSubsFont).
			Before the fix, all three were missing glyphs.</p>
			<p style="font-family: garuda; font-size: 24pt">Next &#x20000; then &#x21D2; &#x101;</p>
		');
	}

	/**
	 * Removes every cached metrics file of the fonts the document substitutes into.
	 *
	 * @param string $directory The font cache directory
	 */
	private function removeCachedMetrics($directory)
	{
		foreach ($this->uncachedFonts as $font) {
			foreach ((array) glob($directory . '/' . $font . '.*') as $file) {
				unlink($file);
			}
		}
	}
}
