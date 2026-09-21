<?php

namespace Mpdf;

use Mpdf\Unicode\Ucdn;

/**
 * The generator behind composer arabicjoining:update, run over an ArabicShaping.txt and a
 * DerivedJoiningType.txt written here rather than Unicode's - one line per joining type, and one per
 * script whose joining the shaper is and is not asked about.
 *
 * The last two tests rewrite a copy of the class rather than src/Shaper/Arabic.php, and the first of
 * them is the one that says what a generated line looks like.
 */
class ArabicJoiningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var ArabicJoining
	 */
	private $joining;

	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-arabic-joining-' . getmypid();
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0777, true);
		}
		file_put_contents($this->dir . '/ArabicShaping.txt', $this->shaping());
		file_put_contents($this->dir . '/DerivedJoiningType.txt', $this->derived());

		$this->joining = new ArabicJoining('9.9.9', $this->dir);
	}

	protected function tear_down()
	{
		parent::tear_down();

		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);
	}

	/**
	 * Dual-joining is both directions at once, and it is what most of a cursive script's letters are
	 */
	public function testADualJoiningCharacterIsInBothTables()
	{
		$tables = $this->joining->tables();

		$this->assertContains(0x0628, $tables['leftJoining'], 'BEH joins to the letter after it');
		$this->assertContains(0x0628, $tables['rightJoining'], 'BEH joins to the letter before it');
	}

	public function testARightJoiningCharacterIsInTheRightTableOnly()
	{
		$tables = $this->joining->tables();

		$this->assertNotContains(0x0627, $tables['leftJoining']);
		$this->assertContains(0x0627, $tables['rightJoining']);
	}

	public function testALeftJoiningCharacterIsInTheLeftTableOnly()
	{
		$tables = $this->joining->tables();

		$this->assertContains(0x0620, $tables['leftJoining']);
		$this->assertNotContains(0x0620, $tables['rightJoining']);
	}

	/**
	 * Non-joining and transparent-joining characters join on neither side. U+0655 is the second of them
	 * here and is in no table at all: ArabicShaping.txt lists next to none of the T type, so its T lines
	 * are read for nothing and the Transparent-Joining table is built from DerivedJoiningType.txt alone.
	 *
	 * @dataProvider dataCharactersThatJoinNothing
	 */
	public function testACharacterThatJoinsNeitherWayIsInNoTable($codepoint)
	{
		$tables = $this->joining->tables();

		$this->assertNotContains($codepoint, $tables['leftJoining']);
		$this->assertNotContains($codepoint, $tables['rightJoining']);
		$this->assertNotContains($codepoint, $tables['transparent']);
	}

	public function dataCharactersThatJoinNothing()
	{
		return [
			'U+0600, joining type U' => [0x0600],
			'U+0655, joining type T in ArabicShaping.txt and nowhere else' => [0x0655],
		];
	}

	/**
	 * The Transparent type is what joining reads straight over, and the letters either side of such a
	 * character see each other rather than it. One of each script the shaper resolves, so that a table
	 * with a whole script missing cannot pass as complete.
	 *
	 * @dataProvider dataTransparentCharacters
	 */
	public function testATransparentCharacterIsInTheTransparentTableAndNeitherJoiningTable($codepoint)
	{
		$tables = $this->joining->tables();

		$this->assertContains($codepoint, $tables['transparent']);
		$this->assertNotContains($codepoint, $tables['leftJoining']);
		$this->assertNotContains($codepoint, $tables['rightJoining']);
	}

	public function dataTransparentCharacters()
	{
		return [
			'U+064E FATHA' => [0x064E],
			'U+0898, the mark #259 was measured with' => [0x0898],
			'U+0711 SYRIAC SUPERSCRIPT ALAPH' => [0x0711],
			'U+07EB, N\'Ko' => [0x07EB],
			'U+0859, Mandaic' => [0x0859],
		];
	}

	/**
	 * DerivedJoiningType.txt states ranges where ArabicShaping.txt never does, so one line can stand for
	 * a whole block of marks and every codepoint of it has to be written.
	 */
	public function testEveryCharacterOfARangeIsWritten()
	{
		$transparent = $this->joining->tables()['transparent'];

		$this->assertSame([0x0610, 0x0611, 0x0612, 0x0613, 0x0614, 0x0615], array_slice($transparent, 0, 6));
	}

	/**
	 * Out of scope for the same reason a Mongolian letter is: Mongolian forms a run of its own and shapes
	 * elsewhere, so a Mongolian variation selector is read for no run this shaper is given.
	 */
	public function testATransparentCharacterOfAScriptTheShaperNeverResolvesIsLeftOut()
	{
		$this->assertNotContains(0x180B, $this->joining->tables()['transparent']);
	}

	/**
	 * Causing a join means causing it in both directions, so a Join_Causing character is in both tables.
	 * The two here are of no script of their own - the tatweel a word is stretched with is Common and ZWJ
	 * is Inherited - which is the pair that would be lost if the scope were the four scripts alone.
	 *
	 * @dataProvider dataJoinCausingCharacters
	 */
	public function testAJoinCausingCharacterIsInBothTables($codepoint)
	{
		$tables = $this->joining->tables();

		$this->assertContains($codepoint, $tables['leftJoining']);
		$this->assertContains($codepoint, $tables['rightJoining']);
	}

	public function dataJoinCausingCharacters()
	{
		return [
			'U+0640 TATWEEL, script Common' => [0x0640],
			'U+200D ZWJ, script Inherited' => [0x200D],
		];
	}

	/**
	 * One letter of each of the four scripts resolveJoining() is called for, so that a table with a whole
	 * script missing cannot pass as complete
	 *
	 * @dataProvider dataScriptsTheShaperResolves
	 */
	public function testACharacterOfAScriptTheShaperResolvesIsWritten($codepoint)
	{
		$this->assertContains($codepoint, $this->joining->tables()['rightJoining']);
	}

	public function dataScriptsTheShaperResolves()
	{
		return [
			'arab' => [0x0628],
			'syrc' => [0x0860],
			'nko ' => [0x07CA],
			'mand' => [0x084F],
		];
	}

	/**
	 * Mongolian and Adlam join as well, and reach their forms through GSUB like every other script. What
	 * mPDF should do about them is a question about those scripts rather than about these two tables, and
	 * an entry here would be read for no run.
	 *
	 * @dataProvider dataScriptsTheShaperNeverResolves
	 */
	public function testACharacterOfAScriptTheShaperNeverResolvesIsLeftOut($codepoint)
	{
		$tables = $this->joining->tables();

		$this->assertNotContains($codepoint, $tables['leftJoining']);
		$this->assertNotContains($codepoint, $tables['rightJoining']);
	}

	public function dataScriptsTheShaperNeverResolves()
	{
		return [
			'Mongolian' => [0x1820],
			'Adlam' => [0x1E900],
		];
	}

	/**
	 * A character Ucdn gives no script is written rather than dropped. ScriptRuns::startsARun() is false
	 * for Unknown as it is for Common, so such a character stays in the run before it and
	 * the Arabic shaper is handed it anyway - which is how a joining type arrives that the script table has
	 * not caught up with, and dropping those would be #251's defect again with a longer fuse.
	 *
	 * U+FDD0 is a noncharacter, which Unicode's stability policy will never assign, so it is one of the few
	 * codepoints that reads as Unknown at every release. A codepoint merely reserved today would stop
	 * reaching this branch the moment Unicode gave it a script.
	 */
	public function testACharacterUcdnGivesNoScriptIsStillWritten()
	{
		$tables = $this->joining->tables();

		$this->assertContains(0xFDD0, $tables['leftJoining']);
		$this->assertContains(0xFDD1, $tables['transparent'], 'and the same for the Transparent type');
	}

	/**
	 * The four scripts are Otl's decision rather than this generator's: selectShaper() is what sends a run
	 * to the Arabic shaper, and a script added to that branch without being added here would bring the
	 * defect back for it with nothing failing. selectShaper() reads nothing but its argument, so an
	 * instance without a constructor is enough to ask it.
	 */
	public function testTheScopeIsTheScriptsOtlSendsToTheArabicShaper()
	{
		$selectShaper = new \ReflectionMethod('Mpdf\Otl', 'selectShaper');
		$selectShaper->setAccessible(true);
		$otl = new \ReflectionClass('Mpdf\Otl');

		$arabic = [];
		foreach (array_keys(Ucdn::$uni_scriptblock) as $script) {
			if ($selectShaper->invoke($otl->newInstanceWithoutConstructor(), $script) === 'A') {
				$arabic[] = $script;
			}
		}

		$this->assertSame(ArabicJoining::scripts(), $arabic);
	}

	public function testEachTableIsSortedByCodepoint()
	{
		foreach ($this->joining->tables() as $name => $codepoints) {
			$sorted = $codepoints;
			sort($sorted);

			$this->assertSame($sorted, $codepoints, $name);
		}
	}

	public function testTheRewrittenClassCarriesEveryTableAndTheVersionTheyWereBuiltFrom()
	{
		$copy = $this->dir . '/Copy.php';
		file_put_contents($copy, $this->copy('0.0.0', "\t\t0x0001 => 1,\n", "\t\t0x0002 => 1,\n", "\t\t0x0003 => 1,\n"));

		$written = $this->joining->rewrite($copy);

		$expected = $this->copy(
			'9.9.9',
			"\t\t0x0620 => 1, 0x0628 => 1, 0x0640 => 1, 0x07CA => 1, 0x084F => 1, 0x0860 => 1, 0x200D => 1, 0xFDD0 => 1,\n",
			"\t\t0x0627 => 1, 0x0628 => 1, 0x0640 => 1, 0x0710 => 1, 0x07CA => 1, 0x084F => 1, 0x0860 => 1, 0x200D => 1,\n"
			. "\t\t0x10EC2 => 1,\n",
			"\t\t0x0610 => 1, 0x0611 => 1, 0x0612 => 1, 0x0613 => 1, 0x0614 => 1, 0x0615 => 1, 0x064E => 1, 0x0711 => 1,\n"
			. "\t\t0x07EB => 1, 0x0859 => 1, 0x0898 => 1, 0xFDD1 => 1,\n"
		);

		$this->assertSame(['leftJoining' => 8, 'rightJoining' => 9, 'transparent' => 12], $written);
		$this->assertSame($expected, file_get_contents($copy));

		$this->joining->rewrite($copy);

		$this->assertSame($expected, file_get_contents($copy), 'a second run over the same file writes the same bytes');
	}

	/**
	 * The generator is what produced the tables that are checked in: running it again over the file it was
	 * run with must write the same bytes, or a diff cannot be read.
	 *
	 * Skipped unless that file is still on this machine - it is not committed, and the test is worth
	 * nothing against another version of the database.
	 */
	public function testTheCheckedInTablesAreWhatTheGeneratorWrites()
	{
		$version = ArabicJoining::DEFAULT_VERSION;
		$files = __DIR__ . '/../../utils/data/ucd/' . $version;
		if (!is_file($files . '/ArabicShaping.txt') || !is_file($files . '/DerivedJoiningType.txt')) {
			$this->markTestSkipped(sprintf('Unicode %s is not unpacked here: composer arabicjoining:update', $version));
		}

		$source = __DIR__ . '/../../src/Shaper/Arabic.php';
		$copy = $this->dir . '/Rebuilt.php';
		copy($source, $copy);

		$joining = new ArabicJoining($version, $files);
		$joining->rewrite($copy);

		$this->assertSame(
			file_get_contents($source),
			file_get_contents($copy),
			'composer arabicjoining:update would rewrite src/Shaper/Arabic.php'
		);
	}

	/**
	 * @return string A class holding the three joining tables, ready to be rewritten
	 */
	private function copy($version, $left, $right, $transparent)
	{
		return "<?php\n\nclass Copy\n{\n\n\t// UNIDATA_VERSION " . $version . "\n"
			. "\tpublic static \$leftJoining = [\n" . $left . "\t];\n\n"
			. "\tpublic static \$rightJoining = [\n" . $right . "\t];\n\n"
			. "\tpublic static \$transparent = [\n" . $transparent . "\t];\n\n}\n";
	}

	/**
	 * An ArabicShaping.txt of a dozen lines, carrying every joining type, two Join_Causing characters of no
	 * script, one letter of each script the shaper resolves, two of scripts it does not and one codepoint
	 * Ucdn gives no script. The codepoints are Unicode's own, because what is in scope is read from the
	 * script Ucdn gives each one; the types are not always, the L line being put on an Arabic letter so that
	 * the reading of it does not depend on which release first gave an in-scope character that type.
	 *
	 * @return string
	 */
	private function shaping()
	{
		return "# ArabicShaping-9.9.9.txt\n"
			. "#\n"
			. "# Unicode; Schematic Name; Joining Type; Joining Group\n"
			. "0600; ARABIC NUMBER SIGN; U; No_Joining_Group\n"
			. "0620; KASHMIRI YEH; L; YEH\n"
			. "0627; ALEF; R; ALEF\n"
			. "0628; BEH; D; BEH\n"
			. "0640; TATWEEL; C; No_Joining_Group\n"
			. "0655; HAMZA BELOW; T; No_Joining_Group\n"
			. "0710; SYRIAC ALAPH; R; ALAPH\n"
			. "07CA; NKO A; D; No_Joining_Group\n"
			. "084F; MANDAIC IN; D; No_Joining_Group\n"
			. "0860; MALAYALAM NGA; D; MALAYALAM NGA\n"
			. "200D; ZERO WIDTH JOINER; C; No_Joining_Group\n"
			. "1820; MONGOLIAN LETTER A; D; No_Joining_Group\n"
			. "1E900; ADLAM CAPITAL ALIF; D; No_Joining_Group\n"
			. "10EC2; DAL WITH VERTICAL 2 DOTS BELOW; R; DAL\n"
			. "FDD0; NONCHARACTER; L; No_Joining_Group\n";
	}

	/**
	 * A DerivedJoiningType.txt of a dozen lines, in the columns Unicode writes it in: a range and a single
	 * codepoint, one transparent mark of each script the shaper resolves, one of a script it does not, one
	 * codepoint Ucdn gives no script, and a line of another type to be read past. As above, the codepoints
	 * are Unicode's own because the scope is read from the script Ucdn gives each one.
	 *
	 * @return string
	 */
	private function derived()
	{
		return "# DerivedJoiningType-9.9.9.txt\n"
			. "#\n"
			. "# Joining_Type=Transparent\n"
			. "0610..0615    ; T # Mn   [6] ARABIC SIGN SALLALLAHOU ALAYHE WASSALLAM..ARABIC SMALL HIGH TAH\n"
			. "064E          ; T # Mn       ARABIC FATHA\n"
			. "0711          ; T # Mn       SYRIAC LETTER SUPERSCRIPT ALAPH\n"
			. "07EB          ; T # Mn       NKO COMBINING SHORT HIGH TONE\n"
			. "0859          ; T # Mn       MANDAIC AFFRICATION MARK\n"
			. "0898          ; T # Mn       ARABIC SMALL HIGH WORD AL-JUZ\n"
			. "180B          ; T # Mn       MONGOLIAN FREE VARIATION SELECTOR ONE\n"
			. "FDD1          ; T # Cn       NONCHARACTER\n"
			. "#\n"
			. "# Joining_Type=Dual_Joining\n"
			. "0628          ; D # Lo       ARABIC LETTER BEH\n";
	}

}
