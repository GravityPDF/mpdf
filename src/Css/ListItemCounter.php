<?php

namespace Mpdf\Css;

/**
 * Reads what counter-reset, counter-set and counter-increment do to the list-item counter, the one counter mPDF keeps.
 * Named counters are only shown through generated content, which mPDF does not draw
 */
final class ListItemCounter
{

	/**
	 * @param array $properties An element's merged CSS
	 * @param string $property COUNTER-RESET, COUNTER-SET or COUNTER-INCREMENT
	 *
	 * @return array|null [the integer given, or null for none, whether the counter is reversed()], or null when the
	 *                    property does not name list-item. Where it is named more than once, the last one counts
	 */
	public static function read(array $properties, $property)
	{
		if (!isset($properties[$property])) {
			return null;
		}

		preg_match_all('/\G\s*(reversed\(\s*([^\s()]+)\s*\)|[^\s()]+)(?:\s+([+-]?\d+)(?![\w.]))?/i', trim($properties[$property]), $m, PREG_SET_ORDER);

		$found = null;
		foreach ($m as $counter) {
			$reversed = isset($counter[2]) && $counter[2] !== '';
			$name = strtolower($reversed ? $counter[2] : $counter[1]);
			if ($name === 'list-item') {
				$found = [isset($counter[3]) && $counter[3] !== '' ? (int) $counter[3] : null, $reversed];
			}
		}

		return $found;
	}
}
