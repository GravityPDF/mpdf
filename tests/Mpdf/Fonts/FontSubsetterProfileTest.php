<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * With _RECALC_PROFILE defined true, makeSubset works out maxp's maxima from the glyphs it keeps
 * rather than copying the original font's.
 *
 * The constant is read when FontSubsetter loads and cannot be redefined afterwards, so each case runs
 * in a process of its own that defines it first. The expected maxima are what fontTools' maxp.recalc()
 * works out for the same program.
 */
class FontSubsetterProfileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Poppins' glyph for the acute accent, a component of Aacute
	 */
	const ACUTE = 774;

	/**
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-profile';

	/**
	 * A is simple, Aacute a compound of A and acute, and the soft hyphen a compound of hyphen, which is
	 * itself a compound of minus - so the subset holds simple glyphs, a compound and a nested compound.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider fontProvider
	 */
	public function testRecalculatesTheMaximaOfTheGlyphsASubsetKeeps($emptyAcute, array $expected)
	{
		$this->assertFalse(class_exists(FontSubsetter::class, false), 'FontSubsetter was loaded before the constant could be defined');
		define('_RECALC_PROFILE', true);

		$file = GoldenMaster::FONT_DIR . '/Poppins-Regular.ttf';
		if ($emptyAcute) {
			$file = $this->fontWithAnEmptyGlyph($file, self::ACUTE);
		}

		$subsetter = new FontSubsetter(new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win'));
		$program = $subsetter->makeSubset($file, [0x41, 0xAD, 0xC1], 0, false, false);

		$reader = new BlobReader($this->tables($program)['maxp']);
		$reader->skip(6); // version, numGlyphs
		$maxima = [
			'maxPoints' => $reader->readUInt16(),
			'maxContours' => $reader->readUInt16(),
			'maxCompositePoints' => $reader->readUInt16(),
			'maxCompositeContours' => $reader->readUInt16(),
		];
		$reader->skip(14); // maxZones to maxSizeOfInstructions
		$maxima['maxComponentElements'] = $reader->readUInt16();
		$maxima['maxComponentDepth'] = $reader->readUInt16();

		$this->assertSame($expected, $maxima);
	}

	public function fontProvider()
	{
		return [
			'Poppins' => [false, [
				'maxPoints' => 16,
				'maxContours' => 5,
				'maxCompositePoints' => 15,
				'maxCompositeContours' => 3,
				'maxComponentElements' => 2,
				'maxComponentDepth' => 2,
			]],
			// An empty glyph has no outline to record, so Aacute reaches a component with nothing to add
			'Poppins with an empty acute' => [true, [
				'maxPoints' => 16,
				'maxContours' => 5,
				'maxCompositePoints' => 11,
				'maxCompositeContours' => 2,
				'maxComponentElements' => 2,
				'maxComponentDepth' => 2,
			]],
		];
	}

	/**
	 * No font in the corpus has a compound glyph with an empty component, so one is made by cutting the
	 * outline out of a glyph that is one.
	 *
	 * @return string Where the font was written
	 */
	private function fontWithAnEmptyGlyph($file, $glyphIdx)
	{
		$tables = $this->tables(file_get_contents($file));

		// Poppins' loca is the short format, offsets stored halved, and every glyph is padded to an even length
		$offsets = [];
		foreach (str_split($tables['loca'], 2) as $halved) {
			$offsets[] = 2 * (new BlobReader($halved))->readUInt16();
		}

		$glyf = '';
		$loca = '';
		for ($i = 0; $i < count($offsets) - 1; $i++) {
			$loca .= TableWriter::uint16(strlen($glyf) / 2);
			if ($i !== $glyphIdx) {
				$glyf .= substr($tables['glyf'], $offsets[$i], $offsets[$i + 1] - $offsets[$i]);
			}
		}
		$loca .= TableWriter::uint16(strlen($glyf) / 2);

		$tables['glyf'] = $glyf;
		$tables['loca'] = $loca;

		$writer = new TableWriter();
		foreach ($tables as $tag => $bytes) {
			$writer->add($tag, $bytes);
		}

		return (new Cache($this->tmpDir))->write('empty-glyph-' . $glyphIdx . '.ttf', $writer->program());
	}

	/**
	 * @return string[] Every table the font carries, by four-character tag
	 */
	private function tables($program)
	{
		$reader = new BlobReader($program);
		$reader->skip(4); // sfntVersion
		$tableCount = $reader->readUInt16();
		$reader->skip(6); // searchRange, entrySelector, rangeShift

		$tables = [];
		for ($i = 0; $i < $tableCount; $i++) {
			$tag = $reader->read(4);
			$reader->skip(4); // checksum
			$offset = FontReader::uint32($reader->read(4));
			$length = FontReader::uint32($reader->read(4));

			$tables[$tag] = substr($program, $offset, $length);
		}

		return $tables;
	}
}
