<?php

namespace Mpdf\Shaper;

/**
 * Arabic and Syriac positional forms are resolved here rather than by GSUB, so until this moved out
 * of Otl the only way to ask what form a character got was to render a PDF and read it back.
 */
class ArabicTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ALL_FORMS = 'isol fina fin2 fin3 medi med2 init';

	/** U+0628 BEH, dual-joining */
	const BEH = '00628';

	/** U+062F DAL, right-joining only: it joins to the letter before it but not to the one after */
	const DAL = '0062F';

	/** U+064E FATHA, a transparent-joining mark */
	const FATHA = '0064E';

	/** U+0712 SYRIAC LETTER BETH, dual-joining */
	const BETH = '00712';

	/** U+074F SYRIAC LETTER SOGDIAN FE, dual-joining */
	const SOGDIAN_FE = '0074F';

	/** U+08AD ARABIC LETTER LOW ALEF, non-joining: it joins nothing on either side */
	const LOW_ALEF = '008AD';

	/**
	 * The font's rtlSUB table, as TTFontFile builds it: replacement hex per form, indexed
	 * 0=isolated 1=final 2=initial 3=medial
	 */
	private function glyphs()
	{
		return [
			self::BEH => ['B_ISOL', 'B_FINA', 'B_INIT', 'B_MEDI'],
			self::DAL => ['D_ISOL', 'D_FINA'],
			self::BETH => ['BE_ISOL', 'BE_FINA', 'BE_INIT', 'BE_MEDI'],
			self::SOGDIAN_FE => ['F_ISOL', 'F_FINA', 'F_INIT', 'F_MEDI'],
		];
	}

	/**
	 * Two dual-joining letters: the first can only join forward, the last only backward
	 */
	public function testADualJoiningPairTakesInitialThenFinal()
	{
		$forms = $this->shape([self::BEH, self::BEH]);

		$this->assertSame([['B_INIT', 2], ['B_FINA', 1]], $forms);
	}

	public function testAThreeLetterRunTakesMedialInTheMiddle()
	{
		$forms = $this->shape([self::BEH, self::BEH, self::BEH]);

		$this->assertSame([['B_INIT', 2], ['B_MEDI', 3], ['B_FINA', 1]], $forms);
	}

	/**
	 * DAL joins to the preceding letter but not the following one, so the letter after it has to start
	 * a new run rather than continue the one DAL ends
	 */
	public function testARightJoiningLetterBreaksTheRunAfterIt()
	{
		$forms = $this->shape([self::BEH, self::DAL, self::BEH]);

		$this->assertSame([['B_INIT', 2], ['D_FINA', 1], ['B_ISOL', 0]], $forms);
	}

	public function testALoneLetterIsIsolated()
	{
		$this->assertSame([['B_ISOL', 0]], $this->shape([self::BEH]));
	}

	/**
	 * A transparent-joining mark is invisible to joining: the letters either side of it still see
	 * each other, and the mark itself is left alone
	 */
	public function testAMarkBetweenTwoLettersDoesNotBreakTheirJoin()
	{
		$forms = $this->shape([self::BEH, self::FATHA, self::BEH]);

		$this->assertSame([['B_INIT', 2], [self::FATHA, 0], ['B_FINA', 1]], $forms);
	}

	/**
	 * The four form features can be switched off through OTLtags, in which case the character is left
	 * as it came in rather than substituted
	 */
	public function testAFormWhoseFeatureIsNotRequestedIsLeftAlone()
	{
		$forms = $this->shape([self::BEH, self::BEH], 'isol fina');

		$this->assertSame([[self::BEH, 0], ['B_FINA', 1]], $forms);
	}

	/**
	 * U+074F SOGDIAN FE is dual-joining, so it joins to the letter before it and that letter takes a
	 * medial or initial form. One entry of the right-joining table was missing its `=> 1`, which filed
	 * U+074F as a value under the next free integer key instead of as a key of its own, and left
	 * SOGDIAN FE joining nothing backwards.
	 */
	public function testADualJoiningSyriacLetterPullsTheLetterBeforeItIntoInitialForm()
	{
		$forms = $this->shape([self::BETH, self::SOGDIAN_FE], self::ALL_FORMS, 'syrc');

		$this->assertSame([['BE_INIT', 2], ['F_FINA', 1]], $forms);
	}

	/**
	 * The key the missing value took was U+08AD, one past the largest key the literal had reached, so
	 * LOW ALEF read as right-joining. It is joining type U - it joins nothing - and the letter before
	 * it stays isolated.
	 */
	public function testANonJoiningLetterLeavesTheLetterBeforeItIsolated()
	{
		$forms = $this->shape([self::BEH, self::LOW_ALEF]);

		$this->assertSame([['B_ISOL', 0], [self::LOW_ALEF, 0]], $forms);
	}

	/**
	 * The same character through a real font, which is where the wrong entry showed: Estrangelo Edessa
	 * carries a BETH for each of the four forms, and the form the shaper asks for is the glyph that
	 * ends up drawn. A run ending in SOGDIAN FE has to draw the same BETH as a run ending in another
	 * BETH - both are dual-joining, so both take the letter before them into initial form. The glyphs
	 * are compared against each other rather than named, because the codepoints they are drawn under
	 * are assigned as the subset is built.
	 */
	public function testSogdianFeDrawsTheSameInitialFormAsAnotherDualJoiningLetter()
	{
		$beforeFe = $this->render(self::BETH, self::SOGDIAN_FE);
		$beforeBeth = $this->render(self::BETH, self::BETH);

		$this->assertSame($beforeBeth, $beforeFe);
	}

	/**
	 * Text is drawn in visual order, so the letter written first is the last one drawn.
	 *
	 * @return string the glyph the first of the two characters was drawn as
	 */
	private function render($first, $second)
	{
		$mpdf = new \Mpdf\TextRecordingMpdf();
		$mpdf->WriteHTML(sprintf(
			'<p style="font-family:estrangeloedessa">&#x%s;&#x%s;</p>',
			ltrim($first, '0'),
			ltrim($second, '0')
		));

		$drawn = preg_split('//u', $mpdf->drawnText[0], -1, PREG_SPLIT_NO_EMPTY);

		return end($drawn);
	}

	/**
	 * A font may write one form as several glyphs - a dotless base and the dots drawn under it, which
	 * is how the Nastaliq faces write most of their initial and medial forms. Only the first can go
	 * where the character stood, so the rest are handed back for Otl to splice in; what is handed back
	 * is the whole form rather than the tail of it, because that is what the Multiple Substitution
	 * path it goes through replaces the character with.
	 *
	 * hexdec() reads '0E01D 0FBB3' as 0xE01D0FBB3 - it stops at nothing and ignores the space - so the
	 * letter used to come out as a code point of sixty billion. @see \Mpdf\MultipleFormTest
	 */
	public function testAFormOfSeveralGlyphsIsHandedBackForTheCallerToSplice()
	{
		$info = [['hex' => self::BEH, 'uni' => hexdec(self::BEH)]];

		$multiple = Arabic::shape($info, [self::BEH => ['0E01D 0FBB3']], '', self::ALL_FORMS, 'arab');

		$this->assertSame('0E01D', $info[0]['hex']);
		$this->assertSame(0xE01D, $info[0]['uni']);
		$this->assertSame([0 => [0xE01D, 0xFBB3]], $multiple);
	}

	/**
	 * A form of one glyph is written straight into the run and nothing is left over, which is every
	 * form of every font the corpus held before Katibeh.
	 */
	public function testAFormOfOneGlyphLeavesNothingToSplice()
	{
		$info = [['hex' => self::BEH, 'uni' => hexdec(self::BEH)]];

		$this->assertSame([], Arabic::shape($info, $this->glyphs(), ' ' . self::FATHA, self::ALL_FORMS, 'arab'));
		$this->assertSame('B_ISOL', $info[0]['hex']);
	}

	/**
	 * Every entry of all three tables is a codepoint mapped to 1, and they are only ever read with
	 * isset(), so a value that is not 1 is a codepoint that was typed without its `=> 1` and has been
	 * filed under a key that means nothing.
	 */
	public function testEveryJoiningTableEntryIsFiledUnderItsOwnCodepoint()
	{
		foreach (['leftJoining', 'rightJoining', 'transparent'] as $table) {
			$values = array_unique(array_values(Arabic::${$table}));

			$this->assertSame([1], $values, $table . ' has an entry that is not a codepoint => 1');
		}
	}

	/**
	 * @return array one [hex, form] pair per character, in logical order
	 */
	private function shape($hexes, $usetags = self::ALL_FORMS, $scriptTag = 'arab')
	{
		$info = [];
		foreach ($hexes as $hex) {
			$info[] = ['hex' => $hex, 'uni' => hexdec($hex)];
		}

		Arabic::shape($info, $this->glyphs(), ' ' . self::FATHA, $usetags, $scriptTag);

		$forms = [];
		foreach ($info as $char) {
			$forms[] = [$char['hex'], $char['form']];
		}

		return $forms;
	}

}
