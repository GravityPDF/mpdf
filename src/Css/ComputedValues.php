<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;

/**
 * An element's computed values, as each frame on the stack of open elements carries them under CssMode::STANDARD.
 *
 * An element starts from the computed values its parent's frame holds of the properties InheritedProperties names. The
 * cascade and the CSS-wide keywords then give its own values, and compute() makes them computed values:
 *
 * - font-size is absolute: a percentage, em, ex, larger or smaller is taken of the parent's size, rem of html's, and a
 *   keyword such as large of the document's default size;
 * - font-weight is a number: bolder and lighter step from the parent's weight, as RelativeFontValues gives;
 * - line-height stays a number or normal where it was given as one, and a length or percentage is made the absolute
 *   length it stands for at the element's own font size;
 * - every other length given in em, ex, ch or rem is made absolute at the element's own font size, so that a
 *   descendant that inherits it, or takes it through inherit, gets the length and not the unit;
 * - percentages other than those of font-size and line-height stay percentages, taken of whatever the element that
 *   uses them is in.
 *
 * A property the frames do not hold has its initial value, which is the state an element is drawn in before its own
 * CSS is applied. So the frames stay small, and an element that sets nothing hands on what it was handed.
 *
 * Custom properties and var() resolve at computed-value time, so they belong here once mPDF reads them (#595).
 */
final class ComputedValues
{

	/**
	 * A length in a unit taken of a font size
	 */
	const FONT_RELATIVE_LENGTH = '/(?<![\w.#-])([-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[-+]?\d+)?)(em|ex|ch|rem)\b/i';

	/**
	 * A line-height of zero, in any unit or none, which is drawn as no height rather than as normal
	 */
	const ZERO_LINE_HEIGHT = '/^[0.]*0(?:[a-z]+|%)?$/i';

	/**
	 * Absolute units a font size may be given in, which are kept as they are written
	 */
	const ABSOLUTE_FONT_SIZE = '/^[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:mm|cm|q|in|pt|pc|px)$/i';

	/**
	 * Properties other than font-size and line-height whose lengths may be given in a unit taken of a font size
	 */
	const LENGTHS = [
		'LETTER-SPACING' => true,
		'WORD-SPACING' => true,
		'TEXT-INDENT' => true,
		'TEXT-SHADOW' => true,
		'TEXT-OUTLINE-WIDTH' => true,
		'PADDING-TOP' => true,
		'PADDING-RIGHT' => true,
		'PADDING-BOTTOM' => true,
		'PADDING-LEFT' => true,
		'MARGIN-TOP' => true,
		'MARGIN-RIGHT' => true,
		'MARGIN-BOTTOM' => true,
		'MARGIN-LEFT' => true,
		'BORDER-TOP' => true,
		'BORDER-RIGHT' => true,
		'BORDER-BOTTOM' => true,
		'BORDER-LEFT' => true,
		'BORDER-TOP-WIDTH' => true,
		'BORDER-RIGHT-WIDTH' => true,
		'BORDER-BOTTOM-WIDTH' => true,
		'BORDER-LEFT-WIDTH' => true,
		'BORDER-TOP-LEFT-RADIUS-H' => true,
		'BORDER-TOP-LEFT-RADIUS-V' => true,
		'BORDER-TOP-RIGHT-RADIUS-H' => true,
		'BORDER-TOP-RIGHT-RADIUS-V' => true,
		'BORDER-BOTTOM-LEFT-RADIUS-H' => true,
		'BORDER-BOTTOM-LEFT-RADIUS-V' => true,
		'BORDER-BOTTOM-RIGHT-RADIUS-H' => true,
		'BORDER-BOTTOM-RIGHT-RADIUS-V' => true,
		'BORDER-SPACING-H' => true,
		'BORDER-SPACING-V' => true,
		'BOX-SHADOW' => true,
		'WIDTH' => true,
		'HEIGHT' => true,
		'MIN-WIDTH' => true,
		'MAX-WIDTH' => true,
		'MIN-HEIGHT' => true,
		'MAX-HEIGHT' => true,
		'TOP' => true,
		'RIGHT' => true,
		'BOTTOM' => true,
		'LEFT' => true,
	];

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var array|null The computed values inheritedFrom() was last given
	 */
	private $lastParent;

	/**
	 * @var array What inheritedFrom() gave for them
	 */
	private $lastInherited = [];

	/**
	 * @var array Absolute font sizes read so far, in mm, by how they are written, or null for one that is not a size
	 */
	private $sizes = [];

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
	 * @param array $parent The computed values on the parent's frame
	 *
	 * @return array What an element inherits: the values of the inherited properties its parent's frame holds
	 */
	public function inheritedFrom(array $parent)
	{
		// Siblings are merged one after another, so the parent is usually the one seen last
		if ($parent !== $this->lastParent) {
			$this->lastParent = $parent;
			$this->lastInherited = InheritedProperties::of($parent, InheritedProperties::names());
		}

		return $this->lastInherited;
	}

