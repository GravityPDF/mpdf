<?php

namespace Mpdf\Css;

/**
 * The logical values of text-align, which mPDF reads under CssMode::STANDARD: start and end, taken against the
 * direction of the element that has them, and match-parent, which takes the parent's alignment with its start or end
 * taken against the parent's direction
 */
final class TextAlign
{

	/**
	 * @param string $value start or end
	 * @param string|null $direction ltr or rtl; ltr when null
	 *
	 * @return string L or R, as mPDF holds an alignment
	 */
	public static function physical($value, $direction)
	{
		$rtl = strtolower((string) $direction) === 'rtl';

		return (strtolower($value) === 'end') !== $rtl ? 'R' : 'L';
	}

	/**
	 * What text-align: match-parent computes to
	 *
	 * @param array $parent The computed values on the parent's frame
	 *
	 * @return string The parent's text-align, with start and end replaced by left or right
	 */
	public static function matchParent(array $parent)
	{
		$align = isset($parent['TEXT-ALIGN']) ? strtolower($parent['TEXT-ALIGN']) : 'start';
		if ($align !== 'start' && $align !== 'end' && $align !== 'match-parent') {
			return $parent['TEXT-ALIGN'];
		}

		$direction = isset($parent['DIRECTION']) ? $parent['DIRECTION'] : 'ltr';

		return self::physical($align === 'end' ? 'end' : 'start', $direction) === 'R' ? 'right' : 'left';
	}
}
