<?php

namespace Mpdf\Color;

/**
 * Builds data/iccprofiles/Gray_sRGB_TRC.icc, a monochrome ICC profile (version 2.1) whose tone curve is
 * sRGB's, so that grey level g is the sRGB neutral (g, g, g) and draws as DeviceGray g does. PDF/X-4
 * printing to an RGB output intent permits no DeviceGray, so mPDF writes grey in an ICC-based colour
 * space built on this profile instead. What composer grayprofile:update runs - see
 * utils/grayprofile_update.php.
 *
 * mPDF builds its own profile rather than shipping someone else's, so the file is under mPDF's own
 * licence, GPL-2.0-only, which its copyright tag states. It is the header, a tag table, and four tags: a
 * description, a copyright, the D50 media white point and the grey tone curve.
 */
final class GrayIccProfile
{

	/**
	 * Where mPDF reads the profile from
	 */
	const FILE = __DIR__ . '/../../../data/iccprofiles/Gray_sRGB_TRC.icc';

	/**
	 * The copyright tag's text
	 */
	const COPYRIGHT = 'Copyright 2026 mPDF contributors. Licensed under the GNU General Public License version 2 (GPL-2.0-only).';

	/**
	 * The number of entries in the tone curve, which a reader interpolates between
	 */
	const CURVE_ENTRIES = 1024;

	/**
	 * @return string The profile
	 */
	public static function build()
	{
		$d50 = self::xyz(0.9642, 1.0, 0.8249);
		$tags = [
			'desc' => self::description('Gray, sRGB tone curve (mPDF)'),
			'cprt' => 'text' . pack('N', 0) . self::COPYRIGHT . "\0",
			'wtpt' => $d50,
			'kTRC' => self::curve(),
		];

		// The header is 128 bytes, followed by the tag count and a 12-byte entry for each tag
		$offset = 128 + 4 + 12 * count($tags);
		$table = pack('N', count($tags));
		$data = '';
		foreach ($tags as $signature => $tag) {
			$table .= $signature . pack('NN', $offset + strlen($data), strlen($tag));
			$data .= str_pad($tag, (int) ceil(strlen($tag) / 4) * 4, "\0");
		}

		$header = pack('N', 128 + strlen($table) + strlen($data))
			. pack('N', 0) // preferred CMM: none
			. pack('N', 0x02100000) // version 2.1
			. 'mntr' // device class: display
			. 'GRAY' // data colour space
			. 'XYZ ' // profile connection space
			. pack('n6', 2026, 1, 1, 0, 0, 0) // created, fixed so that building it again gives the same file
			. 'acsp'
			. str_repeat("\0", 24) // platform, flags, manufacturer, model and attributes
			. pack('N', 0) // rendering intent: perceptual
			. substr($d50, 8) // illuminant: D50, the tag's values alone
			. str_repeat("\0", 48); // creator and reserved

		return $header . $table . $data;
	}

	/**
	 * @param string $text ASCII
	 *
	 * @return string A textDescriptionType tag, with no Unicode or ScriptCode description
	 */
	private static function description($text)
	{
		return 'desc' . pack('NN', 0, strlen($text) + 1) . $text . "\0" . pack('NNnC', 0, 0, 0, 0) . str_repeat("\0", 67);
	}

	/**
	 * @param float $x
	 * @param float $y
	 * @param float $z
	 *
	 * @return string An XYZType tag, each value an s15Fixed16Number
	 */
	private static function xyz($x, $y, $z)
	{
		return 'XYZ ' . pack('NNNN', 0, round($x * 65536), round($y * 65536), round($z * 65536));
	}

	/**
	 * @return string A curveType tag of sRGB's transfer function, from IEC 61966-2-1
	 */
	private static function curve()
	{
		$curve = 'curv' . pack('NN', 0, self::CURVE_ENTRIES);
		for ($i = 0; $i < self::CURVE_ENTRIES; $i++) {
			$v = $i / (self::CURVE_ENTRIES - 1);
			$linear = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
			$curve .= pack('n', (int) round($linear * 65535));
		}

		return $curve;
	}
}
