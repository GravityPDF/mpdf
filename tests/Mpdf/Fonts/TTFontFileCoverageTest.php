<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Fonts\Table\LookupFlag;
use Mpdf\TTFontFile;

/**
 * The three shapes the parser reads a Coverage table in, by name and through _getCoverage()'s flags.
 *
 * The GSUB rules read from it name glyph 9 as 00000 too, without a warning (#307).
 *
 * The table is Format 1 over glyphs 3, 9 and 5. Glyph 9 stands for no character, and glyph 5 stands
 * for U+0041 as glyph 3 does.
 */
class TTFontFileCoverageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var TTFontFile
	 */
	private $ttf;

	public function set_up()
	{
		$this->ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata')), 'win');
		$this->ttf->glyphToChar = [3 => [0x41], 5 => [0x41, 0xC0]];
	}

	/**
	 * A glyph no character reaches still takes its place, as 00000, so the ones after it keep the
	 * Coverage Index the subtable's arrays are ordered by
	 */
	public function testHexKeepsEveryPositionInCoverageOrder()
	{
		$this->assertSame(['00041', '00000', '00041'], $this->call('coverageHex'));
		$this->assertSame(['00041', '00000', '00041'], $this->call('_getCoverage'));
		$this->assertSame(['00041', '00000', '00041'], $this->call('_getCoverage', [true, 2]));
	}

	public function testGlyphIdsAreTheTableAsWritten()
	{
		$this->assertSame([3, 9, 5], $this->call('_getCoverage', [false]));
	}

	/**
	 * Keyed by character, so two glyphs for one character leave the later index
	 */
	public function testIndexByCharacterKeepsTheLastIndexOfEachCharacter()
	{
		$this->assertSame([0x41 => 2, 0 => 1], $this->call('coverageIndexByChar'));
		$this->assertSame([0x41 => 2, 0 => 1], $this->call('_getCoverage', [false, 2]));
	}

	/**
	 * A Single Substitution reads its coverage as the other lookup types do.
	 */
	public function testSingleSubstitutionReadsAnUnreachedGlyphAs00000()
	{
		$subs = $this->readRules([
			'Type' => 1,
			'Subtable' => [['Format' => 2, 'CoverageTableOffset' => 0, 'Glyphs' => [5, 5, 3]]],
		]);

		$this->assertSame([['00041'], ['00000'], ['00041']], array_column($subs, 'Replace'));
	}

	/**
	 * So does a ligature component.
	 */
	public function testLigatureReadsAnUnreachedComponentAs00000()
	{
		$subs = $this->readRules([
			'Type' => 4,
			'Subtable' => [[
				'Format' => 1,
				'CoverageTableOffset' => 0,
				'LigSetCount' => 1,
				'LigSet' => [['LigCount' => 1, 'Ligature' => [['CompCount' => 2, 'GlyphID' => [1 => 9], 'LigGlyph' => 5]]]],
			]],
		]);

		$this->assertSame([['00041', '00000']], array_column($subs, 'Replace'));
	}

	/**
	 * So do the input, backtrack and lookahead sequences of a context rule.
	 */
	public function testAContextRuleReadsAnUnreachedGlyphAs00000()
	{
		$this->assertSame([1 => '00041', 2 => '00000'], $this->callQuietly('glyphStrings', [[1 => 3, 2 => 9]]));
	}

	/**
	 * @param mixed[] $lookup One lookup, its flags left to skip nothing
	 *
	 * @return mixed[] The rules readGSUBrules() records for its one subtable
	 */
	private function readRules(array $lookup)
	{
		// A GDEF with no classes, which is all a lookup whose flag skips nothing asks of one
		$this->setProperty('lookupFlag', new LookupFlag('synthetic', [
			'GlyphClassBases' => '',
			'GlyphClassMarks' => '',
			'GlyphClassLigatures' => '',
			'GlyphClassComponents' => '',
			'MarkGlyphSets' => [],
			'MarkAttachmentType' => [],
		]));

		$lookups = [$lookup + ['Flag' => 0, 'MarkFilteringSet' => '', 'SubtableCount' => 1]];
		$this->callQuietly('readGSUBrules', [&$lookups]);

		return $lookups[0]['Subtable'][0]['subs'];
	}

	/**
	 * Calls the method and asserts it raised no warning or notice.
	 */
	private function callQuietly($method, array $arguments = [])
	{
		$raised = [];
		set_error_handler(function ($number, $message) use (&$raised) {
			$raised[] = $message;

			return true;
		});

		try {
			$result = $this->call($method, $arguments);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);

		return $result;
	}

	/**
	 * Calls a non-public method with the reader over the Coverage table.
	 */
	private function call($method, array $arguments = [])
	{
		$this->setProperty('reader', new BlobReader(pack('n*', 1, 3, 3, 9, 5)));

		$projection = new \ReflectionMethod($this->ttf, $method);
		// A no-op from PHP 8.1 and deprecated from 8.5
		if (PHP_VERSION_ID < 80100) {
			$projection->setAccessible(true);
		}

		return $projection->invokeArgs($this->ttf, $arguments);
	}

	/**
	 * Sets a non-public property of the parser.
	 */
	private function setProperty($name, $value)
	{
		$property = new \ReflectionProperty($this->ttf, $name);
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$property->setValue($this->ttf, $value);
	}

}
