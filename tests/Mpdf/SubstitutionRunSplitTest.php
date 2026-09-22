<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * How a text token holding more than one run of characters the document font cannot draw is split
 * between the backup fonts.
 *
 * The document font is DejaVu Sans, which has Latin and Cyrillic but no Thai, CJK or people. The
 * backup fonts are Garuda, which has the Thai, Sun-ExtA, which has the CJK, and Noto Emoji, which has
 * the people; none of them has what another is here for. Sun-ExtB holds Plane 2 and is reached as the
 * SIP font rather than as a backup.
 */
class SubstitutionRunSplitTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param string $text   The paragraph's text
	 * @param array  $config Merged over the configuration below
	 *
	 * @return \Mpdf\SubstitutionPassCountingMpdf
	 */
	private function render($text, array $config = [])
	{
		// No font packages, so nothing the suite installs is put ahead of these
		$mpdf = new SubstitutionPassCountingMpdf(array_merge([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../../packages/Dejavu-Family/fonts',
				__DIR__ . '/../../packages/Emoji/fonts',
				__DIR__ . '/../../packages/Garuda/fonts',
				__DIR__ . '/../../packages/SunExt/fonts',
			],
			'fontdata' => [
				'dejavusans' => ['R' => 'DejaVuSans.ttf'],
				'garuda' => ['R' => 'Garuda.ttf'],
				'notoemoji' => ['R' => 'NotoEmoji-Regular.ttf'],
				'sunexta' => ['R' => 'Sun-ExtA.ttf'],
				'sunextb' => ['R' => 'Sun-ExtB.ttf'],
				'sipfont' => ['R' => 'Sun-ExtB.ttf'],
			],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['garuda', 'sunexta', 'notoemoji'],
		], $config));
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		return $mpdf;
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return string
	 */
	private function text(array $codepoints)
	{
		return implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints));
	}

	/**
	 * @param string $text   The paragraph's text
	 * @param array  $config Merged over the configuration render() uses
	 *
	 * @return array[] Each piece of the line as it was drawn: [font family, its codepoints]
	 */
	private function drawn($text, array $config = [])
	{
		return $this->render($text, $config)->drawnPieces();
	}

	/**
	 * Three scripts in one paragraph, each in the backup font that has it, with the Latin between them
	 * left in the document font
	 */
	public function testEveryRunOfAMixedScriptParagraphGoesToItsOwnFont()
	{
		$pieces = $this->drawn($this->text([0x61, 0x0E01, 0x0E02, 0x62, 0x4E2D, 0x6587, 0x63, 0x0E01, 0x64]));

		$this->assertSame([
			['dejavusans', [0x61]],
			['garuda', [0x0E01, 0x0E02]],
			['dejavusans', [0x62]],
			['sunexta', [0x4E2D, 0x6587]],
			['dejavusans', [0x63]],
			['garuda', [0x0E01]],
			['dejavusans', [0x64]],
		], $pieces);
	}

	/**
	 * An emoji between words goes to the emoji font on its own, as many times as the paragraph holds one
	 */
	public function testEmojiBesideTextGoToTheEmojiFont()
	{
		$pieces = $this->drawn($this->text([0x61, 0x1F468, 0x62, 0x1F469, 0x63]));

		$this->assertSame([
			['dejavusans', [0x61]],
			['notoemoji', [0x1F468]],
			['dejavusans', [0x62]],
			['notoemoji', [0x1F469]],
			['dejavusans', [0x63]],
		], $pieces);
	}

	/**
	 * A sequence joined by U+200D is one emoji, so the second of them is moved whole: a pass that lost
	 * count of where it was in the text after the first would cut this one apart
	 */
	public function testASecondEmojiSequenceIsMovedWhole()
	{
		$family = [0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467];
		$pieces = $this->drawn($this->text(array_merge([0x61], $family, [0x62], $family, [0x63])));

		// The joiners are dropped after the substitution, as they are for any text drawn without OTL
		$this->assertSame([
			['dejavusans', [0x61]],
			['notoemoji', [0x1F468, 0x1F469, 0x1F467]],
			['dejavusans', [0x62]],
			['notoemoji', [0x1F468, 0x1F469, 0x1F467]],
			['dejavusans', [0x63]],
		], $pieces);
	}

	/**
	 * Each run of Plane 2 text reaches the SIP font, which is tried before the backup fonts
	 */
	public function testEveryRunOfPlaneTwoTextGoesToTheSipFont()
	{
		$pieces = $this->drawn(
			$this->text([0x61, 0x20000, 0x20001, 0x62, 0x20002, 0x63]),
			['backupSIPFont' => 'sunextb']
		);

		$this->assertSame([
			['dejavusans', [0x61]],
			['sunextb', [0x20000, 0x20001]],
			['dejavusans', [0x62]],
			['sunextb', [0x20002]],
			['dejavusans', [0x63]],
		], $pieces);
	}

	/**
	 * A font registered as BMP-only is read without the part of its character map above the Basic
	 * Multilingual Plane, so Plane 2 text is moved out of it however much of it the file has. Sun-ExtB
	 * is registered twice here, so that the font the text leaves and the one it reaches are the same
	 * file and only the registration differs.
	 */
	public function testPlaneTwoTextLeavesABmpOnlyFont()
	{
		$config = ['default_font' => 'sunextb', 'backupSIPFont' => 'sipfont'];
		$text = $this->text([0x20000, 0x20001, 0x20002]);

		$this->assertSame(
			[['sunextb', [0x20000, 0x20001, 0x20002]]],
			$this->drawn($text, $config),
			'the document font has Plane 2 and draws it'
		);
		$this->assertSame(
			[['sipfont', [0x20000, 0x20001, 0x20002]]],
			$this->drawn($text, array_merge($config, ['BMPonly' => ['sunextb']]))
		);
	}

	/**
	 * Text the document font draws itself is left in one piece, and text nothing can draw is left where
	 * it is rather than being moved
	 */
	public function testTextThatNeedsNoSubstitutionIsLeftAlone()
	{
		$latin = $this->text([0x61, 0x62, 0x63]);

		$this->assertSame([['dejavusans', [0x61, 0x62, 0x63]]], $this->drawn($latin));
		$this->assertSame(
			[['dejavusans', [0x61, 0x0E01, 0x62]]],
			$this->drawn($this->text([0x61, 0x0E01, 0x62]), ['useSubstitutions' => false])
		);
	}

	/**
	 * The scan splits a token into every run it holds in one pass, rather than looking at the rest of
	 * the token again for each one. The first paragraph is there to put the backup fonts in the
	 * document, since the scan stops wherever taking another run would add one.
	 */
	public function testATokenIsSplitIntoAllItsRunsInOnePass()
	{
		$text = $this->text([0x61, 0x0E01, 0x62, 0x4E2D, 0x63, 0x1F468, 0x64, 0x0E01, 0x65]);

		$mpdf = $this->render($text);
		$mpdf->substitutionPasses = 0;
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		$this->assertSame(2, $mpdf->substitutionPasses, 'one pass takes the four runs, one finds nothing left');
	}

}
