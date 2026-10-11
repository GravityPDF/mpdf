<?php

namespace Mpdf\Css;

/**
 * A background longhand that lists one value per layer, as the standard CSS mode reads `background-image: url(a.png),
 * linear-gradient(red, blue)`. The list is kept in one string so it cascades like any other value, joined with a
 * character no CSS value holds: a layer's image can itself hold commas, in a data URI or a gradient's stops. A list of
 * one is the value itself.
 */
final class BackgroundLayers
{

	/**
	 * Separates the layers' values
	 */
	const SEPARATOR = "\x1F";

	/**
	 * Joins the layers' values into one property value
	 *
	 * @param string[] $values One value per layer, the top layer first
	 * @return string
	 */
	public static function join(array $values)
	{
		return implode(self::SEPARATOR, $values);
	}

	/**
	 * Splits a property value into its layers' values
	 *
	 * @param string $value
	 * @return string[] The top layer's value first
	 */
	public static function split($value)
	{
		return explode(self::SEPARATOR, (string) $value);
	}

	/**
	 * The value a layer takes. A list shorter than the list of images repeats, as CSS has it
	 *
	 * @param string $value
	 * @param int $layer The layer's index, the top layer 0
	 * @return string
	 */
	public static function valueFor($value, $layer)
	{
		$values = self::split($value);

		return $values[$layer % count($values)];
	}

	/**
	 * The value the bottom layer takes, the one the background colour is drawn with
	 *
	 * @param string $value
	 * @param string $images The background-image list, which gives the number of layers
	 * @return string
	 */
	public static function bottomValue($value, $images)
	{
		return self::valueFor($value, count(self::split($images)) - 1);
	}

}
