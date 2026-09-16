<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\HtmlRecordingMpdf;
use Mpdf\OtlDump;
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

		$ttf = new TTFontFile($this->cache(), 'win');
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

		$ttf = new TTFontFileAnalysis($this->cache(), 'win');
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
		$this->assertBothGiveUpOn($this->withHeader('NotoSansSinhala-Subset.ttf', $header), $TTCfontID, $expected);
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
	 * Past the header the throw comes from each class's own reader, extractInfo or extractCoreInfo,
	 * and for a collection from after selectFont has moved into the file. The handle has to be let go
	 * of from there as well as from the header.
	 *
	 * @dataProvider unreadableNameTableProvider
	 */
	public function testEachClassLetsGoOfAFontItCouldReadOnlyPartOf($asCollection, $TTCfontID, $expected)
	{
		$this->assertBothGiveUpOn($this->withUnreadableNameTable('NotoSansSinhala-Subset.ttf', $asCollection), $TTCfontID, $expected);
	}

	public function unreadableNameTableProvider()
	{
		$expected = [
			'parser' => 'Mpdf\Exception\FontException: Error loading font: Unknown name table format 9 for font <font>',
			'browser' => 'Mpdf\MpdfException: ERROR - NOT ADDED as Unknown name table format 9 - <font>',
		];

		return [
			'a plain font' => [false, 0, $expected],
			'the one font of a collection' => [true, 1, $expected],
		];
	}

	/**
	 * getCTG opens the file for itself, after getMetrics has closed it, to read the character map of a
	 * font that is embedded whole. It can give up from the header, like getMetrics, or from having
	 * more unmapped glyphs than the Private Use Area has codes to give them.
	 *
	 * @dataProvider characterMapProvider
	 */
	public function testTheParserLetsGoOfAFontOnceItHasReadItsCharacterMap($variant, $argument, $TTCfontID, $expected)
	{
		$file = $this->$variant('NotoSansSinhala-Subset.ttf', $argument);

		$parser = new TTFontFile($this->cache(), 'win');

		$this->assertLetsGoOf($parser, $file, $expected, function () use ($parser, $file, $TTCfontID) {
			$parser->getCTG($file, $TTCfontID, false, true);
		});
	}

	public function characterMapProvider()
	{
		return [
			'as many glyphs as the Private Use Area holds' => ['withGlyphCount', 0x1000, 0, null],
			'a collection of a version it cannot read' => ['withHeader', "ttcf\x00\x03\x00\x00", 1,
				'Mpdf\Exception\FontException: Error parsing TrueType Collection: version=196608 (<font>)',
			],
			'more glyphs than the Private Use Area holds' => ['withGlyphCount', 0xFFFF, 0,
				'Mpdf\Exception\FontException: Font "<font>" cannot map all included glyphs into Private Use Area U+E000-U+F8FF; cannot use useOTL on this font',
			],
		];
	}

	/**
	 * The font browser reads a collection's header through getTTCFonts before reading each font of it
	 * with extractCoreInfo, and nothing it does in between needs the file open. So it is let go of
	 * when the header reads as well as when it does not.
	 *
	 * @dataProvider collectionHeaderProvider
	 */
	public function testTheBrowserLetsGoOfACollectionOnceItHasReadItsHeader($header, $expected, $fonts)
	{
		$file = $this->withHeader('NotoSansSinhala-Subset.ttf', $header);
		$browser = new TTFontFileAnalysis($this->cache(), 'win');

		$this->assertLetsGoOf($browser, $file, $expected, function () use ($browser, $file) {
			$browser->getTTCFonts($file);
		});

		$this->assertSame($fonts, $browser->TTCFonts);
	}

	public function collectionHeaderProvider()
	{
		return [
			'not a collection' => ["\x00\x01\x00\x00", 'Mpdf\Exception\FontException: Not a TrueType Collection: version=65536 (<font>)', []],
			'a collection of a version it cannot read' => ["ttcf\x00\x03\x00\x00", 'Mpdf\Exception\FontException: Error parsing TrueType Collection: version=196608 (<font>)', []],
			// ttcf, version 1.0, two fonts, and their offsets
			'a collection it can read' => ['ttcf' . pack('NNNN', 0x00010000, 2, 20, 40), null, [1 => ['offset' => 20], 2 => ['offset' => 40]]],
		];
	}

	/**
	 * @param string|null $expected The complaint, as describe() gives it, or null for none
	 */
	private function assertLetsGoOf(TTFontFile $ttf, $file, $expected, \Closure $read)
	{
		$raised = $this->raisedBy($file, $read);
		$stillOpen = $this->holdsFileOpen($ttf);

		unlink($file);

		$this->assertSame($expected, $raised);
		$this->assertFalse($stillOpen, 'still holding open the file it read');
	}

	/**
	 * Whether each class gave up on the file with the complaint expected, and let go of it.
	 *
	 * OtlDump reads through the parser's getMetrics(), so it gives up with the parser's complaint.
	 *
	 * Letting go is read from each reader rather than inferred from the unlink() at the end: POSIX
	 * removes a file that is still open without complaint, so that unlink() only ever failed on
	 * Windows, and a handle left open has to fail this everywhere.
	 */
	private function assertBothGiveUpOn($file, $TTCfontID, $expected)
	{
		$parser = new TTFontFile($this->cache(), 'win');
		$browser = new TTFontFileAnalysis($this->cache(), 'win');
		$dump = new OtlDump(new HtmlRecordingMpdf(['mode' => 'utf-8', 'tempDir' => __DIR__ . '/../tmp/mpdf/analysis']), $this->cache(), 'win');

		$expected['dump'] = $expected['parser'];
		$raised = array_filter([
			'parser' => $this->raisedBy($file, function () use ($parser, $file, $TTCfontID) {
				$parser->getMetrics($file, uniqid('', true), $TTCfontID);
			}),
			'browser' => $this->raisedBy($file, function () use ($browser, $file, $TTCfontID) {
				$browser->extractCoreInfo($file, $TTCfontID);
			}),
			'dump' => $this->raisedBy($file, function () use ($dump, $file, $TTCfontID) {
				$dump->getMetrics($file, uniqid('', true), $TTCfontID, false, false, 0xFF, 'summary');
			}),
		]);

		$stillOpen = array_keys(array_filter([
			'parser' => $this->holdsFileOpen($parser),
			'browser' => $this->holdsFileOpen($browser),
			'dump' => $this->holdsFileOpen($dump),
		]));

		unlink($file);

		$this->assertSame($expected, $raised);
		$this->assertSame([], $stillOpen, 'still holding open the file it gave up on');
	}

	/**
	 * Read through a closure bound to each class, because the reader is protected on the parser and
	 * the handle private on the reader, and neither wants to be public for a test's sake
	 */
	private function holdsFileOpen(TTFontFile $ttf)
	{
		$reader = \Closure::bind(function () {
			return $this->reader;
		}, $ttf, TTFontFile::class);

		$handle = \Closure::bind(function () {
			return $this->handle;
		}, $reader(), FileReader::class);

		return $handle() !== null;
	}

	/**
	 * Renames the OS/2 entry in the table directory rather than removing it, so that every other
	 * table stays where its own entry says it is. The parser looks the table up by tag, so a tag it
	 * never asks for is a font that does not carry one.
	 */
	private function withoutOsTwoTable($file)
	{
		$font = file_get_contents(__DIR__ . '/../../data/ttf/' . $file);

		$font = substr_replace($font, 'XXXX', $this->tableRecord($font, 'OS/2'), 4);

		return $this->writeFontVariant($font, 'no-os2');
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

		return $this->writeFontVariant($font, 'header');
	}

	/**
	 * Overwrites the glyph count maxp states. getCTG gives a Private Use Area code to every glyph up to
	 * it that the cmap does not reach, and reads nothing else by it.
	 */
	private function withGlyphCount($file, $numGlyphs)
	{
		$font = file_get_contents(__DIR__ . '/../../data/ttf/' . $file);

		$offset = unpack('N', substr($font, $this->tableRecord($font, 'maxp') + 8, 4));
		$font = substr_replace($font, pack('n', $numGlyphs), $offset[1] + 4, 2);

		return $this->writeFontVariant($font, 'glyph-count');
	}

	/**
	 * @return int Where the table directory entry for $tag starts
	 */
	private function tableRecord($font, $tag)
	{
		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			$record = 12 + $i * 16;
			if (substr($font, $record, 4) === $tag) {
				return $record;
			}
		}

		$this->fail(sprintf('The font has no %s table', $tag));
	}

	/**
	 * A font whose header reads but whose name table states a format neither class reads, so that the
	 * throw comes from past the table directory. Asked to, it wraps the font as the one font of a
	 * TrueType Collection, which moves every table 16 bytes on to make room for the collection header.
	 */
	private function withUnreadableNameTable($file, $asCollection)
	{
		$font = file_get_contents(__DIR__ . '/../../data/ttf/' . $file);
		$shift = $asCollection ? 16 : 0;

		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			$record = 12 + $i * 16;
			$offset = unpack('N', substr($font, $record + 8, 4));

			if (substr($font, $record, 4) === 'name') {
				$font = substr_replace($font, pack('n', 9), $offset[1], 2);
			}

			$font = substr_replace($font, pack('N', $offset[1] + $shift), $record + 8, 4);
		}

		if ($asCollection) {
			// ttcf, version 1.0, one font, and that font's offset
			$font = 'ttcf' . pack('NNN', 0x00010000, 1, 16) . $font;
		}

		return $this->writeFontVariant($font, $asCollection ? 'name-ttc' : 'name');
	}

	/**
	 * @return string Where the altered font was written, for the caller to read and then unlink
	 */
	private function writeFontVariant($font, $prefix)
	{
		$path = __DIR__ . '/../tmp/mpdf/analysis/' . $prefix . '-' . getmypid() . '.ttf';
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		file_put_contents($path, $font);

		return $path;
	}

	/**
	 * @return string|null What $read raised, as describe() gives it, or null if it raised nothing
	 */
	private function raisedBy($file, \Closure $read)
	{
		try {
			$read();
		} catch (\Exception $e) {
			return $this->describe($e, $file);
		}

		return null;
	}

	/**
	 * @return string The exception's class and message, with the temporary path taken back out so
	 *                that the expectation can be written down
	 */
	private function describe(\Exception $e, $file)
	{
		return get_class($e) . ': ' . str_replace($file, '<font>', $e->getMessage());
	}

	private function cache()
	{
		return new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/analysis'));
	}

	private function analyse($file)
	{
		$ttf = new TTFontFileAnalysis($this->cache(), 'win');

		return $ttf->extractCoreInfo(__DIR__ . '/../../data/ttf/' . $file);
	}

}
