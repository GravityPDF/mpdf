<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * The format 4 cmap subtable each of the three builders writes declares the length it occupies.
 *
 * The subset golden masters pin the bytes, so they would catch this field moving, but they cannot say
 * whether it is right: a wrong value is as stable as a right one. #140 was a field short by the whole
 * of glyphIdArray, which no PDF reader ever opens - a subsetted font is embedded as Type0/Identity-H
 * and glyphs are resolved through the CIDToGIDMap beside it - and which every font tool refuses.
 */
class FontSubsetterCmapTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT_DIR = __DIR__ . '/../../data/ttf';

	/**
	 * Where the parser's cached metrics go, so the measurement does not depend on what ran before it
	 *
	 * @var string
	 */
	private $tmpDir;

	public function set_up()
	{
		parent::set_up();

		$this->tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-cmap';
		if (!is_dir($this->tmpDir)) {
			mkdir($this->tmpDir, 0777, true);
		}

		foreach (glob($this->tmpDir . '/*') as $file) {
			unlink($file);
		}
	}

	/**
	 * @dataProvider fontProvider
	 */
	public function testMakeSubsetDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->makeSubset($this->file($font), $this->pangram($font), 0, false, false));
	}

	/**
	 * @dataProvider fontProvider
	 */
	public function testMakeSubsetSipDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->makeSubsetSIP($this->file($font), $this->pangram($font), 0, false, 0));
	}

	/**
	 * repackageTTF rewrites the whole font rather than a subset, so it takes no character list. It
	 * only builds a cmap of its own under useOTL; without it the original table is copied over.
	 *
	 * @dataProvider fontProvider
	 */
	public function testRepackageTtfDeclaresTheLengthItWrote($font)
	{
		$this->assertDeclaredLength($this->subsetter()->repackageTTF($this->file($font), 0, false, true));
	}

	/**
	 * Fonts wide enough to segment the subtable several ways, while staying inside the uint16 the
	 * length field is written as - repackaging a font of more than 32,768 mapped characters overflows
	 * it, which is a defect of its own.
	 */
	public function fontProvider()
	{
		return [
			'NotoSans-Regular' => ['NotoSans-Regular'],
			'Poppins-Regular' => ['Poppins-Regular'],
			'Manjari-Regular' => ['Manjari-Regular'],
			'angerthas' => ['angerthas'],
			'NotoSansMono-GDEF13-Subset' => ['NotoSansMono-GDEF13-Subset'],
		];
	}

	/**
	 * Read the length out of the emitted subtable's header and compare it against the bytes between
	 * the subtable and whatever follows it.
	 */
	private function assertDeclaredLength($program)
	{
		$cmap = $this->table($program, 'cmap');
		$this->assertNotNull($cmap, 'The font program carries no cmap table');

		$subtable = $this->format4Offset($cmap);
		$this->assertNotNull($subtable, 'The cmap carries no format 4 subtable');

		list($offset, $end) = $subtable;

		$this->assertSame($end - $offset, $this->uint16($cmap, $offset + 2));
	}

	/**
	 * @return array|null The format 4 subtable's start and end within the cmap, or null if there is none
	 */
	private function format4Offset($cmap)
	{
		$offsets = [];
		for ($i = 0; $i < $this->uint16($cmap, 2); $i++) {
			$offsets[] = $this->uint32($cmap, 4 + 8 * $i + 4);
		}

		// The builders point several encoding records at one subtable, and write the subtables in the
		// order the records list them
		$offsets = array_unique($offsets);
		sort($offsets);
		$offsets[] = strlen($cmap);

		for ($i = 0; $i < count($offsets) - 1; $i++) {
			if ($this->uint16($cmap, $offsets[$i]) === 4) {
				return [$offsets[$i], $offsets[$i + 1]];
			}
		}

		return null;
	}

	/**
	 * @return string|null The named table's bytes, taken from the emitted font's own table directory
	 */
	private function table($program, $tag)
	{
		for ($i = 0; $i < $this->uint16($program, 4); $i++) {
			$record = 12 + 16 * $i;
			if (substr($program, $record, 4) === $tag) {
				return substr($program, $this->uint32($program, $record + 8), $this->uint32($program, $record + 12));
			}
		}

		return null;
	}

	private function uint16($bytes, $offset)
	{
		$read = unpack('n', substr($bytes, $offset, 2));

		return $read[1];
	}

	private function uint32($bytes, $offset)
	{
		$read = unpack('N', substr($bytes, $offset, 4));

		return $read[1];
	}

	/**
	 * A space, the digits and both cases of the alphabet: 63 characters in four contiguous runs, which
	 * is what a pangram asks a text font for.
	 *
	 * @return int[] Those of them the font maps, as Unicode code points
	 */
	private function pangram($font)
	{
		$characters = array_merge([0x20], range(0x30, 0x39), range(0x41, 0x5A), range(0x61, 0x7A));

		return array_values(array_intersect($characters, array_keys($this->parser()->getCTG($this->file($font), 0, false, 0))));
	}

	private function file($font)
	{
		return self::FONT_DIR . '/' . $font . '.ttf';
	}

	private function subsetter()
	{
		return new FontSubsetter($this->parser());
	}

	private function parser()
	{
		return new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win');
	}
}
