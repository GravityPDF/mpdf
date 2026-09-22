<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;

/**
 * A backup font the substitution scan tries and passes over leaves nothing in the document, whether its
 * metrics were cached or not.
 *
 * The document font is DejaVu Sans. Noto Emoji, the first backup font, lacks the Chinese, which Sun-ExtA
 * has; the man is in neither DejaVu nor Sun-ExtA.
 */
class BackupFontTrialTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** 2000-01-01 */
	const CREATION_DATE = 946684800;

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
		list($coldFonts, $coldPdf) = $this->render();
		list($warmFonts, $warmPdf) = $this->render();

		$this->assertSame(['dejavusans', 'sunexta', 'notoemoji'], array_keys($warmFonts));
		$this->assertSame(array_keys($warmFonts), array_keys($coldFonts));
		$this->assertSame($warmPdf, $coldPdf);
	}

	/**
	 * @return array [Mpdf::$fonts, the PDF]
	 */
	private function render()
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
			'backupSubsFont' => ['notoemoji', 'sunexta'],
			'useSubstitutions' => true,
			'exposeVersion' => false,
			'creationDate' => self::CREATION_DATE,
		]);
		$mpdf->SetCompression(false);

		$mpdf->WriteHTML('<p>Hello 你好 &#x1F468;</p>');

		return [$mpdf->fonts, $mpdf->OutputBinaryData()];
	}

}
