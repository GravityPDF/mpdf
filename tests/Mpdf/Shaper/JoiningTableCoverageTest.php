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
 * the forms these tests expect.
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

	/**
	 * A letter either side of a character Unicode gives a joining type and mPDF had not got.
	 *
	 * Each case names the run, the position the form is asserted at, and the form HarfBuzz draws there.
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
			'Arabic Extended-B after a Beh' => ['arab', [self::BEH, '00870'], 0, ['B_INIT', 2]],
			// U+08B3 AIN WITH THREE DOTS BELOW, Arabic Extended-A, dual-joining
			'Arabic Extended-A after a Beh' => ['arab', [self::BEH, '008B3'], 0, ['B_INIT', 2]],
			'Arabic Extended-A before a Beh' => ['arab', ['008B3', self::BEH], 1, ['B_FINA', 1]],
			// U+10EC2 DAL WITH VERTICAL TWO DOTS BELOW, Arabic Extended-C, right-joining
			'Arabic Extended-C after a Beh' => ['arab', [self::BEH, '10EC2'], 0, ['B_INIT', 2]],
			// U+0860 MALAYALAM NGA and U+0867 MALAYALAM RA, the Syriac Supplement, dual- and right-joining
			'the Syriac Supplement after a Beth' => ['syrc', [self::BETH, '00860'], 0, ['BE_INIT', 2]],
			'the Syriac Supplement before a Beth' => ['syrc', ['00860', self::BETH], 1, ['BE_FINA', 1]],
			'a right-joining Syriac Supplement letter after a Beth' => ['syrc', [self::BETH, '00867'], 0, ['BE_INIT', 2]],
			// U+084F MANDAIC IN and U+0856 MANDAIC DUSHENNA, dual- and right-joining
			'a dual-joining Mandaic letter after an Att' => ['mand', [self::ATT, '0084F'], 0, ['A_INIT', 2]],
			'a dual-joining Mandaic letter before an Att' => ['mand', ['0084F', self::ATT], 1, ['A_FINA', 1]],
			'a right-joining Mandaic letter after an Att' => ['mand', [self::ATT, '00856'], 0, ['A_INIT', 2]],
		];
	}

	/**
	 * U+0847 MANDAIC IT is right-joining: it joins to the letter before it and not to the one after, so
	 * the letter after it starts a run of its own. It was the one character of the four blocks filed in
	 * the wrong table - in the left-joining one, where it drew the following letter as though joined -
	 * and `hb-shape` leaves that letter nominal:
	 *
	 *   [uni0628|.notdef]   0847,0628 through Lateef, against [uni0628.fina|uni0645.init] for 0645,0628
	 */
	public function testALetterAfterARightJoiningMandaicLetterStandsAlone()
	{
		$forms = $this->forms(['00847', self::ATT], 'mand');

		$this->assertSame(['A_ISOL', 0], $forms[1]);
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
			self::BEH => ['B_ISOL', 'B_FINA', 'B_INIT', 'B_MEDI'],
			self::BETH => ['BE_ISOL', 'BE_FINA', 'BE_INIT', 'BE_MEDI'],
			self::ATT => ['A_ISOL', 'A_FINA', 'A_INIT', 'A_MEDI'],
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
			$entities .= sprintf('&#x%s;', ltrim($hex, '0'));
		}

		$mpdf = new \Mpdf\TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $family . '">' . $entities . '</p>');

		return array_reverse(preg_split('//u', $mpdf->drawnText[0], -1, PREG_SPLIT_NO_EMPTY));
	}

}
