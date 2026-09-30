<?php

namespace Mpdf\Css;

/**
 * The CSS-wide keywords, which every property takes, and what resolving them needs to know of each property mPDF
 * reads: whether it is inherited, and its initial value. CssMerger resolves them once an element's CSS is merged,
 * under the standard CSS mode.
 *
 * Properties are named as the normalised declarations name them: uppercased, with each shorthand expanded into the
 * longhands mPDF reads, such as BORDER-TOP and its WIDTH, STYLE and COLOR parts, BORDER-SPACING-H and
 * BORDER-TOP-LEFT-RADIUS-H.
 */
final class CssWideKeywords
{

	/**
	 * @var array<string, true> The keywords, as keys
	 */
	private static $keywords = ['inherit' => true, 'initial' => true, 'unset' => true, 'revert' => true, 'revert-layer' => true];

	/**
	 * @var array<string, true> The inherited properties mPDF reads that are not among InheritedProperties::names(),
	 * as no channel hands them on
	 */
	private static $otherInherited = [
		'BORDER-COLLAPSE' => true,
		'BORDER-SPACING-H' => true,
		'BORDER-SPACING-V' => true,
		'CAPTION-SIDE' => true,
		'EMPTY-CELLS' => true,
		'IMAGE-ORIENTATION' => true,
		'IMAGE-RENDERING' => true,
		'VISIBILITY' => true,
		'WHITE-SPACE' => true,
	];

	/**
	 * @var array<string, true>|null Every inherited property mPDF reads, as keys, built on first use
	 */
	private static $inherited;

