<?php

namespace Mpdf;

/**
 * Approximates a blurred text-shadow, which PDF cannot draw, as a stack of translucent copies of the text, each
 * stroked a little wider than the last. Where the copies overlap their opacities compound, so a point that only the
 * widest copies reach is fainter than one inside the glyph that every copy covers.
 *
 * CSS blurs a shadow with a Gaussian whose standard deviation is half the blur radius. Outside a straight edge that
 * leaves an opacity of half the shadow's alpha at the edge, falling to near nothing at the blur radius. The layers are
 * picked to follow that fall-off in bands. A glyph's interior stays at the opacity of the edge, as the stroke can only
 * grow the glyph, never thin it.
 */
final class TextShadowBlur
{

	/**
	 * The widest a band may be, in mm: about a pixel at 100 DPI, narrow enough that the steps do not show
	 */
	const BAND = 0.25;

	/**
	 * The most layers one shadow is drawn with, as each repeats the text in the page's content stream
	 */
	const MAX_LAYERS = 8;

	/**
	 * The stroked copies that draw a text-shadow blurred by the given radius, widest first
	 *
	 * @param float $blur The blur radius, in mm
	 * @param float $alpha The shadow colour's own alpha, from 0 to 1
	 *
	 * @return array[] Each ['width' => the stroke width in mm, 'alpha' => the copy's alpha, from 0 to 1]
	 */
	public static function layers($blur, $alpha)
	{
		$count = (int) min(self::MAX_LAYERS, max(2, ceil($blur / self::BAND)));

		// A band is covered by its own layer and every wider one, so each layer adds what the wider ones leave short of
		// the opacity the band should have, sampled where it starts: band 1 reaches from the glyph's edge outwards
		$layers = [];
		$outer = 0;
		for ($band = $count; $band >= 1; $band--) {
			$opacity = $alpha * self::edgeOpacity(($band - 1) / $count);
			$layers[] = [
				'width' => 2 * $blur * $band / $count,
				'alpha' => 1 - (1 - $opacity) / (1 - $outer),
			];
			$outer = $opacity;
		}

		return $layers;
	}

	/**
	 * How opaque a Gaussian blur leaves the outside of a straight edge, at a distance from it measured in blur radii
	 *
	 * @param float $distance 0 at the edge, 1 at the blur radius
	 *
	 * @return float From 0.5 at the edge down towards 0
	 */
	private static function edgeOpacity($distance)
	{
		// The standard deviation is half the blur radius, so this is 0.5 * erfc(distance / (sigma * sqrt(2)))
		return 0.5 * self::erfc(M_SQRT2 * $distance);
	}

	/**
	 * The complementary error function for x >= 0, to within 1.5e-7 (Abramowitz and Stegun 7.1.26)
	 *
	 * @param float $x
	 *
	 * @return float
	 */
	private static function erfc($x)
	{
		$t = 1 / (1 + 0.3275911 * $x);
		$poly = $t * (0.254829592 + $t * (-0.284496736 + $t * (1.421413741 + $t * (-1.453152027 + $t * 1.061405429))));

		return $poly * exp(-$x * $x);
	}
}
