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

	/** U+0651 SHADDA, a transparent-joining mark, and stacked with a vowel in ordinary pointed Arabic */
	const SHADDA = '00651';

	/** U+06E7 ARABIC SMALL HIGH YEH, a transparent-joining mark Zawgyi-One states a final form for */
	const SMALL_HIGH_YEH = '006E7';

	/** U+06EB ARABIC EMPTY CENTRE HIGH STOP, another */
	const EMPTY_CENTRE_HIGH_STOP = '006EB';

	/** U+0710 SYRIAC LETTER ALAPH, right-joining, and the only letter with fin2, fin3 and med2 forms */
	const ALAPH = '00710';

	/** U+0730 SYRIAC PTHAHA ABOVE, a transparent-joining vowel, and ordinary in pointed Syriac */
	const PTHAHA = '00730';

	/** U+0733 SYRIAC ZQAPHA ABOVE */
	const ZQAPHA = '00733';

	/** U+0736 SYRIAC RBASA ABOVE */
	const RBASA = '00736';

	/** U+073A SYRIAC HBASA ABOVE */
	const HBASA = '0073A';

	/** U+073B SYRIAC HBASA BELOW */
	const HBASA_BELOW = '0073B';

	/** U+0712 SYRIAC LETTER BETH, dual-joining */
	const BETH = '00712';

	/** U+0715 SYRIAC LETTER DALATH, right-joining */
	const DALATH = '00715';

	/** U+0716 SYRIAC LETTER DOTLESS DALATH RISH, right-joining */
	const DOTLESS_DALATH_RISH = '00716';

	/** U+072A SYRIAC LETTER RISH, right-joining */
	const RISH = '0072A';

	/** U+074F SYRIAC LETTER SOGDIAN FE, dual-joining */
	const SOGDIAN_FE = '0074F';

	/** U+08AD ARABIC LETTER LOW ALEF, non-joining: it joins nothing on either side */
	const LOW_ALEF = '008AD';

	/** U+0300 COMBINING GRAVE ACCENT, outside the Transparent-Joining table and in GDEF's mark class */
	const COMBINING_GRAVE = '00300';

	/**
	 * The font's rtlSUB table, as TTFontFile builds it: replacement hex per form, indexed
	 * 0=isolated 1=final 2=initial 3=medial, and for Alaph 4=med2 5=fin2 6=fin3.
	 *
	 * Alaph is given the forms Estrangelo Edessa states for it, which is every one but the isolated.
	 * SHADDA is given a final form and no isolated one, which is how Zawgyi-One states the forms of a
	 * mark - and is what makes the difference between the two visible.
	 */
	private function glyphs()
	{
		return [
			self::BEH => ['B_ISOL', 'B_FINA', 'B_INIT', 'B_MEDI'],
			self::DAL => ['D_ISOL', 'D_FINA'],
			self::SHADDA => [1 => 'SH_FINA'],
			self::ALAPH => [1 => 'A_FINA', 4 => 'A_MED2', 5 => 'A_FIN2', 6 => 'A_FIN3'],
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
	 * A mark's own form follows from the two letters around it, and every mark of a stack stands
	 * between the same two. The character in front of a mark was read raw rather than past the rest of
	 * the stack, so the first mark saw the second; a mark is in neither joining table, and the form
	 * fell to isolated while the same mark alone took its final form. GravityPDF/mpdf#162.
	 */
	public function testEveryMarkOfAStackTakesTheFormTheLettersAroundItCallFor()
	{
		$alone = $this->shape([self::BEH, self::SHADDA, self::BEH]);
		$underAVowel = $this->shape([self::BEH, self::SHADDA, self::FATHA, self::BEH]);
		$doubled = $this->shape([self::BEH, self::SHADDA, self::SHADDA, self::BEH]);

		$this->assertSame(['SH_FINA', 1], $alone[1]);
		$this->assertSame(['SH_FINA', 1], $underAVowel[1]);
		$this->assertSame([['SH_FINA', 1], ['SH_FINA', 1]], [$doubled[1], $doubled[2]]);
	}

	/**
	 * Nothing limits how many marks a base carries, and the lookback stepped over at most three of
	 * them: a fourth left the following letter reading a mark as the character behind it, and a mark
	 * joins nothing. GravityPDF/mpdf#154.
	 *
	 * The first letter is asserted alongside the last because the two directions are found
	 * differently - forwards is the last letter the backwards walk over the run passed, which never
	 * had a limit - and it is the pair that says the marks are invisible to joining from either side.
	 */
	public function testALetterJoinsToTheBaseBeforeItHoweverManyMarksAreBetweenThem()
	{
		$four = [self::BETH, self::PTHAHA, self::ZQAPHA, self::RBASA, self::HBASA, self::BETH];
		$five = [self::BETH, self::PTHAHA, self::ZQAPHA, self::RBASA, self::HBASA, self::HBASA_BELOW, self::BETH];

		$forms = $this->shape($four, self::ALL_FORMS, 'syrc');

		$this->assertSame(['BE_INIT', 2], $forms[0]);
		$this->assertSame(['BE_FINA', 1], $forms[5]);
		$this->assertSame(['BE_FINA', 1], $this->shape($five, self::ALL_FORMS, 'syrc')[6]);
	}

	/**
	 * The marks counted are the Transparent-Joining table together with GDEF's mark class, so a mark
	 * only the font declares takes up a place in the walk like any other.
	 */
	public function testAMarkOnlyGdefDeclaresIsSteppedOverWithTheRest()
	{
		$run = [self::BETH, self::PTHAHA, self::ZQAPHA, self::RBASA, self::COMBINING_GRAVE, self::BETH];

		$forms = $this->shape($run, self::ALL_FORMS, 'syrc', self::COMBINING_GRAVE);

		$this->assertSame(['BE_FINA', 1], $forms[5]);
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
	 * fin3 is the Alaph a Syriac font draws at the end of a word after DALATH, DOTLESS DALATH RISH or
	 * RISH. Those three are right-joining, so the Alaph after one of them stands apart from it - which
	 * is why the form exists, and why the test the form sat behind, that the letter before it joins
	 * forwards, could never be true. GravityPDF/mpdf#132.
	 */
	public function testAnAlaphEndingAWordAfterDalathOrRishTakesFin3()
	{
		foreach ([self::DALATH, self::DOTLESS_DALATH_RISH, self::RISH] as $preceding) {
			$forms = $this->shape([$preceding, self::ALAPH], self::ALL_FORMS, 'syrc');

			$this->assertSame(['A_FIN3', 6], $forms[1], $preceding . ' before the Alaph');
		}
	}

	/**
	 * fin2 is the same position after a letter that does join forwards, and it is what every Alaph
	 * ending a word was taking
	 */
	public function testAnAlaphEndingAWordAfterADualJoiningLetterTakesFin2()
	{
		$forms = $this->shape([self::BETH, self::ALAPH], self::ALL_FORMS, 'syrc');

		$this->assertSame([['BE_INIT', 2], ['A_FIN2', 5]], $forms);
	}

	/**
	 * med2 is the Alaph inside a word after a letter that joins forwards, and it is reached by the same
	 * guard as fin2
	 */
	public function testAnAlaphInsideAWordAfterADualJoiningLetterTakesMed2()
	{
		$forms = $this->shape([self::BETH, self::ALAPH, self::BETH], self::ALL_FORMS, 'syrc');

		$this->assertSame(['A_MED2', 4], $forms[1]);
	}

	/**
	 * The three letters only call for fin3 at the end of the word. Inside one the Alaph is left as it
	 * came in, because Estrangelo Edessa - like every Syriac font in the corpus - states no isolated
	 * form for it.
	 */
	public function testAnAlaphInsideAWordAfterDalathOrRishIsLeftAlone()
	{
		$forms = $this->shape([self::RISH, self::ALAPH, self::BETH], self::ALL_FORMS, 'syrc');

		$this->assertSame([self::ALAPH, 0], $forms[1]);
	}

	/**
	 * Through a real font: Estrangelo Edessa carries a fin3 Alaph and nothing could ask for it, so the
	 * nominal U+0710 was what got drawn. It is a glyph of its own - not the fin2 the same Alaph takes
	 * after a dual-joining letter - and the same one after all three letters.
	 */
	public function testAnAlaphAfterDalathOrRishDrawsTheFormTheFontStatesForIt()
	{
		$afterDalath = $this->render([self::DALATH, self::ALAPH]);
		$afterDotless = $this->render([self::DOTLESS_DALATH_RISH, self::ALAPH]);
		$afterRish = $this->render([self::RISH, self::ALAPH]);

		$this->assertSame($afterDalath[1], $afterDotless[1]);
		$this->assertSame($afterDalath[1], $afterRish[1]);
		$this->assertNotSame(\Mpdf\Utils\UtfString::codeHex2utf(self::ALAPH), $afterDalath[1]);
		$this->assertNotSame($this->render([self::BETH, self::ALAPH])[1], $afterDalath[1]);
	}

	/**
	 * A vowel between the base and the Alaph is transparent to joining, so the base is still what the
	 * Alaph's form follows from. The form was read off the character immediately before the Alaph
	 * instead, which in pointed text is the vowel, and a vowel joins nothing. GravityPDF/mpdf#142.
	 */
	public function testAVowelBeforeTheAlaphLeavesItTheFormOfTheBaseBeforeThat()
	{
		$fin2 = $this->shape([self::BETH, self::PTHAHA, self::ALAPH], self::ALL_FORMS, 'syrc');
		$fin3 = $this->shape([self::DALATH, self::PTHAHA, self::ALAPH], self::ALL_FORMS, 'syrc');
		$med2 = $this->shape([self::BETH, self::PTHAHA, self::ALAPH, self::BETH], self::ALL_FORMS, 'syrc');

		$this->assertSame(['A_FIN2', 5], $fin2[2]);
		$this->assertSame(['A_FIN3', 6], $fin3[2]);
		$this->assertSame(['A_MED2', 4], $med2[2]);
	}

	/**
	 * Pointed Syriac stacks more than one mark on a letter, and nothing limits how many, so the walk
	 * has to carry on rather than step once
	 */
	public function testSeveralVowelsBeforeTheAlaphLeaveItTheFormOfTheBaseBeforeThem()
	{
		$run = [self::BETH, self::PTHAHA, self::ZQAPHA, self::RBASA, self::HBASA, self::ALAPH];

		$this->assertSame(['A_FIN2', 5], $this->shape($run, self::ALL_FORMS, 'syrc')[5]);
	}

	/**
	 * The lookback reads the Transparent-Joining table together with GDEF's mark class, the way the
	 * rest of the shaper does, so a font that files a mark of its own is followed as well.
	 */
	public function testAGdefMarkBeforeTheAlaphLeavesItTheFormOfTheBaseBeforeIt()
	{
		$forms = $this->shape([self::BETH, self::COMBINING_GRAVE, self::ALAPH], self::ALL_FORMS, 'syrc', self::COMBINING_GRAVE);

		$this->assertSame(['A_FIN2', 5], $forms[2]);
	}

	/**
	 * The word-end test read the character after the Alaph raw, so a vowel on the Alaph itself counted
	 * as a letter following it: fin2 became med2, and fin3 was refused altogether.
	 */
	public function testAVowelAfterTheAlaphStillLeavesItEndingTheWord()
	{
		$fin2 = $this->shape([self::BETH, self::ALAPH, self::PTHAHA], self::ALL_FORMS, 'syrc');
		$fin3 = $this->shape([self::DALATH, self::ALAPH, self::PTHAHA], self::ALL_FORMS, 'syrc');

		$this->assertSame(['A_FIN2', 5], $fin2[1]);
		$this->assertSame(['A_FIN3', 6], $fin3[1]);
	}

	/**
	 * A letter after the vowel is still a letter, and the Alaph is still inside the word
	 */
	public function testAVowelOnTheAlaphInsideAWordLeavesItMed2()
	{
		$forms = $this->shape([self::BETH, self::ALAPH, self::PTHAHA, self::BETH], self::ALL_FORMS, 'syrc');

		$this->assertSame(['A_MED2', 4], $forms[1]);
	}

	/**
	 * Through a real font, either side of the Alaph: a vowel is drawn where it was written and changes
	 * nothing about the letters around it, so the pointed word has to draw the Alaph the unpointed one
	 * draws. Estrangelo Edessa states no isolated Alaph, so what a lost form left behind was the
	 * nominal U+0710.
	 */
	public function testAPointedWordDrawsTheSameAlaphAsTheUnpointedWord()
	{
		// BETH reaches fin2 and DALATH fin3; what the other two right-joining letters draw is already
		// asserted against DALATH above
		foreach ([self::BETH, self::DALATH] as $base) {
			$unpointed = $this->render([$base, self::ALAPH]);
			$beforeAlaph = $this->render([$base, self::PTHAHA, self::ALAPH]);
			$afterAlaph = $this->render([$base, self::ALAPH, self::PTHAHA]);

			$this->assertSame($unpointed[1], $beforeAlaph[2], $base . ' with the vowel before the Alaph');
			$this->assertSame($unpointed[1], $afterAlaph[1], $base . ' with the vowel after the Alaph');
		}
	}

	/**
	 * Through a real font: the marks are drawn where they were written and the letters either side
	 * read as though they were not there, so a heavily pointed word has to draw the same two BETHs as
	 * the unpointed one. Estrangelo Edessa states no isolated BETH either, so the second letter losing
	 * its final form left the nominal U+0712 behind.
	 */
	public function testAHeavilyPointedWordDrawsTheSameLettersAsTheUnpointedWord()
	{
		$unpointed = $this->render([self::BETH, self::BETH]);
		$pointed = $this->render([self::BETH, self::PTHAHA, self::ZQAPHA, self::RBASA, self::HBASA, self::BETH]);

		$this->assertSame($unpointed[0], $pointed[0]);
		$this->assertSame($unpointed[1], $pointed[5]);
	}

	/**
	 * Through a real font: Zawgyi-One is the one font in the bundle whose rtlSUB states positional
	 * forms for a transparent-joining character, and U+06E7 and U+06EB each carry a final one. A mark
	 * has to draw the same glyph whether it stands alone between two Behs or heads a stack between the
	 * same two, and what the lost form left behind was the nominal U+06E7. The letters are asserted
	 * beside it because joining is what the two halves of the test disagreed about, and it is the
	 * letters that would show it reaching further than the mark.
	 */
	public function testAStackedMarkDrawsTheFormTheFontStatesForItLikeTheSameMarkAlone()
	{
		$alone = $this->render([self::BEH, self::SMALL_HIGH_YEH, self::BEH], 'zawgyi-one');
		$underAnother = $this->render([self::BEH, self::SMALL_HIGH_YEH, self::EMPTY_CENTRE_HIGH_STOP, self::BEH], 'zawgyi-one');
		$doubled = $this->render([self::BEH, self::SMALL_HIGH_YEH, self::SMALL_HIGH_YEH, self::BEH], 'zawgyi-one');

		$this->assertNotSame(\Mpdf\Utils\UtfString::codeHex2utf(self::SMALL_HIGH_YEH), $alone[1]);
		$this->assertSame($alone[1], $underAnother[1]);
		$this->assertSame([$alone[1], $alone[1]], [$doubled[1], $doubled[2]]);
		$this->assertSame([$alone[0], $alone[2]], [$underAnother[0], $underAnother[3]]);
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
		$beforeFe = $this->render([self::BETH, self::SOGDIAN_FE]);
		$beforeBeth = $this->render([self::BETH, self::BETH]);

		$this->assertSame($beforeBeth[0], $beforeFe[0]);
	}

	/**
	 * Text is drawn in visual order, so the letter written first is the last one drawn.
	 *
	 * @return string[] the glyph each character was drawn as, in the order they were written
	 */
	private function render($hexes, $family = 'estrangeloedessa')
	{
		$entities = '';
		foreach ($hexes as $hex) {
			$entities .= sprintf('&#x%s;', ltrim($hex, '0'));
		}

		$mpdf = new \Mpdf\TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $family . '">' . $entities . '</p>');

		return array_reverse(preg_split('//u', $mpdf->drawnText[0], -1, PREG_SPLIT_NO_EMPTY));
	}

	/**
	 * A form the font states as several glyphs leaves its base where the character stood and is handed
	 * back whole, because the whole of it is what replaces the character. @see \Mpdf\MultipleFormTest
	 *
	 * `uni` is asserted beside `hex` because nothing downstream shows it wrong: `shapeArabic()`
	 * substitutes over every position handed back, writing `uni` again from the same glyph.
	 * GravityPDF/mpdf#114.
	 */
	public function testAFormOfSeveralGlyphsIsHandedBackForTheCallerToSubstitute()
	{
		$info = [['hex' => self::BEH, 'uni' => hexdec(self::BEH)]];
		Arabic::resolveJoining($info, ' ' . self::FATHA);

		$multiple = Arabic::shape($info, [self::BEH => ['0E01D 0FBB3']], ' ' . self::FATHA, self::ALL_FORMS, 'arab');

		$this->assertSame([0 => [0xE01D, 0xFBB3]], $multiple);
		$this->assertSame('0E01D', $info[0]['hex']);
		$this->assertSame(0xE01D, $info[0]['uni']);
	}

	/**
	 * A form of one glyph is written straight into the run and there is nothing to hand back, which is
	 * every form of every font in the corpus.
	 */
	public function testAFormOfOneGlyphIsHandedBackAsNothing()
	{
		$info = [['hex' => self::BEH, 'uni' => hexdec(self::BEH)]];
		Arabic::resolveJoining($info, ' ' . self::FATHA);

		$this->assertSame([], Arabic::shape($info, $this->glyphs(), ' ' . self::FATHA, self::ALL_FORMS, 'arab'));
	}

	/**
	 * The form is read off the characters as written and carried on the run, so a character something
	 * has replaced by the time the forms are drawn still takes the one its own joining called for. That
	 * is how a letter 'ccmp' takes apart reaches the forms of the rasm it leaves behind (#209): the
	 * rasm is unencoded, mPDF maps it into the Private Use Area, and a Private Use codepoint is in none
	 * of the joining tables.
	 */
	public function testAGlyphThatReplacedACharacterTakesTheFormTheCharacterJoinedAs()
	{
		$rasm = '0E001';
		$info = [
			['hex' => self::BEH, 'uni' => hexdec(self::BEH)],
			['hex' => self::BEH, 'uni' => hexdec(self::BEH)],
		];
		Arabic::resolveJoining($info, ' ' . self::FATHA);

		$info[0]['hex'] = $rasm;
		$info[1]['hex'] = $rasm;
		Arabic::shape($info, [$rasm => ['R_ISOL', 'R_FINA', 'R_INIT']], ' ' . self::FATHA, self::ALL_FORMS, 'arab');

		$this->assertSame(
			[['R_INIT', 2], ['R_FINA', 1]],
			[[$info[0]['hex'], $info[0]['form']], [$info[1]['hex'], $info[1]['form']]]
		);
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
	 * A form a chained rule gives is drawn where the text holds the glyphs the rule names, and a glyph
	 * whose hex is only part of one of theirs is not one of them: 00628 is inside 100628, and 0064E
	 * inside 10064E.
	 *
	 * @dataProvider dataContextNamingAPlaneSixteenGlyph
	 */
	public function testAFormsContextIsNotMetByAGlyphWhoseHexIsPartOfOneItNames($prel, $ignore, $hexes, $expected)
	{
		$glyphs = $this->glyphs();
		$glyphs[self::DAL] = ['D_ISOL', 'D_FINA', 'prel' => [1 => [$prel]], 'ignore' => [1 => $ignore]];

		$this->assertSame($expected, $this->shape($hexes, self::ALL_FORMS, 'arab', self::FATHA, $glyphs));
	}

	public function dataContextNamingAPlaneSixteenGlyph()
	{
		return [
			'the backtrack' => [
				'1' . self::BEH,
				'()',
				[self::BEH, self::DAL],
				[['B_INIT', 2], [self::DAL, 0]],
			],
			'the glyphs the lookup skips' => [
				self::BEH,
				'((?:(?: 1' . self::FATHA . '))*)',
				[self::BEH, self::FATHA, self::DAL],
				[['B_INIT', 2], [self::FATHA, 0], [self::DAL, 0]],
			],
		];
	}

	/**
	 * The walk over the glyphs a chained rule's lookup ignores read past the edge of the run: a notice
	 * for each glyph on PHP 7, and from PHP 8 a walk that never returned (#204). Here the notice is what
	 * fails, so a regression cannot hang the suite.
	 *
	 * @dataProvider dataContextWalkedToTheEdgeOfTheRun
	 */
	public function testAFormsContextWalkReadsNothingPastTheEdgeOfTheRun($hexes, $glyphs, $expected)
	{
		set_error_handler(function ($number, $message, $file, $line) {
			throw new \ErrorException($message, 0, $number, $file, $line);
		});

		try {
			$forms = $this->shape($hexes, self::ALL_FORMS, 'arab', self::FATHA, $glyphs);
		} finally {
			restore_error_handler();
		}

		$this->assertSame($expected, $forms);
	}

	/**
	 * The same cases, all in one child process under a time limit, for a regression that reads past the
	 * edge without a notice. The limit is the child's own rather than a `timeout` around it, so it holds
	 * on Windows too, and the child reports nothing short of a fatal error, so a walk warning on every
	 * step cannot fill the stderr pipe and leave it blocked instead of timed out.
	 */
	public function testAFormsContextWalkReturnsAtTheEdgeOfTheRun()
	{
		$runs = [];
		$expected = [];
		foreach ($this->dataContextWalkedToTheEdgeOfTheRun() as $name => $case) {
			$runs[$name] = [$case[0], $case[1]];
			$expected[$name] = $case[2];
		}

		// base64, because Windows argument quoting does not survive the JSON's double quotes
		$arg = base64_encode(json_encode([$runs, self::ALL_FORMS, ' ' . self::FATHA]));
		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
			. escapeshellarg(__DIR__ . '/../Fixtures/arabic-shape.php') . ' ' . $arg;

		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$this->assertSame(0, proc_close($process), $errors);

		$this->assertSame($expected, json_decode($output, true));
	}

	public function dataContextWalkedToTheEdgeOfTheRun()
	{
		// The forms are the presentation forms rather than names, because the run is left holding them
		// and shape() reads each back as hex
		$ignoreFatha = '((?:(?: ' . self::FATHA . '))*)';

		return [
			'a backtrack that runs out at the start of the run' => [
				[self::FATHA, self::DAL],
				[self::DAL => ['0FEA9', '0FEAA', 'prel' => [0 => [self::BEH]], 'ignore' => [0 => $ignoreFatha]]],
				[[self::FATHA, 0], [self::DAL, 0]],
			],
			'a lookahead that runs out at the end of the run' => [
				[self::BEH, self::FATHA],
				[self::BEH => ['0FE8F', 'postl' => [0 => [self::DAL]], 'ignore' => [0 => $ignoreFatha]]],
				[[self::BEH, 0], [self::FATHA, 0]],
			],
			'a lookahead whose second position runs out after the first is met' => [
				[self::BEH, self::FATHA, self::DAL, self::FATHA],
				[self::BEH => ['0FE8F', '0FE90', '0FE91', 'postl' => [2 => [self::DAL, self::DAL]], 'ignore' => [2 => $ignoreFatha]]],
				[[self::BEH, 0], [self::FATHA, 0], [self::DAL, 0], [self::FATHA, 0]],
			],
			'a backtrack met past the ignored glyphs' => [
				[self::BEH, self::FATHA, self::DAL],
				[self::DAL => ['0FEA9', '0FEAA', 'prel' => [1 => [self::BEH]], 'ignore' => [1 => $ignoreFatha]]],
				[[self::BEH, 0], [self::FATHA, 0], ['0FEAA', 1]],
			],
			'a lookahead met past the ignored glyphs' => [
				[self::BEH, self::FATHA, self::DAL],
				[self::BEH => ['0FE8F', '0FE90', '0FE91', 'postl' => [2 => [self::DAL]], 'ignore' => [2 => $ignoreFatha]]],
				[['0FE91', 2], [self::FATHA, 0], [self::DAL, 0]],
			],
		];
	}

	/**
	 * @return array one [hex, form] pair per character, in logical order
	 */
	private function shape($hexes, $usetags = self::ALL_FORMS, $scriptTag = 'arab', $glyphClassMarks = self::FATHA, $glyphs = null)
	{
		$info = [];
		foreach ($hexes as $hex) {
			$info[] = ['hex' => $hex, 'uni' => hexdec($hex)];
		}

		Arabic::resolveJoining($info, ' ' . $glyphClassMarks);
		Arabic::shape($info, $glyphs === null ? $this->glyphs() : $glyphs, ' ' . $glyphClassMarks, $usetags, $scriptTag);

		$forms = [];
		foreach ($info as $char) {
			$forms[] = [$char['hex'], $char['form']];
		}

		return $forms;
	}

}
