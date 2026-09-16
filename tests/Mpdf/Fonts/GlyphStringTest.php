<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;

/**
 * The OTL code compares and concatenates these strings as text, so their width is the contract, and
 * is pinned here rather than assumed.
 */
class GlyphStringTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider codepointProvider
	 */
	public function testACharacterIsWrittenAsFiveUpperCaseHexDigits($codepoint, $expected)
	{
		$this->assertSame($expected, GlyphString::of($codepoint));
	}

	public function codepointProvider()
	{
		return [
			'the first' => [0, '00000'],
			'latin' => [0x41, '00041'],
			'lower case in hex, upper case here' => [0x644, '00644'],
			'the last of the BMP' => [0xFFFF, '0FFFF'],
			'the first outside it' => [0x10000, '10000'],
			'the last the width holds' => [0xFFFFF, 'FFFFF'],
			// Plane 16 does not fit, and comes back a digit wider rather than truncated
			'past the width' => [0x10FFFF, '10FFFF'],
		];
	}

	/**
	 * A six-digit glyph holds two five-digit ones: U+100300 is 10030 followed by a 0, and a 1 followed
	 * by 00300. Neither is in a class that names only U+100300.
	 *
	 * @dataProvider listProvider
	 */
	public function testAGlyphIsInAListOnlyWhereTheListNamesIt($glyphs, $glyph, $expected)
	{
		$set = GlyphString::set($glyphs);

		$this->assertSame($expected, isset($set[$glyph]), 'set()');
		$this->assertSame($expected, GlyphString::inList($glyphs, $glyph), 'inList()');
	}

	public function listProvider()
	{
		return [
			'plane 16, itself' => [' 100300', '100300', true],
			'a BMP glyph its hex ends with' => [' 100300', '00300', false],
			'a plane 1 glyph its hex begins with' => [' 100300', '10030', false],
			'a plane 16 glyph whose hex ends with a BMP one in the list' => [' 00300', '100300', false],
			'the first of several' => [' 00300| 100300| 00302', '00300', true],
			'the last of several' => [' 00300| 100300| 00302', '00302', true],
			'between two that hold it' => [' 100300| 00300| 003001', '00300', true],
			'several, none of them it' => [' 00301| 100300| 00302', '00300', false],
			'an empty class' => ['', '00300', false],
			'a Coverage table, without the spaces' => ['00041|100300', '00300', false],
		];
	}

	/**
	 * inList() reads whatever separates the glyphs, since the lists it is asked about are not all
	 * GDEF's shape
	 */
	public function testAListMaySeparateItsGlyphsWithAnythingButAHexDigit()
	{
		$this->assertTrue(GlyphString::inList('0FE8E 0FE94 ', '0FE94'));
		$this->assertFalse(GlyphString::inList('0FE8E 10FE94 ', '0FE94'));
		$this->assertTrue(GlyphString::inList('((?:(?: 00300| 00301))*)', '00301'));
		$this->assertFalse(GlyphString::inList('((?:(?: 100300| 100301))*)', '00301'));
	}

	/**
	 * \Mpdf\unicode_hex() has been a public function of the Mpdf namespace since 5.7.1 and upstream
	 * declares it in the same place, so anything outside the library that calls it has to keep
	 * getting the answer it always did.
	 */
	public function testTheGlobalFunctionOutsideCallersUseStillAgrees()
	{
		// Declared in TTFontFile.php, and PHP autoloads classes rather than functions
		class_exists(TTFontFile::class);

		$this->assertTrue(function_exists('\Mpdf\unicode_hex'));
		$this->assertSame('00644', \Mpdf\unicode_hex(0x644));
	}

}
