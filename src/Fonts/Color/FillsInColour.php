<?php

namespace Mpdf\Fonts\Color;

/**
 * Filling a path in a colour of a COLR font's palette, the way each version draws a layer
 */
trait FillsInColour
{

	/**
	 * @param array          $colour    As ColorFontFile::colour() gives it
	 * @param string         $fill      The path and the operator filling it
	 * @param GlyphResources $resources Where a colour less than opaque registers its graphics state
	 *
	 * @return string The fill in the colour, which sets its colour inside q and Q so what follows is
	 *                drawn in whatever colour it would have been, or nothing where the colour is
	 *                transparent
	 */
	private function filled(array $colour, $fill, GlyphResources $resources)
	{
		list($rgb, $alpha) = $colour;
		if ($alpha <= 0) {
			return '';
		}

		$operators = [];
		if ($alpha < 1) {
			$operators[] = $resources->alpha($alpha);
		}
		if ($rgb !== null) {
			$operators[] = $resources->rgb($rgb);
		}

		return $operators ? 'q ' . implode(' ', $operators) . "\n" . $fill . "\nQ\n" : $fill . "\n";
	}
}
