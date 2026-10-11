<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\Utils\UtfString;

/**
 * The margin boxes along the top and bottom of a page, @top-left to @bottom-right in an @page rule, drawn as mPDF's
 * non-HTML headers and footers: a table of three cells across the page.
 *
 * The parser keeps each box's properties among those of its @page rule, under the box's name, such as
 * @TOP-CENTER>>CONTENT, so they cascade through the :first, :left, :right and named page rules one by one as the
 * page's own properties do.
 */
class PageMarginBoxes
{

	/**
	 * The margin boxes that have a cell in a header or footer, by the edge they run along and their place on it
	 */
	const BOXES = [
		'TOP' => ['LEFT' => 'top-left', 'CENTER' => 'top-center', 'RIGHT' => 'top-right'],
		'BOTTOM' => ['LEFT' => 'bottom-left', 'CENTER' => 'bottom-center', 'RIGHT' => 'bottom-right'],
	];

	/**
	 * The properties of a box its cell takes
	 */
	const STYLES = ['COLOR', 'FONT-FAMILY', 'FONT-SIZE', 'FONT-STYLE', 'FONT-WEIGHT', 'TEXT-ALIGN', 'VERTICAL-ALIGN'];

	/**
	 * The counter styles of counter() that mPDF numbers pages in, besides decimal
	 */
	const COUNTER_STYLES = '/^(?:upper-latin|upper-alpha|lower-latin|lower-alpha|upper-roman|lower-roman|lower-greek|hebrew|cjk-decimal|arabic-indic|bengali|devanagari|gujarati|gurmukhi|kannada|malayalam|oriya|persian|tamil|telugu|thai|urdu|cambodian|khmer|lao|myanmar)$/';

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @param Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Whether a margin box is one this class draws
	 *
	 * @param string $name The at-rule's name, without the @
	 *
	 * @return bool
	 */
	public function isBox($name)
	{
		return in_array(strtolower($name), array_merge(array_values(self::BOXES['TOP']), array_values(self::BOXES['BOTTOM'])), true);
	}

	/**
	 * The text a content value puts in a box, as HTML with mPDF's page number placeholders
	 *
	 * Strings, counter(page) and counter(pages), the counters with a counter style or not, or none or normal for no
	 * content. Any other value, such as string() or element() for a running header, is not read: a browser drops a
	 * declaration it cannot read, so an earlier one applies.
	 *
	 * @param string $value
	 *
	 * @return string|null Null for a value that is not read
	 */
	public function content($value)
	{
		$value = trim($value);
		if (preg_match('/^(?:none|normal)$/i', $value)) {
			return '';
		}

		$html = '';
		$offset = 0;
		$token = '/\G\s*+(?:"((?:[^"\\\\]|\\\\[\s\S])*+)"|\'((?:[^\'\\\\]|\\\\[\s\S])*+)\'|counter\(\s*+(pages?)\s*+(?:,\s*+([-a-z0-9]+)\s*+)?\))/i';

		while ($offset < strlen($value)) {
			if (!preg_match($token, $value, $m, 0, $offset)) {
				return null;
			}

			$offset += strlen($m[0]);

			if (isset($m[3]) && $m[3] !== '') {
				$html .= $this->counter(strtolower($m[3]), isset($m[4]) ? strtolower($m[4]) : '');
			} else {
				$html .= $this->text($m[1] !== '' ? $m[1] : (isset($m[2]) ? $m[2] : ''));
			}
		}

		return $html;
	}

	/**
	 * The header or footer the margin boxes along an edge of a page make
	 *
	 * @param array $properties The page's properties, as CssManager::pageProperties() gives them
	 * @param string $edge TOP for the header, BOTTOM for the footer
	 *
	 * @return string The header's or footer's HTML, or '' when no box along the edge has content
	 */
	public function html(array $properties, $edge)
	{
		$cells = [];
		foreach (self::BOXES[$edge] as $place => $box) {
			$prefix = '@' . strtoupper($box) . '>>';
			$content = isset($properties[$prefix . 'CONTENT']) ? $this->content($properties[$prefix . 'CONTENT']) : '';

			$style = ['TEXT-ALIGN' => strtolower($place)];
			foreach (self::STYLES as $property) {
				if (isset($properties[$prefix . $property])) {
					$style[$property] = $properties[$prefix . $property];
				}
			}

			$cells[$place] = [(string) $content, $style];
		}

		if ($cells['LEFT'][0] === '' && $cells['CENTER'][0] === '' && $cells['RIGHT'][0] === '') {
			return '';
		}

		list($left, $center, $right) = $this->mpdf->_shareHeaderFooterWidth($cells['LEFT'][0], $cells['CENTER'][0], $cells['RIGHT'][0]);
		$widths = ['LEFT' => $left, 'CENTER' => $center, 'RIGHT' => $right];

		// A table's columns run from the right in a right-to-left document, and each box keeps to its own side
		if ($this->mpdf->directionality === 'rtl') {
			$cells = array_reverse($cells, true);
		}

		if ($edge === 'TOP') {
			$valign = 'bottom';
			$padding = '0 0 ' . $this->mpdf->header_line_spacing . 'em 0';
		} else {
			$valign = 'top';
			$padding = $this->mpdf->footer_line_spacing . 'em 0 0 0';
		}

		$html = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: ' . $valign . '; color: #000000;"><tr>';
		foreach ($cells as $place => $cell) {
			$html .= '<td width="' . $widths[$place] . '%" style="padding: ' . $padding . ';';
			foreach ($cell[1] as $property => $value) {
				$html .= ' ' . strtolower($property) . ': ' . str_replace(['"', '<', '>'], ["'", '', ''], $value) . ';';
			}
			$html .= '">' . $cell[0] . '</td>';
		}

		return $html . '</tr></table>';
	}

	/**
	 * The placeholder mPDF replaces with a page number, or the number of pages, in a header or footer
	 *
	 * @param string $counter page or pages
	 * @param string $style The counter style, or '' for decimal
	 *
	 * @return string
	 */
	private function counter($counter, $style)
	{
		if ($style === 'none') {
			return '';
		}

		$placeholder = $counter === 'page' ? '{PAGENO}' : $this->mpdf->aliasNbPg;
		if (!preg_match(self::COUNTER_STYLES, $style)) {
			return $placeholder;
		}

		return $counter === 'page' ? '{PAGENO:' . $style . '}' : '{nb:' . $style . '}';
	}

	/**
	 * The HTML for the text of a CSS string, its escapes read
	 *
	 * @param string $string Between its quotes
	 *
	 * @return string
	 */
	private function text($string)
	{
		$text = preg_replace_callback('/\\\\(?:([0-9a-f]{1,6})(?:\r\n|[ \t\n\r\f])?|(\r\n|[\n\r\f])|([\s\S]))/i', function ($m) {
			if (isset($m[3])) {
				return $m[3];
			}

			if (isset($m[2]) && $m[2] !== '') {
				return '';
			}

			$code = hexdec($m[1]);

			return $code === 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF) ? "\xEF\xBF\xBD" : UtfString::code2utf($code);
		}, $string);

		return str_replace("\n", '<br />', htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
	}

}
