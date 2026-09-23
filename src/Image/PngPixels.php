<?php

namespace Mpdf\Image;

use Mpdf\MpdfException;

/**
 * A PNG decoded to 8-bit RGB samples and, where any pixel is less than opaque, an 8-bit alpha channel
 * beside them: the two images a PDF draws a transparent picture with, the second as its /SMask.
 *
 * ImageProcessor sends a PNG with an alpha channel through GD, which keeps seven bits of the alpha
 * and draws the same file differently from one GD build to the next. A colour emoji is a small PNG
 * whose edges are all alpha, so the bitmap fonts decode theirs here instead: every colour type and
 * bit depth PNG defines, and Adam7 interlacing.
 *
 * Every row is first unpacked to a byte a sample - scaled to 0-255, or a palette index as it stands -
 * so each colour type is then split into RGB and alpha a whole image at a time.
 *
 * @see https://www.w3.org/TR/png-3/
 */
class PngPixels
{

	/**
	 * @var int
	 */
	public $width;

	/**
	 * @var int
	 */
	public $height;

	/**
	 * @var string Three bytes per pixel, row by row from the top
	 */
	public $rgb;

	/**
	 * @var string|null One byte per pixel, or null where every pixel is opaque
	 */
	public $alpha;

	/**
	 * A palette image as a PDF can draw it without decoding it: its palette, its bit depth and its image
	 * data as the file holds it, deflated and each row still filtered, which /Predictor 15 undoes. Null
	 * where the image is not a palette image, or is interlaced, which no predictor undoes.
	 *
	 * @var array|null ['palette' => PLTE, 'depth' => bits per index, 'data' => the IDAT stream]
	 */
	public $indexed;

