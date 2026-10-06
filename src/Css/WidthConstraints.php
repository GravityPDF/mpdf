<?php

namespace Mpdf\Css;

use Mpdf\SizeConverter;

/**
 * The min-width and max-width of a block, resolved against its containing block, and the clamp they put on its width
 */
final class WidthConstraints
{

	/**
	 * @var float
	 */
	private $min = 0.0;

	/**
	 * @var float|null None where max-width is not set
	 */
	private $max = null;

	/**
	 * @param SizeConverter $sizeConverter
	 * @param array $properties The block's merged CSS, by upper-cased property
	 * @param float $containerWidth The inner width of the containing block, which a percentage is taken of
	 * @param float $fontSize The block's font size in mm, which an em is taken of
	 */
	public function __construct(SizeConverter $sizeConverter, array $properties, $containerWidth, $fontSize)
	{
		$max = $this->length($sizeConverter, $properties, 'MAX-WIDTH', $containerWidth, $fontSize);
		if ($max !== null) {
			$this->max = $max;
		}
		$min = $this->length($sizeConverter, $properties, 'MIN-WIDTH', $containerWidth, $fontSize);
		if ($min !== null) {
			$this->min = $min;
		}
	}

	/**
	 * Whether either limit is set
	 *
	 * @return bool
	 */
	public function any()
	{
		return $this->max !== null || $this->min > 0;
	}

	/**
	 * $width held within the limits: max-width first, then min-width, which wins where the two conflict (CSS 2.1 10.4)
	 *
	 * @param float $width
	 *
	 * @return float
	 */
	public function clamp($width)
	{
		if ($this->max !== null && $width > $this->max) {
			$width = $this->max;
		}
		if ($width < $this->min) {
			$width = $this->min;
		}

		return $width;
	}

	/**
	 * The length $property gives, in mm, or null where it is not set or is a keyword such as none or auto
	 *
	 * @param SizeConverter $sizeConverter
	 * @param array $properties
	 * @param string $property
	 * @param float $containerWidth
	 * @param float $fontSize
	 *
	 * @return float|null
	 */
	private function length(SizeConverter $sizeConverter, array $properties, $property, $containerWidth, $fontSize)
	{
		if (!isset($properties[$property]) || !preg_match('/^[\d.]/', trim($properties[$property]))) {
			return null;
		}

		return max(0, $sizeConverter->convert($properties[$property], $containerWidth, $fontSize, false));
	}
}
