<?php

namespace Mpdf\Css;

/**
 * A text-indent value read under CssMode::STANDARD: a length or percentage, with the hanging and each-line keywords
 * in any order around it.
 *
 * A block keeps it in blk[] and a table cell in its cell array, under the same keys: text_indent, the length, and
 * text_indent_hanging and text_indent_each_line, the keywords. Mpdf::lineTextIndent() reads them when it lays a line.
 */
final class TextIndent
{

	/**
	 * @param string $value The declared value
	 *
	 * @return array|null ['length' => string, 'hanging' => bool, 'each-line' => bool], or null when the value is not
	 *                    one length with at most one of each keyword
	 */
	public static function parse($value)
	{
		$indent = ['length' => null, 'hanging' => false, 'each-line' => false];
		foreach (preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
			$keyword = strtolower($part);
			if ($keyword === 'hanging' || $keyword === 'each-line') {
				if ($indent[$keyword]) {
					return null;
				}
				$indent[$keyword] = true;
			} elseif ($indent['length'] === null && !preg_match('/^-?[a-z]/i', $part)) {
				$indent['length'] = $part;
			} else {
				return null;
			}
		}

		return $indent['length'] === null ? null : $indent;
	}
}
