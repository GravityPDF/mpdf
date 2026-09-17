<?php

namespace Mpdf\Shaper;

/**
 * Cutting, trimming and editing a laid-out run of text together with the text it was laid out from.
 *
 * A run - OTLdata, the 19th entry of a textbuffer chunk - is an array of three parallel parts, each
 * indexed by character position in the text: 'group', a string of one S (space), M (mark) or C
 * (anything else) per character; 'char_data', a list of one array per character holding at least
 * its 'uni' and 'bidi_class'; and 'GPOSinfo', positioning keyed by position, present only where a
 * character has any. When the text is cut or a character taken out, all three have to follow it.
 *
 * These work on the array in place, and not on an object, because runs are copied by assignment -
 * into the part of a line saved for the next one, into an object's attributes - and every copy is
 * expected to be independent. So is the copy Mpdf::getStateSnapshot() takes of the textbuffer for a
 * look-ahead: an object inside it would be shared with the document, and a run the look-ahead
 * trimmed would stay trimmed once the snapshot was restored.
 */
class OtlData
{

	/**
	 * Cut a run in two, at a line break.
	 *
	 * What is left of the run keeps everything before the cut; what is returned starts at the restart
	 * position, which is past the cut where the break took a space with it.
	 *
	 * @param array      $cOTLdata      The run, truncated in place to the part before the break
	 * @param int        $OTLcutoffpos  Where the first part ends
	 * @param int|string $OTLrestartpos Where the second part begins, or '' (or 0) for the cutoff
	 *
	 * @return array The part after the break
	 */
	public static function split(&$cOTLdata, $OTLcutoffpos, $OTLrestartpos = '')
	{
		if (!$OTLrestartpos) {
			$OTLrestartpos = $OTLcutoffpos;
		}
		$newOTLdata = ['GPOSinfo' => [], 'char_data' => []];
		$newOTLdata['group'] = substr($cOTLdata['group'], $OTLrestartpos);
		$cOTLdata['group'] = substr($cOTLdata['group'], 0, $OTLcutoffpos);

		if (isset($cOTLdata['GPOSinfo']) && $cOTLdata['GPOSinfo']) {
			foreach ($cOTLdata['GPOSinfo'] as $k => $val) {
				if ($k >= $OTLrestartpos) {
					$newOTLdata['GPOSinfo'][($k - $OTLrestartpos)] = $val;
				}
				if ($k >= $OTLcutoffpos) {
					unset($cOTLdata['GPOSinfo'][$k]);
				}
			}
		}
		if (isset($cOTLdata['char_data'])) {
			$newOTLdata['char_data'] = array_slice($cOTLdata['char_data'], $OTLrestartpos);
			array_splice($cOTLdata['char_data'], $OTLcutoffpos);
		}

		if (isset($cOTLdata['GPOSinfo'])) {
			ksort($cOTLdata['GPOSinfo']);
		}
		ksort($newOTLdata['GPOSinfo']);

		return $newOTLdata;
	}

	/**
	 * A copy of part of a run, with the positioning renumbered to start at zero.
	 *
	 * @param array $OTLdata The run
	 * @param int   $pos     Where the part begins
	 * @param int   $len     How many characters of it to take
	 *
	 * @return array The part, as a run of its own
	 */
	public static function slice($OTLdata, $pos, $len)
	{
		// applyOTL() leaves OTLdata empty for a blank string, so every key here is optional
		$newOTLdata = ['GPOSinfo' => [], 'char_data' => []];
		$newOTLdata['group'] = isset($OTLdata['group']) ? substr($OTLdata['group'], $pos, $len) : '';

		if (!empty($OTLdata['GPOSinfo'])) {
			foreach ($OTLdata['GPOSinfo'] as $k => $val) {
				if ($k >= $pos && $k < ($pos + $len)) {
					$newOTLdata['GPOSinfo'][($k - $pos)] = $val;
				}
			}
		}

		if (isset($OTLdata['char_data'])) {
			$newOTLdata['char_data'] = array_slice($OTLdata['char_data'], $pos, $len);
		}

		if ($newOTLdata['GPOSinfo']) {
			ksort($newOTLdata['GPOSinfo']);
		}

		return $newOTLdata;
	}

	/**
	 * Put one character at the front of a run, moving the rest of it along by one.
	 *
	 * @param array  $cOTLdata The run
	 * @param array  $charData The character's entry, as Bidi::prepare() would have left it
	 * @param string $group    Its group: S, M or C
	 */
	public static function prependChar(&$cOTLdata, $charData, $group)
	{
		// applyOTL() leaves OTLdata empty for a blank string, so every key here is optional
		$cOTLdata += ['group' => '', 'char_data' => [], 'GPOSinfo' => []];

		$cOTLdata['group'] = $group . $cOTLdata['group'];
		array_unshift($cOTLdata['char_data'], $charData);

		if ($cOTLdata['GPOSinfo']) {
			$newGPOSinfo = [];
			foreach ($cOTLdata['GPOSinfo'] as $k => $val) {
				$newGPOSinfo[$k + 1] = $val;
			}
			$cOTLdata['GPOSinfo'] = $newGPOSinfo;
		}
	}

