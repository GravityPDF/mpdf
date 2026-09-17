<?php

namespace Mpdf;

/**
 * The generator behind composer otlanguages:update, run over a hb-ot-tag-table.hh written here rather
 * than HarfBuzz's - a couple of dozen lines carrying all four of the shapes its tables come in, and
 * the languages whose mapping is worth knowing by heart.
 *
 * The last test rewrites a copy of the class rather than src/Ucdn.php, and is the one that says what a
 * generated line looks like.
 */
class OtLanguageTagsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var OtLanguageTags
	 */
	private $tags;

	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-ot-language-tags-' . getmypid();
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0777, true);
		}
		file_put_contents($this->dir . '/hb-ot-tag-table.hh', $this->header());

		$this->tags = new OtLanguageTags('9.9.9', $this->dir);
	}

	protected function tear_down()
	{
		parent::tear_down();

		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);
	}

	public function testATwoLetterCodeKeepsEveryTagHarfBuzzListsForItInThatOrder()
	{
		$table = $this->tags->table();

		$this->assertSame('HYE0HYE ', $table['hy'][0]);
	}

	public function testAThreeLetterCodeTakesItsTagsFromTheRangeItIndexes()
	{
		$table = $this->tags->table();

		$this->assertSame('SCS SLA ATH ', $table['scs'][0]);
		$this->assertSame('FAN CPP ', $table['acf'][0]);
	}

	public function testABlockedCodeBecomesAKeyWithNoTags()
	{
		$table = $this->tags->table();

		$this->assertSame(['', 'Abé != Abaza'], $table['aba']);
	}

	public function testALanguageNameDropsTheLanguageSystemHarfBuzzWritesAfterIt()
	{
		$table = $this->tags->table();

		$this->assertSame('Arbëreshë Albanian', $table['aae'][1]);
	}

	/**
	 * A two-letter code before every three-letter one starting with it, which is the order HarfBuzz's
	 * space-padded tags are in.
	 */
	public function testTheTableIsSortedAsHarfBuzzSortsIt()
	{
		$this->assertSame(['aa', 'aae', 'aba', 'abq', 'acf', 'hy', 'scs'], array_keys($this->tags->table()));
	}

	public function testTheRewrittenClassCarriesTheTableAndTheVersionItWasBuiltFrom()
	{
		$copy = $this->dir . '/Copy.php';
		file_put_contents($copy, $this->copy("\t// HARFBUZZ_VERSION 0.0.0\n", "\t\t'xx' => 'XXX ', /* Replaced */\n"));

		$written = $this->tags->rewrite($copy);

		$expected = $this->copy(
			"\t// HARFBUZZ_VERSION 9.9.9\n",
			"\t\t'aa' => 'AFR ', /* Afar */\n"
			. "\t\t'aae' => 'SQI ', /* Arbëreshë Albanian */\n"
			. "\t\t'aba' => '', /* Abé != Abaza */\n"
			. "\t\t'abq' => 'ABA ', /* Abaza */\n"
			. "\t\t'acf' => 'FAN CPP ', /* Saint Lucian Creole French */\n"
			. "\t\t'hy' => 'HYE0HYE ', /* Armenian */\n"
			. "\t\t'scs' => 'SCS SLA ATH ', /* North Slavey */\n"
		);

		$this->assertSame(['languages' => 6, 'blocked' => 1, 'tags' => 10], $written);
		$this->assertSame($expected, file_get_contents($copy));

		$this->tags->rewrite($copy);

		$this->assertSame($expected, file_get_contents($copy), 'a second run over the same table writes the same file');
	}

	/**
	 * @return string A class holding a language system table, ready to be rewritten
	 */
	private function copy($version, $table)
	{
		return "<?php\n\nclass Copy\n{\n\n" . $version . "\tpublic static \$ot_languages = [\n" . $table . "\t];\n\n}\n";
	}

	/**
	 * A hb-ot-tag-table.hh of a couple of dozen lines, carrying the shapes HarfBuzz's four tables use:
	 * a code listed once, a code listed twice, a code indexed into a list of tags and a blocked code.
	 *
	 * @return string
	 */
	private function header()
	{
		return "/* == Start of generated table == */\n"
			. "static const LangTag ot_languages2[] = {\n"
			. "  {HB_TAG('a','a',' ',' '),\tHB_TAG('A','F','R',' ')},\t/* Afar */\n"
			. "  {HB_TAG('h','y',' ',' '),\tHB_TAG('H','Y','E','0')},\t/* Armenian -> Armenian East */\n"
			. "  {HB_TAG('h','y',' ',' '),\tHB_TAG('H','Y','E',' ')},\t/* Armenian */\n"
			. "};\n"
			. "\n"
			. "#ifndef HB_NO_LANGUAGE_LONG\n"
			. "static const hb_tag_t ot_languages3_blocked[] = {\n"
			. "  HB_TAG('a','b','a',' '),\t/* Abé != Abaza */\n"
			. "};\n"
			. "\n"
			. "static const LangTag ot_languages3[] = {\n"
			. "  {HB_TAG('a','a','e',' '),\tHB_TAG('S','Q','I',' ')},\t/* Arbëreshë Albanian -> Albanian */\n"
			. "  {HB_TAG('a','b','q',' '),\tHB_TAG('A','B','A',' ')},\t/* Abaza */\n"
			. "};\n"
			. "\n"
			. "static const hb_tag_t ot_languages3_multi_values[] = {\n"
			. "  HB_TAG('S','C','S',' '),\t/* North Slavey */\n"
			. "  HB_TAG('S','L','A',' '),\t/* North Slavey -> Slavey */\n"
			. "  HB_TAG('A','T','H',' '),\t/* North Slavey -> Athapaskan */\n"
			. "  HB_TAG('F','A','N',' '),\t/* Saint Lucian Creole French -> French Antillean */\n"
			. "  HB_TAG('C','P','P',' '),\t/* Saint Lucian Creole French -> Creoles */\n"
			. "};\n"
			. "\n"
			. "static const LangTagRange ot_languages3_multi[] = {\n"
			. "  {HB_TAG('a','c','f',' '),\t3,\t2},\t/* Saint Lucian Creole French -> French Antillean */\n"
			. "  {HB_TAG('s','c','s',' '),\t0,\t3},\t/* North Slavey */\n"
			. "};\n"
			. "#endif\n"
			. "/* == End of generated table == */\n";
	}

}
