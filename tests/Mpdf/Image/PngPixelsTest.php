<?php

namespace Mpdf\Image;

/**
 * PngPixels against ImageMagick, over one 13 x 7 picture saved in every colour type, bit depth and
 * interlacing PNG has. Each .rgba beside a fixture is `magick <png> -depth 8 RGBA:<rgba>`.
 *
 * The malformed images are built here instead, each from a 2 x 2 RGB image with one thing wrong.
 */
class PngPixelsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const DIR = __DIR__ . '/../../data/img/pngpixels/';

	/**
	 * The decoded colour and alpha match ImageMagick's byte for byte, and an image with no pixel less
	 * than opaque has no alpha channel
	 *
	 * @dataProvider images
	 *
	 * @param string $name   The fixture, a PNG with the ImageMagick RGBA dump of it beside it
	 * @param bool   $opaque Whether every pixel of it is opaque
	 */
	public function testEveryPixelIsWhatImageMagickReads($name, $opaque)
	{
		$pixels = new PngPixels(file_get_contents(self::DIR . $name . '.png'));
		$expected = file_get_contents(self::DIR . $name . '.rgba');

		$this->assertSame(13, $pixels->width);
		$this->assertSame(7, $pixels->height);
		$this->assertSame($opaque, $pixels->alpha === null, 'an alpha channel only where a pixel is less than opaque');

		$alpha = $pixels->alpha === null ? str_repeat("\xFF", 91) : $pixels->alpha;
		$actual = '';
		for ($i = 0; $i < 91; $i++) {
			$actual .= substr($pixels->rgb, $i * 3, 3) . $alpha[$i];
		}

		$this->assertSame(bin2hex($expected), bin2hex($actual));
	}

	/**
	 * @return array[] Each fixture's name, and whether every pixel of it is opaque
	 */
	public function images()
	{
		return [
			'8-bit RGBA' => ['rgba8-None', false],
			'8-bit RGBA, interlaced' => ['rgba8-PNG', false],
			'16-bit RGBA, interlaced' => ['rgba16-PNG', false],
			'8-bit RGB' => ['rgb8-None', true],
			'16-bit RGB' => ['rgb16-None', true],
			'RGB with a transparent colour' => ['rt-None', false],
			'8-bit palette with transparency' => ['p8-None', false],
			'2-bit palette with transparency' => ['p2-None', false],
			'1-bit palette with transparency' => ['p1-None', false],
			'1-bit grey, interlaced' => ['g1-PNG', true],
			'2-bit grey' => ['g2-None', true],
			'16-bit grey, interlaced' => ['g16-PNG', true],
			'grey with a transparent level' => ['gt-None', false],
			'grey and alpha, interlaced' => ['la8-PNG', false],
			'4-bit grey' => ['g4-None', true],
			'4-bit palette with transparency' => ['p4-None', false],
			'16-bit grey and alpha' => ['la16-None', false],
			'16-bit RGB with a transparent colour, beside colours that differ from it in the low byte' => ['rt16-None', false],
			'2-bit grey with a transparent level' => ['gt2-None', false],
		];
	}

	/**
	 * Data without the PNG signature throws rather than being decoded as noise
	 */
	public function testAFileThatIsNotAPngIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');

		new PngPixels("\xFF\xD8\xFF\xE0 a JPEG");
	}

	/**
	 * The image the malformed ones are made from decodes, so each of them fails for its one fault
	 */
	public function testTheImageTheMalformedOnesAreMadeFromDecodes()
	{
		$pixels = new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2)], ['IDAT', gzcompress($this->rows())]]));

		$this->assertSame(2, $pixels->width);
		$this->assertSame(2, $pixels->height);
		$this->assertSame('102030405060708090a0b0c0', bin2hex($pixels->rgb));
		$this->assertNull($pixels->alpha);
	}

	/**
	 * Image data that ends before the last row is refused rather than decoded short
	 */
	public function testATruncatedImageIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('ends before its last row');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2)], ['IDAT', gzcompress(substr($this->rows(), 0, 7))]]));
	}

	/**
	 * An interlaced image whose data runs out in a later pass is refused the same way
	 */
	public function testATruncatedInterlacedImageIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('ends before its last row');

		// A 2 x 2 image has pixels in passes 1, 6 and 7, of one, one and two pixels: here only the first two
		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2, 0, 0, 1)], ['IDAT', gzcompress("\0\x10\x20\x30\0\x40\x50\x60")]]));
	}

	/**
	 * An interlaced image whose header claims far more pixels than its data holds is refused before
	 * room is made for them
	 */
	public function testAnImageFarLargerThanItsDataIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('ends before its last row');

		new PngPixels($this->png([['IHDR', $this->header(0x7FFFFFFF, 0x7FFFFFFF, 8, 2, 0, 0, 1)], ['IDAT', gzcompress($this->rows())]]));
	}

	/**
	 * A palette image with no PLTE is refused rather than its indexes being taken for colours
	 */
	public function testAPaletteImageWithoutAPaletteIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('no palette');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 3)], ['IDAT', gzcompress("\0\0\1\0\1\0")]]));
	}

	/**
	 * A palette index past the last colour PLTE holds is refused rather than left a byte in the RGB
	 */
	public function testAPaletteIndexWithNoColourIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('palette has no colour for');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 3)], ['PLTE', "\x10\x20\x30\x40\x50\x60"], ['IDAT', gzcompress("\0\0\1\0\1\2")]]));
	}

	/**
	 * A header PNG does not allow is refused before any of the data is decoded by it
	 *
	 * @dataProvider malformedHeaders
	 *
	 * @param string $header  IHDR's data
	 * @param string $message Part of the message it is refused with
	 */
	public function testAHeaderPngDoesNotDefineIsRefused($header, $message)
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage($message);

		new PngPixels($this->png([['IHDR', $header], ['IDAT', gzcompress($this->rows())]]));
	}

	/**
	 * @return array[] Each header's data, and part of the message it is refused with
	 */
	public function malformedHeaders()
	{
		return [
			'grey at 3 bits' => [$this->header(2, 2, 3, 0), 'does not define'],
			'grey at 0 bits' => [$this->header(2, 2, 0, 0), 'does not define'],
			'RGB at 4 bits' => [$this->header(2, 2, 4, 2), 'does not define'],
			'palette at 16 bits' => [$this->header(2, 2, 16, 3), 'does not define'],
			'grey and alpha at 4 bits' => [$this->header(2, 2, 4, 4), 'does not define'],
			'RGBA at 2 bits' => [$this->header(2, 2, 2, 6), 'does not define'],
			'colour type 5' => [$this->header(2, 2, 8, 5), 'does not define'],
			'no width' => [$this->header(0, 2, 8, 2), 'no width or no height'],
			'no height' => [$this->header(2, 0, 8, 2), 'no width or no height'],
			'a width past 31 bits' => [$this->header(0x80000000, 2, 8, 2), 'no width or no height'],
			'compression method 1' => [$this->header(2, 2, 8, 2, 1), 'method PNG does not define'],
			'filter method 1' => [$this->header(2, 2, 8, 2, 0, 1), 'method PNG does not define'],
			'interlace method 2' => [$this->header(2, 2, 8, 2, 0, 0, 2), 'method PNG does not define'],
		];
	}

	/**
	 * A row in a filter type past Paeth is refused rather than unfiltered as Paeth
	 */
	public function testAnUndefinedFilterTypeIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('filter type 5');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2)], ['IDAT', gzcompress("\0\x10\x20\x30\x40\x50\x60\5\x70\x80\x90\xa0\xb0\xc0")]]));
	}

	/**
	 * A PNG with no IHDR is refused
	 */
	public function testAnImageWithNoHeaderIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('no header');

		new PngPixels($this->png([['IDAT', gzcompress($this->rows())]]));
	}

	/**
	 * An IHDR shorter than the 13 bytes it always has counts as no header, rather than being read past
	 */
	public function testAShortHeaderIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('no header');

		new PngPixels($this->png([['IHDR', substr($this->header(2, 2, 8, 2), 0, 12)], ['IDAT', gzcompress($this->rows())]]));
	}

	/**
	 * A PNG with no IDAT is refused
	 */
	public function testAnImageWithNoDataIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('no image data');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2)]]));
	}

	/**
	 * Image data that is not a zlib stream is refused
	 */
	public function testDataThatCannotBeInflatedIsRefused()
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('cannot be inflated');

		new PngPixels($this->png([['IHDR', $this->header(2, 2, 8, 2)], ['IDAT', 'not zlib']]));
	}

	/**
	 * A chunk whose CRC does not match is read all the same. ImageProcessor checks no PNG's CRCs, so an
	 * emoji refused here for its CRC would be drawn by any other route a document takes it; and the CRC
	 * guards a file in transit, which a font's bitmap table, read from the font file, is not
	 */
	public function testABadCrcIsAccepted()
	{
		$png = $this->png([['IHDR', $this->header(2, 2, 8, 2)], ['IDAT', gzcompress($this->rows())]]);

		// IHDR's CRC follows the signature, IHDR's length and type, and its 13 bytes of data
		$png = substr_replace($png, "\0\0\0\0", 8 + 8 + 13, 4);

		$pixels = new PngPixels($png);

		$this->assertSame('102030405060708090a0b0c0', bin2hex($pixels->rgb));
	}

	/**
	 * A PNG made of the given chunks and an IEND, each with the CRC its type and data give it
	 *
	 * @param array[] $chunks Each chunk's type and data, in order
	 *
	 * @return string
	 */
	private function png(array $chunks)
	{
		$chunks[] = ['IEND', ''];

		$png = "\x89PNG\r\n\x1a\n";
		foreach ($chunks as $chunk) {
			list($type, $data) = $chunk;
			$png .= pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
		}

		return $png;
	}

	/**
	 * IHDR's data
	 *
	 * @param int $width       Pixels across
	 * @param int $height      Pixels down
	 * @param int $depth       Bits a sample
	 * @param int $colourType  PNG's colour type
	 * @param int $compression The compression method
	 * @param int $filter      The filter method
	 * @param int $interlace   The interlace method
	 *
	 * @return string
	 */
	private function header($width, $height, $depth, $colourType, $compression = 0, $filter = 0, $interlace = 0)
	{
		return pack('NNCCCCC', $width, $height, $depth, $colourType, $compression, $filter, $interlace);
	}

	/**
	 * The two rows of the 2 x 2 8-bit RGB image, each after its filter type byte, 0 for none
	 *
	 * @return string
	 */
	private function rows()
	{
		return "\0\x10\x20\x30\x40\x50\x60\0\x70\x80\x90\xa0\xb0\xc0";
	}
}
