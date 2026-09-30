<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\Utils\UtfString;

/**
 * The HTML attributes the standard cascade reads as presentational hints: author rules of no specificity, applied
 * over the inherited values and the built-in defaults, and under any stylesheet rule. Each attribute is read on the
 * elements the Rendering section of the HTML Living Standard gives it to, so `<a color>` is ignored as a browser
 * ignores it. `bgcolor`, `cellpadding`, `cellspacing` and a table's `align` are read where the table is built.
 */
class PresentationalHints
{

	/**
	 * mPDF's own tags that run the cascade. They keep every attribute mPDF has read for them
	 */
	const OWN_TAGS = ['BARCODE', 'DOTTAB', 'TEXTCIRCLE'];

	/**
	 * The elements each attribute that sets one property is a hint on, and the property. mPDF also sizes a meter and
	 * a progress bar by their width and height
	 */
	const PROPERTIES = [
		'COLOR' => [['FONT', 'HR'], 'COLOR'],
		'WIDTH' => [['HR', 'IMG', 'METER', 'PROGRESS', 'TABLE', 'TD', 'TH'], 'WIDTH'],
		'HEIGHT' => [['IMG', 'METER', 'PROGRESS', 'TABLE', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR'], 'HEIGHT'],
		'VALIGN' => [['TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR'], 'VERTICAL-ALIGN'],
	];

	/**
	 * The text alignment `align` gives the elements it aligns the text of, by its value
	 */
	const TEXT_ALIGN = [
		'DIV' => ['left' => 'left', 'right' => 'right', 'center' => 'center', 'middle' => 'center', 'justify' => 'justify'],
		'P' => ['left' => 'left', 'right' => 'right', 'center' => 'center', 'justify' => 'justify'],
		'CELL' => ['left' => 'left', 'right' => 'right', 'center' => 'center', 'middle' => 'center', 'absmiddle' => 'center', 'justify' => 'justify'],
	];

	/**
	 * The elements that take their text alignment from `align` as a paragraph does, and as a table cell does
	 */
	const TEXT_ALIGN_OF = [
		'DIV' => 'DIV',
		'P' => 'P', 'H1' => 'P', 'H2' => 'P', 'H3' => 'P', 'H4' => 'P', 'H5' => 'P', 'H6' => 'P',
		'THEAD' => 'CELL', 'TBODY' => 'CELL', 'TFOOT' => 'CELL', 'TR' => 'CELL', 'TD' => 'CELL', 'TH' => 'CELL',
	];

	/**
	 * How `align` places an image, by its value
	 */
	const IMAGE_ALIGN = [
		'left' => ['FLOAT' => 'left'],
		'right' => ['FLOAT' => 'right'],
		'top' => ['VERTICAL-ALIGN' => 'top'],
		'baseline' => ['VERTICAL-ALIGN' => 'baseline'],
		'texttop' => ['VERTICAL-ALIGN' => 'text-top'],
		'absmiddle' => ['VERTICAL-ALIGN' => 'middle'],
		'abscenter' => ['VERTICAL-ALIGN' => 'middle'],
		'middle' => ['VERTICAL-ALIGN' => 'middle'],
		'center' => ['VERTICAL-ALIGN' => 'middle'],
		'bottom' => ['VERTICAL-ALIGN' => 'bottom'],
	];

	/**
	 * The margins `align` gives a horizontal rule, by its value
	 */
	const RULE_ALIGN = [
		'left' => ['MARGIN-LEFT' => '0', 'MARGIN-RIGHT' => 'auto'],
		'right' => ['MARGIN-LEFT' => 'auto', 'MARGIN-RIGHT' => '0'],
		'center' => ['MARGIN-LEFT' => 'auto', 'MARGIN-RIGHT' => 'auto'],
	];

	/**
	 * The marker `type` gives a list or a list item, by its value. The letters and numerals are matched as written,
	 * the words in any case
	 */
	const LIST_TYPES = [
		'1' => 'decimal',
		'a' => 'lower-latin',
		'A' => 'upper-latin',
		'i' => 'lower-roman',
		'I' => 'upper-roman',
		'none' => 'none',
		'disc' => 'disc',
		'circle' => 'circle',
		'square' => 'square',
	];

	/**
	 * The font sizes of `<font size>`, by its value
	 */
	const FONT_SIZES = [
		'+1' => '120%',
		'-1' => '86%',
		'1' => 'XX-SMALL',
		'2' => 'X-SMALL',
		'3' => 'SMALL',
		'4' => 'MEDIUM',
		'5' => 'LARGE',
		'6' => 'X-LARGE',
		'7' => 'XX-LARGE',
	];

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Css\NormalizeProperties
	 */
	private $normalizeProperties;

	/**
	 * @var array|null The border ofCellBorder() gives, once it has been asked for
	 */
	private $cellBorder;

	/**
	 * @param \Mpdf\Mpdf $mpdf For the characters a table cell can align on
	 * @param \Mpdf\Css\NormalizeProperties $normalizeProperties
	 */
	public function __construct(Mpdf $mpdf, NormalizeProperties $normalizeProperties)
	{
		$this->mpdf = $mpdf;
		$this->normalizeProperties = $normalizeProperties;
	}

	/**
	 * The properties an element's attributes set as hints. `dir` and `lang` are read on every element
	 *
	 * @param string $tag Uppercased
	 * @param array $attr The element's attributes, keyed uppercased
	 *
	 * @return array CSS properties, keyed uppercased
	 */
	public function of($tag, array $attr)
	{
		$hints = [];

		if (!empty($attr['DIR'])) {
			$hints['DIRECTION'] = $attr['DIR'];
		}

		if (!empty($attr['LANG'])) {
			$hints['LANG'] = $attr['LANG'];
		}

		foreach (self::PROPERTIES as $attribute => list($tags, $property)) {
			if (!empty($attr[$attribute]) && in_array($tag, $tags, true)) {
				$hints[$property] = $attr[$attribute];
			}
		}

		if ($tag === 'FONT') {
			$hints = array_merge($hints, $this->ofFont($attr));
		}

		if (isset($attr['ALIGN'])) {
			$hints = array_merge($hints, $this->ofAlign($tag, strtolower(trim($attr['ALIGN'])), $attr));
		}

		if (isset($attr['NOWRAP']) && ($tag === 'TD' || $tag === 'TH')) {
			$hints['WHITE-SPACE'] = 'nowrap';
		}

		if ($tag === 'IMG' || ($tag === 'INPUT' && isset($attr['TYPE']) && strtolower($attr['TYPE']) === 'image')) {
			$hints = array_merge($hints, $this->ofImageSpacing($attr));
		}

		if (($tag === 'TABLE' || $tag === 'IMG') && isset($attr['BORDER'])) {
			$hints = array_merge($hints, $this->ofBorder($tag, $attr['BORDER']));
		}

		if (isset($attr['TYPE']) && in_array($tag, ['OL', 'UL', 'LI'], true)) {
			$type = array_key_exists($attr['TYPE'], self::LIST_TYPES) ? $attr['TYPE'] : strtolower($attr['TYPE']);
			if (array_key_exists($type, self::LIST_TYPES)) {
				$hints['LIST-STYLE-TYPE'] = self::LIST_TYPES[$type];
			}
		}

		if ($tag === 'HR' && isset($attr['SIZE'])) {
			$size = self::nonNegativeInteger($attr['SIZE']);
			if ($size) {
				$hints['HEIGHT'] = $size . 'px';
			}
		}

		return $hints;
	}

	/**
	 * The font family and size `<font face>` and `<font size>` set
	 *
	 * @param array $attr The font element's attributes
	 *
	 * @return array CSS properties
	 */
	public function ofFont(array $attr)
	{
		$hints = [];

		if (!empty($attr['FACE'])) {
			$hints = $this->normalizeProperties->normalize(['FONT-FAMILY' => $attr['FACE']]);
		}

		if (isset($attr['SIZE']) && array_key_exists($attr['SIZE'], self::FONT_SIZES)) {
			$hints['FONT-SIZE'] = self::FONT_SIZES[$attr['SIZE']];
		}

		return $hints;
	}

	/**
	 * The border each cell of a table with a `border` attribute takes, as a browser's default stylesheet gives it
	 *
	 * @return array CSS properties
	 */
	public function ofCellBorder()
	{
		if ($this->cellBorder === null) {
			$this->cellBorder = $this->ofBorder('TABLE', '1');
		}

		return $this->cellBorder;
	}

	/**
	 * The width of the border `border` gives a table or an image: a table's is 1px where the value is not a number,
	 * and an image has none at 0
	 *
	 * @param string $tag TABLE or IMG
	 * @param string $value The attribute's value
	 *
	 * @return int Pixels, 0 for no border
	 */
	public static function borderWidth($tag, $value)
	{
		$width = self::nonNegativeInteger($value);

		return $width === null && $tag === 'TABLE' ? 1 : (int) $width;
	}

	/**
	 * What `align` sets on an element
	 *
	 * @param string $tag
	 * @param string $align The attribute's value, lowercased
	 * @param array $attr The element's attributes, for the character a table cell aligns on
	 *
	 * @return array CSS properties
	 */
	private function ofAlign($tag, $align, array $attr)
	{
		if (array_key_exists($tag, self::TEXT_ALIGN_OF)) {
			$values = self::TEXT_ALIGN[self::TEXT_ALIGN_OF[$tag]];
			if (isset($values[$align])) {
				return ['TEXT-ALIGN' => $values[$align]];
			}

			return $align === 'char' && ($tag === 'TD' || $tag === 'TH') ? $this->ofCharAlign($attr) : [];
		}

		if ($tag === 'IMG' && array_key_exists($align, self::IMAGE_ALIGN)) {
			return self::IMAGE_ALIGN[$align];
		}

		if ($tag === 'HR' && array_key_exists($align, self::RULE_ALIGN)) {
			return self::RULE_ALIGN[$align];
		}

		if ($tag === 'CAPTION' && $align === 'bottom') {
			return ['CAPTION-SIDE' => 'bottom'];
		}

		return [];
	}

	/**
	 * The decimal alignment `align="char"` gives a table cell: on its `char`, or on the point where it names none
	 *
	 * @param array $attr The cell's attributes
	 *
	 * @return array CSS properties, none for a character mPDF cannot align on
	 */
	private function ofCharAlign(array $attr)
	{
		if (empty($attr['CHAR'])) {
			return ['TEXT-ALIGN' => 'DPR'];
		}

		$char = UtfString::strcode2utf(html_entity_decode($attr['CHAR']));
		$key = array_search($char, $this->mpdf->decimal_align, true);

		return $key === false ? [] : ['TEXT-ALIGN' => $key . 'R'];
	}

	/**
	 * The margins `vspace` and `hspace` give an image
	 *
	 * @param array $attr The image's attributes
	 *
	 * @return array CSS properties
	 */
	private function ofImageSpacing(array $attr)
	{
		$hints = [];

		if (!empty($attr['VSPACE'])) {
			$hints['MARGIN-TOP'] = $hints['MARGIN-BOTTOM'] = $attr['VSPACE'];
		}

		if (!empty($attr['HSPACE'])) {
			$hints['MARGIN-LEFT'] = $hints['MARGIN-RIGHT'] = $attr['HSPACE'];
		}

		return $hints;
	}

	/**
	 * The solid black border `border` draws around a table or an image
	 *
	 * @param string $tag TABLE or IMG
	 * @param string $value The attribute's value
	 *
	 * @return array CSS properties
	 */
	private function ofBorder($tag, $value)
	{
		$width = self::borderWidth($tag, $value);

		return $width ? $this->normalizeProperties->normalize(['BORDER' => $width . 'px solid #000000']) : [];
	}

	/**
	 * Reads a value as HTML's rules for parsing non-negative integers do: the digits it starts with, after any
	 * whitespace and a plus sign
	 *
	 * @param string $value
	 *
	 * @return int|null Null where the value does not start with a number
	 */
	private static function nonNegativeInteger($value)
	{
		return preg_match('/^\s*\+?(\d+)/', $value, $matches) ? (int) $matches[1] : null;
	}
}
