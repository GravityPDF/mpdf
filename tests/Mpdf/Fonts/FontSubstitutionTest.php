<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;
use Mpdf\Unicode\Emoji;

/**
 * The choices behind moving text into a backup font, asked of FontSubstitution directly.
 *
 * DejaVu Sans is the document font. Noto Emoji is a black and white emoji font and TestEmoji-CBDT one
 * mPDF draws in colour.
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
	 * Builds a document in DejaVu Sans with both emoji fonts as backups, black and white first
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../../data/ttf/color',
				__DIR__ . '/../../../packages/Emoji/fonts',
				__DIR__ . '/../../../packages/Dejavu-Family/fonts',
			],
			'fontdata' => [
				'dejavusans' => ['R' => 'DejaVuSans.ttf'],
				'notoemoji' => ['R' => 'NotoEmoji-Regular.ttf', 'useOTL' => 0xFF],
				'coloremoji' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF],
			],
			'default_font' => 'dejavusans',
			'backupSubsFont' => ['notoemoji', 'coloremoji'],
		]);
		$this->mpdf->SetFont('dejavusans');

		$this->substitution = new FontSubstitution($this->mpdf);
	}

	/**
	 * A run starting with an emoji that asks for colour tries the colour font first
	 */
	public function testColourPutsTheColourFontsFirst()
	{
		$this->assertSame(
			[['coloremoji', 'notoemoji'], ['coloremoji']],
			$this->substitution->backupFontOrder(Emoji::PRESENTATION_EMOJI)
		);
	}

	/**
	 * A run starting with an emoji that asks for text tries the fonts without colour first
	 */
	public function testTextPutsTheOtherFontsFirst()
	{
		$this->assertSame(
			[['notoemoji', 'coloremoji'], ['notoemoji']],
			$this->substitution->backupFontOrder(Emoji::PRESENTATION_TEXT)
		);
	}

	/**
	 * A run that asks for nothing takes the fonts in the order they were configured, and loads none
	 * of them to find out whether they are in colour
	 */
	public function testNoPresentationKeepsTheConfiguredOrder()
	{
		$this->assertSame(
			[['notoemoji', 'coloremoji'], []],
			$this->substitution->backupFontOrder(Emoji::PRESENTATION_DEFAULT)
		);
		$this->assertArrayNotHasKey('coloremoji', $this->mpdf->fonts);
	}

	/**
	 * A colour font that is already the current one is not offered the run first: it is the font
	 * the run is moving out of
	 */
	public function testTheCurrentFontIsNotPutFirst()
	{
		$this->mpdf->SetFont('coloremoji');

		$this->assertSame(
			[['notoemoji', 'coloremoji'], []],
			$this->substitution->backupFontOrder(Emoji::PRESENTATION_EMOJI)
		);
	}

	/**
	 * Joiners, selectors and tags draw nothing, so a font lacking them still covers the emoji
	 */
	public function testAFontCoversAnEmojiWithoutItsFormattingCharacters()
	{
		$cw = $this->mpdf->fonts['dejavusans']['cw'];

		$this->assertTrue($this->substitution->fontCovers($cw, [0x1F600, 0xE0067, 0xE007F]));
		$this->assertFalse($this->substitution->fontCovers($cw, [0x1F468]));
	}

	/**
	 * DejaVu lacks the man, so he goes to a backup font whatever he asks for
	 */
	public function testAnEmojiTheCurrentFontLacksWantsAnotherFont()
	{
		$this->assertTrue($this->substitution->emojiWantsAnotherFont([0x1F468], Emoji::PRESENTATION_DEFAULT));
	}

	/**
	 * DejaVu has the grinning face, and nothing asks for another presentation, so it stays
	 */
	public function testAnEmojiTheCurrentFontHasStaysWhereNothingAsksOtherwise()
	{
		$this->assertFalse($this->substitution->emojiWantsAnotherFont([0x1F600], Emoji::PRESENTATION_DEFAULT));
	}

	/**
	 * The widths of a font the document has not loaded come back without it being added
	 */
	public function testTheWidthsOfAFontComeBackWithoutAddingIt()
	{
		$this->substitution->widths('notoemoji');
		$this->mpdf->fonts = array_intersect_key($this->mpdf->fonts, ['dejavusans' => true]);

		$cw = $this->substitution->widths('notoemoji');

		$this->assertTrue($this->substitution->fontCovers($cw, [0x1F468]));
		$this->assertArrayNotHasKey('notoemoji', $this->mpdf->fonts);
	}

	/**
	 * A family fontdata does not name has no widths
	 */
	public function testAnUnknownFamilyHasNoWidths()
	{
		$this->assertNull($this->substitution->widths('nosuchfont'));
	}

}
