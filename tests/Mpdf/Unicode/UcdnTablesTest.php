<?php

namespace Mpdf\Unicode;

/**
 * The generator behind composer ucdn:update, run over a database written here rather than the real
 * one - a few dozen lines carrying the shapes a Unicode Character Database uses, and the properties
 * of characters whose values are worth knowing by heart.
 *
 * Every test but the last is a round trip: build the tables into a copy of Ucdn, load that copy and
 * read them back through get_ucd_record() itself. A table the generator builds wrongly and reads
 * back consistently is exactly the failure the fixtures of UcdnTest cannot see, and the only way to
 * rule it out is to walk the three index levels with the code the renderer walks them with.
 *
 * The copy is built over the first 0x5000 codepoints instead of all 0x110000, which is the same
 * table with fewer blocks in it and turns a minute of table building into an instant.
 */
class UcdnTablesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * How much of Unicode the copy covers. A multiple of 256, so that the last block of each index
	 * level is whole.
	 */
	const CODEPOINTS = 0x5000;

	/**
	 * Names the copies apart, so that more than one test may load one
	 *
	 * @var int
	 */
	private static $copies = 0;

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var string The class the current test built, ready to be called statically
	 */
	private $ucdn;

	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-ucdn-tables-' . getmypid() . '-' . self::$copies;
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0777, true);
		}

		foreach ($this->database() as $name => $body) {
			file_put_contents($this->dir . '/' . $name, $body);
		}

		$this->ucdn = $this->build();
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
	 * The three fields UnicodeData.txt states outright, read back through the index tables
	 */
	public function testACharacterReadsBackTheRecordItWasBuiltFrom()
	{
		$class = $this->ucdn;

		$this->assertSame(
			[Ucdn::UNICODE_GENERAL_CATEGORY_UPPERCASE_LETTER, 0, Ucdn::BIDI_CLASS_L, 0, 3, 0, Ucdn::SCRIPT_LATIN],
			$class::get_ucd_record(0x0041),
			'U+0041 LATIN CAPITAL LETTER A'
		);

		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK, $class::get_general_category(0x0301));
		$this->assertSame(230, $class::get_combining_class(0x0301), 'U+0301 COMBINING ACUTE ACCENT');
		$this->assertSame(Ucdn::BIDI_CLASS_NSM, $class::get_bidi_class(0x0301));
		$this->assertSame(Ucdn::SCRIPT_INHERITED, $class::get_script(0x0301));

		$this->assertSame(1, $class::get_mirrored(0x0028), 'U+0028 LEFT PARENTHESIS mirrors');
		$this->assertSame(0, $class::get_mirrored(0x0041), 'U+0041 does not');
	}

	/**
	 * A block too large to list is written as a First/Last pair of lines, and every codepoint between
	 * them has the record the pair states
	 */
	public function testAFirstLastPairFillsEveryCodepointBetweenThem()
	{
		$class = $this->ucdn;
		$record = [Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER, 0, Ucdn::BIDI_CLASS_L, 0, 2, 0, Ucdn::SCRIPT_HAN];

		$this->assertSame($record, $class::get_ucd_record(0x3400), 'the first of the pair');
		$this->assertSame($record, $class::get_ucd_record(0x4000), 'a codepoint the file never names');
		$this->assertSame($record, $class::get_ucd_record(0x4DBF), 'the last of the pair');
		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_UNASSIGNED, $class::get_general_category(0x4DC0), 'past the end of it');
	}

	/**
	 * Bidi class is the one property whose unassigned codepoints are not all alike: it defaults to L
	 * over the whole of Unicode, and the file then narrows that to R over the Hebrew block and AL over
	 * the Arabic ones. Lay the defaults in the wrong order and a reserved codepoint in an Arabic block
	 * reads left-to-right.
	 */
	public function testAnUnassignedCodepointTakesTheNarrowestDefaultOverIt()
	{
		$class = $this->ucdn;

		$this->assertSame(Ucdn::BIDI_CLASS_R, $class::get_bidi_class(0x05EB), 'reserved inside the Hebrew block');
		$this->assertSame(Ucdn::BIDI_CLASS_AL, $class::get_bidi_class(0x070E), 'reserved inside an Arabic block');
		$this->assertSame(Ucdn::BIDI_CLASS_L, $class::get_bidi_class(0x0100), 'reserved with no default over it');

		foreach ([0x05EB, 0x070E, 0x0100] as $char) {
			$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_UNASSIGNED, $class::get_general_category($char));
			$this->assertSame(Ucdn::SCRIPT_UNKNOWN, $class::get_script($char));
		}
	}

	/**
	 * The four isolate classes of Unicode 6.3 are written as ON, which is the class Bidi resolves
	 * them as - see the note on UcdnTables
	 */
	public function testAnIsolateInitiatorIsWrittenAsANeutral()
	{
		$class = $this->ucdn;

		$this->assertSame(Ucdn::BIDI_CLASS_ON, $class::get_bidi_class(0x2066), 'U+2066 LEFT-TO-RIGHT ISOLATE');
		$this->assertSame(Ucdn::UNICODE_GENERAL_CATEGORY_FORMAT, $class::get_general_category(0x2066), 'the rest of its record is the database\'s');
	}

	/**
	 * Nothing reads normalization_check and nothing has ever written it
	 */
	public function testTheNormalizationCheckIsZeroThroughout()
	{
		$class = $this->ucdn;

		foreach ([0x0041, 0x0301, 0x0628, 0x3400, 0x4DC0] as $char) {
			$record = $class::get_ucd_record($char);
			$this->assertSame(0, $record[5], sprintf('U+%04X', $char));
		}
	}

	/**
	 * get_ucd_record() answers for anything, and record 0 - what a number past the last codepoint
	 * Unicode has reads - is the record of a codepoint nothing is known about
	 */
	public function testACodepointPastTheEndOfUnicodeReadsAsUnassigned()
	{
		$class = $this->ucdn;

		$this->assertSame(
			[Ucdn::UNICODE_GENERAL_CATEGORY_UNASSIGNED, 0, Ucdn::BIDI_CLASS_L, 0, 5, 0, Ucdn::SCRIPT_UNKNOWN],
			$class::get_ucd_record(0x110000)
		);
	}

	/**
	 * Both ways round, from the one direction BidiMirroring.txt states
	 */
	public function testAMirroredCharacterIsPairedBothWaysRound()
	{
		$class = $this->ucdn;

		$this->assertSame([40 => 41, 41 => 40], $class::$mirror_pairs);
	}

	/**
	 * A script the class has not seen is appended after the last number in use and given the
	 * lowercased ISO 15924 code, and every script that already had a number keeps it - callers
	 * compare those numbers by range.
	 *
	 * The copy has Adlam and Toto taken out of it, leaving holes at their numbers. Where the two the
	 * database appends land is read off the copy rather than written out here, because it is one past
	 * however many scripts Unicode has.
	 */
	public function testANewScriptIsAppendedAndTheRestKeepTheirNumbers()
	{
		$constants = $this->constantsOf($this->ucdn);
		$appended = max(array_diff_key($constants, ['SCRIPT_ADLAM' => 1, 'SCRIPT_TOTO' => 1])) + 1;

		// The copy had neither, and the database lists Toto first - they are numbered alphabetically
		// and after the last number in use, rather than in the order read or into the holes left.
		$this->assertSame($appended, $constants['SCRIPT_ADLAM'], 'Adlam, which the database lists second');
		$this->assertSame($appended + 1, $constants['SCRIPT_TOTO'], 'Toto, which it lists first');

		$block = $this->ucdn;
		$this->assertSame('adlm', $block::$uni_scriptblock[$appended]);
		$this->assertSame('toto', $block::$uni_scriptblock[$appended + 1]);

		$this->assertSame(1, $constants['SCRIPT_LATIN']);
		$this->assertSame(9, $constants['SCRIPT_DEVANAGARI']);
		$this->assertSame(17, $constants['SCRIPT_MALAYALAM']);
		$this->assertSame(102, $constants['SCRIPT_UNKNOWN']);
	}

	/**
	 * The database written here names nine scripts. Every other script keeps the constant and the tag
	 * it has rather than being dropped, and a chosen tag is never rewritten as the code it derives from.
	 */
	public function testAScriptTheDatabaseDoesNotNameKeepsItsConstantAndItsTag()
	{
		$constants = $this->constantsOf($this->ucdn);
		$block = $this->ucdn;

		$this->assertArrayHasKey('SCRIPT_DEVANAGARI', $constants, 'a script the database written here never mentions');
		$this->assertSame('dev2', $block::$uni_scriptblock[$constants['SCRIPT_DEVANAGARI']], 'Devanagari asks for the version 2 tag, not deva');
		$this->assertSame('jamo', $block::$uni_scriptblock[$constants['SCRIPT_HANGUL']], 'Hangul asks for jamo, not hang');
		$this->assertSame('kana', $block::$uni_scriptblock[$constants['SCRIPT_HIRAGANA']]);
	}

	/**
	 * The generator is what produced the table that is checked in: running it again over the database
	 * it was run with must write the same bytes, or a diff cannot be read.
	 *
	 * Skipped unless that database is still on this machine - it is not committed, and the test is
	 * worth nothing against a different one.
	 */
	public function testTheCheckedInTableIsWhatTheGeneratorWrites()
	{
		$version = UcdnTables::DEFAULT_VERSION;
		$files = __DIR__ . '/../../../utils/data/ucd/' . $version;
		if (!is_file($files . '/UnicodeData.txt')) {
			$this->markTestSkipped(sprintf('Unicode %s is not unpacked here: composer ucdn:update', $version));
		}

		$source = __DIR__ . '/../../../src/Unicode/Ucdn.php';
		$copy = $this->dir . '/Rebuilt.php';
		copy($source, $copy);

		$tables = new UcdnTables($version, $files);
		$tables->rewrite($copy);

		$this->assertSame(
			file_get_contents($source),
			file_get_contents($copy),
			'composer ucdn:update would rewrite src/Unicode/Ucdn.php'
		);
	}

	/**
	 * Builds the tables into a copy of Ucdn and loads it.
	 *
	 * The copy is the real class under another name, so the round trip runs through the real
	 * get_ucd_record(), with two scripts taken back out of it so that the database written here has
	 * two to append.
	 *
	 * @return string The class, ready to be called statically
	 */
	private function build()
	{
		$name = 'UcdnCopy' . self::$copies++;
		$file = $this->dir . '/' . $name . '.php';

		// A Windows checkout ends the class's lines with CRLF; the copy is written in LF, which is
		// all the two patterns below and the generator need agree on.
		$source = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../../../src/Unicode/Ucdn.php'));
		$source = str_replace('class Ucdn', 'class ' . $name, $source);
		$source = preg_replace('/\tconst SCRIPT_(ADLAM|TOTO) = \d+;\n/', '', $source);
		$source = preg_replace('/\t\t\/\* SCRIPT_(ADLAM|TOTO) \*\/ \d+ => .*\n/', '', $source);
		file_put_contents($file, $source);

		$tables = new UcdnTables('written-here', $this->dir, self::CODEPOINTS);
		$tables->rewrite($file);

		require $file;

		return __NAMESPACE__ . '\\' . $name;
	}

	/**
	 * @return int[] the value of every SCRIPT_ constant of a class, by name
	 */
	private function constantsOf($class)
	{
		$reflection = new \ReflectionClass($class);
		$scripts = [];

		foreach ($reflection->getConstants() as $constant => $value) {
			if (strpos($constant, 'SCRIPT_') === 0) {
				$scripts[$constant] = $value;
			}
		}

		return $scripts;
	}

	/**
	 * A Unicode Character Database of a few dozen lines, carrying the shapes the real files use: the
	 * "@missing" defaults that narrow one another, a First/Last pair, ranges, single codepoints and
	 * comments. Every value in it is the value Unicode gives that character.
	 *
	 * @return string[] the file's contents, by name
	 */
	private function database()
	{
		return [
			'PropertyValueAliases.txt' => "# Written for UcdnTablesTest\n"
				. "bc ; AL        ; Arabic_Letter\n"
				. "bc ; L         ; Left_To_Right\n"
				. "bc ; LRI       ; Left_To_Right_Isolate\n"
				. "bc ; NSM       ; Nonspacing_Mark\n"
				. "bc ; ON        ; Other_Neutral\n"
				. "bc ; R         ; Right_To_Left\n"
				. "ea ; N         ; Neutral\n"
				. "ea ; Na        ; Narrow\n"
				. "ea ; W         ; Wide\n"
				. "sc ; Arab      ; Arabic\n"
				. "sc ; Hani      ; Han\n"
				. "sc ; Hebr      ; Hebrew\n"
				. "sc ; Latn      ; Latin\n"
				. "sc ; Adlm      ; Adlam\n"
				. "sc ; Toto      ; Toto\n"
				. "sc ; Zinh      ; Inherited\n"
				. "sc ; Zyyy      ; Common\n"
				. "sc ; Zzzz      ; Unknown\n",

			'UnicodeData.txt' => "0028;LEFT PARENTHESIS;Ps;0;ON;;;;;Y;OPENING PARENTHESIS;;;;\n"
				. "0029;RIGHT PARENTHESIS;Pe;0;ON;;;;;Y;CLOSING PARENTHESIS;;;;\n"
				. "0041;LATIN CAPITAL LETTER A;Lu;0;L;;;;;N;;;;0061;\n"
				. "0061;LATIN SMALL LETTER A;Ll;0;L;;;;;N;;;0041;;\n"
				. "0301;COMBINING ACUTE ACCENT;Mn;230;NSM;;;;;N;NON-SPACING ACUTE;;;;\n"
				. "05D0;HEBREW LETTER ALEF;Lo;0;R;;;;;N;;;;;\n"
				. "0628;ARABIC LETTER BEH;Lo;0;AL;;;;;N;;;;;\n"
				. "0870;ARABIC LETTER ALEF WITH ATTACHED FATHA;Lo;0;AL;;;;;N;;;;;\n"
				. "2066;LEFT-TO-RIGHT ISOLATE;Cf;0;LRI;;;;;N;;;;;\n"
				. "3400;<CJK Ideograph Extension A, First>;Lo;0;L;;;;;N;;;;;\n"
				. "4DBF;<CJK Ideograph Extension A, Last>;Lo;0;L;;;;;N;;;;;\n",

			'Scripts.txt' => "# @missing: 0000..10FFFF; Unknown\n"
				. "0028..0029    ; Common # Ps Pe   [2] LEFT PARENTHESIS..RIGHT PARENTHESIS\n"
				. "0041..005A    ; Latin\n"
				. "0061..007A    ; Latin\n"
				. "0300..036F    ; Inherited\n"
				. "05D0..05EA    ; Hebrew\n"
				. "0628          ; Arabic\n"
				. "0870          ; Arabic\n"
				. "2066          ; Common\n"
				. "3400..4DBF    ; Han\n"
				. "1E290..1E2AE  ; Toto\n"
				. "1E900..1E94B  ; Adlam\n",

			'DerivedBidiClass.txt' => "# @missing: 0000..10FFFF; Left_To_Right\n"
				. "# 0590..05FF Hebrew\n"
				. "# @missing: 0590..05FF; Right_To_Left\n"
				. "# 0600..07BF Arabic\n"
				. "# @missing: 0600..07BF; Arabic_Letter\n"
				. "0028..0029    ; ON\n"
				. "0041..005A    ; L\n"
				. "0061..007A    ; L\n"
				. "0300..036F    ; NSM\n"
				. "05D0..05EA    ; R\n"
				. "0628          ; AL\n"
				. "0870          ; AL\n"
				. "2066          ; LRI\n"
				. "3400..4DBF    ; L\n",

			'EastAsianWidth.txt' => "# @missing: 0000..10FFFF; N\n"
				. "0028..0029    ; Na\n"
				. "0041..005A    ; Na\n"
				. "0061..007A    ; Na\n"
				. "3400..4DBF    ; W\n",

			'BidiMirroring.txt' => "# @missing: 0000..10FFFF; <none>\n"
				. "0028; 0029 # LEFT PARENTHESIS\n"
				. "0029; 0028 # RIGHT PARENTHESIS\n",
		];
	}

}
