<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;

/**
 * Reads an element's multi-column properties (column-count, column-width, column-gap, column-fill and column-rule)
 * into the columns mPDF's SetColumns() lays out
 */
final class MultiColumn
{

	/**
	 * The column rule styles drawn as a single solid line. The 3D styles have no second colour to shade with here
	 */
	const SOLID_STYLES = ['solid', 'groove', 'ridge', 'inset', 'outset'];

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var ColorConverter
	 */
	private $colorConverter;

	/**
	 * @param SizeConverter $sizeConverter
	 * @param ColorConverter $colorConverter
	 */
	public function __construct(SizeConverter $sizeConverter, ColorConverter $colorConverter)
	{
		$this->sizeConverter = $sizeConverter;
		$this->colorConverter = $colorConverter;
	}

	/**
	 * Whether the properties ask for columns: a column-count or column-width other than auto
	 *
	 * @param array $properties The element's merged CSS, by upper-cased property
	 *
	 * @return bool
	 */
	public static function isRequested(array $properties)
	{
		return self::count($properties) !== null || self::width($properties) !== null;
	}

	/**
	 * The columns an element is laid out in, or null where it takes fewer than two. A column-width is the narrowest a
	 * column may be, so it gives as many columns as fit, and no more than a column-count also given (CSS Multi-column
	 * Layout 3.4)
	 *
	 * @param array $properties The element's merged CSS, by upper-cased property
	 * @param float $availableWidth The width the columns share, in mm
	 * @param float $fontSize The element's font size in mm, which an em and the normal gap are taken of
	 *
	 * @return array|null [count, gap in mm, whether the last columns are balanced, rule or null]
	 */
	public function layout(array $properties, $availableWidth, $fontSize)
	{
		$gap = $fontSize;
		if (isset($properties['COLUMN-GAP']) && $properties['COLUMN-GAP'] !== 'normal') {
			$length = $this->sizeConverter->convert($properties['COLUMN-GAP'], $availableWidth, $fontSize, false);
			if ($length >= 0) {
				$gap = $length;
			}
		}

		$count = self::count($properties);
		$width = self::width($properties);
		if ($width !== null) {
			$width = $this->sizeConverter->convert($width, $availableWidth, $fontSize, false);
			$fit = $width > 0 ? max(1, (int) floor(($availableWidth + $gap) / ($width + $gap))) : $count;
			$count = $count === null ? $fit : min($count, $fit);
		}

		if ($count === null || $count < 2) {
			return null;
		}

		return [
			'count' => $count,
			'gap' => $gap,
			'balance' => !isset($properties['COLUMN-FILL']) || $properties['COLUMN-FILL'] !== 'auto',
			'rule' => $this->rule($properties, $availableWidth, $fontSize),
		];
	}

	/**
	 * The PDF operators that draw a column rule as a vertical line
	 *
	 * @param array $rule The rule layout() gives
	 * @param string $color The operator that sets the stroke colour
	 * @param float $x Where the line is centred across, in mm from the left of the page
	 * @param float $top The top of the line, in mm from the top of the page
	 * @param float $bottom The foot of the line, in mm from the top of the page
	 * @param float $pageHeight In mm
	 *
	 * @return string
	 */
	public static function ruleOperators(array $rule, $color, $x, $top, $bottom, $pageHeight)
	{
		$w = $rule['w'];
		$lines = [$x];
		$dash = '[] 0 d 0 J';
		if ($rule['style'] === 'double') {
			$lines = [$x - $w / 3, $x + $w / 3];
			$w /= 3;
		} elseif ($rule['style'] === 'dashed') {
			$dash = sprintf('[%.3F %.3F] 0 d 0 J', 3 * $w * Mpdf::SCALE, 3 * $w * Mpdf::SCALE);
		} elseif ($rule['style'] === 'dotted') {
			$dash = sprintf('[0 %.3F] 0 d 1 J', 2 * $w * Mpdf::SCALE);
			$top += $w / 2;
			$bottom -= $w / 2;
		}

		$s = sprintf('q %s %.3F w %s', $color, $w * Mpdf::SCALE, $dash);
		foreach ($lines as $lineX) {
			$s .= sprintf(
				' %.3F %.3F m %.3F %.3F l S',
				$lineX * Mpdf::SCALE,
				($pageHeight - $top) * Mpdf::SCALE,
				$lineX * Mpdf::SCALE,
				($pageHeight - $bottom) * Mpdf::SCALE
			);
		}

		return $s . ' Q';
	}

	/**
	 * The column rule, or null where none is drawn: its style is none or hidden, or its width is 0
	 *
	 * @param array $properties
	 * @param float $availableWidth
	 * @param float $fontSize
	 *
	 * @return array|null [w in mm, style, c as ColorConverter gives it]
	 */
	private function rule(array $properties, $availableWidth, $fontSize)
	{
		$style = isset($properties['COLUMN-RULE-STYLE']) ? $properties['COLUMN-RULE-STYLE'] : 'none';
		if ($style === 'none' || $style === 'hidden') {
			return null;
		}

		$width = isset($properties['COLUMN-RULE-WIDTH']) ? $properties['COLUMN-RULE-WIDTH'] : 'medium';
		$width = $this->sizeConverter->convert($width, $availableWidth, $fontSize, false);
		$color = isset($properties['COLUMN-RULE-COLOR']) ? $this->colorConverter->convert($properties['COLUMN-RULE-COLOR']) : false;
		if ($width <= 0 || !$color) {
			return null;
		}

		return [
			'w' => $width,
			'style' => in_array($style, self::SOLID_STYLES, true) ? 'solid' : $style,
			'c' => $color,
		];
	}

	/**
	 * The column-count, or null where it is auto or not a positive integer
	 *
	 * @param array $properties
	 *
	 * @return int|null
	 */
	private static function count(array $properties)
	{
		if (isset($properties['COLUMN-COUNT']) && preg_match('/^\d+$/', $properties['COLUMN-COUNT']) && (int) $properties['COLUMN-COUNT'] > 0) {
			return (int) $properties['COLUMN-COUNT'];
		}

		return null;
	}

	/**
	 * The column-width as written, or null where it is auto or not a length
	 *
	 * @param array $properties
	 *
	 * @return string|null
	 */
	private static function width(array $properties)
	{
		if (isset($properties['COLUMN-WIDTH']) && preg_match('/^(\d+\.?\d*|\.\d+)[a-z]+$/', $properties['COLUMN-WIDTH'])) {
			return $properties['COLUMN-WIDTH'];
		}

		return null;
	}

}
