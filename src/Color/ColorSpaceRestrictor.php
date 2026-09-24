<?php

namespace Mpdf\Color;

use Mpdf\Mpdf;

class ColorSpaceRestrictor
{

	const RESTRICT_TO_GRAYSCALE = 1;

	const RESTRICT_TO_RGB_SPOT_GRAYSCALE = 2;

	const RESTRICT_TO_CMYK_SPOT_GRAYSCALE = 3;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Color\ColorModeConverter
	 */
	private $colorModeConverter;

	/**
	 * @var int
	 */
	private $mode;

	/**
	 * Process $mode settings
	 *     1 - allow GRAYSCALE only [convert CMYK/RGB->gray]
	 *     2 - allow RGB / SPOT COLOR / Grayscale [convert CMYK->RGB]
	 *     3 - allow CMYK / SPOT COLOR / Grayscale [convert RGB->CMYK]
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param \Mpdf\Color\ColorModeConverter $colorModeConverter
	 * @param int $mode
	 */
	public function __construct(Mpdf $mpdf, ColorModeConverter $colorModeConverter)
	{
		$this->mpdf = $mpdf;
		$this->colorModeConverter = $colorModeConverter;
	}

	/**
	 * @param mixed $c
	 * @param string $color
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]|mixed
	 */
	public function restrictColorSpace($c, $color, &$PDFAXwarnings = [])
	{
		if (!is_array($c)) {
			return $c;
		}

		$mode = (int) $c[0];
		switch ($mode) {
			case 1:
				return $c;
			case 2:
				return $this->restrictSpotColorSpace($c, $PDFAXwarnings);
			case 3:
				return $this->restrictRgbColorSpace($c, $color, $PDFAXwarnings);
			case 4:
				return $this->restrictCmykColorSpace($c, $color, $PDFAXwarnings);
			case 5:
				return $this->restrictRgbaColorSpace($c, $color, $PDFAXwarnings);
			case 6:
				return $this->restrictCmykaColorSpace($c, $color, $PDFAXwarnings);
		}

		return $c;
	}

	/**
	 * @param string $c
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]
	 */
	private function restrictSpotColorSpace($c, &$PDFAXwarnings = [])
	{
		if (!isset($this->mpdf->spotColorIDs[$c[1]])) {
				throw new \Mpdf\MpdfException('Error: Spot colour has not been defined - ' . $this->mpdf->spotColorIDs[$c[1]]);
		}

		if ($this->mpdf->PDFA) {
			if ($this->mpdf->PDFA && !$this->mpdf->PDFAauto) {
				$PDFAXwarnings[] = "Spot color specified '" . $this->mpdf->spotColorIDs[$c[1]] . "' (converted to process color)";
			}
			if ($this->mpdf->restrictColorSpace != 3) {
				$sp = $this->mpdf->spotColors[$this->mpdf->spotColorIDs[$c[1]]];
				$c = $this->colorModeConverter->cmyk2rgb([4, $sp['c'], $sp['m'], $sp['y'], $sp['k']]);
			}
		} elseif ($this->mpdf->restrictColorSpace == 1) {
			$sp = $this->mpdf->spotColors[$this->mpdf->spotColorIDs[$c[1]]];
			$c = $this->colorModeConverter->cmyk2gray([4, $sp['c'], $sp['m'], $sp['y'], $sp['k']]);
		}

		return $c;
	}

	/**
	 * @param mixed $c
	 * @param string $color
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]
	 */
	private function restrictRgbColorSpace($c, $color, &$PDFAXwarnings = [])
	{
		// PDF/X-4 keeps RGB as it is: printing to an RGB condition it is DeviceRGB, and printing to any
		// other it is written in the ICC-based sRGB colour space, so that the press converts the colours
		// of the content and of the images together - see Mpdf::writesCalibratedRgb()
		if ($this->mpdf->isPdfx4()) {
			if ($this->mpdf->pdfxRgbIntent()) {
				return $c;
			}

			$gray = $this->neutralToGray($c);

			return $gray === null ? $c : $gray;
		}

		if (($this->mpdf->PDFX && !$this->mpdf->pdfxRgbIntent()) || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace == 3)) {
			if (($this->mpdf->PDFA && !$this->mpdf->PDFAauto) || ($this->mpdf->PDFX && !$this->mpdf->PDFXauto)) {
				$PDFAXwarnings[] = "RGB color specified '" . $color . "' (converted to CMYK)";
			}
			$c = $this->colorModeConverter->rgb2cmyk($c);
		} elseif ($this->mpdf->restrictColorSpace == 1) {
			$c = $this->colorModeConverter->rgb2gray($c);
		} elseif ($this->mpdf->restrictColorSpace == 3) {
			$c = $this->colorModeConverter->rgb2cmyk($c);
		}

