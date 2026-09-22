<?php

namespace Mpdf\Import;

use Mpdf\Image\PngPixels;
use setasign\Fpdi\PdfParser\Filter\FilterException;

/**
 * Each predictor undone. PNG rows are checked against a PNG and ImageMagick's reading of it, which PngPixelsTest
 * reads the same way; each PNG filter's own arithmetic is PngPixels::unfilter()'s and is tested there.
 */
class PredictorDecoderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const PNG = __DIR__ . '/../../data/img/pngpixels/rgb8-None';

	/**
	 * The rows of a 13 by 7 RGB PNG, written with the Sub and Up filters, come back as its pixels
	 */
	public function testPngRowsComeBackAsThePixels()
	{
		$png = file_get_contents(self::PNG . '.png');
		$data = '';
		foreach (PngPixels::chunks($png) as $chunk) {
			if ($chunk['type'] === 'IDAT') {
				$data .= substr($png, $chunk['payload'], $chunk['size']);
			}
		}

		// ImageMagick's dump is RGBA; the PNG has no alpha
		$expected = implode('', array_map(function ($pixel) {
			return substr($pixel, 0, 3);
		}, str_split(file_get_contents(self::PNG . '.rgba'), 4)));

		$this->assertSame(bin2hex($expected), bin2hex((new PredictorDecoder(15, 3, 8, 13))->decode(gzuncompress($data))));
	}

	/**
	 * Each bit depth ISO 32000-1, 7.4.4.4 allows the TIFF predictor, with the number of colours, and rows of samples
	 * whose differences wrap around below zero and above the largest sample
	 */
	public function tiffDepthProvider()
	{
		return [
			'1 bit, one colour' => [1, 1, [[1, 0, 0, 1, 1, 1, 0, 1, 0, 1, 1], [0, 1, 1, 1, 0, 0, 0, 1, 1, 0, 1]]],
			'2 bits, three colours' => [2, 3, [[3, 0, 1, 2, 3, 0, 0, 1, 3], [1, 1, 1, 3, 3, 3, 0, 2, 1]]],
			'4 bits, two colours' => [4, 2, [[15, 0, 3, 14, 7, 1, 0, 9], [2, 2, 12, 5, 15, 15, 0, 0]]],
			'8 bits, three colours' => [8, 3, [[0, 16, 32, 255, 0, 127, 1, 2, 3], [5, 5, 5, 254, 1, 128, 16, 32, 48]]],
			'16 bits, two colours' => [16, 2, [[65535, 0, 1, 65534, 300, 40000], [7, 65000, 0, 12, 65535, 1]]],
		];
	}

	/**
	 * Samples $bits wide packed most significant first, the row padded to a whole byte
	 *
	 * @param int[] $samples
	 * @param int   $bits
	 *
	 * @return string
	 */
	private function pack(array $samples, $bits)
	{
		$bytes = '';
		$byte = 0;
		$filled = 0;
		foreach ($samples as $sample) {
			for ($bit = $bits - 1; $bit >= 0; $bit--) {
				$byte = ($byte << 1) | (($sample >> $bit) & 1);
				if (++$filled === 8) {
					$bytes .= chr($byte);
					$byte = 0;
					$filled = 0;
				}
			}
		}

		return $filled ? $bytes . chr($byte << (8 - $filled)) : $bytes;
	}

	/**
	 * The TIFF predictor is undone at each bit depth
	 *
	 * @dataProvider tiffDepthProvider
	 *
	 * @param int     $bits
	 * @param int     $colors
	 * @param int[][] $rows
	 */
	public function testTheTiffPredictorIsUndoneAtEachBitDepth($bits, $colors, array $rows)
	{
		$columns = count($rows[0]) / $colors;
		$expected = '';
		$encoded = '';
		foreach ($rows as $row) {
			$differences = [];
			foreach ($row as $i => $sample) {
				$differences[] = ($sample - ($i >= $colors ? $row[$i - $colors] : 0)) & ((1 << $bits) - 1);
			}
			$expected .= $this->pack($row, $bits);
			$encoded .= $this->pack($differences, $bits);
		}

		$this->assertSame(bin2hex($expected), bin2hex((new PredictorDecoder(2, $colors, $bits, $columns))->decode($encoded)));
	}

	/**
	 * A predictor the specification does not define is refused rather than guessed at
	 */
	public function testAnUnknownPredictorIsRefused()
	{
		$this->expectException(FilterException::class);

		(new PredictorDecoder(7, 1, 8, 1))->decode("\x01");
	}

}
