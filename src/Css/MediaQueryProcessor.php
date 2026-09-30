<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;

/**
 * Matches media query lists against the medium CSSselectMedia names, on the page current when the stylesheet is read.
 *
 * A query matches when its media type is that medium or all, and its conditions hold. The width, height and
 * orientation features describe the page box; any other feature is unknown and makes its query fail, as does a query
 * that cannot be parsed.
 */
class MediaQueryProcessor
{

	/**
	 * How far apart, in mm, two lengths can be and still be equal
	 */
	const TOLERANCE = 0.01;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @param Mpdf $mpdf
	 * @param SizeConverter $sizeConverter
	 */
	public function __construct(Mpdf $mpdf, SizeConverter $sizeConverter)
	{
		$this->mpdf = $mpdf;
		$this->sizeConverter = $sizeConverter;
	}

	/**
	 * Filter HTML elements by media query.
	 *
	 * Removes elements (style or link tags) that don't match the configured media type.
	 *
	 * @param string $html HTML content to filter
	 * @param string $pattern Regex pattern to match elements
	 * @return string Filtered HTML
	 */
	public function filterByMediaQuery($html, $pattern)
	{
		preg_match_all($pattern, $html, $m);
		foreach ($m[0] as $i => $url) {
			// Unlike an @media block, a <style> or <link> for any medium is left out when CSSselectMedia names none
			if (!$this->mpdf->CSSselectMedia || !$this->matches($m[1][$i])) {
				$html = str_replace($m[0][$i], '', $html);
			}
		}
		return $html;
	}

