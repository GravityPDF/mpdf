<?php

namespace Mpdf\Unicode;

/**
 * The generator behind composer emoji:update, run over an emoji-data.txt written here rather than
 * Unicode's.
 */
class EmojiTablesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var EmojiTables
	 */
	private $tables;

	/**
	 * Writes an emoji-data.txt of a few lines for the generator to read
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-emoji-tables-' . getmypid();
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0777, true);
		}
		file_put_contents($this->dir . '/emoji-data.txt', $this->data());

		$this->tables = new EmojiTables('9.9.9', $this->dir);
	}

	/**
	 * Removes what set_up() wrote, and anything a test wrote beside it
	 */
	protected function tear_down()
	{
		parent::tear_down();

		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);
	}

	/**
	 * The file lists a property's codepoints as it likes, one per line or a range per line; the table
	 * holds each run of them once
	 */
	public function testRangesThatTouchAreMerged()
	{
		$tables = $this->tables->tables();

		$this->assertSame([[0x23, 0x23], [0x30, 0x39], [0x1F600, 0x1F64F]], $tables['emojiPresentation']);
	}

	/**
	 * A line is filed under the property it names, so one property's codepoints never reach another's
	 * table
	 */
	public function testEachPropertyIsReadIntoItsOwnTable()
	{
		$tables = $this->tables->tables();

		$this->assertSame([[0x1F645, 0x1F647]], $tables['emojiModifierBase']);
		$this->assertSame([[0x1F000, 0x1FAFF]], $tables['extendedPictographic']);
	}

	/**
	 * Rewriting a class replaces each table and the version it names, and leaves the rest of it alone
	 */
	public function testTheRewrittenClassCarriesEveryTableAndTheVersionTheyWereBuiltFrom()
	{
		$copy = $this->dir . '/Copy.php';
		file_put_contents($copy, $this->copy('0.0.0'));

		$written = $this->tables->rewrite($copy);

		$this->assertSame(['emojiPresentation' => 3, 'emojiModifierBase' => 1, 'extendedPictographic' => 1], $written);
		$this->assertSame(
			"<?php\n\nclass Copy\n{\n\n\t// UNIDATA_VERSION 9.9.9\n"
			. "\tprivate static \$emojiPresentation = [\n\t\t[0x0023, 0x0023], [0x0030, 0x0039], [0x1F600, 0x1F64F],\n\t];\n\n"
			. "\tprivate static \$emojiModifierBase = [\n\t\t[0x1F645, 0x1F647],\n\t];\n\n"
			. "\tprivate static \$extendedPictographic = [\n\t\t[0x1F000, 0x1FAFF],\n\t];\n\n}\n",
			file_get_contents($copy)
		);
	}

	/**
	 * The generator is what produced the tables that are checked in: running it again over the file it
	 * was run with must write the same bytes, or a diff cannot be read.
	 *
	 * Skipped unless that file is still on this machine - it is not committed, and the test is worth
	 * nothing against another version of the data.
	 */
	public function testTheCheckedInTablesAreWhatTheGeneratorWrites()
	{
		$version = EmojiTables::DEFAULT_VERSION;
		$files = __DIR__ . '/../../../utils/data/ucd/' . $version;
		if (!is_file($files . '/emoji-data.txt')) {
			$this->markTestSkipped(sprintf('Unicode %s emoji data is not unpacked here: composer emoji:update', $version));
		}

		$source = __DIR__ . '/../../../src/Unicode/Emoji.php';
		$copy = $this->dir . '/Rebuilt.php';
		copy($source, $copy);

		(new EmojiTables($version, $files))->rewrite($copy);

		$this->assertSame(file_get_contents($source), file_get_contents($copy), 'composer emoji:update would rewrite src/Unicode/Emoji.php');
	}

	/**
	 * @param string $version The Unicode version the class claims its tables were built from
	 *
	 * @return string A class holding the three tables, ready to be rewritten
	 */
	private function copy($version)
	{
		$source = "<?php\n\nclass Copy\n{\n\n\t// UNIDATA_VERSION " . $version . "\n";
		foreach (['emojiPresentation', 'emojiModifierBase', 'extendedPictographic'] as $name) {
			$source .= "\tprivate static \$" . $name . " = [\n\t\t[0x0001, 0x0001],\n\t];\n\n";
		}

		return $source . "}\n";
	}

	/**
	 * Every shape a line of the file takes: a single codepoint, a range, two ranges of one property that
	 * touch, and properties the class does not keep. The presentation lines are shaped for the test
	 * rather than copied from Unicode.
	 *
	 * @return string
	 */
	private function data()
	{
		return "# emoji-data.txt\n"
			. "# Version: 9.9.9\n"
			. "0023          ; Emoji                # E0.0   [1] (#️)       hash sign\n"
			. "0023          ; Emoji_Presentation   # not so in Unicode, but a single codepoint to read\n"
			. "0030..0039    ; Emoji_Presentation   # not so in Unicode, but a range to read\n"
			. "1F600..1F63F  ; Emoji_Presentation   # E1.0  [64] grinning face..\n"
			. "1F640..1F64F  ; Emoji_Presentation   # E1.0  [16] ..\n"
			. "1F3FB..1F3FF  ; Emoji_Modifier       # E1.0   [5] skin tones\n"
			. "1F645..1F647  ; Emoji_Modifier_Base  # E0.6   [3] ..\n"
			. "0023          ; Emoji_Component      # E0.0   [1] hash sign\n"
			. "1F000..1FAFF  ; Extended_Pictographic# E0.0[2816] ..\n";
	}
}
