<?php

namespace Mpdf;

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

	/**
	 * 52 distinct characters, 11% of Poppins - over the threshold the shorter documents are under.
	 */
	const ALPHABET = '<p>ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</p>';

	/**
	 * @param string $html   What the document draws
	 * @param array  $config What it is given beyond the font
	 *
	 * @return array The font as it was written, and the document it was written into
	 */
	private function embed($html, array $config = [])
	{
		$mpdf = new Mpdf($config + [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['probe' => ['R' => 'Poppins-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
		$mpdf->WriteHTML($html);
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return [$mpdf->fonts['probe'], $pdf];
	}

	/**
	 * The font program the document embedded, which carries its own length and so can be cut out of
	 * the PDF without trusting the compressed bytes not to read as a keyword.
	 *
	 * @return string
	 */
	private function fontProgram($pdf)
	{
		$found = preg_match_all('/<<\/Length (\d+)\s*\/Filter \/FlateDecode\s*\/Length1 \d+\s*>>\s*stream\r?\n/', $pdf, $matches, PREG_OFFSET_CAPTURE);
		$this->assertSame(1, $found);

		list($header, $length) = [$matches[0][0], $matches[1][0]];

		return gzuncompress(substr($pdf, $header[1] + strlen($header[0]), (int) $length[0]));
	}

	/**
	 * How many glyphs a TrueType font holds, which is maxp's to say.
	 *
	 * @return int
	 */
	private function glyphCount($font)
	{
		list(, $tables) = unpack('n', substr($font, 4, 2));

		for ($table = 0; $table < $tables; $table++) {
			$record = substr($font, 12 + $table * 16, 16);
			if (substr($record, 0, 4) === 'maxp') {
				list(, $offset) = unpack('N', substr($record, 8, 4));
				list(, $glyphs) = unpack('n', substr($font, $offset + 4, 2));

				return $glyphs;
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
	 * Poppins is under 10% of it and 52 is over. Both read as 20% while the seed was the numerator.
	 */
	public function testUsageMovesWithWhatTheDocumentDrew()
	{
		list($sparse) = $this->embed('<p>Hi</p>', ['percentSubset' => 10]);
		list($dense) = $this->embed(self::ALPHABET, ['percentSubset' => 10]);

		$this->assertTrue($sparse['asSubset']);
		$this->assertFalse($dense['asSubset']);
	}

	/**
	 * 10 is below the 20% floor the seed put under Poppins, so this document used to be embedded
	 * whole however little of the font it drew.
	 */
	public function testPercentSubsetDecidesBelowTheOldFloor()
	{
		list($font, $pdf) = $this->embed('<p>Hello</p>', ['percentSubset' => 10]);

		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
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
