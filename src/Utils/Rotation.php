<?php

namespace Mpdf\Utils;

class Rotation
{

	/**
	 * Read mPDF's rotate property: whole degrees, with or without "deg", wrapped to between -180 and 180
	 *
	 * @param string|int $rotate
	 * @param int[] $supported The angles the caller can draw
	 *
	 * @return int The angle, or 0 when it is not one of $supported
	 */
	public static function angle($rotate, array $supported)
	{
		if (1 !== preg_match('/^(-?[0-9]+)(?:deg)?$/i', trim((string) $rotate), $matches)) {
			return 0;
		}

		$degrees = (int) $matches[1] % 360;
		if ($degrees > 180) {
			$degrees -= 360;
		} elseif ($degrees <= -180) {
			$degrees += 360;
		}

		return in_array($degrees, $supported, true) ? $degrees : 0;
	}

}
