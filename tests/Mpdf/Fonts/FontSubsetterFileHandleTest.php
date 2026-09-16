<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * Each of the three builders opens the font file and reads it for hundreds of lines, and any of them
 * can give up part way. The file has to be let go of however the build ends: on Windows a file still
 * open cannot be deleted or replaced.
 *
 * Whether the handle is still open is read off the reader itself rather than tried with unlink(),
 * which POSIX lets remove an open file, so that a leak fails here on every platform.
 */
class FontSubsetterFileHandleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-file-handle';

	/**
	 * @dataProvider buildProvider
	 */
	public function testTheSubsetterLetsGoOfTheFontItBuiltFrom($method, $variant, $argument, $subset, $TTCfontID, $expected)
	{
		list($file, $written) = $this->$variant($argument);
		$subsetter = new FontSubsetter(new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win'));

		$raised = null;
		try {
			if ($method === 'repackageTTF') {
				$subsetter->repackageTTF($file, $TTCfontID, false, true);
			} else {
				$subsetter->$method($file, $this->$subset($file), $TTCfontID, false, true);
			}
		} catch (\Exception $e) {
			$raised = get_class($e) . ': ' . str_replace($file, '<font>', $e->getMessage());
		}

		$stillOpen = $this->holdsFileOpen($subsetter);

		if ($written) {
			unlink($file);
		}

		$this->assertSame($expected, $raised);
		$this->assertFalse($stillOpen, 'still holding open the file it read');
	}

	public function buildProvider()
	{
		$collection = 'Mpdf\Exception\FontException: Error parsing TrueType Collection: version=196608 (<font>)';

		return [
			'makeSubset, built' => ['makeSubset', 'corpusFont', 'NotoSansSinhala-Subset', 'pangram', 0, null],
			'makeSubsetSIP, built' => ['makeSubsetSIP', 'corpusFont', 'NotoSansSinhala-Subset', 'pangram', 0, null],
			'repackageTTF, built' => ['repackageTTF', 'corpusFont', 'NotoSansSinhala-Subset', null, 0, null],

			'makeSubset, a collection of a version it cannot read' => ['makeSubset', 'withHeader', "ttcf\x00\x03\x00\x00", 'pangram', 1, $collection],
			'makeSubsetSIP, a collection of a version it cannot read' => ['makeSubsetSIP', 'withHeader', "ttcf\x00\x03\x00\x00", 'pangram', 1, $collection],
			'repackageTTF, a collection of a version it cannot read' => ['repackageTTF', 'withHeader', "ttcf\x00\x03\x00\x00", null, 1, $collection],

			'makeSubset, more glyphs than the Private Use Area holds' => ['makeSubset', 'withGlyphCount', 0xFFFF, 'pangram', 0,
				'Mpdf\Exception\FontException: <font> : WARNING - Font cannot map all included glyphs into Private Use Area U+E000 - U+F8FF; cannot use useOTL on this font',
			],
			// Thrown from writing the cmap, after every glyph has been read
			'makeSubset, more segments than a format 4 subtable can state' => ['makeSubset', 'corpusFont', 'Blank-WideCmap-Synthetic', 'everyThirdCharacter', 0,
				'Mpdf\Exception\FontException: Font "<font>" needs a format 4 cmap subtable of 174704 bytes, more than its length field can state',
			],
			'makeSubsetSIP, no Unicode cmap' => ['makeSubsetSIP', 'withoutCmapSubtables', 'NotoSansSinhala-Subset', 'pangram', 0,
				'Mpdf\Exception\FontException: Font "<font>" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)',
			],
			'repackageTTF, more glyphs than the Private Use Area holds' => ['repackageTTF', 'withGlyphCount', 0xFFFF, null, 0,
				'Mpdf\Exception\FontException: Problem. Trying to repackage TF file; not enough space for unmapped glyphs',
			],
		];
	}

	/**
	 * Read through closures bound to each class, because the reader is private to the subsetter and
	 * the handle private to the reader
	 */
	private function holdsFileOpen(FontSubsetter $subsetter)
	{
		$reader = \Closure::bind(function () {
			return $this->reader;
		}, $subsetter, FontSubsetter::class);

		$handle = \Closure::bind(function () {
			return $this->handle;
		}, $reader(), FileReader::class);

		return $handle() !== null;
	}

	/**
	 * @return array The font's path, and false for a file that is not to be deleted
	 */
	private function corpusFont($font)
	{
		return [GoldenMaster::FONT_DIR . '/' . $font . '.ttf', false];
	}

	/**
	 * Overwrites the start of the file. A collection's version follows its tag, so a TTCfontID of 1
	 * has the header read give up before the table directory.
	 */
	private function withHeader($header)
	{
		$font = $this->read('NotoSansSinhala-Subset');

		return $this->write(substr_replace($font, $header, 0, strlen($header)));
	}

	/**
	 * Overwrites the glyph count maxp states. Under useOTL every glyph up to it that the cmap does not
	 * reach is given a Private Use Area code, and the area holds 6,400.
	 */
	private function withGlyphCount($numGlyphs)
	{
		$font = $this->read('NotoSansSinhala-Subset');

		return $this->write(substr_replace($font, pack('n', $numGlyphs), $this->tableOffset($font, 'maxp') + 4, 2));
	}

	/**
	 * States that the cmap holds no subtables, leaving the table directory and every other table as
	 * they were
	 */
	private function withoutCmapSubtables($font)
	{
		$font = $this->read($font);

		return $this->write(substr_replace($font, pack('n', 0), $this->tableOffset($font, 'cmap') + 2, 2));
	}

	private function read($font)
	{
		return file_get_contents(GoldenMaster::FONT_DIR . '/' . $font . '.ttf');
	}

	private function write($font)
	{
		return [(new Cache($this->tmpDir))->write(uniqid('font-', true) . '.ttf', $font), true];
	}

	/**
	 * @return int Where the table named $tag starts
	 */
	private function tableOffset($font, $tag)
	{
		$tables = unpack('n', substr($font, 4, 2));
		for ($i = 0; $i < $tables[1]; $i++) {
			$record = 12 + $i * 16;
			if (substr($font, $record, 4) === $tag) {
				$offset = unpack('N', substr($font, $record + 8, 4));

				return $offset[1];
			}
		}

		$this->fail(sprintf('The font has no %s table', $tag));
	}

	/**
	 * @return int[] A space, the digits and the alphabet in both cases
	 */
	private function pangram()
	{
		return array_merge([0x20], range(0x30, 0x39), range(0x41, 0x5A), range(0x61, 0x7A));
	}

	/**
	 * @return int[] Every third character the font maps, which leaves no two of them running on
	 */
	private function everyThirdCharacter($file)
	{
		$parser = new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win');

		return array_values(array_filter(array_keys($parser->getCTG($file, 0, false, false)), function ($code) {
			return $code % 3 === 0;
		}));
	}
}
