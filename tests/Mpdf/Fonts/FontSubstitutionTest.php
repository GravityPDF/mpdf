<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Mpdf;
use Psr\Log\NullLogger;

/**
 * What the substitution scan learns of a backup font, asked of FontSubstitution directly.
 *
 * DejaVu Sans is the document font; Sun-ExtA, which has the Chinese DejaVu lacks, is not loaded.
 */
class FontSubstitutionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Fonts\FontSubstitution
	 */
	private $substitution;

	/**
	 * Builds a document in DejaVu Sans
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf([
			'mode' => 'utf-8',
			'default_font' => 'dejavusans',
		]);
		$this->mpdf->SetFont('dejavusans');

		$this->substitution = new FontSubstitution(
			$this->mpdf,
			new FontCache(new Cache($this->mpdf->tempDir . '/mpdf/ttfontdata')),
			new NullLogger()
		);
	}

	/**
	 * The widths of a font the document has not loaded come back without it being added. The font is
	 * loaded and dropped first, so the widths cannot simply be the document's entry for it
	 */
	public function testTheWidthsOfAFontComeBackWithoutAddingIt()
	{
		$this->mpdf->AddFont('sun-exta');
		$this->substitution->widths('sun-exta');
		$this->mpdf->fonts = array_intersect_key($this->mpdf->fonts, ['dejavusans' => true]);

		$cw = $this->substitution->widths('sun-exta');

		$this->assertTrue($this->mpdf->_charDefined($cw, 0x4F60));
		$this->assertSame(['dejavusans'], array_keys($this->mpdf->fonts));
	}

	/**
	 * A font the document has loaded answers with its own widths
	 */
	public function testALoadedFontGivesTheDocumentsWidths()
	{
		$this->assertSame($this->mpdf->fonts['dejavusans']['cw'], $this->substitution->widths('dejavusans'));
	}

	/**
	 * An Adobe CJK font's widths are the ones AddCJKFont() gives it, and asking for them adds none of
	 * its styles
	 */
	public function testTheWidthsOfACjkFontComeBackWithoutAddingIt()
	{
		$cw = $this->substitution->widths('big5');

		$this->assertSame(['dejavusans'], array_keys($this->mpdf->fonts));

		$this->mpdf->AddFont('big5');
		$this->assertSame($this->mpdf->fonts['big5']['cw'], $cw);
	}

	/**
	 * A fonttrans alias has no widths of its own, and asking does not add the font it stands for
	 */
	public function testAnAliasHasNoWidths()
	{
		$this->mpdf->fonttrans['chinesealias'] = 'sun-exta';

		$this->assertNull($this->substitution->widths('chinesealias'));
		$this->assertSame(['dejavusans'], array_keys($this->mpdf->fonts));
	}

	/**
	 * A Plane 2 character goes to the current font's sip-ext font, then to backupSIPFont, whichever
	 * has it first; anything outside Plane 2 goes to neither
	 *
	 * @dataProvider sipFontProvider
	 *
	 * @param string      $family The current font
	 * @param int         $char
	 * @param string|null $font   The SIP font it goes to
	 */
	public function testAPlaneTwoCharacterGoesToTheSipFontThatHasIt($family, $char, $font)
	{
		$mpdf = $this->mpdf;
		$mpdf->CurrentFont = ['sipext' => isset($mpdf->fontdata[$family]['sip-ext']) ? $mpdf->fontdata[$family]['sip-ext'] : ''];
		$mpdf->currentfontfamily = $family;

		$this->assertSame($font, $this->substitution->sipFontFor($char));
	}

	/**
	 * @return array[]
	 */
	public function sipFontProvider()
	{
		return [
			'Extension B, through backupSIPFont' => ['dejavusans', 0x20BB7, 'sun-extb'],
			'Extension B, through sip-ext' => ['sun-exta', 0x20BB7, 'sun-extb'],
			'Extension E, which Sun-ExtB lacks' => ['dejavusans', 0x2B820, null],
			'Plane 3' => ['dejavusans', 0x30000, null],
			'the Basic Multilingual Plane' => ['dejavusans', 0x4E00, null],
			'backupSIPFont is the current font' => ['sun-extb', 0x20BB7, null],
		];
	}

	/**
	 * A family fontdata does not name has no widths
	 */
	public function testAnUnknownFamilyHasNoWidths()
	{
		$this->assertNull($this->substitution->widths('nosuchfont'));
	}

}
