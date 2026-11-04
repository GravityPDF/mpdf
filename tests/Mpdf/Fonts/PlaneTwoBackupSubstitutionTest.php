<?php

namespace Mpdf\Fonts;

use Mpdf\TextRecordingMpdf;

/**
 * Text after a Plane 2 character is still substituted when nothing of backupSIPFont is in the font
 * cache yet (#828). No font packages are registered, so there is no ranges file to read the fonts from.
 */
class PlaneTwoBackupSubstitutionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use TempFontDirectory;

	/**
	 * Gives the test an empty font cache
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->makeTempFontDirectory('plane-two-backup');
	}

	/**
	 * Removes the font cache
	 */
	protected function tear_down()
	{
		$this->removeTempFontDirectory();

		parent::tear_down();
	}

	/**
	 * The CJK Extension B character goes to backupSIPFont, and what Garuda lacks after it still moves:
	 * the arrow to the core Symbol font and the a-macron to the backup font
	 */
	public function testCharactersAfterAPlaneTwoCharacterAreSubstitutedOnTheFirstRender()
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../../../packages/Dejavu-Family/fonts',
				__DIR__ . '/../../../packages/Garuda/fonts',
				__DIR__ . '/../../../packages/SunExt/fonts',
			],
			'fontdata' => [
				'garuda' => ['R' => 'Garuda.ttf'],
				'sunextb' => ['R' => 'Sun-ExtB.ttf'],
				'dejavusanscondensed' => ['R' => 'DejaVuSansCondensed.ttf'],
			],
			'default_font' => 'garuda',
			'useSubstitutions' => true,
			'backupSIPFont' => 'sunextb',
			'backupSubsFont' => ['dejavusanscondensed'],
		]);

		$mpdf->WriteHTML('<p>Next &#x20000; then &#x21D2; &#x101;</p>');

		$this->assertSame(['garuda', 'sunextb', 'garuda', 'csymbol', 'dejavusanscondensed'], $mpdf->drawnFontFamily);
	}

}
