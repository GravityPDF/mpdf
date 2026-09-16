<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;

/**
 * The one home for the hex strings the OTL code compares characters as. The parser, the dump and the
 * shaper each had their own, and the shaper's reached the same answer by str_pad() where the other
 * two used sprintf(), so the width is worth pinning rather than assuming.
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
