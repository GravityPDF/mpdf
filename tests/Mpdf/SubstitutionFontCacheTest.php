<?php

namespace Mpdf;

/**
 * Character substitution has to work the first time a fallback font is used, when its widths are in
 * neither the loaded fonts nor the font cache.
 */
class SubstitutionFontCacheTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $tempDir;

	/**
	 * Gives each test a temporary directory of its own, so its font cache starts empty.
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-substitution-' . uniqid('', true);
		mkdir($this->tempDir);
	}

	/**
	 * Removes the temporary directory and the font cache written into it.
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
	 * A CJK Extension B character goes to backupSIPFont, and the characters after it that Garuda lacks
	 * are still substituted: the arrow from the core Symbol font, the a-macron from backupSubsFont.
	 */
	public function testCharactersAfterAPlaneTwoCharacterAreSubstitutedFromAnEmptyFontCache()
	{
		$mpdf = $this->createMpdf();
		$mpdf->WriteHTML('<p style="font-family: garuda">Next &#x20000; then &#x21D2; &#x101;</p>');

		$this->assertContains(0x20000, call_user_func_array('array_merge', $mpdf->fonts['sun-extb']['subsets']));
		$this->assertArrayHasKey('csymbol', $mpdf->fonts);
		$this->assertArrayNotHasKey(0x21D2, $mpdf->fonts['garuda']['subset']);
		$this->assertArrayHasKey(0x101, $mpdf->fonts['dejavusanscondensed']['subset']);
	}

	/**
	 * Cyrillic text in a core font goes to the substitution font.
	 */
	public function testCoreFontCharactersAreSubstitutedFromAnEmptyFontCache()
	{
		$mpdf = $this->createMpdf(['default_font' => 'chelvetica']);
		$mpdf->WriteHTML('<p>Hello Привет</p>');

		$this->assertArrayHasKey(0x41F, $mpdf->fonts['dejavusanscondensed']['subset']);
	}

	/**
	 * @param array $config Configuration to merge over the defaults below
	 *
	 * @return \Mpdf\Mpdf A document whose font cache is empty, with distinct SIP and substitution fonts
	 */
	private function createMpdf(array $config = [])
	{
		return new Mpdf(array_merge([
			'tempDir' => $this->tempDir,
			'useSubstitutions' => true,
			'backupSIPFont' => 'sun-extb',
			'backupSubsFont' => ['dejavusanscondensed'],
		], $config));
	}
}