	/**
	 * Take every occurrence of a character out of a text and out of the run laid out from it.
	 *
	 * @param string $txt      The text, edited in place
	 * @param array  $cOTLdata Its run, edited in place
	 * @param string $char     One character, as the text encodes it. Not a regex metacharacter: it is
	 *                         put into a pattern as it is.
	 * @param string $encoding The text's encoding, Mpdf::$mb_enc
	 */
	public static function removeChar(&$txt, &$cOTLdata, $char, $encoding)
	{
		while (mb_strpos($txt, $char, 0, $encoding) !== false) {
			$pos = mb_strpos($txt, $char, 0, $encoding);
			$newGPOSinfo = [];
			$cOTLdata['group'] = substr_replace($cOTLdata['group'], '', $pos, 1);
			if ($cOTLdata['GPOSinfo']) {
				foreach ($cOTLdata['GPOSinfo'] as $k => $val) {
					if ($k > $pos) {
						$newGPOSinfo[($k - 1)] = $val;
					} elseif ($k != $pos) {
						$newGPOSinfo[$k] = $val;
					}
				}
				$cOTLdata['GPOSinfo'] = $newGPOSinfo;
			}
			if (isset($cOTLdata['char_data'])) {
				array_splice($cOTLdata['char_data'], $pos, 1);
			}

			$txt = preg_replace("/" . $char . "/", '', $txt, 1);
		}
	}

	/**
	 * Turn every no-break space in a text into a space, in the text and in the run laid out from it.
	 *
	 * @param string $txt      The text as UTF-8, edited in place
	 * @param array  $cOTLdata Its run, edited in place
	 * @param string $encoding Mpdf::$mb_enc
	 */
	public static function nbspToSpace(&$txt, &$cOTLdata, $encoding)
	{
		$nbsp = "\xc2\xa0";
		while (mb_strpos($txt, $nbsp, 0, $encoding) !== false) {
			$pos = mb_strpos($txt, $nbsp, 0, $encoding);
			if ($cOTLdata['char_data'][$pos]['uni'] == 160) {
				$cOTLdata['char_data'][$pos]['uni'] = 32;
			}
			$txt = preg_replace("/" . $nbsp . "/", ' ', $txt, 1);
		}
	}

	/**
	 * Drop the spaces, U+0020 and U+3000 IDEOGRAPHIC SPACE, from the ends of a run, and the positioning
	 * that went with them.
	 *
	 * @param array $cOTLdata The run, trimmed in place
	 * @param bool  $left     Whether to trim the start
	 * @param bool  $right    Whether to trim the end
	 */
	public static function trim(&$cOTLdata, $left = true, $right = true)
	{
		$len = (!is_array($cOTLdata) || $cOTLdata['char_data'] === null) ? 0 : count($cOTLdata['char_data']);
		$nLeft = 0;
		$nRight = 0;
		for ($i = 0; $i < $len; $i++) {
			if ($cOTLdata['char_data'][$i]['uni'] == 32 || $cOTLdata['char_data'][$i]['uni'] == 12288) {
				$nLeft++;
			} else {
				break;
			}
		}
		for ($i = ($len - 1); $i >= 0; $i--) {
			if ($cOTLdata['char_data'][$i]['uni'] == 32 || $cOTLdata['char_data'][$i]['uni'] == 12288) {
				$nRight++;
			} else {
				break;
			}
		}

		if ($right && $nRight) {
			$cOTLdata['group'] = substr($cOTLdata['group'], 0, strlen($cOTLdata['group']) - $nRight);
			if ($cOTLdata['GPOSinfo']) {
				foreach ($cOTLdata['GPOSinfo'] as $k => $val) {
					if ($k >= $len - $nRight) {
						unset($cOTLdata['GPOSinfo'][$k]);
					}
				}
			}
			if (isset($cOTLdata['char_data'])) {
				for ($i = 0; $i < $nRight; $i++) {
					array_pop($cOTLdata['char_data']);
				}
			}
		}

		if ($left && $nLeft) {
			$cOTLdata['group'] = substr($cOTLdata['group'], $nLeft);
			if ($cOTLdata['GPOSinfo']) {
				$newPOSinfo = [];
				foreach ($cOTLdata['GPOSinfo'] as $k => $val) {
					if ($k >= $nLeft) {
						$newPOSinfo[$k - $nLeft] = $cOTLdata['GPOSinfo'][$k];
					}
				}
				$cOTLdata['GPOSinfo'] = $newPOSinfo;
			}
			if (isset($cOTLdata['char_data'])) {
				for ($i = 0; $i < $nLeft; $i++) {
					array_shift($cOTLdata['char_data']);
				}
			}
		}
	}

}
