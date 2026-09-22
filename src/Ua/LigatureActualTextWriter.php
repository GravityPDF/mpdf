<?php

namespace Mpdf\Ua;

/**
 * Wraps a ligature in a /Span with /ActualText, so the characters it was formed from can be
 * read back when the font's ToUnicode map has no entry for it (Matterhorn 24-001).
 */
class LigatureActualTextWriter
{

	/**
	 * The operator opening the wrapper. It is returned rather than written because the caller
	 * splices it into the TJ string it is building, and so it is not counted by
	 * MarkedContentHelper.
	 *
	 * @param string $actualTextHex UTF-16BE with its BOM, in hex
	 *
	 * @return string
	 */
	public function buildBdcBytes($actualTextHex)
	{
		return '/Span <</ActualText <' . $actualTextHex . '>>> BDC';
	}

	/**
	 * The operator closing what buildBdcBytes() opened
	 *
	 * @return string
	 */
	public function buildEmcBytes()
	{
		return 'EMC';
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return string UTF-16BE with its BOM, in hex, e.g. 'FEFF00660069' for "fi"
	 */
	public function getActualTextEncoding($codepoints)
	{
		$hex = 'FEFF';
		foreach ($codepoints as $cp) {
			// A surrogate or a value outside Unicode would make malformed UTF-16, so it stands as U+FFFD
			if (!is_int($cp) || $cp < 0 || $cp > 0x10FFFF
				|| ($cp >= 0xD800 && $cp <= 0xDFFF)
			) {
				$hex .= 'FFFD';
				continue;
			}
			if ($cp < 0x10000) {
				$hex .= sprintf('%04X', $cp);
			} else {
				$cp -= 0x10000;
				$high = 0xD800 + (($cp >> 10) & 0x3FF);
				$low  = 0xDC00 + ($cp & 0x3FF);
				$hex .= sprintf('%04X%04X', $high, $low);
			}
		}
		return $hex;
	}

	/**
	 * Whether the font's ToUnicode map already gives the ligature these characters, making the
	 * wrapper needless. Only a font carrying 'toUnicodeMultiChar' can say yes; FontWriter maps
	 * each glyph to one character, so for now it is only tests that set it.
	 *
	 * @param int   $glyphIndex
	 * @param int[] $sourceChars The characters the ligature was formed from
	 * @param array $currentFont
	 *
	 * @return bool
	 */
	public function toUnicodeCovers($glyphIndex, $sourceChars, $currentFont)
	{
		if (isset($currentFont['toUnicodeMultiChar'][$glyphIndex])) {
			$mapped = $currentFont['toUnicodeMultiChar'][$glyphIndex];
			if (is_array($mapped) && $mapped === $sourceChars) {
				return true;
			}
		}
		return false;
	}
}
