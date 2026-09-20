<?php

namespace Mpdf;

/**
 * The generated language system table of Ucdn, rebuilt from HarfBuzz's hb-ot-tag-table.hh. What
 * composer otlanguages:update runs - see utils/otlanguages_update.php, which is the argument parsing
 * and nothing else.
 *
 * rewrite() replaces $ot_languages and the HARFBUZZ_VERSION line above it, and leaves the rest of the
 * class alone. HarfBuzz generates its own table from the IANA subtag registry and the OpenType
 * language system registry, and reading its output rather than those two keeps mPDF laying text out
 * the way HarfBuzz does without a second reading of either registry.
 *
 * Four of HarfBuzz's tables become the one mPDF holds:
 *
 *   - ot_languages2 and ot_languages3_multi give a two- or three-letter code every tag it should be
 *     tried against, in order.
 *   - ot_languages3 gives a three-letter code one tag.
 *   - ot_languages3_blocked names the three-letter codes that collide with an unrelated OpenType tag,
 *     e.g. aba is Abé and not Abaza. Those become keys with no tags, which is how the table says
 *     "HarfBuzz has decided this code has no language system" rather than "no entry, so guess".
 *
 * The fifth rule has no table: a three-letter code none of them lists is used upper-cased as its own
 * tag, which OtlTags applies rather than the ~7000 rows it would take to write out.
 *
 * hb_ot_tags_from_complex_language(), the multi-subtag half of HarfBuzz's mapping, is written by hand
 * in OtlTags - it is control flow rather than a table.
 */
class OtLanguageTags
{

	use GeneratedTable;

	/**
	 * The HarfBuzz release read when none is named
	 */
	const DEFAULT_VERSION = '14.3.1';

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string The directory the table is read from, and downloaded to when it is not there
	 */
	private $files;

	public function __construct($version = self::DEFAULT_VERSION, $files = null)
	{
		$this->version = $version;
		$this->files = $files === null ? __DIR__ . '/../../utils/data/harfbuzz/' . $version : $files;
	}

	/**
	 * Rewrites the language system table of the class at $path from HarfBuzz's.
	 *
	 * @param string $path The class to rewrite, in place
	 *
	 * @return array What was written: 'languages', 'blocked' and 'tags'
	 */
	public function rewrite($path)
	{
		$source = $this->sourceInLf($path);
		$table = $this->table();

		$lines = [];
		$blocked = 0;
		$tags = 0;
		foreach ($table as $language => $entry) {
			$lines[] = "\t\t'" . $language . "' => '" . $entry[0] . "', /* " . $entry[1] . ' */';
			$blocked += $entry[0] === '' ? 1 : 0;
			$tags += strlen($entry[0]) / 4;
		}

		$source = $this->replaceVersion($source, 'HARFBUZZ_VERSION', $this->version);
		$source = $this->replaceArray($source, 'public static $ot_languages', implode("\n", $lines));

		$this->writeBack($path, $source);

		return [
			'languages' => count($table) - $blocked,
			'blocked' => $blocked,
			'tags' => $tags,
		];
	}

	/**
	 * The whole table, sorted by language code the way the class holds it: a two-letter code sorts
	 * before every three-letter one starting with it, which is the order HarfBuzz's space-padded tags
	 * are in.
	 *
	 * @return array[] language code => [its tags as one string of four-character tags, its name]
	 */
	public function table()
	{
		$table = [];

		foreach ($this->pairs('LangTag ot_languages2') as $row) {
			if (isset($table[$row[0]])) {
				$table[$row[0]][0] .= $row[1];
				continue;
			}
			$table[$row[0]] = [$row[1], $row[2]];
		}

		foreach ($this->pairs('LangTag ot_languages3') as $row) {
			$table[$row[0]] = [$row[1], $row[2]];
		}

		$values = $this->tagList('hb_tag_t ot_languages3_multi_values');
		foreach ($this->ranges('LangTagRange ot_languages3_multi') as $row) {
			$table[$row[0]] = [implode('', array_slice($values, $row[1], $row[2])), $row[3]];
		}

		foreach ($this->comments('hb_tag_t ot_languages3_blocked') as $language => $comment) {
			$table[$language] = ['', $comment];
		}

		ksort($table, SORT_STRING);

		return $table;
	}

