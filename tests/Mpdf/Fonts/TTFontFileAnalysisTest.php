<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * TTFontFileAnalysis re-walks the table directory by its own route, to answer what a font *is* rather
 * than how to render it - family, style, and which scripts it covers - and it is what generates a
 * font configuration from a directory of files.
 *
 * It reads 95 of the byte offsets that #81 moved onto FileReader, and its three callers in utils/
 * have all been unrunnable since the Strict trait landed ($mpdf->fontTempDir is not declared), so
 * nothing else exercises it at all.
 */
class TTFontFileAnalysisTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider fontProvider
	 */
	public function testWhatACoreInfoWalkFindsInAFont($file, $family, $type, $indic)
	{
		list($name, $bold, $italic, $ftype, $ttcId, $rtl, $isIndic, $cjk) = $this->analyse($file);

		$this->assertSame($family, $name, 'family name, read from the name table');
		$this->assertSame($type, $ftype, 'sans/serif/mono, read from the OS/2 panose bytes');
		$this->assertSame($indic, (bool) $isIndic, 'covers an Indic script, read from cmap');
		$this->assertFalse((bool) $bold);
		$this->assertFalse((bool) $italic);
		$this->assertFalse((bool) $rtl);
		$this->assertFalse((bool) $cjk);
		$this->assertSame(0, $ttcId);
	}

	public function fontProvider()
	{
		return [
			// file, family name, sans/serif/mono, covers an Indic script
			'wide latin' => ['NotoSans-Regular.ttf', 'Noto Sans', 'sans', true],
			'monospace' => ['NotoSansMono-GDEF13-Subset.ttf', 'Noto Sans Mono', 'mono', false],
			'malayalam' => ['Manjari-Regular.ttf', 'Manjari', '', true],
			'devanagari' => ['Poppins-Regular.ttf', 'Poppins', '', true],
			'no OTL tables' => ['angerthas.ttf', 'Angerthas', '', false],
		];
	}

	/**
	 * The two classes reach the name table by different routes - extractCoreInfo walks the table
	 * directory itself, getMetrics goes through extractInfo - so agreeing on what they found there is
	 * the thing worth asserting
	 */
	public function testItReadsTheSameFamilyNameTheParserDoes()
	{
		list($name) = $this->analyse('NotoSansSinhala-Subset.ttf');

		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/analysis')), 'win');
		$ttf->getMetrics(__DIR__ . '/../../data/ttf/NotoSansSinhala-Subset.ttf', uniqid('', true), 0, false, false, 0xFF);

		$this->assertSame($ttf->familyName, $name);
	}

	/**
	 * OS/2 carries fsSelection, which states bold and italic, but it is optional - an old Mac
	 * TrueType need not have it, and then the only statement of either is head.macStyle. The bold
	 * and italic tests fall through to fsSelection with & when macStyle does not claim them, so it
	 * has to be an integer whether the table was there or not. It was initialised to '', which made
	 * that a string & int: a fatal on PHP 8, and a nonsense answer before it.
	 *
	 * Built here rather than added to tests/data/ttf, which both golden masters read in full.
	 */
	public function testAFontWithoutOsTwoIsStillReadForBoldAndItalic()
	{
		$file = $this->withoutOsTwoTable('NotoSansSinhala-Subset.ttf');

		$ttf = new TTFontFileAnalysis(
			new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/analysis')),
			'win'
		);
		list($name, $bold, $italic) = $ttf->extractCoreInfo($file);

		unlink($file);

		$this->assertSame('Noto Sans Sinhala', $name);
		$this->assertFalse($bold);
		$this->assertFalse($italic);
	}

	/**
	 * The header checks - postscript outlines, a collection with no font of it named, a version that
	 * is neither - are one prologue both classes now share. What they must not share is the
	 * complaint: the font browser reads a whole directory and catches these per file to print into a
	 * listing, so its wording and its exception class are what a caller reads.
	 *
	 * @dataProvider unreadableHeaderProvider
	 */
	public function testEachClassComplainsInItsOwnTermsAboutAHeaderNeitherCanRead($header, $TTCfontID, $expected)
	{
		$file = $this->withHeader('NotoSansSinhala-Subset.ttf', $header);
		$cache = new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/analysis'));

		$raised = [];

		try {
			$parser = new TTFontFile($cache, 'win');
			$parser->getMetrics($file, uniqid('', true), $TTCfontID);
		} catch (\Exception $e) {
			$raised['parser'] = get_class($e) . ': ' . str_replace($file, '<font>', $e->getMessage());
		}

		try {
			$browser = new TTFontFileAnalysis($cache, 'win');
			$browser->extractCoreInfo($file, $TTCfontID);
		} catch (\Exception $e) {
			$raised['browser'] = get_class($e) . ': ' . str_replace($file, '<font>', $e->getMessage());
		}

		unlink($file);

		$this->assertSame($expected, $raised);
	}

	public function unreadableHeaderProvider()
	{
		return [
			'postscript outlines' => ['OTTO', 0, [
				'parser' => 'Mpdf\Exception\FontException: Fonts with postscript outlines are not supported (<font>)',
				'browser' => 'Mpdf\Exception\FontException: Fonts with postscript outlines are not supported (<font>)',
			]],
			'a collection, with no font of it named' => ['ttcf', 0, [
				'parser' => 'Mpdf\Exception\FontException: TTCfontID for a TrueType Collection is not defined in mPDF "fontdata" configuration (<font>)',
				'browser' => 'Mpdf\MpdfException: ERROR - Error parsing TrueType Collection - <font>',
			]],
			'a collection of a version neither reads' => ["ttcf\x00\x03\x00\x00", 1, [
				'parser' => 'Mpdf\Exception\FontException: Error parsing TrueType Collection: version=196608 (<font>)',
				'browser' => 'Mpdf\MpdfException: ERROR - NOT ADDED as Error parsing TrueType Collection: version=196608 - <font>',
			]],
			'a version that is neither' => ["\x00\x00\x00\x02", 0, [
				'parser' => 'Mpdf\Exception\FontException: Not a TrueType font: version=2)',
				'browser' => 'Mpdf\MpdfException: ERROR - NOT ADDED as Not a TrueType font: version=2 - <font>',
			]],
		];
	}

	/**
	 * Renames the OS/2 entry in the table directory rather than removing it, so that every other
	 * table stays where its own entry says it is. The parser looks the table up by tag, so a tag it
	 * never asks for is a font that does not carry one.
	 */
	private function withoutOsTwoTable($file)
	{
		$font = file_get_contents(__DIR__ . '/../../data/ttf/' . $file);

		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			$record = 12 + $i * 16;
			if (substr($font, $record, 4) === 'OS/2') {
				$font = substr_replace($font, 'XXXX', $record, 4);
				break;
			}
		}

		$path = __DIR__ . '/../tmp/mpdf/analysis/no-os2-' . getmypid() . '.ttf';
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		file_put_contents($path, $font);

		return $path;
	}

	/**
	 * Overwrites the leading bytes a font states its version in, which is all either class reads
	 * before deciding it cannot go on.
	 */
	private function withHeader($file, $header)
	{
		$font = substr_replace(
			file_get_contents(__DIR__ . '/../../data/ttf/' . $file),
			$header,
			0,
			strlen($header)
		);

		$path = __DIR__ . '/../tmp/mpdf/analysis/header-' . getmypid() . '.ttf';
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		file_put_contents($path, $font);

		return $path;
	}

	private function analyse($file)
	{
		$ttf = new TTFontFileAnalysis(
			new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/analysis')),
			'win'
		);

		return $ttf->extractCoreInfo(__DIR__ . '/../../data/ttf/' . $file);
	}

}