		return $c;
	}

	/**
	 * @param mixed $c
	 * @param string $color
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]
	 */
	private function restrictCmykColorSpace($c, $color, &$PDFAXwarnings = [])
	{
		if (($this->mpdf->PDFA && $this->mpdf->restrictColorSpace != 3) || $this->mpdf->pdfxConvertsCmyk()) {
			if (($this->mpdf->PDFA && !$this->mpdf->PDFAauto) || ($this->mpdf->PDFX && !$this->mpdf->PDFXauto)) {
				$PDFAXwarnings[] = "CMYK color specified '" . $color . "' (converted to RGB)";
			}
			$c = $this->colorModeConverter->cmyk2rgb($c);
		} elseif ($this->mpdf->restrictColorSpace == 1) {
			$c = $this->colorModeConverter->cmyk2gray($c);
		} elseif ($this->mpdf->restrictColorSpace == 2) {
			$c = $this->colorModeConverter->cmyk2rgb($c);
		}

		return $c;
	}

	/**
	 * @param mixed $c
	 * @param string $color
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]
	 */
	private function restrictRgbaColorSpace($c, $color, &$PDFAXwarnings = [])
	{
		// PDF/X-4 keeps the transparency, which the colour space it settles on carries
		if ($this->mpdf->isPdfx4()) {
			return $this->restrictRgbColorSpace($c, $color, $PDFAXwarnings);
		}

		if ($this->mpdf->PDFX || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace == 3)) {
			if (($this->mpdf->PDFA && !$this->mpdf->PDFAauto) || ($this->mpdf->PDFX && !$this->mpdf->PDFXauto)) {
				$PDFAXwarnings[] = "RGB color with transparency specified '" . $color . "' (converted to CMYK" . ($this->mpdf->transparencyAllowed() ? ')' : ' without transparency)');
			}
			$c = $this->withAllowedAlpha($this->colorModeConverter->rgb2cmyk($c));
		} elseif ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace != 3) {
			// An opaque rgba() loses nothing by dropping its alpha
			if (!$this->mpdf->PDFAauto && !$this->mpdf->transparencyAllowed() && $c[4] < 100) {
				$PDFAXwarnings[] = "RGB color with transparency specified '" . $color . "' (converted to RGB without transparency)";
			}
			$c = $this->withAllowedAlpha($c);
		} elseif ($this->mpdf->restrictColorSpace == 1) {
			$c = $this->colorModeConverter->rgb2gray($c);
		} elseif ($this->mpdf->restrictColorSpace == 3) {
			$c = $this->colorModeConverter->rgb2cmyk($c);
		}

		return $c;
	}

	/**
	 * Tagging black sRGB would have the press make it out of all four inks, which fringes text where the
	 * plates are a hair out of register. A neutral colour goes to DeviceGray instead, which ISO 15930-7
	 * permits where the output condition is CMYK or grey, and which such a condition takes as its black
	 * separation. A colour that is neutral but translucent keeps its RGB, since DeviceGray carries no
	 * alpha for mPDF to put it back into.
	 *
	 * @param float[] $c A colour in RGB or RGBA
	 *
	 * @return float[]|null That colour in DeviceGray, or null where it is not neutral and opaque
	 */
	private function neutralToGray($c)
	{
		if ($c[1] != $c[2] || $c[2] != $c[3]) {
			return null;
		}

		if ($c[0] == 5 && $c[4] < 100) {
			return null;
		}

		return [1, $c[1]];
	}

	/**
	 * @param mixed $c
	 * @param string $color
	 * @param string[] $PDFAXwarnings
	 *
	 * @return float[]
	 */
	private function restrictCmykaColorSpace($c, $color, &$PDFAXwarnings = [])
	{
		if ($this->mpdf->isPdfx4()) {
			return $this->restrictCmykColorSpace($c, $color, $PDFAXwarnings);
		}

		if ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace != 3) {
			if (!$this->mpdf->PDFAauto) {
				$PDFAXwarnings[] = "CMYK color with transparency specified '" . $color . "' (converted to RGB" . ($this->mpdf->transparencyAllowed() ? ')' : ' without transparency)');
			}
			$c = $this->withAllowedAlpha($this->colorModeConverter->cmyk2rgb($c));
		} elseif ($this->mpdf->PDFX || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace == 3)) {
			if (!$this->mpdf->transparencyAllowed() && $c[5] < 100 && (($this->mpdf->PDFA && !$this->mpdf->PDFAauto) || ($this->mpdf->PDFX && !$this->mpdf->PDFXauto))) {
				$PDFAXwarnings[] = "CMYK color with transparency specified '" . $color . "' (converted to CMYK without transparency)";
			}
			$c = $this->withAllowedAlpha($c);
		} elseif ($this->mpdf->restrictColorSpace == 1) {
			$c = $this->colorModeConverter->cmyk2gray($c);
		} elseif ($this->mpdf->restrictColorSpace == 2) {
			$c = $this->colorModeConverter->cmyk2rgb($c);
		}

		return $c;
	}

	/**
	 * Drops the alpha from an rgba() or cmyka() colour, unless the document allows transparency
	 *
	 * @param mixed[] $c
	 *
	 * @return mixed[]
	 */
	private function withAllowedAlpha(array $c)
	{
		if ($this->mpdf->transparencyAllowed()) {
			return $c;
		}

		array_pop($c);
		$c[0] -= 2; // 5 (RGBa) to 3 (RGB), 6 (CMYKa) to 4 (CMYK)

		return $c;
	}

}