	/**
	 * The lines of one of HarfBuzz's arrays, by its C declaration.
	 *
	 * @param string $declaration e.g. 'LangTag ot_languages2'
	 *
	 * @return string[]
	 */
	private function body($declaration)
	{
		$source = $this->read('hb-ot-tag-table.hh');
		$pattern = '/^static const ' . preg_quote($declaration, '/') . "\[\] = \{\n(.*?)^\};$/ms";

		if (!preg_match($pattern, $source, $m)) {
			throw new \RuntimeException(sprintf('Could not find HarfBuzz\'s %s', $declaration));
		}

		return explode("\n", trim($m[1], "\n"));
	}

	/**
	 * One of HarfBuzz's {language, tag} arrays, in the order it lists them - which is the order a
	 * language's tags are to be tried in.
	 *
	 * @return array[] [language code, tag padded to four characters, language name]
	 */
	private function pairs($declaration)
	{
		$rows = [];

		foreach ($this->body($declaration) as $line) {
			$rows[] = [$this->code($line), $this->tag($line, 2), $this->name($line)];
		}

		return $rows;
	}

	/**
	 * HarfBuzz's {language, offset, count} array, which indexes the languages with several tags into
	 * ot_languages3_multi_values.
	 *
	 * @return array[] [language code, offset, count, language name]
	 */
	private function ranges($declaration)
	{
		$rows = [];

		foreach ($this->body($declaration) as $line) {
			if (!preg_match('/,\s*(\d+),\s*(\d+)\}/', $line, $m)) {
				throw new \RuntimeException(sprintf('Could not read the range in %s', $line));
			}
			$rows[] = [$this->code($line), (int) $m[1], (int) $m[2], $this->name($line)];
		}

		return $rows;
	}

	/**
	 * One of HarfBuzz's flat tag arrays.
	 *
	 * @return string[] tags padded to four characters
	 */
	private function tagList($declaration)
	{
		$tags = [];

		foreach ($this->body($declaration) as $line) {
			$tags[] = $this->tag($line, 1);
		}

		return $tags;
	}

	/**
	 * One of HarfBuzz's flat tag arrays of language codes, each with what its line says about it. The
	 * blocked codes carry the collision that blocked them, which is the only reason they are listed.
	 *
	 * @return string[] language code => comment
	 */
	private function comments($declaration)
	{
		$comments = [];

		foreach ($this->body($declaration) as $line) {
			if (!preg_match('/\/\* (.*?) \*\//', $line, $m)) {
				throw new \RuntimeException(sprintf('Could not read the comment in %s', $line));
			}
			$comments[$this->code($line)] = $m[1];
		}

		return $comments;
	}

	/**
	 * The language code a line begins with, unpadded: HarfBuzz writes it as a four-character tag.
	 *
	 * @return string
	 */
	private function code($line)
	{
		return rtrim($this->tag($line, 1));
	}

	/**
	 * The nth HB_TAG() of a line, four characters wide, which is how the tables of Ucdn and OtlTags
	 * hold a language system tag.
	 *
	 * @param int $position 1 for the first HB_TAG() of the line
	 *
	 * @return string
	 */
	private function tag($line, $position)
	{
		if (!preg_match_all("/HB_TAG\('(.)','(.)','(.)','(.)'\)/", $line, $m, PREG_SET_ORDER)) {
			throw new \RuntimeException(sprintf('Could not read a tag in %s', $line));
		}
		if (!isset($m[$position - 1])) {
			throw new \RuntimeException(sprintf('There is no tag %d in %s', $position, $line));
		}

		return $m[$position - 1][1] . $m[$position - 1][2] . $m[$position - 1][3] . $m[$position - 1][4];
	}

	/**
	 * The language's name out of a line's comment. HarfBuzz writes the OpenType language system it
	 * maps to after an arrow, which the table has no room to say once per tag.
	 *
	 * @return string
	 */
	private function name($line)
	{
		if (!preg_match('/\/\* (.*?)(?: -> .*)? \*\//', $line, $m)) {
			throw new \RuntimeException(sprintf('Could not read the comment in %s', $line));
		}

		return $m[1];
	}

	/**
	 * Reads one file of HarfBuzz's source.
	 *
	 * @return string
	 */
	public function read($name)
	{
		$url = 'https://raw.githubusercontent.com/harfbuzz/harfbuzz/' . $this->version . '/src/' . $name;

		return $this->cached($this->files . '/' . basename($name), $url);
	}

}
