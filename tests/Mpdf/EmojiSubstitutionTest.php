<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * Which font an emoji is drawn in.
 *
 * The document font is DejaVu Sans unless a test says otherwise. It has the grinning face, the heart,
 * the digits, the joiner and both selectors, but no people, flags or keycap. The backup fonts are Noto
 * Emoji, which is black and white, and TestEmoji-COLRv0 and TestEmoji-CBDT, which are in colour. All
 * carry the same ligatures - a family, a flag, a keycap and a thumb with its skin tone - and none has
 * GDEF.
 */
class EmojiSubstitutionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param int[]    $codepoints     The paragraph's text
	 * @param string[] $backupSubsFont
	 * @param array    $config         Merged over the configuration below
	 *
	 * @return array[] Each piece of the line as it was drawn: [font family, its codepoints]
	 */
	private function drawn(array $codepoints, array $backupSubsFont, array $config = [])
	{
		// No font packages, so the one emoji font the suite installs is not put ahead of these
		$mpdf = new TextRecordingMpdf(array_merge([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../data/ttf/color',
				__DIR__ . '/../../packages/Emoji/fonts',
				__DIR__ . '/../../packages/Dejavu-Family/fonts',
				__DIR__ . '/../../packages/SunExt/fonts',
			],
			'fontdata' => [
				'dejavusans' => ['R' => 'DejaVuSans.ttf'],
				'notoemoji' => ['R' => 'NotoEmoji-Regular.ttf', 'useOTL' => 0xFF],
				'coloremoji' => ['R' => 'TestEmoji-COLRv0.ttf', 'useOTL' => 0xFF],
				'bitmapemoji' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF],
				'sunextb' => ['R' => 'Sun-ExtB.ttf'],
			],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => $backupSubsFont,
		], $config));
		$mpdf->WriteHTML('<p>' . implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints)) . '</p>');

		$pieces = [];
		foreach (array_keys($mpdf->drawnText) as $i) {
			$pieces[] = [$mpdf->drawnFontFamily[$i], $mpdf->drawnCodepoints($i)];
		}

		return $pieces;
	}

	/**
	 * DejaVu has the joiner, but the family still goes to the emoji font whole: split at the joiner,
	 * no GSUB could join it.
	 */
	public function testAZwjSequenceReachesTheEmojiFontWhole()
	{
		$pieces = $this->drawn([0x61, 0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467, 0x62], ['notoemoji']);

		$this->assertCount(3, $pieces);
		$this->assertSame(['dejavusans', [0x61]], $pieces[0]);
		$this->assertSame('notoemoji', $pieces[1][0]);
		$this->assertCount(1, $pieces[1][1], 'the family is one glyph');
		$this->assertSame(['dejavusans', [0x62]], $pieces[2]);
	}

	/**
	 * DejaVu has the digit and the selector and lacks only U+20E3, which is not enough of a keycap
	 */
	public function testAKeycapGoesToTheFontThatHasAllOfIt()
	{
		$pieces = $this->drawn([0x31, 0xFE0F, 0x20E3], ['notoemoji']);

		$this->assertCount(1, $pieces);
		$this->assertSame('notoemoji', $pieces[0][0]);
		$this->assertCount(1, $pieces[0][1], 'the keycap is one glyph');
	}

	/**
	 * The two regional indicators of a flag, and a skin tone after the emoji it colours, go to the
	 * colour font with the rest of their sequence rather than being left in the font before it
	 */
	public function testAFlagAndASkinToneStayWithTheEmojiTheyQualify()
	{
		$pieces = $this->drawn([0x1F1E6, 0x1F1FA, 0x20, 0x1F44D, 0x1F3FD], ['coloremoji']);

		$this->assertSame('coloremoji', $pieces[0][0]);
		$this->assertCount(3, $pieces[0][1], 'the flag, the space the run carries, and the thumb');
	}

	/**
	 * The grinning face has Emoji_Presentation, so it asks for colour without a selector. DejaVu can
	 * draw it, and does, until there is a colour font to draw it in - which is preferred over a black
	 * and white emoji font listed before it.
	 */
	public function testAnEmojiThatAsksForColourIsDrawnInAColourFontWhereThereIsOne()
	{
		$this->assertSame([['dejavusans', [0x1F600]]], $this->drawn([0x1F600], ['notoemoji']));
		$this->assertSame([['coloremoji', [0x1F600]]], $this->drawn([0x1F600], ['notoemoji', 'coloremoji']));
	}

	/**
	 * The heart is text by default. It stays in DejaVu on its own and goes to the colour font with
	 * U+FE0F, which is not drawn in either.
	 */
	public function testASelectorAsksForColourAndIsNotDrawn()
	{
		$this->assertSame([['dejavusans', [0x2764]]], $this->drawn([0x2764], ['coloremoji']));
		$this->assertSame([['coloremoji', [0x2764]]], $this->drawn([0x2764, 0xFE0F], ['coloremoji']));
		$this->assertSame([['dejavusans', [0x2764]]], $this->drawn([0x2764, 0xFE0F], ['notoemoji']), 'with no colour font the heart stays where it was');
	}

	/**
	 * U+FE0E asks for text: DejaVu keeps a grinning face it has, and a thumb it lacks goes to the black
	 * and white font ahead of the colour one listed first
	 */
	public function testATextSelectorKeepsAnEmojiOutOfTheColourFont()
	{
		$this->assertSame([['dejavusans', [0x1F600]]], $this->drawn([0x1F600, 0xFE0E], ['coloremoji']));

		$pieces = $this->drawn([0x1F44D, 0xFE0E], ['coloremoji', 'notoemoji']);
		$this->assertSame('notoemoji', $pieces[0][0]);
	}

	/**
	 * Where the document font is itself a colour font, an emoji it has that asks for colour stays in
	 * it, even with another colour font among the backups
	 */
	public function testAColourDocumentFontKeepsAnEmojiThatAsksForColour()
	{
		$this->assertSame(
			[['coloremoji', [0x1F600]]],
			$this->drawn([0x1F600], ['bitmapemoji', 'notoemoji'], ['default_font' => 'coloremoji'])
		);
	}

	/**
	 * U+FE0E asks for text, which a colour document font cannot give, so the grinning face goes to the
	 * black and white font that can
	 */
	public function testATextSelectorTakesAnEmojiOutOfAColourDocumentFont()
	{
		$pieces = $this->drawn([0x1F600, 0xFE0E], ['notoemoji'], ['default_font' => 'coloremoji']);

		$this->assertCount(1, $pieces);
		$this->assertSame('notoemoji', $pieces[0][0]);
	}

	/**
	 * A run is cut where the presentation asked for changes, so each part is offered to the fonts in
	 * its own order
	 */
	public function testARunIsCutWhereThePresentationChanges()
	{
		$pieces = $this->drawn([0x1F44D, 0x1F44D, 0xFE0E], ['coloremoji', 'notoemoji']);

		$this->assertSame('coloremoji', $pieces[0][0]);
		$this->assertSame('notoemoji', $pieces[1][0]);
	}

	/**
	 * An emoji straight after Plane 2 text ends that text's run, so the text goes to the SIP font and
	 * the emoji to the emoji font, whole
	 */
	public function testAnEmojiEndsARunOfPlaneTwoText()
	{
		$pieces = $this->drawn([0x20000, 0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467], ['notoemoji'], ['backupSIPFont' => 'sunextb']);

		$this->assertCount(2, $pieces);
		$this->assertSame(['sunextb', [0x20000]], $pieces[0]);
		$this->assertSame('notoemoji', $pieces[1][0]);
		$this->assertCount(1, $pieces[1][1], 'the family is one glyph');
	}

	/**
	 * DejaVu has the grinning face, so it ends the run of people DejaVu lacks rather than going to
	 * the emoji font with them
	 */
	public function testAnEmojiTheFontHasEndsARunThatNeedsAnother()
	{
		$pieces = $this->drawn([0x1F468, 0x1F600, 0x1F469], ['notoemoji']);

		$this->assertCount(3, $pieces);
		$this->assertSame('notoemoji', $pieces[0][0]);
		$this->assertSame(['dejavusans', [0x1F600]], $pieces[1]);
		$this->assertSame('notoemoji', $pieces[2][0]);
	}

	/**
	 * No font has U+1FC00 or U+1FC01, which Unicode keeps for emoji not yet encoded. The sequence
	 * they make is left in the document font whole: the text is cut after the sequence, not after its
	 * first codepoint. DejaVu does not draw the joiner.
	 */
	public function testAnEmojiNoFontHasIsLeftWhole()
	{
		$pieces = $this->drawn([0x61, 0x1FC00, 0x200D, 0x1FC01, 0x62], ['notoemoji']);

		$this->assertSame([['dejavusans', [0x61, 0x1FC00, 0x1FC01]], ['dejavusans', [0x62]]], $pieces);
	}
}
