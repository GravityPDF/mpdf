<?php

namespace Mpdf\Ua;

/**
 * The standard structure type each HTML element and mPDF table of contents class is tagged as
 * (ISO 32000-1 §14.8)
 *
 * @see StructureTree::addRoleMapping() for types outside the standard set
 */
class StructType
{

	/**
	 * Structure type of each uppercase HTML tag.
	 *
	 * <a> is a Link only when it has an href that is not blank; Tag\A decides that.
	 *
	 * @var array<string,string>
	 */
	private static $tagMap = [
		'P'          => 'P',
		'H1'         => 'H1', 'H2' => 'H2', 'H3' => 'H3',
		'H4'         => 'H4', 'H5' => 'H5', 'H6' => 'H6',
		'BLOCKQUOTE' => 'BlockQuote',
		'DIV'        => 'Div',
		'SPAN'       => 'Span',
		'A'          => 'Link',
		'UL'         => 'L',  'OL' => 'L',
		'LI'         => 'LI',
		// A definition list is tagged as a list, its terms as labels (Tagged PDF Best Practice Guide §4.2.3)
		'DL'         => 'L', 'DT' => 'Lbl', 'DD' => 'LBody',
		'TABLE'      => 'Table',
		'TR'         => 'TR', 'TD' => 'TD', 'TH' => 'TH',
		'THEAD'      => 'THead', 'TBODY' => 'TBody', 'TFOOT' => 'TFoot',
		'FIGURE'     => 'Figure', 'IMG' => 'Figure',
		'CAPTION'    => 'Caption',
		'FIGCAPTION' => 'Caption',
		'SECTION'    => 'Sect',
		'ARTICLE'    => 'Art',
		'NAV'        => 'Sect',
		'ASIDE'      => 'Sect',
		'MAIN'       => 'Div',
		'HEADER'     => 'Div',
		'FOOTER'     => 'Div',
		'ADDRESS'    => 'P',
		// The Form type belongs to each field, which Mpdf\Form tags, so <form> itself is only a Div
		'FIELDSET'   => 'Sect',
		'FORM'       => 'Div',
		// Tag\Legend opens no element yet: the legend is drawn into the fieldset's border
		'LEGEND'     => 'Caption',
		'PRE'        => 'Code',
		'CODE'       => 'Code',
		'Q'          => 'Quote',
		'STRONG'     => 'Span', 'B' => 'Span',
		'EM'         => 'Span', 'I' => 'Span',
		'ABBR'       => 'Span', 'ACRONYM' => 'Span',
		'SUB'        => 'Span', 'SUP' => 'Span',
		'MARK'       => 'Span', 'DEL' => 'Span',
		'INS'        => 'Span', 'S'   => 'Span',
		'SMALL'      => 'Span',
		// Ruby is tagged with its own types (ISO 32000-1 §14.8.5.6) though mPDF draws the annotation
		// inline. <rtc> has no type of its own: its <rt> children join the Ruby, and it is a Span only
		// when lang or aria-label needs an element to sit on.
		'RUBY'       => 'Ruby', 'RB' => 'RB', 'RT' => 'RT',
		'RP'         => 'RP', 'RTC' => 'Span',
	];

	/**
	 * Structure type of each class mPDF gives its table of contents. A key ending in '_' is a
	 * prefix, so every mpdf_toc_level_N is a TOCI.
	 *
	 * @var array<string,string>
	 */
	private static $tocClassMap = [
		'mpdf_toc'          => 'TOC',
		'mpdf_toc_level_'   => 'TOCI',
		// Link rather than Reference, as the entry carries a link annotation
		'mpdf_toc_a'        => 'Link',
		'mpdf_toc_p_level_' => 'Lbl',
	];

	/**
	 * The standard structure types (ISO 32000-1 §14.8); any other must be role mapped to one
	 *
	 * @var array<int,string>
	 */
	private static $validTypes = [
		'Document', 'Part', 'Art', 'Sect', 'Div', 'BlockQuote', 'Caption',
		'TOC', 'TOCI', 'Index', 'NonStruct', 'Private',
		'P', 'H', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
		'L', 'LI', 'Lbl', 'LBody',
		'Table', 'TR', 'TH', 'TD', 'THead', 'TBody', 'TFoot',
		'Span', 'Quote', 'Note', 'Reference', 'BibEntry', 'Code',
		'Link', 'Annot',
		'Ruby', 'RB', 'RT', 'RP', 'Warichu', 'WT', 'WP',
		'Figure', 'Formula', 'Form',
	];

	/**
	 * Grouping types, which hold other elements rather than content (ISO 32000-1 §14.8.4.2)
	 *
	 * @var array<int,string>
	 */
	private static $groupingTypes = [
		'Document', 'Part', 'Art', 'Sect', 'Div', 'BlockQuote', 'Caption',
		'TOC', 'TOCI', 'Index', 'NonStruct', 'Private',
		'Table', 'THead', 'TBody', 'TFoot', 'L',
	];

	/**
	 * The structure type of an element: its role attribute where that names a standard type,
	 * otherwise the type of its tag
	 *
	 * @param string $htmlTag Any case
	 * @param array  $attr    Attributes with uppercase keys
	 *
	 * @return string|null Null for a tag that is not tagged
	 */
	public static function fromHtmlTag($htmlTag, $attr = [])
	{
		if (!empty($attr['ROLE'])) {
			$role = trim($attr['ROLE']);
			if (in_array($role, self::$validTypes, true)) {
				return $role;
			}
		}
		$upper = strtoupper($htmlTag);
		return isset(self::$tagMap[$upper]) ? self::$tagMap[$upper] : null;
	}

	/**
	 * @param string $cssClass One class name
	 *
	 * @return string|null The structure type of a table of contents class, or null for any other
	 */
	public static function fromCssClass($cssClass)
	{
		if (isset(self::$tocClassMap[$cssClass])) {
			return self::$tocClassMap[$cssClass];
		}
		foreach (self::$tocClassMap as $prefix => $type) {
			if (substr($prefix, -1) === '_' && strncmp($cssClass, $prefix, strlen($prefix)) === 0) {
				return $type;
			}
		}
		return null;
	}

	/**
	 * @param string $type
	 *
	 * @return bool Whether $type is a standard structure type
	 */
	public static function isValid($type)
	{
		return in_array($type, self::$validTypes, true);
	}

	/**
	 * @param string $type
	 *
	 * @return bool Whether $type is a grouping type, which cannot hold content directly
	 */
	public static function isGrouping($type)
	{
		return in_array($type, self::$groupingTypes, true);
	}
}
