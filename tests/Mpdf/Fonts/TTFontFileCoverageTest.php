<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * The three shapes the parser reads a Coverage table in, by name and through _getCoverage()'s flags.
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

	private function call($method, array $arguments = [])
	{
		$reader = new \ReflectionProperty($this->ttf, 'reader');
		$projection = new \ReflectionMethod($this->ttf, $method);

		// A no-op from PHP 8.1 and deprecated from 8.5
		if (PHP_VERSION_ID < 80100) {
			$reader->setAccessible(true);
			$projection->setAccessible(true);
		}

		$reader->setValue($this->ttf, new BlobReader(pack('n*', 1, 3, 3, 9, 5)));

		return $projection->invokeArgs($this->ttf, $arguments);
	}

}
