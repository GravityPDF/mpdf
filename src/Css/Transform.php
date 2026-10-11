<?php

namespace Mpdf\Css;

use Mpdf\Fonts\Color\Geometry;
use Mpdf\SizeConverter;

/**
 * The matrix a CSS transform and transform-origin give a box.
 *
 * A matrix is [a, b, c, d, e, f], which takes the point (x, y) to (a x + c y + e, b x + d y + f), in the page's own
 * space: millimetres, with y running down the page as it does in CSS.
 */
final class Transform
{

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var float
	 */
	private $fontSize;

	/**
	 * @param SizeConverter $sizeConverter
	 * @param float $fontSize The element's font size in mm, which an em is taken of
	 */
	public function __construct(SizeConverter $sizeConverter, $fontSize)
	{
		$this->sizeConverter = $sizeConverter;
		$this->fontSize = $fontSize;
	}

	/**
	 * The matrix $transform draws the box with, about the point $origin names in it. A percentage of translate() or
	 * of the origin is taken of the box's width or height.
	 *
	 * @param string $transform A list of transform functions
	 * @param string $origin The transform-origin, empty for its initial value, the centre
	 * @param float $x The box's left edge
	 * @param float $y The box's top edge
	 * @param float $w The box's width
	 * @param float $h The box's height
	 *
	 * @return float[]|null Null for none, and for a list CSS would reject: a function unknown or with a bad argument
	 */
	public function matrix($transform, $origin, $x, $y, $w, $h)
	{
		$transform = trim($transform);
		if ($transform === '' || strtolower($transform) === 'none') {
			return null;
		}

		if (!preg_match('/^(?:[a-z]+\([^)]*\)\s*)+$/i', $transform)) {
			return null;
		}

		preg_match_all('/([a-z]+)\(([^)]*)\)/i', $transform, $functions, PREG_SET_ORDER);

		// The last function in the list is applied first
		$m = Geometry::IDENTITY;
		foreach ($functions as $function) {
			$step = $this->step(strtolower($function[1]), preg_split('/\s*,\s*|\s+/', trim($function[2])), $w, $h);
			if ($step === null) {
				return null;
			}
			$m = Geometry::multiply($step, $m);
		}

		list($ox, $oy) = $this->origin($origin, $w, $h);
		$ox += $x;
		$oy += $y;

		return Geometry::multiply(Geometry::multiply([1, 0, 0, 1, -$ox, -$oy], $m), [1, 0, 0, 1, $ox, $oy]);
	}

	/**
	 * The matrix of one transform function, or null where CSS would reject it
	 *
	 * @param string $name
	 * @param string[] $args
	 * @param float $w
	 * @param float $h
	 *
	 * @return float[]|null
	 */
	private function step($name, array $args, $w, $h)
	{
		$count = count($args);

		switch ($name) {
			case 'translate':
				if ($count > 2) {
					return null;
				}
				return [1, 0, 0, 1, $this->length($args[0], $w), $count === 2 ? $this->length($args[1], $h) : 0];
			case 'translatex':
				return $count === 1 ? [1, 0, 0, 1, $this->length($args[0], $w), 0] : null;
			case 'translatey':
				return $count === 1 ? [1, 0, 0, 1, 0, $this->length($args[0], $h)] : null;
			case 'scale':
				$sx = $this->factor($args[0]);
				$sy = $count === 2 ? $this->factor($args[1]) : $sx;
				return $count <= 2 && $sx !== null && $sy !== null ? [$sx, 0, 0, $sy, 0, 0] : null;
			case 'scalex':
				$sx = $this->factor($args[0]);
				return $count === 1 && $sx !== null ? [$sx, 0, 0, 1, 0, 0] : null;
			case 'scaley':
				$sy = $this->factor($args[0]);
				return $count === 1 && $sy !== null ? [1, 0, 0, $sy, 0, 0] : null;
			case 'rotate':
				$a = $this->angle($args[0]);
				return $count === 1 && $a !== null ? [cos($a), sin($a), -sin($a), cos($a), 0, 0] : null;
			case 'skew':
				$ax = $this->angle($args[0]);
				$ay = $count === 2 ? $this->angle($args[1]) : 0;
				return $count <= 2 && $ax !== null && $ay !== null ? [1, tan($ay), tan($ax), 1, 0, 0] : null;
			case 'skewx':
				$a = $this->angle($args[0]);
				return $count === 1 && $a !== null ? [1, 0, tan($a), 1, 0, 0] : null;
			case 'skewy':
				$a = $this->angle($args[0]);
				return $count === 1 && $a !== null ? [1, tan($a), 0, 1, 0, 0] : null;
		}

		return null;
	}

	/**
	 * The point transform-origin names, from the box's top left corner
	 *
	 * @param string $origin
	 * @param float $w
	 * @param float $h
	 *
	 * @return float[]
	 */
	private function origin($origin, $w, $h)
	{
		$tokens = preg_split('/\s+/', strtolower(trim($origin)));
		if ($tokens[0] === '') {
			return [$w / 2, $h / 2];
		}

		if (count($tokens) === 1) {
			$tokens = in_array($tokens[0], ['top', 'bottom'], true) ? ['center', $tokens[0]] : [$tokens[0], 'center'];
		} elseif (in_array($tokens[0], ['top', 'bottom'], true) || in_array($tokens[1], ['left', 'right'], true)) {
			$tokens = [$tokens[1], $tokens[0]];
		}

		$keywords = ['left' => '0%', 'top' => '0%', 'center' => '50%', 'right' => '100%', 'bottom' => '100%'];
		$ox = isset($keywords[$tokens[0]]) ? $keywords[$tokens[0]] : $tokens[0];
		$oy = isset($keywords[$tokens[1]]) ? $keywords[$tokens[1]] : $tokens[1];

		return [$this->length($ox, $w), $this->length($oy, $h)];
	}

	/**
	 * A length in mm, a percentage taken of $reference
	 *
	 * @param string $value
	 * @param float $reference
	 *
	 * @return float
	 */
	private function length($value, $reference)
	{
		return (float) $this->sizeConverter->convert($value, $reference, $this->fontSize, false);
	}

	/**
	 * A scale factor, written as a number or a percentage, or null where it is neither
	 *
	 * @param string $value
	 *
	 * @return float|null
	 */
	private function factor($value)
	{
		if (is_numeric($value)) {
			return (float) $value;
		}

		if (preg_match('/^([-+]?[0-9]*\.?[0-9]+)%$/', $value, $m)) {
			return (float) $m[1] / 100;
		}

		return null;
	}

	/**
	 * An angle in radians, or null where it is not one. Only a zero may leave out its unit.
	 *
	 * @param string $value
	 *
	 * @return float|null
	 */
	private function angle($value)
	{
		if (!preg_match('/^([-+]?[0-9]*\.?[0-9]+)(deg|grad|rad|turn)?$/i', $value, $m)) {
			return null;
		}

		$unit = isset($m[2]) ? strtolower($m[2]) : '';
		$perTurn = ['deg' => 360, 'grad' => 400, 'rad' => 2 * M_PI, 'turn' => 1];

		if ($unit === '') {
			return (float) $m[1] == 0 ? 0.0 : null;
		}

		return (float) $m[1] / $perTurn[$unit] * 2 * M_PI;
	}
}
