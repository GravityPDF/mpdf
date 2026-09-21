<?php

namespace Mpdf\Unicode;

use Mpdf\GeneratedTable;

/**
 * The property tables of Mpdf\Unicode\Emoji, rebuilt from Unicode's emoji-data.txt. What composer emoji:update
 * runs - see utils/emoji_update.php, which is the argument parsing and nothing else.
 *
 * rewrite() replaces $emojiPresentation, $emojiModifierBase, $extendedPictographic and the
 * UNIDATA_VERSION line above them, and leaves the rest of the class alone. Each table is the ranges
 * the file lists for one property, with ranges that touch merged, so Emoji::inRanges() can search it.
 *
 * Emoji, Emoji_Modifier and Emoji_Component are not written. What they add to these three is the keycap
 * bases, the regional indicators, the skin tones, the joiner, the selectors, the keycap and the tags,
 * which UTS #51 fixes and the class names by codepoint.
 */
class EmojiTables
{

	use GeneratedTable;

	const DEFAULT_VERSION = UcdnTables::DEFAULT_VERSION;

	/**
	 * How many ranges a line of a written table carries
	 */
	const PER_LINE = 4;

	/**
	 * @var string[] The property each table is built from, by the table's name
	 */
	private static $properties = [
		'emojiPresentation' => 'Emoji_Presentation',
		'emojiModifierBase' => 'Emoji_Modifier_Base',
		'extendedPictographic' => 'Extended_Pictographic',
	];

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string The directory the file is read from, and downloaded to when it is not there
	 */
	private $files;

	/**
	 * @param string      $version The Unicode version to build from
	 * @param string|null $files   Where emoji-data.txt is, or null for utils/data/ucd/<version>
	 */
	public function __construct($version = self::DEFAULT_VERSION, $files = null)
	{
		$this->version = $version;
		$this->files = $files === null ? __DIR__ . '/../../../utils/data/ucd/' . $version : $files;
	}

	/**
	 * Rewrites the property tables of the class at $path.
	 *
	 * @param string $path The class to rewrite, in place
	 *
	 * @return int[] How many ranges each table was written with, by its property name
	 */
	public function rewrite($path)
	{
		$source = $this->sourceInLf($path);
		$source = $this->replaceVersion($source, 'UNIDATA_VERSION', $this->version);

		$written = [];
		foreach ($this->tables() as $name => $ranges) {
			$source = $this->replaceArray($source, 'private static $' . $name, $this->entries($ranges));
			$written[$name] = count($ranges);
		}

		$this->writeBack($path, $source);

		return $written;
	}

	/**
	 * @return int[][][] Each table as [first, last] ranges in codepoint order, keyed by its name
	 */
	public function tables()
	{
		list(, $values) = $this->ranges($this->lines('emoji/emoji-data.txt'));

		$tables = [];
		foreach (self::$properties as $name => $property) {
			$ranges = [];
			foreach ($values as $range) {
				if ($range[2] === $property) {
					$ranges[] = [$range[0], $range[1]];
				}
			}
			$tables[$name] = $this->merged($ranges);
		}

		return $tables;
	}

	/**
	 * @param int[][] $ranges
	 *
	 * @return int[][] The same codepoints in as few ranges as they make, in order
	 */
	private function merged(array $ranges)
	{
		usort($ranges, function ($a, $b) {
			return $a[0] - $b[0];
		});

		$merged = [];
		foreach ($ranges as $range) {
			$last = count($merged) - 1;
			if ($last >= 0 && $range[0] <= $merged[$last][1] + 1) {
				$merged[$last][1] = max($merged[$last][1], $range[1]);
			} else {
				$merged[] = $range;
			}
		}

		return $merged;
	}

	/**
	 * @param int[][] $ranges
	 *
	 * @return string
	 */
	private function entries(array $ranges)
	{
		$entries = [];
		foreach ($ranges as $range) {
			$entries[] = sprintf('[0x%04X, 0x%04X]', $range[0], $range[1]);
		}

		return $this->chunkedLines($entries, self::PER_LINE);
	}

}
