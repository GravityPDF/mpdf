<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;

/**
 * A backup font the substitution scan tries and passes over leaves nothing in the document, whether its
 * metrics were cached or not.
 *
 * The document font is DejaVu Sans. Noto Emoji lacks the Chinese, which Sun-ExtA has; the man is in
 * neither DejaVu nor Sun-ExtA.
 */
class BackupFontTrialTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** 2000-01-01 */
	const CREATION_DATE = 946684800;

	const CHINESE_AND_MAN = '<p>Hello 你好 &#x1F468;</p>';

	/** The man, asking to be drawn as text */
	const TEXT_MAN = '<p>Hello &#x1F468;&#xFE0E;</p>';

	/**
	 * @var string
	 */
	private $tempDir;

	/**
	 * Gives each test a font cache of its own, empty to begin with
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-backup-font-trial-' . uniqid('', true);
		mkdir($this->tempDir, 0777, true);
	}

	/**
	 * Removes the font cache
	 */
	protected function tear_down()
	{
		foreach ([$this->tempDir . '/mpdf/ttfontdata', $this->tempDir . '/mpdf'] as $directory) {
			foreach (glob($directory . '/*') as $entry) {
				if (is_file($entry)) {
					unlink($entry);
				}
			}

			rmdir($directory);
		}

		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The first render fills the cache and the second reads it; both give the same fonts the same
	 * numbers, so the same bytes
	 */
	public function testAColdCacheGivesTheSameDocumentAsAWarmOne()
	{
		list($coldFonts, $coldPdf) = $this->render(['notoemoji', 'sunexta'], self::CHINESE_AND_MAN);
		list($warmFonts, $warmPdf) = $this->render(['notoemoji', 'sunexta'], self::CHINESE_AND_MAN);

		$this->assertSame(['dejavusans', 'sunexta', 'notoemoji'], array_keys($warmFonts));
		$this->assertSame(array_keys($warmFonts), array_keys($coldFonts));
		$this->assertSame($warmPdf, $coldPdf);
	}

	/**
	 * A run starting with an emoji that asks for a presentation has the scan ask each backup font
	 * whether it draws in colour. Sun-ExtA is asked and passed over, and the document is the one that
	 * never had it as a backup font.
	 */
	public function testABackupFontAskedWhetherItDrawsInColourIsNotAdded()
	{
		list($askedFonts, $askedPdf) = $this->render(['sunexta', 'notoemoji'], self::TEXT_MAN);
		list($fonts, $pdf) = $this->render(['notoemoji'], self::TEXT_MAN);

		$this->assertSame(['dejavusans', 'notoemoji'], array_keys($askedFonts));
		$this->assertSame(array_keys($fonts), array_keys($askedFonts));
		$this->assertSame($pdf, $askedPdf);
	}

	/**
	 * @param string[] $backupSubsFont
	 * @param string   $html
	 *
	 * @return array [Mpdf::$fonts, the PDF]
	 */
	private function render(array $backupSubsFont, $html)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../../../packages/Emoji/fonts',
				__DIR__ . '/../../../packages/Dejavu-Family/fonts',
				__DIR__ . '/../../../packages/SunExt/fonts',
			],
			'fontdata' => [
				'dejavusans' => ['R' => 'DejaVuSans.ttf'],
				'notoemoji' => ['R' => 'NotoEmoji-Regular.ttf', 'useOTL' => 0xFF],
				'sunexta' => ['R' => 'Sun-ExtA.ttf'],
			],
			'default_font' => 'dejavusans',
			'backupSubsFont' => $backupSubsFont,
			'useSubstitutions' => true,
			'exposeVersion' => false,
			'creationDate' => self::CREATION_DATE,
		]);
		$mpdf->SetCompression(false);

		$mpdf->WriteHTML($html);

		return [$mpdf->fonts, $mpdf->OutputBinaryData()];
	}

}
