<?php

namespace Mpdf\Fonts;

/**
 * The checksum an OpenType table directory carries for each table, and the arithmetic on it.
 *
 * Held as a pair of 16-bit halves rather than one 32-bit number because this library supports
 * PHP 5.6 and a 32-bit build has no integer that fits the sum: the top bit of a uint32 is the sign
 * bit there, so a table whose words add past 2^31 would come back negative and compare unequal to
 * the value the font states.
 *
 * Both halves of font handling need this. The parser verifies the checksums a file arrives with;
 * the subsetter works out the ones a file it builds should carry.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/otff#calculating-checksums
 */
class TableChecksum
{

	/**
	 * How many bytes of a table are unpacked at once: a multiple of four, and small enough that no
	 * sum below overflows within one
	 */
	const CHUNK = 65536;

	/**
	 * @param string $data One table's bytes, padded to a multiple of four as the spec requires
	 *
	 * @return int[] The high and low 16 bits of the sum of the table read as uint32s
	 */
	public static function of($data)
	{
		if (strlen($data) % 4) {
			$data .= str_repeat("\0", 4 - (strlen($data) % 4));
		}

		if (PHP_INT_SIZE >= 8) {
			return self::sumWords($data);
		}

		return self::sumHalves($data);
	}

	/**
	 * Sum the table as uint32s, a chunk at a time, where a PHP integer is wide enough to hold one.
	 *
	 * A chunk's sum stays below 2^46, so it is cut to 32 bits once per chunk rather than per word.
	 *
	 * @return int[] See of()
	 */
	private static function sumWords($data)
	{
		$len = strlen($data);
		$sum = 0;

		for ($i = 0; $i < $len; $i += self::CHUNK) {
			$sum = ($sum + array_sum(unpack('N*', substr($data, $i, self::CHUNK)))) & 0xFFFFFFFF;
		}

		return [$sum >> 16, $sum & 0xFFFF];
	}

	/**
	 * Sum the table as pairs of uint16s, carrying from the low half into the high one per chunk.
	 *
	 * For a 32-bit build, where unpack('N') gives a word of 2^31 or more back as a negative number.
	 * Neither half of one chunk's sum can reach 2^31.
	 *
	 * @return int[] See of()
	 */
	private static function sumHalves($data)
	{
		$len = strlen($data);
		$hi = 0;
		$lo = 0;

		for ($i = 0; $i < $len; $i += self::CHUNK) {
			$words = unpack('n*', substr($data, $i, self::CHUNK));
			$count = count($words);
			for ($j = 1; $j < $count; $j += 2) {
				$hi += $words[$j];
				$lo += $words[$j + 1];
			}
			$hi = ($hi + ($lo >> 16)) & 0xFFFF;
			$lo &= 0xFFFF;
		}

		return [$hi, $lo];
	}

	/**
	 * $x - $y, borrowing between the halves by hand.
	 *
	 * The head table's own checksum is stated as 0xB1B0AFBA less the checksum of the whole file, and
	 * the parser subtracts the checksumAdjustment back out before comparing a head table it read.
	 *
	 * @return int[] The high and low 16 bits of the difference
	 */
	public static function subtract(array $x, array $y)
	{
		list($xhi, $xlo) = $x;
		list($yhi, $ylo) = $y;

		if ($ylo > $xlo) {
			$xlo += 1 << 16;
			++$yhi;
		}
		$reslo = $xlo - $ylo;

		if ($yhi > $xhi) {
			$xhi += 1 << 16;
		}
		$reshi = ($xhi - $yhi) & 0xFFFF;

		return [$reshi, $reslo];
	}
}
