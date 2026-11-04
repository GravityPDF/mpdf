<?php

namespace Snapshots;

use Mpdf\Fonts\FontRegistry;

/**
 * Text in a core font that the core font cannot draw, moved into a backup font that nothing has cached
 * yet (#829). The backup font is registered under a key of its own and its cache entries are removed
 * before each render, so every run of the snapshot is the first that font has had.
 *
 * @group snapshot
 */
class CoreFontBackupFirstRenderSnapshotTest extends Snapshot
{
	/**
	 * The fontdata key the backup font is registered under, which names its cache entries
	 */
	const BACKUP_FONT = 'corefontbackupfirstrender';

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'core-font-backup-first-render';
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
		$this->mpdf = $this->createMpdf([
			// No font packages, whose own backup fonts would be tried ahead of this one
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../../packages/Dejavu-Family/fonts'],
			'fontdata' => [self::BACKUP_FONT => ['R' => 'DejaVuSans.ttf']],
			'default_font' => 'chelvetica',
			'useSubstitutions' => true,
			'backupSubsFont' => [self::BACKUP_FONT],
		]);

		$this->forgetBackupFont();

		ob_start();
		?>
		<style>
			p.note { color: #606060; }
			.sample { font-size: 16pt; margin-bottom: 4mm; }
		</style>

		<h1>mPDF</h1>
		<h2>A backup font for a core font, on its first render</h2>

		<p class="note">Each sample below is set in a PDF core font, whose windows-1252 encoding has no
			Cyrillic or Greek. That text is drawn in the backup font, DejaVu Sans, and the Latin text around
			it stays in the core font. Nothing of DejaVu Sans is in the font cache when the document is made,
			so this is the first time the backup font is used. No sample may draw a question mark in place
			of a Cyrillic or Greek letter.</p>

		<h3>Helvetica</h3>
		<p class="sample" style="font-family: chelvetica">Hello Привет, and Γειά σου</p>

		<h3>Times</h3>
		<p class="sample" style="font-family: ctimes">Hello Привет, and Γειά σου</p>

		<h3>Courier</h3>
		<p class="sample" style="font-family: ccourier">Hello Привет, and Γειά σου</p>
		<?php
		$this->mpdf->WriteHTML(ob_get_clean());
	}

	/**
	 * Removes what an earlier render cached of the backup font, so this render reads it afresh
	 */
	private function forgetBackupFont()
	{
		$cached = glob($this->mpdf->tempDir . '/mpdf/ttfontdata/' . self::BACKUP_FONT . '.*');
		foreach ($cached ?: [] as $file) {
			unlink($file);
		}
	}
}
