<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * Text a core font cannot draw moves into a backup font the first time that font is used, with nothing
 * of it in the font cache yet (#829).
 */
class CoreFontBackupSubstitutionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @var string A font cache no render has written to before the test
	 */
	private $tempDir;

	/**
	 * Gives each test an empty tempDir of its own
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-core-font-backup-' . uniqid('', true);
		mkdir($this->tempDir);
	}

	/**
	 * Removes the tempDir and what the renders wrote to it
	 */
	protected function tear_down()
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The Cyrillic is drawn in DejaVu Sans on the first render, as it is on the second, and the Latin
	 * text stays in Helvetica both times
	 */
	public function testTheBackupFontDrawsWhatTheCoreFontCannotOnTheFirstRender()
	{
		foreach ([1, 2] as $render) {
			$pdf = $this->render('<p style="font-family: chelvetica">Hello Привет</p>', [
				'mode' => 'utf-8',
				'tempDir' => $this->tempDir,
				'fontRegistry' => new FontRegistry([]),
				'fontDir' => [__DIR__ . '/../../packages/Dejavu-Family/fonts'],
				'fontdata' => ['dejavusans' => ['R' => 'DejaVuSans.ttf']],
				'default_font' => 'chelvetica',
				'useSubstitutions' => true,
				'backupSubsFont' => ['dejavusans'],
			]);

			$this->assertSame(['Helvetica', 'DejaVuSans'], $this->fontsDrawnIn($pdf), 'Render ' . $render);
		}
	}

	/**
	 * @param string $pdf
	 *
	 * @return string[] The base name of each font the first page draws text in, in the order it first
	 *                  draws in it, without a subset's tag. A font selected but never drawn in, such as
	 *                  the default font the page starts in, is left out.
	 */
	private function fontsDrawnIn($pdf)
	{
		preg_match_all('#/F(\d+) [\d.]+ Tf|\) Tj|> Tj#', $this->pageContents($pdf)[0], $operators, PREG_SET_ORDER);

		$drawn = [];
		$selected = null;
		foreach ($operators as $operator) {
			if (isset($operator[1])) {
				$selected = $operator[1];
			} elseif ($selected !== null) {
				$drawn[$selected] = true;
			}
		}

		$fonts = [];
		foreach (array_keys($drawn) as $resource) {
			preg_match('#/F' . $resource . ' (\d+) 0 R#', $pdf, $ref);
			preg_match('#/BaseFont /(?:[A-Z]+\+)?(\S+)#', $this->object($pdf, $ref[1]), $name);
			$fonts[] = $name[1];
		}

		return $fonts;
	}

}