	/**
	 * @var array<string, string> The initial value of each property mPDF reads, written as mPDF reads it. A property
	 * not listed is left unset, which mPDF reads as its initial value: auto for the sizes and offsets, none for float,
	 * clear and transform, and nothing for mPDF's own properties. FONT-FAMILY and FONT-SIZE take the document's default
	 * font and size, which CssMerger supplies
	 */
	private static $initial = [
		'BACKGROUND-CLIP' => 'border-box',
		'BACKGROUND-COLOR' => 'transparent',
		'BACKGROUND-IMAGE' => '',
		'BACKGROUND-ORIGIN' => 'padding-box',
		'BACKGROUND-POSITION' => '0% 0%',
		'BACKGROUND-REPEAT' => 'repeat',
		'BACKGROUND-SIZE' => 'auto',
		'BORDER-BOTTOM' => 'medium none currentcolor',
		'BORDER-BOTTOM-COLOR' => 'currentcolor',
		'BORDER-BOTTOM-LEFT-RADIUS-H' => '0',
		'BORDER-BOTTOM-LEFT-RADIUS-V' => '0',
		'BORDER-BOTTOM-RIGHT-RADIUS-H' => '0',
		'BORDER-BOTTOM-RIGHT-RADIUS-V' => '0',
		'BORDER-BOTTOM-STYLE' => 'none',
		'BORDER-BOTTOM-WIDTH' => 'medium',
		'BORDER-COLLAPSE' => 'separate',
		'BORDER-LEFT' => 'medium none currentcolor',
		'BORDER-LEFT-COLOR' => 'currentcolor',
		'BORDER-LEFT-STYLE' => 'none',
		'BORDER-LEFT-WIDTH' => 'medium',
		'BORDER-RIGHT' => 'medium none currentcolor',
		'BORDER-RIGHT-COLOR' => 'currentcolor',
		'BORDER-RIGHT-STYLE' => 'none',
		'BORDER-RIGHT-WIDTH' => 'medium',
		'BORDER-SPACING-H' => '0',
		'BORDER-SPACING-V' => '0',
		'BORDER-TOP' => 'medium none currentcolor',
		'BORDER-TOP-COLOR' => 'currentcolor',
		'BORDER-TOP-LEFT-RADIUS-H' => '0',
		'BORDER-TOP-LEFT-RADIUS-V' => '0',
		'BORDER-TOP-RIGHT-RADIUS-H' => '0',
		'BORDER-TOP-RIGHT-RADIUS-V' => '0',
		'BORDER-TOP-STYLE' => 'none',
		'BORDER-TOP-WIDTH' => 'medium',
		'BOX-DECORATION-BREAK' => 'slice',
		'BOX-SHADOW' => 'none',
		'CAPTION-SIDE' => 'top',
		'COLOR' => '#000000',
		'DIRECTION' => 'ltr',
		'DISPLAY' => 'inline',
		'EMPTY-CELLS' => 'show',
		'FONT-FEATURE-SETTINGS' => 'normal',
		'FONT-KERNING' => 'auto',
		'FONT-LANGUAGE-OVERRIDE' => 'normal',
		'FONT-STYLE' => 'normal',
		'FONT-VARIANT-ALTERNATES' => 'normal',
		'FONT-VARIANT-CAPS' => 'normal',
		'FONT-VARIANT-LIGATURES' => 'normal',
		'FONT-VARIANT-NUMERIC' => 'normal',
		'FONT-VARIANT-POSITION' => 'normal',
		'FONT-WEIGHT' => 'normal',
		'HYPHENS' => 'manual',
		'IMAGE-ORIENTATION' => '0',
		'IMAGE-RENDERING' => 'auto',
		'LETTER-SPACING' => 'normal',
		'LINE-HEIGHT' => 'normal',
		'LINE-STACKING-SHIFT' => 'consider-shifts',
		'LINE-STACKING-STRATEGY' => 'inline-line-height',
		'LIST-STYLE-IMAGE' => 'none',
		'LIST-STYLE-POSITION' => 'outside',
		'LIST-STYLE-TYPE' => 'disc',
		'MARGIN-BOTTOM' => '0',
		'MARGIN-LEFT' => '0',
		'MARGIN-RIGHT' => '0',
		'MARGIN-TOP' => '0',
		'OPACITY' => '1',
		'OVERFLOW' => 'visible',
		'PADDING-BOTTOM' => '0',
		'PADDING-LEFT' => '0',
		'PADDING-RIGHT' => '0',
		'PADDING-TOP' => '0',
		'PAGE-BREAK-AFTER' => 'auto',
		'PAGE-BREAK-BEFORE' => 'auto',
		'PAGE-BREAK-INSIDE' => 'auto',
		'POSITION' => 'static',
		'TEXT-ALIGN' => 'start',
		'TEXT-DECORATION' => 'none',
		'TEXT-INDENT' => '0',
		'TEXT-OUTLINE' => 'none',
		'TEXT-SHADOW' => 'none',
		'TEXT-TRANSFORM' => 'none',
		'VERTICAL-ALIGN' => 'baseline',
		'VISIBILITY' => 'visible',
		'WHITE-SPACE' => 'normal',
		'WORD-SPACING' => 'normal',
		'Z-INDEX' => 'auto',
	];

	/**
	 * @param mixed $value A declared value
	 *
	 * @return string|null The CSS-wide keyword the value is, lowercased, or null if it is not one
	 */
	public static function keywordOf($value)
	{
		if (!is_string($value)) {
			return null;
		}

		$value = strtolower(trim($value));

		return isset(self::$keywords[$value]) ? $value : null;
	}

	/**
	 * @param string $property Uppercased
	 *
	 * @return bool Whether the property is inherited
	 */
	public static function isInherited($property)
	{
		if (self::$inherited === null) {
			self::$inherited = array_fill_keys(InheritedProperties::names(), true) + self::$otherInherited;
		}

		return isset(self::$inherited[$property]);
	}

	/**
	 * @param string $property Uppercased
	 *
	 * @return string|null The property's initial value, as mPDF reads it, or null to leave the property unset
	 */
	public static function initialValue($property)
	{
		return isset(self::$initial[$property]) ? self::$initial[$property] : null;
	}
}
