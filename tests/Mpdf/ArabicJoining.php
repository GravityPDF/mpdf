<?php

namespace Mpdf;

/**
 * The joining tables of Mpdf\Shaper\Arabic, rebuilt from a Unicode Character Database's
 * ArabicShaping.txt. What composer arabicjoining:update runs - see utils/arabicjoining_update.php,
 * which is the argument parsing and nothing else.
 *
 * rewrite() replaces $leftJoining, $rightJoining and the UNIDATA_VERSION line above them, and leaves
 * the rest of the class alone. A character is left-joining where ArabicShaping.txt gives its
 * Joining_Type as L, D or C, and right-joining where it gives R, D or C, which is what the two tables
 * say: whether a letter joins to what follows it, and whether it joins to what precedes it.
 *
 * $transparent is not generated. ArabicShaping.txt lists no Transparent-Joining character at all -
 * Unicode leaves T to be derived from the general category, and says so in the file's own header - and
 * the table also carries codepoints that are no joining type of Unicode's, the presentation-form
 * ligatures a font's 'ccmp' produces. It stays hand-written.
 */
class ArabicJoining
{

	use GeneratedTable;

	/**
	 * The database read when none is named.
	 *
	 * Tied to the one Ucdn's tables were built from, because the script is what routes a run to this
	 * shaper: a joining type read from a newer database than Ucdn's scripts would be a form resolved for
	 * a character Otl never sends here. Moving one means moving both.
	 */
	const DEFAULT_VERSION = UcdnTables::DEFAULT_VERSION;

	/**
	 * How many entries a line of a written table carries, as the hand-written tables were laid out
	 */
	const PER_LINE = 8;

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string The directory the database is read from, and downloaded to when it is not there
	 */
	private $files;

	public function __construct($version = self::DEFAULT_VERSION, $files = null)
	{
		$this->version = $version;
		$this->files = $files === null ? __DIR__ . '/../../utils/data/ucd/' . $version : $files;
	}

	/**
	 * Rewrites the two joining tables of the class at $path from the database.
	 *
	 * @param string $path The class to rewrite, in place
	 *
	 * @return int[] How many characters each table was written with, by its property name
	 */
	public function rewrite($path)
	{
		$source = $this->sourceInLf($path);
		$tables = $this->tables();

		$source = preg_replace(
			'/\t\/\/ UNIDATA_VERSION [\d.]+\n/',
			"\t// UNIDATA_VERSION " . $this->version . "\n",
			$source,
			1
		);

		$written = [];
		foreach ($tables as $name => $codepoints) {
			$source = $this->replaceArray($source, 'public static $' . $name, $this->entries($codepoints));
			$written[$name] = count($codepoints);
		}

		$this->writeBack($path, $source);

		return $written;
	}

	/**
	 * Both tables, each as its codepoints in ascending order, keyed by the property that holds them.
	 *
	 * @return int[][]
	 */
	public function tables()
	{
		$left = [];
		$right = [];

		foreach ($this->joiningTypes() as $codepoint => $type) {
			if ($type !== 'R') {
				$left[] = $codepoint;
			}
			if ($type !== 'L') {
				$right[] = $codepoint;
			}
		}

		sort($left);
		sort($right);

		return ['leftJoining' => $left, 'rightJoining' => $right];
	}

	/**
	 * Every character the shaper can be asked about that joins on either side, by codepoint.
	 *
	 * @return string[] codepoint => Joining_Type, one of L, R, D or C
	 */
	private function joiningTypes()
	{
		$types = [];

		foreach ($this->lines('ArabicShaping.txt') as $line) {
			$line = preg_replace('/#.*$/', '', $line);
			if (!preg_match('/^\s*([0-9A-F]+)\s*;[^;]*;\s*([A-Z])\s*;/', $line, $m)) {
				continue;
			}

			$codepoint = hexdec($m[1]);
			if (!in_array($m[2], ['L', 'R', 'D', 'C'], true) || !$this->inScope($codepoint)) {
				continue;
			}

			$types[$codepoint] = $m[2];
		}

		return $types;
	}

	/**
	 * Whether the shaper is ever asked how this character joins.
	 *
	 * Otl splits a line by script and picks the shaper from it, so only the four scripts resolveJoining()
	 * is called for reach these tables. Mongolian, Phags-pa, Manichaean, Psalter Pahlavi, Chorasmian,
	 * Sogdian, Old Uyghur, Hanifi Rohingya and Adlam all have joining types in the same file and all
	 * shape elsewhere, and whether mPDF should join them is a question about those scripts rather than
	 * about this table.
	 *
	 * The scripts are read from Ucdn rather than from a list of block ranges so that the two cannot
	 * disagree: a character Ucdn gives no script to cannot reach this shaper however it joins, which is
	 * why the two tables are only as new as Ucdn's script table and why DEFAULT_VERSION is tied to it.
	 */
	private function inScope($codepoint)
	{
		$script = Ucdn::get_script($codepoint);

		// The Join_Causing characters - the tatweel a word is stretched with, and ZWJ - belong to no one
		// script, because they join whatever is written beside them. Unicode gives them Common.
		if ($script === Ucdn::SCRIPT_COMMON || $script === Ucdn::SCRIPT_INHERITED) {
			return true;
		}

		return in_array($script, [Ucdn::SCRIPT_ARABIC, Ucdn::SCRIPT_SYRIAC, Ucdn::SCRIPT_NKO, Ucdn::SCRIPT_MANDAIC], true);
	}

	/**
	 * A table as the class holds it: PER_LINE entries to a line, so a block boundary can be read off the
	 * codepoints.
	 *
	 * @param int[] $codepoints
	 *
	 * @return string
	 */
	private function entries($codepoints)
	{
		$lines = [];

		foreach (array_chunk($codepoints, self::PER_LINE) as $chunk) {
			$entries = [];
			foreach ($chunk as $codepoint) {
				$entries[] = sprintf('0x%04X => 1', $codepoint);
			}
			$lines[] = "\t\t" . implode(', ', $entries) . ',';
		}

		return implode("\n", $lines);
	}

	/**
	 * Reads one file of the database.
	 *
	 * @return string[] the file's lines
	 */
	public function lines($name)
	{
		$url = 'https://www.unicode.org/Public/' . $this->version . '/ucd/' . $name;

		return explode("\n", $this->cached($this->files . '/' . basename($name), $url));
	}

}
