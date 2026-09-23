<?php

namespace Mpdf\Writer;

/**
 * The ToUnicode CMap of a font written a byte at a time, which is what tells a reader the text
 * behind the bytes a page draws: what a selection copies out, what a search matches, and what a
 * screen reader says.
 *
 * Two writers need it. FontWriter takes this path for a font with Supplementary Multilingual or
 * Ideographic Plane coverage, which it writes as a series of subsets of up to 255 characters, each
 * a simple font of its own. Type3FontWriter encodes a colour font the same way, and additionally
 * maps a ligature back to the characters it was formed from, so a copied emoji family is the ZWJ
 * sequence it was typed as.
 *
 * A bfchar block holds a hundred entries at most, so a subset larger than that takes several. That
 * limit is why this is worth having in one place: the entries are the font's whole text layer, and
 * a reader that enforces the limit finds nothing for the bytes past the hundredth of a block.
 *
 * @see https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/PDF32000_2008.pdf 9.10.3
 * @see https://adobe-type-tools.github.io/font-tech-notes/pdfs/5411.ToUnicode.pdf 1.4.1
 */
class ToUnicode
{

	/**
	 * The most entries a bfchar block may hold
	 */
	const BLOCK = 100;

	/**
	 * @param int[]|int[][] $codeToChars Byte => the character it draws, or the characters it stands
	 *                                   for where one byte copies out as several
	 *
	 * @return string The CMap, ready to be written as a stream
	 */
	public static function byteCMap(array $codeToChars)
	{
		$entries = [];
		foreach ($codeToChars as $code => $chars) {
			$text = '';
			foreach ((array) $chars as $char) {
				$text .= self::utf16BigEndian($char);
			}

			$entries[] = sprintf('<%02X> <%s>', $code, $text);
		}

		$cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
			. "/CIDSystemInfo <</Registry (Adobe) /Ordering (UCS) /Supplement 0>> def\n"
			. "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
			. "1 begincodespacerange\n<00> <FF>\nendcodespacerange\n";

		foreach (array_chunk($entries, self::BLOCK) as $block) {
			$cmap .= count($block) . " beginbfchar\n" . implode("\n", $block) . "\nendbfchar\n";
		}

		return $cmap . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
	}

	/**
	 * The code units of a character, which is what a CMap gives its Unicode value in. Above the basic
	 * plane that is the surrogate pair UTF-16 splits the character into.
	 *
	 * @param int $char A code point
	 *
	 * @return string Its UTF-16BE code units in upper case hexadecimal
	 */
	private static function utf16BigEndian($char)
	{
		if ($char > 0xFFFF) {
			$char -= 0x10000;

			return sprintf('%04X%04X', 0xD800 + ($char >> 10), 0xDC00 + ($char & 0x3FF));
		}

		return sprintf('%04X', $char);
	}
}
