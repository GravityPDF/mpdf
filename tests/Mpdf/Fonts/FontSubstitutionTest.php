<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;

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

		$this->substitution = new FontSubstitution($this->mpdf);
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
	 * A family fontdata does not name has no widths
	 */
	public function testAnUnknownFamilyHasNoWidths()
	{
		$this->assertNull($this->substitution->widths('nosuchfont'));
	}

}
