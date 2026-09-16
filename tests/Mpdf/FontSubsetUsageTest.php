<?php

namespace Mpdf;

use Mpdf\Fonts\BlobReader;
use Mpdf\Fonts\FontReader;

/**
 * What `Writer\FontWriter::writeFonts()` reads `percentSubset` against: the share of a font the
 * document drew. GravityPDF/mpdf#152 is that it read it against the 32-127 range `AddFont()` seeded
 * every font's subset with, so a document of Latin text was counted as drawing 96 characters
 * whatever it said, and the option decided nothing below the floor that put under it - 20% of
 * Poppins, whose 470 characters the seed is 96 of.
 *
 * The seed now goes in where the subset font is built rather than where the font is registered, so
 * the glyphs a subset carries are what they always were and only the figure moves.
 */
class FontSubsetUsageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use ProbeFont;

	/**
	 * The font program the document embedded, which states its own length and so can be cut out of
	 * the PDF without trusting the compressed bytes not to read as a keyword.
	 *
	 * @return string
	 */
	private function fontProgram($pdf)
	{
		$found = preg_match_all('/<<\/Length (\d+)\s*\/Filter \/FlateDecode\s*\/Length1 \d+\s*>>\s*stream\r?\n/', $pdf, $matches, PREG_OFFSET_CAPTURE);
		$this->assertSame(1, $found);

		$start = $matches[0][0][1] + strlen($matches[0][0][0]);

		return gzuncompress(substr($pdf, $start, (int) $matches[1][0][0]));
	}

	/**
	 * How many glyphs a TrueType font holds, which is maxp's to say.
	 *
	 * @return int
	 */
	private function glyphCount($font)
	{
		$reader = new BlobReader($font);
		$reader->skip(4); // sfntVersion
		$numTables = $reader->readUInt16();
		$reader->skip(6); // searchRange, entrySelector, rangeShift

		for ($table = 0; $table < $numTables; $table++) {
			$tag = $reader->read(4);
			$reader->skip(4); // checksum
			$offset = FontReader::uint32($reader->read(4));
			$reader->skip(4); // length

			if ($tag === 'maxp') {
				$reader->seek($offset + 4); // past the table's version

				return $reader->readUInt16();
			}
		}

		$this->fail('The font has no maxp table');
	}

	/**
	 * Nothing but the two characters drawn, where the seed used to make it 96 for any document.
	 */
	public function testTheSubsetHoldsWhatTheDocumentDrew()
	{
		list($font) = $this->embed('<p>Hi</p>');

		$this->assertSame([72 => 72, 105 => 105], $font['subset']);
	}

	/**
	 * Two documents of the same font under the same threshold, answered differently: 2 characters of
	 * Poppins is under 10% of it and 52 is over. Both read as 20% while the seed was the numerator,
	 * and 10 is below the floor the seed put under the font, so neither could be subsetted at all.
	 */
	public function testPercentSubsetDecidesOnWhatTheDocumentDrew()
	{
		list($sparse, $sparsePdf) = $this->embed('<p>Hi</p>', ['percentSubset' => 10]);
		list($dense, $densePdf) = $this->embed($this->alphabet(), ['percentSubset' => 10]);

		$this->assertSubsetted($sparse, $sparsePdf);
		$this->assertEmbeddedWhole($dense, $densePdf);
	}

	/**
	 * The subset is still built from the ASCII range and not from the handful of glyphs the document
	 * drew, so two documents that draw different Latin text embed the same font program.
	 */
	public function testTheSubsetFontStillCarriesTheAsciiRange()
	{
		list(, $hi) = $this->embed('<p>Hi</p>');
		list(, $hello) = $this->embed('<p>Hello</p>');

		$program = $this->fontProgram($hi);

		$this->assertSame($program, $this->fontProgram($hello));
		$this->assertSame(99, $this->glyphCount($program));
	}

	/**
	 * What the document draws beyond the range is added to it rather than replacing it.
	 */
	public function testACharacterOutsideTheAsciiRangeIsAddedToTheSubsetFont()
	{
		list(, $pdf) = $this->embed('<p>H&eacute;llo</p>');

		$this->assertSame(101, $this->glyphCount($this->fontProgram($pdf)));
	}
}
