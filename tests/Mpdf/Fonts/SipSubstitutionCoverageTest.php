<?php

namespace Mpdf\Fonts;

use Mpdf\Container\SimpleContainer;
use Mpdf\Fonts\Fixtures\DirectoryFontRegistration;
use Mpdf\RecordingFontFileFinder;
use Mpdf\TextRecordingMpdf;

/**
 * A character above U+1FFFF moves into Sun-ExtB, the SIP font, only where Sun-ExtB has it; the rest
 * are offered to the backup fonts like any other character the paragraph's font lacks.
 *
 * Sun-ExtA and Sun-ExtB are registered, so backupSIPFont is Sun-ExtB and Sun-ExtA's sip-ext font is
 * Sun-ExtB. The only backup font is a test font whose ranges file says it has U+2B820 (CJK Extension
 * E, which Sun-ExtB lacks) and U+30000 (Plane 3). Every family is read from its ranges file, so the
 * finder is asked only for the fonts text is drawn in.
 */
class SipSubstitutionCoverageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use TempFontDirectory;

	/**
	 * Gives each test an empty font cache, and a package holding the test font and its ranges file
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->makeTempFontDirectory('sip-coverage');

		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$font = $this->fontDir . '/angerthas.ttf';
		$this->writeRanges($font . '.ranges.php', [[0x20, 0x7E], [0x2B820, 0x2B820], [0x30000, 0x30000]], filesize($font));
	}

	/**
	 * Removes the font cache and the test font
	 */
	protected function tear_down()
	{
		$this->removeTempFontDirectory();

		parent::tear_down();
	}

	/**
	 * Each character is drawn in the font that has it, and the finder is asked for no other font
	 *
	 * @dataProvider characterProvider
	 *
	 * @param int[]    $codepoints       The paragraph's text
	 * @param string   $family           The paragraph's font
	 * @param bool     $useSubstitutions
	 * @param array[]  $pieces           What is drawn: [font family, its codepoints]
	 * @param string[] $lookups          The font files the finder is asked for once the document is made
	 */
	public function testACharacterIsDrawnInTheFontThatHasIt(array $codepoints, $family, $useSubstitutions, array $pieces, array $lookups)
	{
		$finder = new RecordingFontFileFinder([]);
		$mpdf = $this->render($codepoints, $finder, $family, $useSubstitutions);

		$this->assertSame($pieces, $mpdf->drawnPieces());
		$this->assertSame($lookups, array_values(array_unique($finder->lookups)));
	}

	/**
	 * @return array[]
	 */
	public function characterProvider()
	{
		return [
			'Extension B, which Sun-ExtB has' => [
				[0x41, 0x20BB7, 0x42],
				'dejavusans',
				true,
				[['dejavusans', [0x41]], ['sun-extb', [0x20BB7]], ['dejavusans', [0x42]]],
				['Sun-ExtB.ttf'],
			],
			'Extension E, which only the backup font has' => [
				[0x41, 0x2B820, 0x42],
				'dejavusans',
				true,
				[['dejavusans', [0x41]], ['angerthas', [0x2B820]], ['dejavusans', [0x42]]],
				['angerthas.ttf'],
			],
			'Extension E, which no font has' => [
				[0x41, 0x2B821, 0x42],
				'dejavusans',
				true,
				[['dejavusans', [0x41, 0x2B821]], ['dejavusans', [0x42]]],
				[],
			],
			'Extension B then Extension E, each in its own font' => [
				[0x20BB7, 0x2B820],
				'dejavusans',
				true,
				[['sun-extb', [0x20BB7]], ['angerthas', [0x2B820]]],
				['Sun-ExtB.ttf', 'angerthas.ttf'],
			],
			'Plane 3 alone' => [
				[0x41, 0x30000, 0x42],
				'dejavusans',
				true,
				[['dejavusans', [0x41]], ['angerthas', [0x30000]], ['dejavusans', [0x42]]],
				['angerthas.ttf'],
			],
			'Plane 3 after a Plane 2 character' => [
				[0x41, 0x20BB7, 0x42, 0x30000, 0x43],
				'dejavusans',
				true,
				[['dejavusans', [0x41]], ['sun-extb', [0x20BB7]], ['dejavusans', [0x42]], ['angerthas', [0x30000]], ['dejavusans', [0x43]]],
				['Sun-ExtB.ttf', 'angerthas.ttf'],
			],
			'Plane 3 straight after a Plane 2 character' => [
				[0x41, 0x20BB7, 0x30000, 0x43],
				'dejavusans',
				true,
				[['dejavusans', [0x41]], ['sun-extb', [0x20BB7]], ['angerthas', [0x30000]], ['dejavusans', [0x43]]],
				['Sun-ExtB.ttf', 'angerthas.ttf'],
			],
			'Extension B in Sun-ExtA, without useSubstitutions' => [
				[0x41, 0x20BB7, 0x42],
				'sun-exta',
				false,
				[['sun-exta', [0x41]], ['sun-extb', [0x20BB7]], ['sun-exta', [0x42]]],
				['Sun-ExtA.ttf', 'Sun-ExtB.ttf'],
			],
			'Extension E in Sun-ExtA, without useSubstitutions' => [
				[0x41, 0x2B820, 0x42],
				'sun-exta',
				false,
				[['sun-exta', [0x41, 0x2B820, 0x42]]],
				['Sun-ExtA.ttf'],
			],
			'Extension E in Sun-ExtA' => [
				[0x41, 0x2B820, 0x42],
				'sun-exta',
				true,
				[['sun-exta', [0x41]], ['angerthas', [0x2B820]], ['sun-exta', [0x42]]],
				['Sun-ExtA.ttf', 'angerthas.ttf'],
			],
			'Extension E ahead of Extension B in Sun-ExtA' => [
				[0x2B820, 0x20BB7],
				'sun-exta',
				true,
				[['angerthas', [0x2B820]], ['sun-extb', [0x20BB7]]],
				['Sun-ExtA.ttf', 'angerthas.ttf', 'Sun-ExtB.ttf'],
			],
		];
	}

	/**
	 * @param int[]                   $codepoints
	 * @param RecordingFontFileFinder $finder
	 * @param string                  $family           The paragraph's font
	 * @param bool                    $useSubstitutions
	 *
	 * @return TextRecordingMpdf The document, with the paragraph written
	 */
	private function render(array $codepoints, RecordingFontFileFinder $finder, $family, $useSubstitutions)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'fontRegistry' => new FontRegistry([
				new \Mpdf\Fonts\DejavuFamily\Registration(),
				new \Mpdf\Fonts\SunExt\Registration(),
				new DirectoryFontRegistration($this->fontDir, ['angerthas' => ['R' => 'angerthas.ttf']]),
			]),
			'default_font' => 'dejavusans',
			'useSubstitutions' => $useSubstitutions,
		], new SimpleContainer(['fontFileFinder' => $finder]));
		$mpdf->backupSubsFont = ['angerthas'];
		$finder->lookups = [];

		$mpdf->WriteHTML('<p style="font-family: ' . $family . '">' . implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints)) . '</p>');

		return $mpdf;
	}

}