	/**
	 * Samples per pixel, by colour type
	 *
	 * @var int[]
	 */
	private static $channels = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4];

	/**
	 * The bit depths each colour type may have
	 *
	 * @var int[][]
	 */
	private static $depths = [0 => [1, 2, 4, 8, 16], 2 => [8, 16], 3 => [1, 2, 4, 8], 4 => [8, 16], 6 => [8, 16]];

	/**
	 * strtr() tables from a packed byte to its samples a byte each, built as a depth is first met
	 *
	 * @var string[][]
	 */
	private static $unpackers = [];

	/**
	 * @param string $png The file
	 *
	 * @throws \Mpdf\MpdfException If it is not a PNG this can read
	 */
	public function __construct($png)
	{
		$header = null;
		$palette = '';
		$transparency = '';
		$data = '';

		foreach (self::chunks($png) as $chunk) {
			$payload = substr($png, $chunk['payload'], $chunk['size']);
			if ($chunk['type'] === 'IHDR' && $chunk['size'] === 13) {
				$header = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $payload);
			} elseif ($chunk['type'] === 'PLTE') {
				$palette = $payload;
			} elseif ($chunk['type'] === 'tRNS') {
				$transparency = $payload;
			} elseif ($chunk['type'] === 'IDAT') {
				$data .= $payload;
			}
		}

		// CRCs go unchecked, as ImageProcessor leaves them for every other PNG a document draws
		if (!$header || $data === '') {
			throw new MpdfException('A PNG image with no header or no image data');
		}

		$this->validate($header, $palette);

		$inflated = @gzuncompress($data);
		if ($inflated === false) {
			throw new MpdfException('A PNG image whose data cannot be inflated');
		}

		$this->width = $header['width'];
		$this->height = $header['height'];

		if ($header['colorType'] === 3 && !$header['interlace']) {
			$this->indexed = ['palette' => $palette, 'depth' => $header['bitDepth'], 'data' => $data];
		}

		// Every pixel's bits, without the filter bytes and padding: a floor checked before the header's
		// size is trusted with any memory, which each pass then checks exactly
		if (strlen($inflated) < ceil($this->width * $this->height * self::$channels[$header['colorType']] * $header['bitDepth'] / 8)) {
			throw new MpdfException('A PNG image whose data ends before its last row');
		}

		// A 16-bit transparent grey or colour has to be matched before its samples are rounded to 8 bits,
		// so it is matched as the rows are read and becomes an alpha sample beside them
		$header['key'] = '';
		if ($header['bitDepth'] === 16 && $transparency !== '' && ($header['colorType'] === 0 || $header['colorType'] === 2)) {
			$header['key'] = $transparency;
		}

		$pixels = $header['interlace'] ? $this->deinterlace($inflated, $header) : $this->pass($inflated, $this->width, $this->height, $header)[0];

		if ($header['key'] !== '') {
			$header['colorType'] += 4;
			$transparency = '';
		}

		$this->split($pixels, $header, $palette, $transparency);
	}

	/**
	 * Refuses a header PNG does not allow, or a palette image without its palette, before any of the
	 * data is decoded by it
	 *
	 * @param array  $header  IHDR, as read by the constructor
	 * @param string $palette PLTE, or ''
	 *
	 * @throws \Mpdf\MpdfException If it is not one PNG allows
	 */
	private function validate(array $header, $palette)
	{
		if (!isset(self::$depths[$header['colorType']]) || !in_array($header['bitDepth'], self::$depths[$header['colorType']], true)) {
			throw new MpdfException(sprintf('A PNG image of colour type %d at %d bits, which PNG does not define', $header['colorType'], $header['bitDepth']));
		}

		// Width and height are unsigned 31-bit numbers, which a 32-bit PHP reads as negative when the top bit is set
		if ($header['width'] <= 0 || $header['height'] <= 0 || $header['width'] > 0x7FFFFFFF || $header['height'] > 0x7FFFFFFF) {
			throw new MpdfException('A PNG image with no width or no height');
		}

		if ($header['compression'] !== 0 || $header['filter'] !== 0 || $header['interlace'] > 1) {
			throw new MpdfException('A PNG image with a compression, filter or interlace method PNG does not define');
		}

		if ($header['colorType'] === 3 && ($palette === '' || strlen($palette) % 3 !== 0)) {
			throw new MpdfException('A palette PNG image with no palette');
		}
	}

	/**
	 * The chunks of a PNG, in order, up to IEND
	 *
	 * @param string $data The file
	 *
	 * @return \Generator Each chunk's type, the size of its data, and where its data starts
	 */
	public static function chunks($data)
	{
		$length = strlen($data);

		if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
			return;
		}

		// Length, type and CRC come to 12 bytes around each chunk's data
		for ($p = 8; $p + 12 <= $length; $p += 12 + $size) {
			$size = unpack('N', substr($data, $p, 4))[1];
			if ($size < 0 || $p + 12 + $size > $length) {
				break;
			}

			$type = substr($data, $p + 4, 4);

			yield ['type' => $type, 'size' => $size, 'payload' => $p + 8];

			if ($type === 'IEND') { // PNG 5.6: nothing follows the end of the datastream
				break;
			}
		}
	}

	/**
	 * One image's scanlines, filtering undone and unpacked to a byte a sample
	 *
	 * @param string $data   The inflated IDAT stream, from where this image starts
	 * @param int    $width  The image's width in pixels
	 * @param int    $height The image's height in pixels
	 * @param array  $header IHDR, as read by the constructor, with the 16-bit transparent colour as 'key'
	 *
	 * @return array [the pixels, the number of bytes of $data they took]
	 *
	 * @throws \Mpdf\MpdfException If the data ends before the last row
	 */
	private function pass($data, $width, $height, array $header)
	{
		$channels = self::$channels[$header['colorType']];
		$samples = $width * $channels;
		$bpp = max(1, ($channels * $header['bitDepth']) >> 3);
		$stride = ($samples * $header['bitDepth'] + 7) >> 3;

		if (strlen($data) < $height * ($stride + 1)) {
			throw new MpdfException('A PNG image whose data ends before its last row');
		}

		$pixels = '';
		$previous = str_repeat("\0", $stride);
		for ($y = 0, $pos = 0; $y < $height; $y++, $pos += $stride + 1) {
			$row = self::unfilter(ord($data[$pos]), substr($data, $pos + 1, $stride), $previous, $bpp);
			$unpacked = $this->samples($row, $header['bitDepth'], $header['colorType'] === 3, $samples);
			$pixels .= $header['key'] === '' ? $unpacked : $this->keyed($row, $unpacked, $header['key'], $channels);
			$previous = $row;
		}

		return [$pixels, $pos];
	}

	/**
	 * One scanline with its filter undone
	 *
	 * @param int    $filter   The filter type byte the scanline starts with, 0 to 4
	 * @param string $row      The scanline after that byte
	 * @param string $previous The scanline above, already unfiltered, or zeros for the first
	 * @param int    $bpp      Bytes per complete pixel, at least 1
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException If the filter type is not one PNG defines
	 */
	public static function unfilter($filter, $row, $previous, $bpp)
	{
		$stride = strlen($row);

		switch ($filter) {
			case 0:
				return $row;

			case 1:
				for ($i = $bpp; $i < $stride; $i++) {
					$row[$i] = chr((ord($row[$i]) + ord($row[$i - $bpp])) & 0xFF);
				}

				return $row;

			case 2:
				for ($i = 0; $i < $stride; $i++) {
					$row[$i] = chr((ord($row[$i]) + ord($previous[$i])) & 0xFF);
				}

				return $row;

			case 3:
				for ($i = 0; $i < $stride; $i++) {
					$left = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
					$row[$i] = chr((ord($row[$i]) + (($left + ord($previous[$i])) >> 1)) & 0xFF);
				}

				return $row;

			case 4:
				break;

			default:
				throw new MpdfException(sprintf('A PNG image with a row in filter type %d, which PNG does not define', $filter));
		}

		for ($i = 0; $i < $stride; $i++) {
			$left = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
			$up = ord($previous[$i]);
			$upperLeft = $i >= $bpp ? ord($previous[$i - $bpp]) : 0;
			$p = $left + $up - $upperLeft;
			$pa = abs($p - $left);
			$pb = abs($p - $up);
			$pc = abs($p - $upperLeft);
			$row[$i] = chr((ord($row[$i]) + (($pa <= $pb && $pa <= $pc) ? $left : ($pb <= $pc ? $up : $upperLeft))) & 0xFF);
		}

		return $row;
	}

	/**
	 * Packed samples as a byte each: 16-bit samples rounded to 8, and samples of fewer bits scaled up
	 * to fill the byte, except palette indexes, which stay as they are
	 *
	 * @param string $packed  The samples at the image's own depth
	 * @param int    $depth   Bits a sample
	 * @param bool   $indexes Whether they are palette indexes
	 * @param int    $count   How many there are, since a packed row ends in padding bits
	 *
	 * @return string
	 */
	private function samples($packed, $depth, $indexes, $count)
	{
		if ($depth === 8) {
			return $packed;
		}

		$samples = '';

		if ($depth === 16) {
			foreach (unpack('n*', $packed) as $value) {
				$samples .= chr((int) round($value / 257));
			}

			return $samples;
		}

		return substr(strtr($packed, $this->unpacker($depth, $indexes)), 0, $count);
	}

	/**
	 * The strtr() table that turns each byte of samples packed at 1, 2 or 4 bits into those samples
	 * a byte each, from the leftmost bits
	 *
	 * @param int  $depth   Bits a sample
	 * @param bool $indexes Whether they are palette indexes, kept as they are rather than scaled
	 *
	 * @return string[]
	 */
	private function unpacker($depth, $indexes)
	{
		$key = $depth . ($indexes ? 'i' : '');

		if (!isset(self::$unpackers[$key])) {
			$max = (1 << $depth) - 1;
			self::$unpackers[$key] = [];
			for ($byte = 0; $byte < 256; $byte++) {
				$samples = '';
				for ($shift = 8 - $depth; $shift >= 0; $shift -= $depth) {
					$value = ($byte >> $shift) & $max;
					$samples .= chr($indexes ? $value : (int) round($value * 255 / $max));
				}
				self::$unpackers[$key][chr($byte)] = $samples;
			}
		}

		return self::$unpackers[$key];
	}

	/**
	 * A row of 16-bit grey or colour pixels with an alpha sample after each, clear where the pixel is
	 * the transparent one tRNS names and opaque elsewhere
	 *
	 * @param string $row      The unfiltered scanline, at 16 bits a sample
	 * @param string $unpacked The same pixels a byte a sample
	 * @param string $key      tRNS: the transparent grey level or RGB colour, at 16 bits a sample
	 * @param int    $channels Samples a pixel, 1 or 3
	 *
	 * @return string
	 */
	private function keyed($row, $unpacked, $key, $channels)
	{
		$pixels = '';
		foreach (str_split($row, $channels * 2) as $i => $pixel) {
			$pixels .= substr($unpacked, $i * $channels, $channels) . ($pixel === $key ? "\0" : "\xFF");
		}

		return $pixels;
	}

	/**
	 * Adam7: seven passes, each a smaller image filtered on its own, whose pixels are dealt back onto
	 * the full grid
	 *
	 * @param string $data   The inflated IDAT stream
	 * @param array  $header IHDR, as read by the constructor
	 *
	 * @return string The pixels of the whole image, a byte a sample
	 */
	private function deinterlace($data, array $header)
	{
		$passes = [[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]];
		$channels = self::$channels[$header['colorType']] + ($header['key'] === '' ? 0 : 1);

		$pixels = array_fill(0, $this->width * $this->height, '');

		$pos = 0;
		foreach ($passes as $pass) {
			list($x0, $y0, $dx, $dy) = $pass;
			$width = (int) ceil(($this->width - $x0) / $dx);
			$height = (int) ceil(($this->height - $y0) / $dy);
			if ($width <= 0 || $height <= 0) {
				continue;
			}

			list($passPixels, $length) = $this->pass(substr($data, $pos), $width, $height, $header);
			$pos += $length;

			foreach (str_split($passPixels, $channels) as $i => $pixel) {
				$pixels[($y0 + (int) ($i / $width) * $dy) * $this->width + $x0 + ($i % $width) * $dx] = $pixel;
			}
		}

		return implode('', $pixels);
	}

	/**
	 * Fills $rgb and $alpha from pixels at a byte a sample
	 *
	 * @param string $pixels       The whole image, a byte a sample
	 * @param array  $header       IHDR, as read by the constructor
	 * @param string $palette      PLTE, three bytes an entry, or ''
	 * @param string $transparency tRNS: an alpha per palette entry, or the one grey level or RGB colour
	 *                             that is transparent, as 16-bit values at the image's own depth
	 */
	private function split($pixels, array $header, $palette, $transparency)
	{
		// The transparent grey or colour, scaled as the pixels were
		$key = '';
		if ($transparency !== '' && $header['colorType'] !== 3) {
			$max = (1 << $header['bitDepth']) - 1;
			foreach (unpack('n*', $transparency) as $value) {
				$key .= chr((int) round($value * 255 / $max));
			}
		}

		switch ($header['colorType']) {
			case 0:
				$grey = [];
				$alphas = [];
				for ($level = 0; $level < 256; $level++) {
					$grey[chr($level)] = str_repeat(chr($level), 3);
					$alphas[chr($level)] = chr($level) === $key ? "\0" : "\xFF";
				}
				$this->rgb = strtr($pixels, $grey);
				$alpha = strtr($pixels, $alphas);
				break;

			case 2:
				$this->rgb = $pixels;
				$alpha = '';
				if ($key !== '') {
					foreach (str_split($pixels, 3) as $pixel) {
						$alpha .= $pixel === $key ? "\0" : "\xFF";
					}
				}
				break;

			case 3:
				$colours = [];
				$alphas = [];
				for ($index = 0, $count = strlen($palette) / 3; $index < $count; $index++) {
					$colours[chr($index)] = substr($palette, $index * 3, 3);
					$alphas[chr($index)] = $index < strlen($transparency) ? $transparency[$index] : "\xFF";
				}
				$this->rgb = strtr($pixels, $colours);
				$alpha = strtr($pixels, $alphas);
				break;

			case 4:
				$this->rgb = preg_replace('/(.)./s', '$1$1$1', $pixels);
				$alpha = preg_replace('/.(.)/s', '$1', $pixels);
				break;

			default:
				$this->rgb = preg_replace('/(...)./s', '$1', $pixels);
				$alpha = preg_replace('/...(.)/s', '$1', $pixels);
		}

		// A palette index past the end of PLTE has no colour to become, and strtr() leaves it a byte
		if (strlen($this->rgb) !== $this->width * $this->height * 3) {
			throw new MpdfException('A PNG image with a pixel its palette has no colour for');
		}

		$this->alpha = $alpha === '' || strspn($alpha, "\xFF") === strlen($alpha) ? null : $alpha;
	}
}
