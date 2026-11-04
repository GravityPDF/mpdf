<?php

namespace Mpdf\Fonts;

use Mpdf\Log\Context;
use Mpdf\TestLogger;
use Psr\Log\NullLogger;

/**
 * Reading and writing the files that say which characters a font has.
 */
class CharacterRangesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var \Mpdf\TestLogger
	 */
	private $logger;

	/**
	 * Gives each test an empty directory to write files into
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-character-ranges-' . uniqid('', true);
		mkdir($this->dir, 0777, true);
		$this->logger = new TestLogger();
	}

	/**
	 * Removes the directory and what the test wrote in it
	 */
	protected function tear_down()
	{
		foreach (glob($this->dir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->dir);

		parent::tear_down();
	}

	/**
	 * The ranges come from the codepoints with a width, leaving out the first pair, which holds the count
	 */
	public function testTheRangesAreTheCodepointsWithAWidth()
	{
		$cw = str_repeat("\0", 0x100 * 2);
		$cw[0] = "\x01";
		foreach ([0x20, 0x21, 0x22, 0x41, 0xFF] as $codepoint) {
			$cw[$codepoint * 2 + 1] = "\x05";
		}
		$cw[0x30 * 2] = "\x02";

		$this->assertSame([[0x20, 0x22], [0x30, 0x30], [0x41, 0x41], [0xFF, 0xFF]], CharacterRanges::fromWidths($cw, 1)->toArray()['ranges']);
	}

	/**
	 * A width whose low byte is zero ends no range, and a zero width does not hide behind an odd offset
	 */
	public function testAZeroByteInsideAWidthEndsNoRange()
	{
		$cw = "\0\0" . "\x01\0" . "\0\x01" . "\0\0" . "\x01\0";

		$this->assertSame([[1, 2], [4, 4]], CharacterRanges::fromWidths($cw, 1)->toArray()['ranges']);
	}

	/**
	 * Built back into widths, the ranges define exactly the characters the font's own widths do
	 */
	public function testTheWidthsDefineWhatTheFontDefines()
	{
		$font = __DIR__ . '/../../data/ttf/angerthas.ttf';
		$command = new CharacterRangesCommand();
		$cw = $this->parse($font);
		$widths = $command->rangesOf($font)->metrics(false)['cw'];

		$mpdf = new \Mpdf\Mpdf(['mode' => 'c']);
		for ($codepoint = 1; $codepoint < strlen($cw) / 2; $codepoint++) {
			if ($mpdf->_charDefined($cw, $codepoint) !== $mpdf->_charDefined($widths, $codepoint)) {
				$this->fail(sprintf('U+%04X is defined in one and not the other', $codepoint));
			}
		}

		$this->assertGreaterThan(0, strlen($widths));
	}

	/**
	 * A file mPDF ships, or one the font cache holds, reads back what it was written from, colour
	 * fields included
	 *
	 * @dataProvider roundTripProvider
	 *
	 * @param string   $format
	 * @param string[] $colorFormats
	 * @param bool     $hasOutlines
	 */
	public function testAFileReadsBackTheMetricsItWasWrittenFrom($format, array $colorFormats, $hasOutlines)
	{
		$ranges = $this->ranges([[0x20, 0x7E], [0xA0, 0x377], [0x20000, 0x20005]], 1234, $colorFormats, $hasOutlines);
		$read = $this->readFile($format === 'php' ? $ranges->toPhp() : json_encode($ranges->toArray()), $format);

		$this->assertSame($ranges->toArray(), $read->toArray());
		$this->assertSame([], $this->logger->records);
	}

	/**
	 * @return array[]
	 */
	public function roundTripProvider()
	{
		return [
			'php' => ['php', [], false],
			'json' => ['json', [], false],
			'php, a colour font' => ['php', ['CBDT', 'COLR'], true],
			'json, a colour font' => ['json', ['CBDT', 'COLR'], true],
		];
	}

	/**
	 * The PHP file is the ranges and nothing else, in the hexadecimal they are read in, and a colour
	 * font's carries its colour fields ahead of the ranges
	 */
	public function testThePhpFileIsTheRangesInHexadecimal()
	{
		$this->assertSame(
			"<?php\n\nreturn [\n\t'format' => 1,\n\t'size' => 741536,\n\t'ranges' => [\n"
			. "\t\t[0x0020, 0x007E], [0x00A0, 0x0377], [0x0400, 0x04FF], [0x0530, 0x058F],\n"
			. "\t\t[0x20000, 0x2A6D6],\n\t],\n];\n",
			$this->ranges([[0x20, 0x7E], [0xA0, 0x377], [0x400, 0x4FF], [0x530, 0x58F], [0x20000, 0x2A6D6]], 741536)->toPhp()
		);
		$this->assertSame(
			"<?php\n\nreturn [\n\t'format' => 1,\n\t'size' => 99,\n\t'colorFormats' => ['CBDT', 'COLR'],\n\t'hasOutlines' => true,\n\t'ranges' => [\n"
			. "\t\t[0x1F600, 0x1F64F],\n\t],\n];\n",
			$this->ranges([[0x1F600, 0x1F64F]], 99, ['CBDT', 'COLR'], true)->toPhp()
		);
	}

	/**
	 * A BMPonly family's widths stop at U+FFFF, as its metrics do
	 */
	public function testBmpOnlyDropsPlaneTwo()
	{
		$ranges = $this->ranges([[0x41, 0x41], [0x20000, 0x20000]]);
		$mpdf = new \Mpdf\Mpdf(['mode' => 'c']);

		$all = $ranges->metrics(false)['cw'];
		$bmp = $ranges->metrics(true)['cw'];

		$this->assertTrue($mpdf->_charDefined($all, 0x20000));
		$this->assertTrue($mpdf->_charDefined($bmp, 0x41));
		$this->assertFalse($mpdf->_charDefined($bmp, 0x20000));
		$this->assertSame(0x10000 * 2, strlen($bmp));
	}

	/**
	 * Without colour fields a font has no colour formats, as a font without colour tables has none in
	 * its metrics
	 */
	public function testAFileWithoutColourFieldsHasNoColour()
	{
		$ranges = CharacterRanges::fromData(json_decode('{"format":1,"size":5,"ranges":[[65,90]]}', true), 'test', $this->logger);

		$this->assertSame(['cw'], array_keys($ranges->metrics(false)));
	}

	/**
	 * A file that is not a valid ranges file is not read, and the warning names it
	 *
	 * @dataProvider invalidFileProvider
	 *
	 * @param string $contents
	 * @param string $format   The file's extension
	 */
	public function testAnInvalidFileIsIgnoredWithAWarning($contents, $format = 'json')
	{
		$this->assertNull($this->readFile($contents, $format));
		$this->assertCount(1, $this->logger->records);
		$this->assertSame('warning', $this->logger->records[0]['level']);
		$this->assertSame(['context' => Context::FONTS], $this->logger->records[0]['context']);
		$this->assertStringContainsString($this->dir . '/font.ttf.ranges.' . $format, $this->logger->records[0]['message']);
	}

	/**
	 * @return string[][]
	 */
	public function invalidFileProvider()
	{
		return [
			'does not decode' => ['{"format":1,'],
			'not an object' => ['"ranges"'],
			'no format' => ['{"size":5,"ranges":[]}'],
			'wrong format' => ['{"format":2,"size":5,"ranges":[]}'],
			'format as a string' => ['{"format":"1","size":5,"ranges":[]}'],
			'non-integer size' => ['{"format":1,"size":"5","ranges":[]}'],
			'size of zero' => ['{"format":1,"size":0,"ranges":[]}'],
			'ranges not a list' => ['{"format":1,"size":5,"ranges":{"a":[1,2]}}'],
			'a pair of one' => ['{"format":1,"size":5,"ranges":[[1]]}'],
			'a pair of three' => ['{"format":1,"size":5,"ranges":[[1,2,3]]}'],
			'a pair holding a string' => ['{"format":1,"size":5,"ranges":[[1,"2"]]}'],
			'a pair holding a float' => ['{"format":1,"size":5,"ranges":[[1,2.5]]}'],
			'a pair that is a number' => ['{"format":1,"size":5,"ranges":[7]]}'],
			'past U+10FFFF' => ['{"format":1,"size":5,"ranges":[[65,1114112]]}'],
			'below zero' => ['{"format":1,"size":5,"ranges":[[-1,65]]}'],
			'reversed' => ['{"format":1,"size":5,"ranges":[[90,65]]}'],
			'overlapping' => ['{"format":1,"size":5,"ranges":[[65,90],[90,100]]}'],
			'unsorted' => ['{"format":1,"size":5,"ranges":[[200,300],[65,90]]}'],
			'colour formats not strings' => ['{"format":1,"size":5,"colorFormats":[1],"ranges":[]}'],
			'a colour format that is not a name' => ['{"format":1,"size":5,"colorFormats":["CBDT\\n"],"hasOutlines":true,"ranges":[]}'],
			'a PHP file, a colour format that is not a name' => ["<?php\n\nreturn ['format' => 1, 'size' => 5, 'colorFormats' => ['x\\', 1, \\''], 'hasOutlines' => true, 'ranges' => []];\n", 'php'],
			'outlines not a boolean' => ['{"format":1,"size":5,"colorFormats":["CBDT"],"hasOutlines":1,"ranges":[]}'],
			'a PHP file, reversed' => ["<?php\n\nreturn ['format' => 1, 'size' => 5, 'ranges' => [[0x50, 0x40]]];\n", 'php'],
		];
	}

	/**
	 * What a font gives that a ranges file cannot hold is refused before anything is written, so a
	 * colour format cannot carry code into a shipped PHP file
	 *
	 * @dataProvider invalidFontValuesProvider
	 *
	 * @param mixed  $size
	 * @param array  $colorFormats
	 * @param mixed  $hasOutlines
	 * @param string $problem      What the exception names
	 */
	public function testValuesARangesFileCannotHoldAreRefused($size, array $colorFormats, $hasOutlines, $problem)
	{
		$this->expectException('Mpdf\Exception\FontException');
		$this->expectExceptionMessage('Character ranges are not valid: ' . $problem);

		CharacterRanges::fromWidths("\0\0\x01\x01", $size, $colorFormats, $hasOutlines);
	}

	/**
	 * @return array[]
	 */
	public function invalidFontValuesProvider()
	{
		return [
			'a colour format that closes its string' => [1, ["x', system('id'), '"], true, 'its colorFormats are not a list of names'],
			'a colour format that is an object' => [1, [new \ArrayObject()], true, 'its colorFormats are not a list of names'],
			'a colour format that is a number' => [1, [1], true, 'its colorFormats are not a list of names'],
			'an empty colour format' => [1, [''], true, 'its colorFormats are not a list of names'],
			'a colour format ending in a newline' => [1, ["CBDT\n"], true, 'its colorFormats are not a list of names'],
			'outlines that are not a boolean' => [1, ['CBDT'], 1, 'its hasOutlines is not a boolean'],
			'a size of zero, as filesize() fails with' => [false, [], false, 'its size is not a positive integer'],
			'a size that is a string' => ['5', [], false, 'its size is not a positive integer'],
			'a size that is a float' => [5.0, [], false, 'its size is not a positive integer'],
		];
	}

	/**
	 * However the object came to hold it, a value that is not a ranges file's is not written as PHP
	 *
	 * @dataProvider corruptedStateProvider
	 *
	 * @param string $property
	 * @param mixed  $value
	 */
	public function testNothingIsWrittenFromStateThatIsNotARangesFile($property, $value)
	{
		$ranges = $this->ranges([[0x41, 0x5A]], 5, ['CBDT'], true);
		$reflection = new \ReflectionProperty($ranges, $property);
		if (PHP_VERSION_ID < 80100) {
			$reflection->setAccessible(true);
		}
		$reflection->setValue($ranges, $value);

		$this->expectException('Mpdf\Exception\FontException');

		$ranges->toPhp();
	}

	/**
	 * @return array[]
	 */
	public function corruptedStateProvider()
	{
		return [
			'a size that is code' => ['size', "1, system('id')"],
			'a colour format that is code' => ['colorFormats', ["x', system('id'), '"]],
			'outlines that are code' => ['hasOutlines', "system('id')"],
			'a range holding a string' => ['ranges', [[0x41, "0x5A]; system('id'); //"]]],
			'a range past U+10FFFF' => ['ranges', [[0x41, 0x110000]]],
			'overlapping ranges' => ['ranges', [[0x41, 0x5A], [0x50, 0x60]]],
			'ranges that are not a list' => ['ranges', ['a' => [0x41, 0x5A]]],
		];
	}

	/**
	 * A colour font's file, as fromWidths() makes it from what TTFontFile gives, includes back to exactly
	 * the array it was written from
	 */
	public function testAColourFontsPhpFileIncludesBackToItsArray()
	{
		$ranges = CharacterRanges::fromWidths("\0\0\x01\x01\x01\x01", 7, ['COLRv1', 'COLRv0', 'SVG', 'CBDT', 'sbix'], false);
		$path = $this->dir . '/font.ttf.ranges.php';
		file_put_contents($path, $ranges->toPhp());

		$this->assertSame(
			['format' => 1, 'size' => 7, 'colorFormats' => ['COLRv1', 'COLRv0', 'SVG', 'CBDT', 'sbix'], 'hasOutlines' => false, 'ranges' => [[1, 2]]],
			include $path
		);
	}

	/**
	 * @param int[][]  $ranges
	 * @param int      $size
	 * @param string[] $colorFormats
	 * @param bool     $hasOutlines
	 *
	 * @return CharacterRanges
	 */
	private function ranges(array $ranges, $size = 1, array $colorFormats = [], $hasOutlines = false)
	{
		$data = ['format' => CharacterRanges::FORMAT, 'size' => $size, 'ranges' => $ranges];
		if ($colorFormats) {
			$data += ['colorFormats' => $colorFormats, 'hasOutlines' => $hasOutlines];
		}

		return CharacterRanges::fromData($data, 'test', new NullLogger());
	}

	/**
	 * Writes a ranges file into the test's directory and reads it back, a PHP file by including it as a
	 * shipped one is read, and a JSON one by decoding it as the font cache's is
	 *
	 * @param string $contents
	 * @param string $format   'php' or 'json', the file's extension
	 *
	 * @return CharacterRanges|null
	 */
	private function readFile($contents, $format)
	{
		$path = $this->dir . '/font.ttf.ranges.' . $format;
		file_put_contents($path, $contents);

		return CharacterRanges::fromData($format === 'php' ? include $path : json_decode($contents, true), $path, $this->logger);
	}

	/**
	 * @param string $font
	 *
	 * @return string The font's widths, as MetricsGenerator is given them
	 */
	private function parse($font)
	{
		$ttf = new \Mpdf\TTFontFile(new FontCache(new \Mpdf\Cache($this->dir)), 'win');
		$ttf->getMetrics($font, 'test');

		return $ttf->charWidths;
	}

}
