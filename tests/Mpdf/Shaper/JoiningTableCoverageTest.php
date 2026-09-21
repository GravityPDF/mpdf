<?php

namespace Mpdf\Shaper;

/**
 * A letter is drawn joined wherever Unicode says the character beside it joins, and the two joining
 * tables are all mPDF reads that from. Missing from both, a character joins nothing and unjoins its
 * neighbour: the letter before it was drawn with no form at all where HarfBuzz gives it init or medi
 * (#251).
 *
 * The characters here are the ones Unicode has added since the tables were last extended by hand - the
 * Syriac Supplement, Arabic Extended-A, -B and -C, and five Mandaic letters. `hb-shape` 14.3.1 draws
 * the forms these tests expect. It is the oracle for the crown letters too, but not directly: no
 * released HarfBuzz carries a joining type for them, so the two tests that name them read it from a
 * character of the same type that Unicode 17 already had.
 *
 * The third table stopped in the same place, with the same consequence the other way round: a mark
 * neither it nor the font's GDEF knew was read as a base, took a form of its own and broke the join
 * across itself (#259). The opposite error is the five Arabic presentation forms, which were read as
 * transparent by hand where Unicode makes them bases (#267).
 */
class JoiningTableCoverageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ALL_FORMS = 'isol fina fin2 fin3 medi med2 init';

	/** U+0628 BEH, dual-joining, and the letter #251 was measured with */
	const BEH = '00628';

	/** U+0712 SYRIAC BETH, dual-joining */
	const BETH = '00712';

	/** U+0710 SYRIAC ALAPH, right-joining, and the only letter with a med2 form */
	const ALAPH = '00710';

	/** U+0841 MANDAIC ATT, dual-joining */
	const ATT = '00841';

	/** U+0645 MEEM, dual-joining, and the letter the corpus already joined a Beh to */
	const MEEM = '00645';

	/** U+074F SYRIAC SOGDIAN FE, dual-joining, and the letter the corpus already joined an Alaph over */
	const SOGDIAN_FE = '0074F';

	/** U+10ED9 ARABIC CROWN LETTER BEH, Arabic Extended-C, and left-joining as of Unicode 18 */
	const CROWN_BEH = '10ED9';

	/** U+064E ARABIC FATHA, transparent-joining, and the one mark of the corpus Lateef draws */
	const FATHA = '0064E';

	/*
	 * Stand-ins for the glyphs a font's rtlSUB table names. Arabic::shape() reads each as hex, as it
	 * does a real font's, so they are codes in the Private Use Area rather than names.
	 */
	const B_ISOL = '0F100';

	const B_FINA = '0F101';

	const B_INIT = '0F102';

	const B_MEDI = '0F103';

	const BE_ISOL = '0F104';

	const BE_FINA = '0F105';

	const BE_INIT = '0F106';

	const BE_MEDI = '0F107';

	const A_ISOL = '0F108';

	const A_FINA = '0F109';

	const A_INIT = '0F10A';

	const A_MEDI = '0F10B';

	/**
	 * A letter either side of a character Unicode gives a joining type and mPDF had not got. Both sides,
	 * because a dual-joining character is read out of both tables and only one of them ever had it.
	 *
	 * @dataProvider dataRunsBesideARecentlyAddedCharacter
	 */
	public function testALetterBesideARecentlyAddedCharacterTakesTheFormItJoinsIn($scriptTag, $run, $position, $expected)
	{
		$forms = $this->forms($run, $scriptTag);

		$this->assertSame($expected, $forms[$position]);
	}

	public function dataRunsBesideARecentlyAddedCharacter()
	{
		return [
			// U+0870 ALEF WITH ATTACHED FATHA, Arabic Extended-B, right-joining
			'Arabic Extended-B after a Beh' => ['arab', [self::BEH, '00870'], 0, [self::B_INIT, 2]],
			// U+08B3 AIN WITH THREE DOTS BELOW, Arabic Extended-A, dual-joining
			'Arabic Extended-A after a Beh' => ['arab', [self::BEH, '008B3'], 0, [self::B_INIT, 2]],
			'Arabic Extended-A before a Beh' => ['arab', ['008B3', self::BEH], 1, [self::B_FINA, 1]],
			// U+10EC2 DAL WITH VERTICAL TWO DOTS BELOW, Arabic Extended-C, right-joining
			'Arabic Extended-C after a Beh' => ['arab', [self::BEH, '10EC2'], 0, [self::B_INIT, 2]],
			// U+0860 MALAYALAM NGA and U+0867 MALAYALAM RA, the Syriac Supplement, dual- and right-joining
			'the Syriac Supplement after a Beth' => ['syrc', [self::BETH, '00860'], 0, [self::BE_INIT, 2]],
			'the Syriac Supplement before a Beth' => ['syrc', ['00860', self::BETH], 1, [self::BE_FINA, 1]],
			'a right-joining Syriac Supplement letter after a Beth' => ['syrc', [self::BETH, '00867'], 0, [self::BE_INIT, 2]],
			// U+084F MANDAIC IN and U+0856 MANDAIC DUSHENNA, dual- and right-joining
			'a dual-joining Mandaic letter after an Att' => ['mand', [self::ATT, '0084F'], 0, [self::A_INIT, 2]],
			'a dual-joining Mandaic letter before an Att' => ['mand', ['0084F', self::ATT], 1, [self::A_FINA, 1]],
			'a right-joining Mandaic letter after an Att' => ['mand', [self::ATT, '00856'], 0, [self::A_INIT, 2]],
		];
	}

	/**
	 * U+0847 MANDAIC IT is right-joining: it joins to the letter before it and not to the one after, so
	 * the letter after it starts a run of its own. It was the one character of the four blocks filed in
	 * the wrong table - in the left-joining one, where it drew the following letter as though joined.
	 *
	 * No font in the corpus draws Mandaic, so the oracle is read with an Arabic letter after U+0847. The
	 * joining type is the character's own and HarfBuzz reads it from its own table, so what follows it
	 * only has to be a letter that shows a final form:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/LateefRegOT.ttf \
	 *       --no-clusters --no-positions --unicodes=0847,0628
	 *   [uni0628|.notdef]
	 *   $ ... --unicodes=0645,0628
	 *   [uni0628.fina|uni0645.init]
	 */
	public function testALetterAfterARightJoiningMandaicLetterStandsAlone()
	{
		$forms = $this->forms(['00847', self::ATT], 'mand');

		$this->assertSame([self::A_ISOL, 0], $forms[1]);
	}

	/**
	 * Joining_Type=L, which no character either table held at Unicode 17 had: an L character joins to the
	 * letter written after it and not to the one written before it, so it is the only type that stands in
	 * $leftJoining alone. The second assertion is what says the crown letters are L rather than D; it
	 * reads the same way for a character in neither table.
	 *
	 * `hb-shape` 14.3.1 cannot be asked about U+10ED9 - nor can 14.4.0, the latest release: their joining
	 * tables are still generated from ArabicShaping-17.0.0.txt, so they read the crown letters as joining
	 * nothing at all. The state machine that turns a joining type into a form is byte-identical between
	 * 14.3.1 and the revision that regenerated the table from ArabicShaping-18.0.0.txt, so the oracle for
	 * the type is read with U+10ACD MANICHAEAN LETTER HETH, which Unicode 17 already gives Joining_Type=L.
	 * The corpus has no Manichaean font and Manichaean is a run of its own, so it is read beside an Arabic
	 * letter with the script forced:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/LateefRegOT.ttf \
	 *       --script=arab --direction=rtl --no-clusters --no-positions --unicodes=10ACD,0628
	 *   [uni0628.fina|.notdef]
	 *   $ ... --unicodes=0628,10ACD
	 *   [.notdef|uni0628]
	 *
	 * Lateef has no isolated Beh, so the second is the nominal character: joined on neither side.
	 */
	public function testALetterAfterALeftJoiningCrownLetterTakesItsFinalForm()
	{
		$after = $this->forms([self::CROWN_BEH, self::BEH], 'arab');
		$before = $this->forms([self::BEH, self::CROWN_BEH], 'arab');

		$this->assertSame([self::B_FINA, 1], $after[1]);
		$this->assertSame([self::B_ISOL, 0], $before[0], 'the letter before it is joined by nothing');
	}

	/**
	 * The same through a real font, on the side Lateef can show: a Beh after the crown letter is the glyph
	 * it is after a Meem. Lateef draws no glyph for U+10ED9, so the Beh is the whole of what is measured.
	 */
	public function testABehAfterALeftJoiningCrownLetterDrawsTheSameFormAsAfterAMeem()
	{
		$afterMeem = $this->render([self::MEEM, self::BEH], 'lateef');

		$this->assertSame($afterMeem[1], $this->render([self::CROWN_BEH, self::BEH], 'lateef')[1]);
	}

	/**
	 * Through a real font: Lateef has a Beh and every form of it, and draws no glyph at all for any of the
	 * three characters after it, so the Beh is the whole of what is being measured. A Beh before one of
	 * them has to be the same glyph as a Beh before a Meem, which is the initial form:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/LateefRegOT.ttf \
	 *       --no-clusters --no-positions --unicodes=0628,0870
	 *   [.notdef|uni0628.init]
	 *
	 * @dataProvider dataRecentlyAddedArabicLetters
	 */
	public function testABehBeforeARecentlyAddedArabicLetterDrawsTheSameFormAsBeforeAMeem($hex)
	{
		$beforeMeem = $this->render([self::BEH, self::MEEM], 'lateef');

		$this->assertSame($beforeMeem[0], $this->render([self::BEH, $hex], 'lateef')[0]);
	}

	public function dataRecentlyAddedArabicLetters()
	{
		return [
			'U+0870, Arabic Extended-B' => ['00870'],
			'U+08B3, Arabic Extended-A' => ['008B3'],
			'U+10EC2, Arabic Extended-C' => ['10EC2'],
		];
	}

	/**
	 * Through a real font, and the second thing these tables carry: the Alaph's med2 is resolved from the
	 * joining types since #244, so a dual-joining letter after it is what says it is inside the word. The
	 * Syriac Supplement being in neither table left the Alaph before U+0860 taking its final form instead:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/Estrangelo-Edessa.otf \
	 *       --no-clusters --no-positions --unicodes=0712,0710,0860
	 *   [.notdef|U0710Medi2|U0712Init]
	 *   $ ... --unicodes=0712,0710,074F
	 *   [.notdef|U0710Medi2|U0712Init]
	 */
	public function testAnAlaphBeforeASyriacSupplementLetterDrawsTheMedialFormItDrawsBeforeSogdianFe()
	{
		$beforeFe = $this->render([self::BETH, self::ALAPH, self::SOGDIAN_FE], 'estrangeloedessa');
		$beforeNga = $this->render([self::BETH, self::ALAPH, '00860'], 'estrangeloedessa');

		$this->assertSame($beforeFe[1], $beforeNga[1]);
	}

	/**
	 * A mark Unicode gives Joining_Type=T is transparent to joining whether or not the font has a glyph
	 * for it, so the letters either side of it see each other over it exactly as they do over a fatha.
	 * What hid this is that transparentJoining() merges the font's GDEF mark class into the table, so the
	 * fonts that broke were the ones with no glyph for the mark - which, for every one of these, is every
	 * font in the corpus.
	 *
	 * Lateef has a Beh, a Meem and a fatha, and nothing for the marks named here:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/LateefRegOT.ttf \
	 *       --no-clusters --no-positions --unicodes=0628,064E,0645
	 *   [uni0645.fina|uni064E|uni0628.init]
	 *   $ ... --unicodes=0628,0898,0645
	 *   [uni0645.fina|.notdef|uni0628.init]
	 *
	 * @dataProvider dataTransparentCharactersNoFontOfTheCorpusDraws
	 */
	public function testABehAndAMeemJoinThroughAMarkTheFontHasNoGlyphFor($hex)
	{
		$throughFatha = $this->render([self::BEH, self::FATHA, self::MEEM], 'lateef');
		$throughMark = $this->render([self::BEH, $hex, self::MEEM], 'lateef');

		$this->assertSame($throughFatha[0], $throughMark[0], 'the Beh takes its initial form');
		$this->assertSame($throughFatha[2], $throughMark[2], 'the Meem takes its final form');
	}

	public function dataTransparentCharactersNoFontOfTheCorpusDraws()
	{
		return [
			'U+0898, Arabic Extended-B' => ['00898'],
			'U+08D3, Arabic Extended-A' => ['008D3'],
			'U+10EFB, Arabic Extended-C' => ['10EFB'],
			'U+061C ARABIC LETTER MARK, a format character rather than a mark' => ['0061C'],
		];
	}

	/**
	 * DerivedJoiningType.txt lists none of U+FC5E..U+FC62, and its default for a codepoint it does not
	 * list is Non_Joining, so no table the shaper reads states them. mPDF kept the five by hand and merged
	 * them into the Transparent table at run time until #267; this says that list has not come back, for
	 * all five rather than for the one alone that a font of the corpus can be asked about.
	 *
	 * @dataProvider dataPresentationLigatures
	 */
	public function testAPresentationFormLigatureIsInNoTableTheShaperReads($codepoint)
	{
		$this->assertArrayNotHasKey($codepoint, Arabic::$transparent);
		$this->assertArrayNotHasKey($codepoint, Arabic::$leftJoining);
		$this->assertArrayNotHasKey($codepoint, Arabic::$rightJoining);
	}

	public function dataPresentationLigatures()
	{
		return [
			'U+FC5E' => [0xFC5E],
			'U+FC5F' => [0xFC5F],
			'U+FC60' => [0xFC60],
			'U+FC61' => [0xFC61],
			'U+FC62' => [0xFC62],
		];
	}

	/**
	 * What that costs a document that writes one of them between two letters. A Non_Joining character is
	 * a base, so it breaks the join rather than being read over, where a fatha in the same place is read
	 * over and the letters either side still see each other:
	 *
	 *   $ hb-shape --font-file=packages/Middle-East-Scripts-Bundle/fonts/LateefRegOT.ttf \
	 *       --no-clusters --no-positions --unicodes=0628,064E,0645
	 *   [uni0645.fina|uni064E|uni0628.init]
	 *   $ ... --unicodes=0628,FC5E,0645
	 *   [uni0645|.notdef|uni0628]
	 *
	 * Lateef draws no isolated Beh and no isolated Meem, so each side of the ligature comes out as the
	 * nominal character, which is what the letter written alone is drawn as.
	 */
	public function testABehAndAMeemStandApartAcrossAPresentationFormLigature()
	{
		$joined = $this->render([self::BEH, self::MEEM], 'lateef');
		$behAlone = $this->render([self::BEH], 'lateef');
		$meemAlone = $this->render([self::MEEM], 'lateef');
		$throughFatha = $this->render([self::BEH, self::FATHA, self::MEEM], 'lateef');
		$throughLigature = $this->render([self::BEH, '0FC5E', self::MEEM], 'lateef');

		$this->assertSame($joined[0], $throughFatha[0], 'over a fatha the Beh still takes its initial form');
		$this->assertSame($joined[1], $throughFatha[2], 'and the Meem its final');
		$this->assertSame($behAlone[0], $throughLigature[0], 'across the ligature the Beh is unjoined');
		$this->assertSame($meemAlone[0], $throughLigature[2], 'and so is the Meem');
	}

	/**
	 * @return array one [hex, form] pair per character of the run, in logical order
	 */
	private function forms($hexes, $scriptTag)
	{
		$info = [];
		foreach ($hexes as $hex) {
			$info[] = ['hex' => $hex, 'uni' => hexdec($hex)];
		}

		Arabic::resolveJoining($info, '', $scriptTag);
		Arabic::shape($info, $this->glyphs(), self::ALL_FORMS);

		$forms = [];
		foreach ($info as $char) {
			$forms[] = [$char['hex'], $char['form']];
		}

		return $forms;
	}

	/**
	 * A font's rtlSUB table as TTFontFile builds it: the glyph for each form, indexed 0=isolated 1=final
	 * 2=initial 3=medial. Only the three letters whose form is asserted are stated - a character the table
	 * says nothing about comes through the shaper as it was written, which is every one of the recently
	 * added characters in every font of the corpus.
	 *
	 * @return string[][]
	 */
	private function glyphs()
	{
		return [
			self::BEH => [self::B_ISOL, self::B_FINA, self::B_INIT, self::B_MEDI],
			self::BETH => [self::BE_ISOL, self::BE_FINA, self::BE_INIT, self::BE_MEDI],
			self::ATT => [self::A_ISOL, self::A_FINA, self::A_INIT, self::A_MEDI],
		];
	}

	/**
	 * Text is drawn in visual order, so the character written first is the last one drawn.
	 *
	 * @return string[] the glyph each character was drawn as, in the order they were written
	 */
	private function render($hexes, $family)
	{
		$entities = '';
		foreach ($hexes as $hex) {
			$entities .= sprintf('&#x%s;', $hex);
		}

		$mpdf = new \Mpdf\TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $family . '">' . $entities . '</p>');

		return array_reverse(preg_split('//u', $mpdf->drawnText[0], -1, PREG_SPLIT_NO_EMPTY));
	}

}
