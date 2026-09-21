<?php

namespace Mpdf;

use Mpdf\Unicode\Ucdn;
use Mpdf\Unicode\UcdnTables;

/**
 * The joining tables of Mpdf\Shaper\Arabic, rebuilt from a Unicode Character Database. What composer
 * arabicjoining:update runs - see utils/arabicjoining_update.php, which is the argument parsing and
 * nothing else.
 *
 * rewrite() replaces $leftJoining, $rightJoining, $transparent and the UNIDATA_VERSION line above them,
 * and leaves the rest of the class alone. A character is left-joining where ArabicShaping.txt gives its
 * Joining_Type as L, D or C, and right-joining where it gives R, D or C, which is what the first two
 * tables say: whether a letter joins to what follows it, and whether it joins to what precedes it. The
 * third is the Transparent type, which joining reads straight over.
 *
 * T comes out of extracted/DerivedJoiningType.txt rather than ArabicShaping.txt, whose own header says
 * the type is left to be derived from the general category rather than listed there.
 */
class ArabicJoining
{

	use GeneratedTable;

	/**
	 * The database read when none is named, which is the release the rest of mPDF's generated Unicode
	 * tables are at. Named there rather than again here so that one answer to "which Unicode is this?"
	 * covers the repository; either generator still takes a version of its own on the command line.
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
	 * Rewrites the three joining tables of the class at $path from the database.
	 *
	 * @param string $path The class to rewrite, in place
	 *
	 * @return int[] How many characters each table was written with, by its property name
	 */
	public function rewrite($path)
	{
		$source = $this->sourceInLf($path);
		$tables = $this->tables();

		$source = $this->replaceVersion($source, 'UNIDATA_VERSION', $this->version);

		$written = [];
		foreach ($tables as $name => $codepoints) {
			$source = $this->replaceArray($source, 'public static $' . $name, $this->entries($codepoints));
			$written[$name] = count($codepoints);
		}

		$this->writeBack($path, $source);

		return $written;
	}

	/**
	 * All three tables, each as its codepoints in ascending order, keyed by the property that holds them.
	 *
	 * @return int[][]
	 */
	public function tables()
	{
		$left = [];
		$right = [];

		foreach ($this->joiningTypes() as $codepoint => $type) {
			if (in_array($type, ['L', 'D', 'C'], true)) {
				$left[] = $codepoint;
			}
			if (in_array($type, ['R', 'D', 'C'], true)) {
				$right[] = $codepoint;
			}
		}

		$transparent = $this->transparentCharacters();

		sort($left);
		sort($right);
		sort($transparent);

		return ['leftJoining' => $left, 'rightJoining' => $right, 'transparent' => $transparent];
	}

	/**
	 * Every character the shaper can be asked about that joins on either side, by codepoint. The
	 * non-joining and transparent-joining types are dropped here rather than partitioned above, so that
	 * neither table is a table of what is left over.
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
	 * Every character the shaper can be asked about that joining reads straight over: Unicode's
	 * Transparent type, which is the marks and the invisible format characters rather than just the
	 * vowels this table was once named for.
	 *
	 * The derivation Unicode publishes states ranges, which ArabicShaping.txt never does, so one line can
	 * stand for thousands of codepoints. Its "@missing" default is Non_Joining, so the defaults ranges()
	 * reads first are nothing to expand.
	 *
	 * @return int[]
	 */
	private function transparentCharacters()
	{
		$codepoints = [];

		list(, $values) = $this->ranges($this->lines('extracted/DerivedJoiningType.txt'));

		foreach ($values as $range) {
			list($start, $end, $type) = $range;
			if ($type !== 'T') {
				continue;
			}

			for ($codepoint = $start; $codepoint <= $end; $codepoint++) {
				if ($this->inScope($codepoint)) {
					$codepoints[] = $codepoint;
				}
			}
		}

		return $codepoints;
	}

	/**
	 * Whether this character can stand inside a run the Arabic shaper is given, which is the only way
	 * any of the tables is ever read.
	 *
	 * ScriptRuns::split() cuts a line into runs at each change of script and Otl::selectShaper()
	 * picks the shaper from the run's, so the four scripts of scripts() are in scope - Mongolian,
	 * Phags-pa, Manichaean, Psalter Pahlavi, Chorasmian, Sogdian, Old Uyghur, Hanifi Rohingya and Adlam
	 * have joining types in the same file, form runs of their own and shape elsewhere, and whether mPDF
	 * should join them is a question about those scripts rather than about this table.
	 */
	private function inScope($codepoint)
	{
		$script = Ucdn::get_script($codepoint);

		// A character that starts no run of its own is left in the run before it, so any of them can be
		// read as part of an Arabic one. That includes Unknown, which is how a codepoint newer than
		// Ucdn's script table arrives, and it still unjoins the letter before it, so dropping those
		// would be this same defect with a shorter fuse.
		if (!ScriptRuns::startsARun($script)) {
			return true;
		}

		return in_array($script, self::scripts(), true);
	}

	/**
	 * The scripts Otl::selectShaper() hands to the Arabic shaper, and so the scripts resolveJoining() is
	 * called for. Kept in step with that method by ArabicJoiningTest.
	 *
	 * @return int[]
	 */
	public static function scripts()
	{
		return [Ucdn::SCRIPT_ARABIC, Ucdn::SCRIPT_SYRIAC, Ucdn::SCRIPT_NKO, Ucdn::SCRIPT_MANDAIC];
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
		$entries = [];
		foreach ($codepoints as $codepoint) {
			$entries[] = sprintf('0x%04X => 1', $codepoint);
		}

		return $this->chunkedLines($entries, self::PER_LINE);
	}

}
