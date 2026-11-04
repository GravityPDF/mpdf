<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;

/**
 * A backup font the substitution scan tries and passes over leaves nothing in the document, whether its
 * metrics were cached or not.
 *
 * The document font is DejaVu Sans, which lacks the Chinese, Thai, Khmer and Myanmar below.
 */
class BackupFontTrialTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

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
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		/** @var \SplFileInfo $item */
		foreach ($iterator as $item) {
			if ($item->isDir()) {
				rmdir($item->getPathname());
			} else {
				unlink($item->getPathname());
			}
		}

		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The first render fills the cache and the second reads it; both give the same fonts the same
	 * numbers, so the same bytes. Garuda lacks the Chinese, which Sun-ExtA has.
	 *
	 * @param string[] $backupSubsFont
	 * @param string   $html
	 * @param string[] $fonts          The fonts the document should hold
	 *
	 * @dataProvider documentProvider
	 */
	public function testAColdCacheGivesTheSameDocumentAsAWarmOne(array $backupSubsFont, $html, array $fonts)
	{
		list($coldFonts, $coldPdf) = $this->render($backupSubsFont, $html);
		list($warmFonts, $warmPdf) = $this->render($backupSubsFont, $html);

		$this->assertSame($fonts, array_keys($warmFonts));
		$this->assertSame(array_keys($warmFonts), array_keys($coldFonts));
		$this->assertSame($warmPdf, $coldPdf);
	}

	/**
	 * @return array[] Backup fonts, the HTML, and the fonts the document holds
	 */
	public function documentProvider()
	{
		return [
			'one font tried before the one used' => [
				['garuda', 'sun-exta'],
				'<p>Hello 你好</p>',
				['dejavusans', 'sun-exta'],
			],
			// GravityPDF/mpdf#834: DejaVu Sans Condensed, FreeSans, Dhyana and FreeSerif draw nothing here
			'several scripts' => [
				['dejavusanscondensed', 'freesans', 'garuda', 'dhyana', 'khmeros', 'tharlon', 'freeserif', 'sun-exta'],
				'<p>Thai: ภาษาไทย</p><p>Khmer: ភាសាខ្មែរ</p><p>Myanmar: မြန်မာဘာသာ</p><p>日本語</p>',
				['dejavusans', 'garuda', 'khmeros', 'tharlon', 'sun-exta'],
			],
		];
	}

	/**
	 * @param string[] $backupSubsFont
	 * @param string   $html
	 *
	 * @return array [Mpdf::$fonts, the PDF without its dates and file identifier]
	 */
	private function render(array $backupSubsFont, $html)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'default_font' => 'dejavusans',
			'backupSubsFont' => $backupSubsFont,
			'useSubstitutions' => true,
		]);
		$mpdf->SetCompression(false);

		$mpdf->WriteHTML($html);

		$pdf = preg_replace(
			['~/(CreationDate|ModDate) \(D:[^)]*\)~', '~/ID \[<[0-9a-f]+> <[0-9a-f]+>\]~'],
			['/$1 ()', '/ID []'],
			$mpdf->OutputBinaryData()
		);

		return [$mpdf->fonts, $pdf];
	}

}
