<?php

namespace Mpdf;

/**
 * The generated tables of Ucdn, rebuilt from a Unicode Character Database. What composer ucdn:update
 * runs - see utils/ucdn_update.php, which is the argument parsing and nothing else.
 *
 * rewrite() reads a copy of the class, replaces the SCRIPT_ constants, $uni_scriptblock,
 * $ucd_records, $mirror_pairs and the three index tables get_ucd_record() walks, and writes it back.
 * Everything else in the class is written by hand and is left exactly as it is - including
 *
 *   - the number of every script that already has a constant. Callers compare those numbers by range
 *     (Otl picks the Indic shaper for SCRIPT_DEVANAGARI..SCRIPT_MALAYALAM), so a script keeps the
 *     number it has, and a script the class has not seen before is appended after the last in use.
 *   - the tag of every script already in $uni_scriptblock. Those are chosen rather than derived -
 *     Devanagari asks for 'dev2' and Hangul for 'jamo' - so only a script not yet listed is given
 *     one, the lowercased ISO 15924 code the OpenType registry has used for every script added since.
 *
 * A script the class names but the database does not keeps both, rather than being dropped: Unicode
 * has never withdrawn a script, and a caller holding its number should not depend on that.
 *
 * Two fields of the record are not taken from the database:
 *
 *   - normalization_check, which no caller reads and which has been 0 for every codepoint since the
 *     table was first written, stays 0.
 *   - bidi_class, where the four isolate classes of Unicode 6.3 (LRI, RLI, FSI, PDI) are written as
 *     ON. Bidi has the nineteen classes of the earlier algorithm and drives isolates from the
 *     codepoint instead, and its rules N1/N2 resolve ON and WS - which is the set UAX #9 resolves as
 *     neutral or isolate formatting, so ON is the class that keeps those four in it.
 */
class UcdnTables
{

	use GeneratedTable;

	/**
	 * The database read when none is named
	 */
	const DEFAULT_VERSION = '17.0.0';

	/**
	 * Every codepoint Unicode has. The tests build a shorter table, which is the same table with
	 * fewer blocks in it.
	 */
	const CODEPOINTS = 0x110000;

	/**
	 * The east asian width values, which are the only part of a record the class gives no constant
	 *
	 * @var int[]
	 */
	private static $widths = ['F' => 0, 'H' => 1, 'W' => 2, 'Na' => 3, 'A' => 4, 'N' => 5];

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string The directory the database is read from, and downloaded to when it is not there
	 */
	private $files;

	/**
	 * @var int
	 */
	private $codepoints;

	public function __construct($version = self::DEFAULT_VERSION, $files = null, $codepoints = self::CODEPOINTS)
	{
		$this->version = $version;
		$this->files = $files === null ? __DIR__ . '/../../utils/data/ucd/' . $version : $files;
		$this->codepoints = $codepoints;
	}