	/**
	 * @param array $properties The element's properties once the cascade and the CSS-wide keywords have been applied,
	 *                          over what it inherits
	 * @param array $parent The computed values on the parent's frame
	 * @param array|null $own The element's own declarations, keyed by property, or null when all of $properties are.
	 *                        What it inherits is the parent's computed values already
	 * @param float|null $medium The size, in pt, a keyword such as large is taken of, or null for the default font size
	 *
	 * @return array The element's computed values
	 */
	public function compute(array $properties, array $parent, $own = null, $medium = null)
	{
		if ($own === null) {
			$own = $properties;
		}

		$font = $this->languageFont($properties);
		if ($font !== null) {
			$properties['FONT-FAMILY'] = $font;
		}

		$parentSize = $this->fontSize($parent, $this->mpdf->default_font_size / Mpdf::SCALE);
		$fontSize = $parentSize;
		if (isset($own['FONT-SIZE'], $properties['FONT-SIZE'])) {
			$size = $this->computeFontSize($properties['FONT-SIZE'], $parentSize, $medium === null ? $this->mpdf->default_font_size : $medium);
			// A value that is not a size, such as a form field's auto, is left for the element to read
			if ($size !== null) {
				$properties['FONT-SIZE'] = $size;
			}
			$fontSize = $this->fontSize($properties, $parentSize);
		}
		if (isset($own['FONT-WEIGHT'], $properties['FONT-WEIGHT'])) {
			$parentWeight = isset($parent['FONT-WEIGHT']) ? $parent['FONT-WEIGHT'] : RelativeFontValues::NORMAL_WEIGHT;
			$weight = RelativeFontValues::weight($properties['FONT-WEIGHT'], $parentWeight);
			if ($weight !== null) {
				$properties['FONT-WEIGHT'] = (string) $weight;
			} elseif (isset($parent['FONT-WEIGHT'])) {
				// A value that is not a weight leaves the parent's
				$properties['FONT-WEIGHT'] = $parent['FONT-WEIGHT'];
			} else {
				unset($properties['FONT-WEIGHT']);
			}
		}

		if (isset($own['LINE-HEIGHT'], $properties['LINE-HEIGHT'])) {
			$properties['LINE-HEIGHT'] = $this->computeLineHeight($properties['LINE-HEIGHT'], $fontSize);
		}

		foreach (array_intersect_key($own, self::LENGTHS) as $property => $declared) {
			$value = isset($properties[$property]) ? $properties[$property] : null;
			if (is_string($value) && strpbrk($value, 'eEhH') !== false) {
				$properties[$property] = $this->absoluteLengths($value, $fontSize);
			}
		}

		return $properties;
	}


	/**
	 * The font mPDF chooses for an element's language under autoLangToFont, which Mpdf::setCSS() draws the element in,
	 * so that what the element holds is handed on
	 *
	 * @param array $properties
	 *
	 * @return string|null The font, or null where the language chooses none
	 */
	private function languageFont(array $properties)
	{
		return empty($properties['LANG']) ? null : $this->mpdf->fontForLanguage($properties['LANG']);
	}

	/**
	 * @param array $computed Computed values, as compute() gives them
	 * @param float $default In mm
	 *
	 * @return float The font size they give, in mm, or the default when they give none
	 */
	private function fontSize(array $computed, $default)
	{
		if (!isset($computed['FONT-SIZE'])) {
			return $default;
		}

		// The same few sizes are read for every element
		$size = $computed['FONT-SIZE'];
		if (!array_key_exists($size, $this->sizes)) {
			$this->sizes[$size] = preg_match(self::ABSOLUTE_FONT_SIZE, $size) ? $this->sizeConverter->convert($size) : null;
		}

		return $this->sizes[$size] === null ? $default : $this->sizes[$size];
	}

	/**
	 * @param string $value
	 * @param float $parentSize In mm
	 * @param float $medium The size a keyword is taken of, in pt
	 *
	 * @return string|null The absolute size, or null for a value that is not a font size
	 */
	private function computeFontSize($value, $parentSize, $medium)
	{
		$value = trim($value);
		if (preg_match(self::ABSOLUTE_FONT_SIZE, $value)) {
			return $value;
		}

		$size = $this->sizeConverter->convertFontSize($value, $parentSize, $medium);

		// Written so that it reads back as the same number
		return $size === null ? null : var_export($size, true) . 'pt';
	}

	/**
	 * @param string|float $value
	 * @param float $fontSize The element's font size, in mm
	 *
	 * @return string|float A number or normal as given, or else the length it stands for, in mm
	 */
	private function computeLineHeight($value, $fontSize)
	{
		$value = trim($value);
		if (preg_match(self::ZERO_LINE_HEIGHT, $value)) {
			return '0mm';
		}

		if (preg_match('/^[0-9.,]*$/', $value) || strtoupper($value) === 'NORMAL' || $value === 'N') {
			return $value;
		}

		$length = $this->sizeConverter->convert($value, $fontSize, $fontSize, true);

		return $length ? $length . 'mm' : $value;
	}

	/**
	 * @param string $value
	 * @param float $fontSize In mm
	 *
	 * @return string The value with each length in a unit taken of a font size made absolute, in mm
	 */
	private function absoluteLengths($value, $fontSize)
	{
		$sizeConverter = $this->sizeConverter;

		return preg_replace_callback(self::FONT_RELATIVE_LENGTH, function ($m) use ($sizeConverter, $fontSize) {
			return $sizeConverter->convert($m[1] . $m[2], $fontSize, $fontSize) . 'mm';
		}, $value);
	}
}
