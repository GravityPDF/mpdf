<?php

namespace Mpdf;

use Mpdf\Css\CssMerger;
use Mpdf\Exception\InvalidArgumentException;
use Mpdf\Utils\Arrays;
use Mpdf\Css\CssParser;

class CssManager
{
	/**
	 * @var \Mpdf\Css\CssParser
	 */
	private $cssParser;

	/**
	 * @var \Mpdf\Css\CssMerger
	 */
	private $cssMerger;

	/**
	 * Main CSS property storage array.
	 *
	 * Stores CSS properties for simple selectors (depth 1).
	 * Format:
	 * [
	 *   'P' => [
	 *     'COLOR' => '#FF0000',
	 *     'FONT-SIZE' => '12pt',
	 *   ],
	 *   'CLASS>>MYCLASS' => [
	 *     'BORDER' => '1px solid black',
	 *   ],
	 *   ...
	 * ]
	 *
	 * @var array
	 */
	public $CSS = [];

	/**
	 * CSS cascade storage for table elements.
	 *
	 * Stores cascaded CSS properties specifically for table elements (TABLE, THEAD, TBODY, TFOOT, TR, TH, TD).
	 * Format is a nested array mirroring the selector hierarchy.
	 * Example for "DIV TABLE TD":
	 * [
	 *   'DIV' => [
	 *     'TABLE' => [
	 *       'TD' => [
	 *         'BORDER' => '1px solid green',
	 *         'depth' => 3
	 *       ]
	 *     ]
	 *   ]
	 * ]
	 *
	 * @var array
	 */
	public $tablecascadeCSS = [];

	/**
	 * Cascading CSS property storage.
	 *
	 * Stores CSS properties for nested/cascaded selectors (depth > 1).
	 * Format is a nested array mirroring the selector hierarchy.
	 * Example for "DIV.myclass P":
	 * [
	 *   'DIV' => [
	 *     'CLASS>>MYCLASS' => [
	 *       'P' => [
	 *         'COLOR' => '#0000FF',
	 *         'depth' => 3
	 *       ]
	 *     ]
	 *   ]
	 * ]
	 *
	 * @var array
	 */
	public $cascadeCSS = [];

	/**
	 * @var int Table CSS cascade level counter
	 */
	public $tbCSSlvl = 0;

	/**
	 * CssManager constructor.
	 *
	 * Initializes the CSS manager with required dependencies and sets up
	 * internal storage structures for CSS properties and cascading.
	 *
	 * @param CssParser $cssParser
	 * @param CssMerger $cssMerger
	 */
	public function __construct(CssParser $cssParser, CssMerger $cssMerger)
	{
		$this->cssParser = $cssParser;
		$this->cssMerger = $cssMerger;
		$this->cssMerger->setCssManager($this);
	}

	/**
	 * Read and parse CSS from HTML content.
	 *
	 * Extracts CSS from style tags, link tags, and @import statements within HTML.
	 * Processes external stylesheets, resolves URLs, handles media queries, and
	 * parses all CSS rules into the internal CSS storage structure.
	 *
	 * @param string $html HTML content containing CSS
	 * @return string HTML with CSS content removed
	 */
	public function readCss($html)
	{
		if (!is_array($this->cascadeCSS)) {
			$this->cascadeCSS = [];
		}

		$html = $this->cssParser->parse($html);

		$this->CSS = Arrays::uniqueRecursiveMerge($this->CSS, $this->cssParser->getCss());
		$this->cascadeCSS = Arrays::uniqueRecursiveMerge($this->cascadeCSS, $this->cssParser->getCascadeCss());

		return $html;
	}

	/**
	 * Parse inline CSS style attribute.
	 *
	 * @param string $html CSS string from style attribute
	 * @return array Parsed CSS properties
	 */
	public function readInlineCss($html)
	{
		return $this->cssParser->parseInlineCss($html);
	}

	/**
	 * Merge CSS properties for an HTML element.
	 *
	 * Main method for applying CSS to an element. Combines CSS from multiple sources
	 * including default styles, stylesheets, inline styles, and inherited properties.
	 * Handles inheritance type (BLOCK, INLINE, TABLE, TOPTABLE) and applies
	 * appropriate cascading rules.
	 *
	 * @param string $inherit Inheritance context (BLOCK, INLINE, TABLE, TOPTABLE)
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes including CLASS, ID, STYLE
	 * @return array Merged CSS properties array
	 */
	public function mergeCss($inherit, $tag, $attr)
	{
		return $this->cssMerger->merge($inherit, $tag, $attr);
	}