	/**
	 * Whether a media query list applies to the medium CSSselectMedia names. Every list applies when it names none, and
	 * an empty list always applies.
	 *
	 * @param string $mediaQueryList
	 * @return bool
	 */
	public function matches($mediaQueryList)
	{
		$mediaQueryList = strtolower(trim($mediaQueryList));
		if (!$this->mpdf->CSSselectMedia || $mediaQueryList === '') {
			return true;
		}

		$media = preg_split('/[\s,|]+/', strtolower(trim($this->mpdf->CSSselectMedia)));
		foreach (explode(',', $mediaQueryList) as $query) {
			if ($this->matchesQuery(trim($query), $media)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether one media query applies
	 *
	 * @param string $query In lower case
	 * @param string[] $media The media CSSselectMedia names
	 * @return bool
	 */
	private function matchesQuery($query, array $media)
	{
		if (!preg_match('/^(?:(not|only)\s+)?([a-z][-a-z0-9]*)(?:\s+and\s+(.+))?$/s', $query, $m)
			|| in_array($m[2], ['not', 'only', 'and', 'or'], true)
		) {
			return $this->evaluateCondition($query) === true;
		}

		$matches = $m[2] === 'all' || in_array($m[2], $media, true);
		if ($matches && isset($m[3])) {
			$matches = $this->evaluateCondition($m[3]);
		}

		return $matches === null ? false : ($m[1] === 'not') !== $matches;
	}

	/**
	 * Evaluate a media condition: features in parentheses, joined by and or by or, or one negated with not
	 *
	 * @param string $condition
	 * @return bool|null Null when the condition is unknown: it names an unknown feature or cannot be parsed
	 */
	private function evaluateCondition($condition)
	{
		if (preg_match('/^not\s*(\(.*)$/s', trim($condition), $m)) {
			list(, $groups) = $this->splitCondition($m[1]);
			if ($groups === null || count($groups) > 1) {
				return null;
			}

			$result = $this->evaluateInParens($groups[0]);

			return $result === null ? null : !$result;
		}

		list($operator, $groups) = $this->splitCondition($condition);
		if ($groups === null) {
			return null;
		}

		$results = [];
		foreach ($groups as $group) {
			$results[] = $this->evaluateInParens($group);
		}

		return $this->combine($results, $operator === 'or');
	}

	/**
	 * Combine the results of the parts of a condition, where a part that is unknown makes the whole unknown unless
	 * another part decides it
	 *
	 * @param array $results Each true, false or null
	 * @param bool $or Whether the parts are joined by or rather than and
	 * @return bool|null
	 */
	private function combine(array $results, $or)
	{
		if (in_array($or, $results, true)) {
			return $or;
		}

		return in_array(null, $results, true) ? null : !$or;
	}

	/**
	 * Split a condition into its parenthesised groups
	 *
	 * @param string $condition
	 * @return array The operator joining the groups, and or or or null for a single group, then the inside of each
	 *               group, or null if the condition is not a list of groups joined by one operator
	 */
	private function splitCondition($condition)
	{
		$operator = null;
		$groups = [];
		$length = strlen($condition);
		$pos = strspn($condition, " \t\n\r\f");

		while ($pos < $length) {
			if ($condition[$pos] !== '(') {
				return [null, null];
			}

			$depth = 0;
			for ($end = $pos; $end < $length; $end++) {
				$depth += $condition[$end] === '(' ? 1 : ($condition[$end] === ')' ? -1 : 0);
				if ($depth === 0) {
					break;
				}
			}

			if ($end >= $length) {
				return [null, null];
			}

			$groups[] = trim(substr($condition, $pos + 1, $end - $pos - 1));
			$pos = $end + 1 + strspn($condition, " \t\n\r\f", $end + 1);
			if ($pos >= $length) {
				break;
			}

			if (!preg_match('/\G(and|or)\s+/', $condition, $join, 0, $pos) || ($operator !== null && $operator !== $join[1])) {
				return [null, null];
			}

			$operator = $join[1];
			$pos += strlen($join[0]);
		}

		return [$operator, $groups ?: null];
	}

	/**
	 * Evaluate what is inside a pair of parentheses: a nested condition or a media feature
	 *
	 * @param string $inside
	 * @return bool|null
	 */
	private function evaluateInParens($inside)
	{
		if (preg_match('/^(\(|not\s*\()/', $inside)) {
			return $this->evaluateCondition($inside);
		}

		if (preg_match('/^([a-z-]+)\s*:\s*(.+)$/s', $inside, $m)) {
			return $this->evaluatePlainFeature($m[1], trim($m[2]));
		}

		if (preg_match('/^[a-z-]+$/', $inside)) {
			// A feature on its own holds when it is not zero, and no page is zero wide or high
			return ($inside === 'orientation' || $this->pageSize($inside) !== null) ? true : null;
		}

		return $this->evaluateRange($inside);
	}

	/**
	 * Evaluate a feature written as name: value, where a min- or max- prefix sets a lower or upper bound
	 *
	 * @param string $name
	 * @param string $value
	 * @return bool|null
	 */
	private function evaluatePlainFeature($name, $value)
	{
		if ($name === 'orientation') {
			if ($value !== 'portrait' && $value !== 'landscape') {
				return null;
			}

			return ($this->mpdf->h >= $this->mpdf->w) === ($value === 'portrait');
		}

		if (preg_match('/^(min|max)-(.+)$/', $name, $m)) {
			return $this->compare($m[2], $m[1] === 'min' ? '>=' : '<=', $value);
		}

		return $this->compare($name, '=', $value);
	}

	/**
	 * Evaluate a feature written in range syntax, such as width >= 600px or 400px < width < 700px
	 *
	 * @param string $range
	 * @return bool|null
	 */
	private function evaluateRange($range)
	{
		$parts = preg_split('/\s*(<=|>=|<|>|=)\s*/', $range, -1, PREG_SPLIT_DELIM_CAPTURE);

		if (count($parts) === 3) {
			return $this->compareEitherWay($parts[0], $parts[1], $parts[2]);
		}

		if (count($parts) !== 5 || $parts[1][0] !== $parts[3][0] || $parts[1][0] === '=') {
			return null;
		}

		return $this->combine([
			$this->compareEitherWay($parts[0], $parts[1], $parts[2]),
			$this->compareEitherWay($parts[2], $parts[3], $parts[4]),
		], false);
	}

	/**
	 * Compare a size feature of the page with a length, with the feature's name on either side of the operator
	 *
	 * @param string $left
	 * @param string $operator
	 * @param string $right
	 * @return bool|null
	 */
	private function compareEitherWay($left, $operator, $right)
	{
		if (preg_match('/^[a-z-]+$/', $left)) {
			return $this->compare($left, $operator, $right);
		}

		$flipped = ['<' => '>', '<=' => '>=', '>' => '<', '>=' => '<=', '=' => '='];

		return $this->compare($right, $flipped[$operator], $left);
	}

	/**
	 * Compare a size feature of the page with a length
	 *
	 * @param string $name
	 * @param string $operator One of <, <=, >, >= and =
	 * @param string $value
	 * @return bool|null Null when the feature is unknown or the value is not a length
	 */
	private function compare($name, $operator, $value)
	{
		$size = $this->pageSize($name);
		$length = $this->length($value);
		if ($size === null || $length === null) {
			return null;
		}

		$difference = abs($size - $length) <= self::TOLERANCE ? 0 : $size - $length;
		switch ($operator) {
			case '<':
				return $difference < 0;
			case '<=':
				return $difference <= 0;
			case '>':
				return $difference > 0;
			case '>=':
				return $difference >= 0;
			case '=':
				return $difference === 0;
		}

		return null;
	}

	/**
	 * The page's width or height in mm, which is also the device's on paper
	 *
	 * @param string $name
	 * @return float|null Null for any other feature
	 */
	private function pageSize($name)
	{
		$sizes = [
			'width' => $this->mpdf->w,
			'device-width' => $this->mpdf->w,
			'height' => $this->mpdf->h,
			'device-height' => $this->mpdf->h,
		];

		return isset($sizes[$name]) ? $sizes[$name] : null;
	}

	/**
	 * A length in mm, with em and rem relative to the default font size
	 *
	 * @param string $value
	 * @return float|null Null when the value is not a length
	 */
	private function length($value)
	{
		if (!preg_match('/^[-+]?(\d*\.)?\d+(px|pt|pc|in|cm|mm|em|rem)?$/', $value, $m)) {
			return null;
		}

		if (!isset($m[2])) {
			return (float) $value === 0.0 ? 0.0 : null;
		}

		return $this->sizeConverter->convert($value, 0, $this->mpdf->default_font_size / Mpdf::SCALE);
	}
}