	/**
	 * Rewrites the generated tables of the class at $path from the database.
	 *
	 * @param string $path The class to rewrite, in place
	 *
	 * @return array What was written: 'records', 'scripts', 'added' (the scripts the class had not
	 *               seen, by number) and 'mirrors'
	 */
	public function rewrite($path)
	{
		$source = $this->sourceInLf($path);

		list($categories, $bidi, $constants) = $this->numbersInUse($source);
		$aliases = $this->aliases($this->lines('PropertyValueAliases.txt'));

		// The four isolate classes Unicode 6.3 added are written as ON - see the note on the class.
		foreach (['LRI', 'RLI', 'FSI', 'PDI'] as $isolate) {
			$bidi[$isolate] = $bidi['ON'];
		}

		list($scripts, $added) = $this->scriptNumbers($this->lines('Scripts.txt'), $constants);

		$properties = $this->properties($categories, $bidi, $scripts, $aliases);
		$unassigned = [$categories['Cn'], 0, $bidi['L'], 0, self::$widths['N'], 0, $scripts['Unknown']];

		list($records, $index0, $index1, $index2) = $this->indexTables($properties, $unassigned);
		$mirrors = $this->mirrorPairs();

		foreach ($added as $name => $number) {
			$constants[$number] = 'SCRIPT_' . strtoupper($name);
		}

		$source = $this->rewriteScripts($source, $constants, $added, $aliases);
		$source = $this->replaceVersion($source, 'UNIDATA_VERSION', $this->version);

		$lines = [];
		foreach ($records as $record) {
			$lines[] = "\t\t[" . implode(', ', $record) . '],';
		}
		$source = $this->replaceArray($source, 'private static $ucd_records', implode("\n", $lines));

		$lines = [];
		foreach ($mirrors as $char => $mirror) {
			$lines[] = "\t\t" . $char . ' => ' . $mirror . ',';
		}
		$source = $this->replaceArray($source, 'public static $mirror_pairs', implode("\n", $lines));

		$source = $this->replaceArray($source, 'private static $index0', $this->wrapped($index0));
		$source = $this->replaceArray($source, 'private static $index1', $this->wrapped($index1));
		$source = $this->replaceArray($source, 'private static $index2', $this->wrapped($index2));

		$this->writeBack($path, $source);

		return [
			'records' => count($records),
			'scripts' => count($constants),
			'added' => $added,
			'mirrors' => count($mirrors),
		];
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

	/**
	 * One byte per codepoint for each of the record's seven fields, in the order the record holds
	 * them. The general category, the canonical combining class and whether the character mirrors are
	 * the three no derived file states more completely, so they come from UnicodeData.txt; the rest
	 * come from the file that carries the property, which also states what an unlisted codepoint has.
	 *
	 * @return string[]
	 */
	private function properties($categories, $bidi, $scripts, $aliases)
	{
		$size = $this->codepoints;

		$category = str_repeat(chr($categories['Cn']), $size);
		$combining = str_repeat(chr(0), $size);
		$mirrored = str_repeat(chr(0), $size);
		$bidiClass = str_repeat(chr($bidi['L']), $size);
		$width = str_repeat(chr(self::$widths['N']), $size);
		$normalization = str_repeat(chr(0), $size);
		$script = str_repeat(chr($scripts['Unknown']), $size);

		$this->unicodeData($this->lines('UnicodeData.txt'), $categories, $category, $combining, $mirrored);
		$this->lay($bidiClass, $this->lines('extracted/DerivedBidiClass.txt'), $this->numbersByName($aliases, 'bc', $bidi));
		$this->lay($width, $this->lines('EastAsianWidth.txt'), $this->numbersByName($aliases, 'ea', self::$widths));
		$this->lay($script, $this->lines('Scripts.txt'), $scripts);

		return [$category, $combining, $bidiClass, $mirrored, $width, $normalization, $script];
	}

	/**
	 * Splits a property file into its "@missing" defaults and its data lines, both as [start, end,
	 * value]. The defaults come first and in the order the file gives them, because the later ones
	 * narrow the earlier: bidi class defaults to L over the whole of Unicode and to AL over the
	 * Arabic blocks.
	 *
	 * @return array[] [$defaults, $values]
	 */
	private function ranges($lines)
	{
		$defaults = [];
		$values = [];

		foreach ($lines as $line) {
			if (preg_match('/^#\s*@missing:\s*([0-9A-F]+)\.\.([0-9A-F]+)\s*;\s*([^#\s][^#]*?)\s*$/', $line, $m)) {
				$defaults[] = [hexdec($m[1]), hexdec($m[2]), $m[3]];
				continue;
			}

			$line = preg_replace('/#.*$/', '', $line);
			if (!preg_match('/^\s*([0-9A-F]+)(?:\.\.([0-9A-F]+))?\s*;\s*(.*?)\s*$/', $line, $m)) {
				continue;
			}

			$values[] = [hexdec($m[1]), hexdec($m[2] === '' ? $m[1] : $m[2]), $m[3]];
		}

		return [$defaults, $values];
	}

	/**
	 * Lays a property file over one byte per codepoint, defaults first.
	 *
	 * @param string $bytes one byte per codepoint, by reference
	 * @param array  $map   the property's values, by whichever name the file writes them
	 */
	private function lay(&$bytes, $lines, $map)
	{
		list($defaults, $values) = $this->ranges($lines);

		foreach (array_merge($defaults, $values) as $range) {
			list($start, $end, $value) = $range;
			if (!isset($map[$value])) {
				throw new \RuntimeException(sprintf('No number for the property value "%s"', $value));
			}
			$this->fill($bytes, $start, $end, chr($map[$value]));
		}
	}

	/**
	 * Reads the three fields of UnicodeData.txt that no derived file states more completely. Every
	 * other codepoint is unassigned, which is what the strings arrive holding. A block too large to
	 * list is written as a First/Last pair of lines rather than as a range.
	 *
	 * @param string $category  one byte per codepoint, by reference
	 * @param string $combining one byte per codepoint, by reference
	 * @param string $mirrored  one byte per codepoint, by reference
	 */
	private function unicodeData($lines, $categories, &$category, &$combining, &$mirrored)
	{
		$first = null;

		foreach ($lines as $line) {
			$fields = explode(';', $line);
			if (count($fields) < 10) {
				continue;
			}

			$code = hexdec($fields[0]);

			if (substr($fields[1], -8) === ', First>') {
				$first = $code;
				continue;
			}
			$start = $first === null ? $code : $first;
			$first = null;

			$this->fill($category, $start, $code, chr($categories[$fields[2]]));
			$this->fill($combining, $start, $code, chr((int) $fields[3]));
			$this->fill($mirrored, $start, $code, chr($fields[9] === 'Y' ? 1 : 0));
		}
	}

	/**
	 * Writes one byte over a range of codepoints, dropping whatever falls outside the table.
	 *
	 * @param string $bytes one byte per codepoint, by reference
	 */
	private function fill(&$bytes, $start, $end, $byte)
	{
		if ($start >= $this->codepoints) {
			return;
		}

		$end = min($end, $this->codepoints - 1);
		for ($code = $start; $code <= $end; $code++) {
			$bytes[$code] = $byte;
		}
	}

	/**
	 * Reads PropertyValueAliases.txt into a map per property, holding every name a file may use - the
	 * short alias the data lines carry and the long name the "@missing" lines do.
	 *
	 * @return array[] property => (name => canonical short alias)
	 */
	private function aliases($lines)
	{
		$aliases = [];

		foreach ($lines as $line) {
			$line = preg_replace('/#.*$/', '', $line);
			$fields = array_map('trim', explode(';', $line));
			if (count($fields) < 3 || $fields[0] === '') {
				continue;
			}

			$property = array_shift($fields);
			$short = $fields[0];
			foreach ($fields as $name) {
				if ($name !== '' && $name !== 'n/a') {
					$aliases[$property][$name] = $short;
				}
			}
		}

		return $aliases;
	}

	/**
	 * Turns one property's aliases into a map of every name it may be written by => the number mPDF
	 * gives it, which is held by canonical short alias.
	 */
	private function numbersByName($aliases, $property, $numbers)
	{
		$byName = [];

		foreach ($aliases[$property] as $name => $short) {
			if (isset($numbers[$short])) {
				$byName[$name] = $numbers[$short];
			}
		}

		return $byName;
	}

	/**
	 * The east asian width values, which are the only part of a record the class gives no constant
	 *
	 * @return int[]
	 */
	public static function widths()
	{
		return self::$widths;
	}

	/**
	 * The numbers the class already gives its general categories and bidi classes, read from its own
	 * constants so that the tables and the constants cannot drift apart, and the name of every script
	 * constant by its number. A general category constant carries the two-letter alias in a comment;
	 * a bidi class constant is named for it.
	 *
	 * @return array[] [$categories, $bidiClasses, $scriptConstants]
	 */
	public function numbersInUse($source)
	{
		preg_match_all('/const UNICODE_GENERAL_CATEGORY_[A-Z_]+ = (\d+);\s*\/\* (\w\w) \*\//', $source, $m);
		$categories = array_combine($m[2], array_map('intval', $m[1]));

		preg_match_all('/const BIDI_CLASS_(\w+) = (\d+);/', $source, $m);
		$bidiClasses = array_combine($m[1], array_map('intval', $m[2]));

		preg_match_all('/const (SCRIPT_[A-Z_0-9]+) = (\d+);/', $source, $m);
		$scriptConstants = array_combine(array_map('intval', $m[2]), $m[1]);

		return [$categories, $bidiClasses, $scriptConstants];
	}

	/**
	 * Gives every script in the database a number: the one its constant already has, or the next
	 * free one. New scripts are taken in alphabetical order, so that a later database adding one
	 * appends it and moves nothing.
	 *
	 * @param string[] $constants the name of each script constant the class has, by its number
	 *
	 * @return array[] [$numbers, $added], both the script's long name => number
	 */
	private function scriptNumbers($lines, $constants)
	{
		list($defaults, $values) = $this->ranges($lines);

		$names = [];
		foreach (array_merge($defaults, $values) as $range) {
			$names[$range[2]] = true;
		}
		$names = array_keys($names);
		sort($names);

		$inUse = array_flip($constants);
		$numbers = [];
		$added = [];
		$next = max(array_keys($constants)) + 1;

		foreach ($names as $name) {
			$constant = 'SCRIPT_' . strtoupper($name);
			if (isset($inUse[$constant])) {
				$numbers[$name] = $inUse[$constant];
				continue;
			}
			$numbers[$name] = $next;
			$added[$name] = $next;
			$next++;
		}

		if ($next > 256) {
			throw new \RuntimeException('There are more scripts than a record\'s byte holds');
		}

		return [$numbers, $added];
	}

	/**
	 * Builds the unique records and the three index tables from the per-codepoint properties.
	 *
	 * The lookup reads index0 by the codepoint's top bits, index1 by the next five and index2 by the
	 * last three, so the records are cut into blocks of 8 and those blocks into blocks of 32, each
	 * level keeping one copy of every block it sees. Record 0 is what a codepoint past the end of
	 * Unicode reads, so it is the record of a codepoint nothing is known about.
	 *
	 * @return array[] [$records, $index0, $index1, $index2]
	 */
	private function indexTables($properties, $unassigned)
	{
		$records = [$unassigned];
		$recordNumbers = [implode(',', $unassigned) => 0];
		$blocks8 = [];
		$blockNumbers8 = [];
		$index2 = [];

		for ($code = 0; $code < $this->codepoints; $code += 8) {
			$block = [];

			for ($c = $code; $c < $code + 8; $c++) {
				$record = [];
				foreach ($properties as $bytes) {
					$record[] = ord($bytes[$c]);
				}

				$key = implode(',', $record);
				if (!isset($recordNumbers[$key])) {
					$recordNumbers[$key] = count($records);
					$records[] = $record;
				}
				$block[] = $recordNumbers[$key];
			}

			$key = implode(',', $block);
			if (!isset($blockNumbers8[$key])) {
				$blockNumbers8[$key] = count($index2) >> 3;
				foreach ($block as $number) {
					$index2[] = $number;
				}
			}
			$blocks8[] = $blockNumbers8[$key];
		}

		$index0 = [];
		$index1 = [];
		$blockNumbers32 = [];
		$total = count($blocks8);

		for ($i = 0; $i < $total; $i += 32) {
			$block = array_slice($blocks8, $i, 32);

			$key = implode(',', $block);
			if (!isset($blockNumbers32[$key])) {
				$blockNumbers32[$key] = count($index1) >> 5;
				foreach ($block as $number) {
					$index1[] = $number;
				}
			}
			$index0[] = $blockNumbers32[$key];
		}

		return [$records, $index0, $index1, $index2];
	}

	/**
	 * The mirror of a character is the pair BidiMirroring.txt gives it, both ways round.
	 *
	 * @return int[]
	 */
	private function mirrorPairs()
	{
		list($noDefaults, $mirrors) = $this->ranges($this->lines('BidiMirroring.txt'));

		$pairs = [];
		foreach ($mirrors as $mirror) {
			$pairs[$mirror[0]] = hexdec($mirror[2]);
		}
		ksort($pairs);

		return $pairs;
	}

	/**
	 * Rewrites the SCRIPT_ constants and $uni_scriptblock, keeping the tag of every script already
	 * listed and giving each new one the lowercased ISO 15924 code.
	 */
	private function rewriteScripts($source, $constants, $added, $aliases)
	{
		preg_match('/\tpublic static \$uni_scriptblock = \[\n(.*?)\n\t\];\n/s', $source, $m);
		preg_match_all('/^\t\t\/\* SCRIPT_[A-Z_0-9]+ \*\/ (\d+) => (.*)$/m', $m[1], $m);
		$tags = array_combine(array_map('intval', $m[1]), $m[2]);

		foreach ($added as $name => $number) {
			$tags[$number] = "'" . str_pad(strtolower($aliases['sc'][$name]), 4) . "',";
		}

		ksort($tags);
		ksort($constants);

		$lines = [];
		$block = [];
		foreach ($tags as $number => $tag) {
			$lines[] = "\tconst " . $constants[$number] . ' = ' . $number . ';';
			$block[] = "\t\t/* " . $constants[$number] . ' */ ' . $number . ' => ' . $tag;
		}

		$source = preg_replace(
			'/\tconst SCRIPT_[A-Z_0-9]+ = \d+;\n(?:\tconst SCRIPT_[A-Z_0-9]+ = \d+;\n)*/',
			implode("\n", $lines) . "\n",
			$source,
			1
		);

		return $this->replaceArray($source, 'public static $uni_scriptblock', implode("\n", $block));
	}

	/**
	 * Wraps a list of numbers the way the tables in the class are wrapped: an indented line of up to
	 * a hundred characters, never breaking a number.
	 */
	private function wrapped($numbers)
	{
		$indent = "\t\t";
		$lines = [];
		$line = '';

		foreach ($numbers as $number) {
			$piece = $line === '' ? (string) $number : ', ' . $number;
			if ($line !== '' && strlen($indent) + strlen($line) + strlen($piece) + 1 > 100) {
				$lines[] = $indent . $line . ',';
				$line = (string) $number;
				continue;
			}
			$line .= $piece;
		}

		if ($line !== '') {
			$lines[] = $indent . $line . ',';
		}

		return implode("\n", $lines);
	}

}