	/**
	 * Preview block-level CSS without creating the block.
	 *
	 * Looks ahead to determine what CSS would be applied to a block element
	 * without actually creating it. Used for planning layout and spacing.
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes array
	 * @return array CSS properties that would be applied
	 */
	public function previewBlockCss($tag, $attr)
	{
		return $this->cssMerger->previewBlockCss($tag, $attr);
	}

	public function getUsedClassNames()
	{
		return $this->cssParser->getUsedClassNames();
	}

	public function getMaxClassDepth()
	{
		return $this->cssParser->getMaxClassDepth();
	}

	/**
	 * Parse box-shadow CSS property.
	 *
	 * Converts box-shadow CSS property string into array format used internally.
	 * Handles multiple shadows, inset shadows, blur, spread, and colors.
	 *
	 * @param string $value Box-shadow property value
	 * @return array Array of shadow definitions
	 */
	public function setCssBoxShadow($value)
	{
		return $this->cssParser->parseBoxShadow($value);
	}

	/**
	 * Parse text-shadow CSS property.
	 *
	 * Converts text-shadow CSS property string into array format used internally.
	 * Handles multiple shadows, blur, and colors.
	 *
	 * @param string $value Text-shadow property value
	 * @return array Array of text shadow definitions
	 */
	public function setCssTextShadow($value)
	{
		return $this->cssParser->parseTextShadow($value);
	}

	/**
	 * Set border dominance level for a specific side.
	 *
	 * @param string $side T|R|B|L
	 * @param int $val Dominance value
	 * @throws InvalidArgumentException
	 * @return void
	 */
	public function setBorderDominance($side, $val)
	{
		$this->cssMerger->setBorderDominance($side, $val);
	}

	/**
	 * Get border dominance level for a specific side.
	 *
	 * @param string $side T|R|B|L
	 * @return int Dominance value
	 */
	public function getBorderDominance($side)
	{
		return $this->cssMerger->getBorderDominance($side);
	}

	/**
	 * Whether a :first, :left or :right rule of the plain @page rule, or of the named page, sets a side margin
	 *
	 * @param string|null $name The named page, or '' or null for none
	 * @return bool
	 */
	public function pseudoPagesSetSideMargins($name)
	{
		$prefixes = ['@PAGE>>'];
		if ($name) {
			$prefixes[] = '@PAGE>>NAMED>>' . strtoupper($name) . '>>';
		}

		foreach ($prefixes as $prefix) {
			foreach (['FIRST', 'LEFT', 'RIGHT'] as $pseudo) {
				$rule = $prefix . 'PSEUDO>>' . $pseudo;
				if (isset($this->CSS[$rule]['MARGIN-LEFT']) || isset($this->CSS[$rule]['MARGIN-RIGHT'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the style sheet has a plain @page rule, or a :first, :left or :right one, that sets anything
	 *
	 * @return bool
	 */
	public function hasUnnamedPageRules()
	{
		foreach (['@PAGE', '@PAGE>>PSEUDO>>FIRST', '@PAGE>>PSEUDO>>LEFT', '@PAGE>>PSEUDO>>RIGHT'] as $rule) {
			if (!empty($this->CSS[$rule])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The properties the :right, :left and :first rules under an @page rule add to the page, less the sheet size
	 *
	 * Their side margins are physical, not inner and outer, so on an even page they are handed over swapped for
	 * Mpdf::pageSideMargins() to swap back.
	 *
	 * @param string $prefix The key of the @page rule in the style sheet, up to its pseudo pages
	 * @param string $side 'R' or 'L', or '' for the defaults of every page
	 * @param bool $first Whether this is the first page
	 * @param string $oddEven 'E' for an even page when margins are mirrored, 'O' for any other page, or '' for the
	 *                        defaults of every page
	 * @return array
	 */
	public function pseudoPageProperties($prefix, $side, $first, $oddEven)
	{
		$properties = [];
		foreach (['RIGHT' => $side == 'R', 'LEFT' => $side == 'L', 'FIRST' => $first] as $pseudo => $applies) {
			if (!$applies || !isset($this->CSS[$prefix . 'PSEUDO>>' . $pseudo])) {
				continue;
			}

			$rule = $this->CSS[$prefix . 'PSEUDO>>' . $pseudo];
			unset($rule['SIZE'], $rule['SHEET-SIZE']);

			if ($oddEven === 'E') {
				$physical = $rule;
				unset($rule['MARGIN-LEFT'], $rule['MARGIN-RIGHT']);
				if (isset($physical['MARGIN-LEFT'])) {
					$rule['MARGIN-RIGHT'] = $physical['MARGIN-LEFT'];
				}
				if (isset($physical['MARGIN-RIGHT'])) {
					$rule['MARGIN-LEFT'] = $physical['MARGIN-RIGHT'];
				}
			}

			$properties = array_merge($properties, $rule);
		}

		return $properties;
	}
}
