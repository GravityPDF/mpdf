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
	 * @param string $data One table's bytes, padded to a multiple of four as the spec requires
	 *
	 * @return int[] The high and low 16 bits of the sum of the table read as uint32s
	 */
	public static function of($data)
	{
		if (strlen($data) % 4) {
			$data .= str_repeat("\0", 4 - (strlen($data) % 4));
		}

		$len = strlen($data);
		$hi = 0x0000;
		$lo = 0x0000;

		for ($i = 0; $i < $len; $i += 4) {
			$hi += (ord($data[$i]) << 8) + ord($data[$i + 1]);
			$lo += (ord($data[$i + 2]) << 8) + ord($data[$i + 3]);
			$hi += ($lo >> 16) & 0xFFFF;
			$lo &= 0xFFFF;
		}

		$hi &= 0xFFFF;

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
