<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\CssMode;
use Mpdf\CssManager;
use Mpdf\Exception\InvalidArgumentException;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Mpdf\Utils\Arrays;

class CssMerger
{

	/**
	 * The properties that resolve currentColor in both modes. Legacy mode raised warnings for it in each
	 */
	const CURRENT_COLOR_PROPERTIES = [
		'BORDER-TOP',
		'BORDER-RIGHT',
		'BORDER-BOTTOM',
		'BORDER-LEFT',
		'BORDER-TOP-COLOR',
		'BORDER-RIGHT-COLOR',
		'BORDER-BOTTOM-COLOR',
		'BORDER-LEFT-COLOR',
		'TEXT-OUTLINE-COLOR',
	];

	/**
	 * The other properties mPDF reads a colour from that can name currentColor, which legacy mode ignores in them. A
	 * background image names it in the stops of a gradient
	 */
	const STANDARD_CURRENT_COLOR_PROPERTIES = [
		'BACKGROUND-COLOR',
		'BACKGROUND-IMAGE',
		'BOX-SHADOW',
		'TEXT-SHADOW',
		'TOPNTAIL',
		'THEAD-UNDERLINE',
	];

	/**
	 * The parts of a border side, in the order its shorthand writes them
	 */
	const BORDER_PARTS = ['WIDTH', 'STYLE', 'COLOR'];

	/**
	 * Matches a border side's shorthand or one of its parts, capturing the side's shorthand and the part
	 */
	const BORDER_SIDE_PROPERTY = '/^(BORDER-(?:TOP|RIGHT|BOTTOM|LEFT))(?:-(WIDTH|STYLE|COLOR))?$/';

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var CssManager
	 */
	private $cssManager;

	/**
	 * @var \Mpdf\Css\NormalizeProperties
	 */
	private $normalizeProperties;

	/**
	 * @var \Mpdf\Css\InlineStyleParser
	 */
	private $inlineStyleParser;

	/**
	 * @var \Mpdf\Css\SelectorParser
	 */
	private $selectorParser;

	/**
	 * @var \Mpdf\Css\InlinePropertyConverter
	 */
	private $inlinePropertyConverter;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * @var array
	 */
	private $cssProperties = [];

	/**
	 * @var \Mpdf\Css\BorderMerger
	 */
	private $borderMerger;

	/**
	 * @var \Mpdf\Css\PresentationalHints
	 */
	private $presentationalHints;

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var bool When true, state outside this object will be modified
	 * @internal self::previewBlockCss() uses this property to look ahead without affecting state
	 */
	private $sideEffects = true;

	public function __construct(
		Mpdf $mpdf,
		NormalizeProperties $normalizeProperties,
		InlineStyleParser $inlineStyleParser,
		SelectorParser $selectorParser,
		InlinePropertyConverter $inlinePropertyConverter,
		ColorConverter $colorConverter,
		BorderMerger $borderMerger,
		PresentationalHints $presentationalHints,
		SizeConverter $sizeConverter
	) {
		$this->mpdf = $mpdf;
		$this->normalizeProperties = $normalizeProperties;
		$this->inlineStyleParser = $inlineStyleParser;
		$this->selectorParser = $selectorParser;
		$this->inlinePropertyConverter = $inlinePropertyConverter;
		$this->colorConverter = $colorConverter;
		$this->borderMerger = $borderMerger;
		$this->presentationalHints = $presentationalHints;
		$this->sizeConverter = $sizeConverter;
	}

	/**
	 * Make the CssManager state available to the merger
	 *
	 * @param CssManager $cssManager
	 * @return void
	 *
	 * @internal Temporary method. Required until the global CssManager properties/state is refactored
	 */
	public function setCssManager(CssManager $cssManager)
	{
		$this->cssManager = $cssManager;
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
	 * @param array $inherited Under the standard cascade, what the element inherits, under everything else merged
	 * @return array Merged CSS properties array
	 */
	public function merge($inherit, $tag, $attr, array $inherited = [])
	{
		$this->cssProperties = [];

		$attr = is_array($attr) ? $attr : [];

		if ($this->mpdf->cssMode === CssMode::STANDARD) {
			return $this->mergeInCascadeOrder($inherit, $tag, $attr, $inherited);
		}

		$classes = [];
		if (isset($attr['CLASS'])) {
			// filter out classes that don't have any CSS applied, which reduces likelyhood of O(2^N) memory issue
			$rawClasses = preg_split('/\s+/', $attr['CLASS']);
			$rawClasses = array_intersect($rawClasses, $this->cssManager->getUsedClassNames());
			$maxDepth = $this->cssManager->getMaxClassDepth();

			$classes = array_map(function ($combination) {
				return implode('.', $combination);
			}, Arrays::allUniqueSortedCombinations($rawClasses, $maxDepth));
		}

		if (!isset($attr['ID'])) {
			$attr['ID'] = '';
		}

		$languageCode = '';
		if (!isset($attr['LANG'])) {
			$attr['LANG'] = '';
		} else {
			$attr['LANG'] = strtolower($attr['LANG']);
			if (strlen($attr['LANG']) === 5) {
				$languageCode = substr($attr['LANG'], 0, 2);
			}
		}

		$this->mergeTableCascadingCss($inherit, $tag, $attr, $classes);
		$this->mergeBlockCascadingCss($inherit, $tag, $attr, $classes);
		$this->mergeInheritedBlockProperties($inherit, $tag);
		$this->mergeInlineAttributes($tag, $attr);
		$this->mergeDefaultCss($tag);
		$this->mergeTableSpecificCss($tag, $attr);
		$this->mergeStylesheetSelectors($tag, $attr, $classes, $languageCode);
		$this->mergeTagSpecificSelectors($tag, $attr, $classes, $languageCode);
		$this->mergeDescendantSelectors($inherit, $tag, $attr, $classes, $languageCode);
		$this->mergeInlineStyle($tag, $attr);
		$this->resolveCurrentColor($inherit);

		return $this->cssProperties;
	}

	/**
	 * Merges an element's CSS as standard mode orders it, in layers, each over the one before: the inherited
	 * values, the built-in defaults and the default stylesheet's rules, the presentational attributes as author rules
	 * of zero specificity, the author rules, and the inline style; then the !important declarations of the author
	 * rules, of the inline style, and of the default stylesheet's rules. The rules of each stylesheet apply by
	 * specificity, then in the order they were written. Each rule that reaches a table cell draws its borders over
	 * those of its neighbours.
	 *
	 * @param string $inherit Inheritance context (BLOCK, INLINE, TABLE, TOPTABLE)
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes, as the tag handler was given them
	 * @param array $inherited What the element inherits, besides what a block takes from its parent block
	 * @return array Merged CSS properties array
	 */
	private function mergeInCascadeOrder($inherit, $tag, array $attr, array $inherited)
	{
		// Found once, when a rule set first has a rule filed under the element. previewBlockCss() looks at an element
		// that is not being written, so it is put in the innermost open element, and nothing is recorded
		$mpdf = $this->mpdf;
		$sideEffects = $this->sideEffects;
		$elements = false;
		$path = function () use ($mpdf, $sideEffects, $tag, $attr, &$elements) {
			if ($elements === false) {
				$elements = $sideEffects ? $mpdf->getStyledElementPath() : $mpdf->getOpenElementPathFor($tag, $attr);
			}

			return $elements;
		};

		if (isset($attr['LANG'])) {
			$attr['LANG'] = strtolower($attr['LANG']);
		}

		$id = isset($attr['ID']) ? $attr['ID'] : '';
		$classes = $this->classesOf($attr);
		$dominance = $tag === 'TD' || $tag === 'TH' ? 9 : false;

		$this->cssProperties = $inherited;
		$this->mergeInheritedBlockProperties($inherit, $tag);
		$inherited = $this->cssProperties;

		// The built-in defaults and the default stylesheet are merged on their own first, for revert to read
		$this->cssProperties = [];
		$this->mergeDefaultCss($tag);
		list($defaultRules, $importantDefaultRules) = $this->cssManager->getDefaultRules()->matchingDeclarations($tag, $id, $classes, $path);
		$this->mergeEach($defaultRules, $dominance);
		$defaults = $this->cssProperties;
		// HTML's rendering rules centre a th only when the text-align it inherits is the initial value
		if ($tag === 'TH' && isset($inherited['TEXT-ALIGN'])) {
			$defaults['TEXT-ALIGN'] = $inherited['TEXT-ALIGN'];
		}
		$this->cssProperties = array_merge($inherited, $defaults);

		$this->mergePresentationalHints($tag, $attr);
		$this->mergeTableSpecificCss($tag, $attr);

		$html = null;
		if ($tag === 'BODY') {
			// Merged outside the document's elements, and written to by Mpdf::SetDefaultBodyCSS() and the like
			$important = $this->cssManager->getImportantCss();
			$importantDefault = $this->cssManager->getDefaultImportantCss();
			$body = isset($this->cssManager->CSS['BODY']) ? $this->cssManager->CSS['BODY'] : [];
			$this->setMergedCss($body, false);
			$rules = [];
			list($importantBody, $html) = $this->mergeDocumentRules();
			$importantRules = array_merge(isset($important['BODY']) ? [$important['BODY']] : [], $importantBody);
			$importantDefaultRules = isset($importantDefault['BODY']) ? [$importantDefault['BODY']] : [];
			// html is body's parent, which a keyword on body reads
			$inherited = array_merge($inherited, $html);
		} else {
			list($rules, $importantRules) = $this->cssManager->getRules()->matchingDeclarations($tag, $id, $classes, $path);
		}
		$this->mergeEach($rules, $dominance);

		list($inline, $importantInline) = isset($attr['STYLE']) ? $this->inlineStyleParser->parseByImportance($attr['STYLE']) : [[], []];
		$this->mergeEach(array_merge([$inline], $importantRules, [$importantInline], $importantDefaultRules), $dominance);

		// Before currentColor, which takes the colour a keyword resolves to
		$this->resolveWideKeywords($inherited, $defaults, $path, $inherit !== 'INLINE' && $inherit !== '', $html);
		$this->resolveCurrentColor($inherit, $inherited);
		if ($this->sideEffects) {
			$this->mpdf->recordStyledElementComputed($this->cssProperties);
		}

		return $this->cssProperties;
	}

	/**
	 * Replaces currentColor in the merged properties with the element's colour, which is its own color, or the one it
	 * inherits. In standard mode, color: currentColor is the colour the element inherits, and a shadow that names no
	 * colour takes currentColor, as a border does. Legacy mode resolves it only where it raised warnings
	 *
	 * @param string $inherit Inheritance context (BLOCK, INLINE, TABLE, TOPTABLE, or empty)
	 * @param array $inherited Under the standard cascade, what the element inherits
	 * @return void
	 */
	private function resolveCurrentColor($inherit, array $inherited = [])
	{
		$properties = self::CURRENT_COLOR_PROPERTIES;

		if ($this->mpdf->cssMode === CssMode::STANDARD) {
			$properties = array_merge($properties, self::STANDARD_CURRENT_COLOR_PROPERTIES);

			foreach (['BOX-SHADOW', 'TEXT-SHADOW'] as $property) {
				if (isset($this->cssProperties[$property])) {
					$this->cssProperties[$property] = ShadowParser::withColor($this->cssProperties[$property], 'currentcolor');
				}
			}

			if (isset($this->cssProperties['COLOR']) && strtolower($this->cssProperties['COLOR']) === 'currentcolor') {
				$color = $this->inheritedColor($inherit, $inherited);
				if ($color === null) {
					unset($this->cssProperties['COLOR']);
				} else {
					$this->cssProperties['COLOR'] = $color;
				}
			}
		}

		$color = null;
		foreach ($properties as $property) {
			if (!isset($this->cssProperties[$property]) || stripos($this->cssProperties[$property], 'currentcolor') === false) {
				continue;
			}

			// The same word in a url() is part of the address
			if ($property === 'BACKGROUND-IMAGE' && stripos($this->cssProperties[$property], 'gradient(') === false) {
				continue;
			}

			if ($color === null) {
				$color = $this->elementColor($inherit);
			}

			$this->cssProperties[$property] = str_ireplace('currentcolor', $color, $this->cssProperties[$property]);
		}
	}

	/**
	 * Resolves the CSS-wide keywords the cascade left in the element's properties:
	 * - inherit: for an inherited property, the value the element starts from, which is its parent's. A block, a table
	 *   and a table part are not handed every inherited value, so failing that they take the value their parent's frame
	 *   on the stack of open elements holds. Failing both, the property is dropped, and the element keeps the state it
	 *   is drawn in, as an inline element does. For any other property, the value its parent's frame holds, or failing
	 *   that the initial value
	 * - initial: the initial value CssWideKeywords gives, or the document's default font and size
	 * - unset: inherit for an inherited property, and initial for any other
	 * - revert and revert-layer: the value of the built-in defaults and the default stylesheet, or failing that unset
	 *
	 * A keyword in one part of a border side (its width, style or colour) is resolved into that side's shorthand too.
	 *
	 * @param array $inherited The properties the element starts from, before the defaults
	 * @param array $defaults The properties the built-in defaults and the default stylesheet give the element
	 * @param callable $path Gives the open elements from the document down to the element, or null for none
	 * @param bool $fromParent Whether an inherited property the element does not start from is read from its parent's
	 *                         frame: for a block, a table and a table part
	 * @param array|null $parentProperties The properties of a parent that has no frame, as html has none for body
	 * @return void
	 */
	private function resolveWideKeywords(array $inherited, array $defaults, callable $path, $fromParent, $parentProperties = null)
	{
		$parent = null;
		$sides = [];
		foreach ($this->cssProperties as $property => $value) {
			$keyword = CssWideKeywords::keywordOf($value);
			if ($keyword === null) {
				continue;
			}

			if (preg_match(self::BORDER_SIDE_PROPERTY, $property, $m)) {
				$sides[$m[1]] = true;
			}

			if ($keyword === 'revert' || $keyword === 'revert-layer') {
				$reverted = $this->declaredValue($defaults, $property);
				if ($reverted !== null) {
					$this->cssProperties[$property] = $reverted;
					continue;
				}
				$keyword = 'unset';
			}

			$isInherited = CssWideKeywords::isInherited($property);
			if ($keyword === 'unset') {
				$keyword = $isInherited ? 'inherit' : 'initial';
			}

			$resolved = null;
			if ($keyword === 'inherit' && $isInherited && isset($inherited[$property])) {
				$resolved = $inherited[$property];
			} elseif ($keyword === 'inherit' && (!$isInherited || $fromParent)) {
				if ($parent === null) {
					$parent = $parentProperties !== null ? $parentProperties : $this->parentComputed($path);
				}
				$resolved = $this->declaredValue($parent, $property);
				if ($resolved === null && !$isInherited) {
					$resolved = $this->initialValue($property);
				}
			} elseif ($keyword === 'initial') {
				$resolved = $this->initialValue($property);
			}

			if ($resolved === null) {
				unset($this->cssProperties[$property]);
			} else {
				$this->cssProperties[$property] = $resolved;
			}
		}

		foreach (array_keys($sides) as $side) {
			$this->resolveBorderSide($side);
		}
	}

	/**
	 * The colour currentColor stands for in the element's merged properties
	 *
	 * @param string $inherit Inheritance context
	 * @return string A colour with no spaces, which a border or shadow value keeps as one component
	 */
	private function elementColor($inherit)
	{
		$color = isset($this->cssProperties['COLOR']) ? $this->cssProperties['COLOR'] : '';
		if (!$this->colorConverter->isColor($color) && strtolower($color) !== 'transparent') {
			$color = $this->inheritedColor($inherit);
		}

		if ($color === null) {
			$color = isset($this->cssManager->CSS['BODY']['COLOR']) ? $this->cssManager->CSS['BODY']['COLOR'] : '#000000';
		}

		return str_replace(' ', '', $color);
	}

	/**
	 * The colour an element takes when it sets none, as mPDF passes it on in each context: a block takes the colour of
	 * the block or inline elements it is opened in, a table's parts their row's, row group's or table's, and anything
	 * else the colour of the text around it
	 *
	 * @param string $inherit Inheritance context
	 * @param array $inherited Under the standard cascade, what the element inherits
	 * @return string|null Null for the document's default colour
	 */
	private function inheritedColor($inherit, array $inherited = [])
	{
		if ($inherit === 'TABLE' || $inherit === 'TOPTABLE') {
			if (isset($inherited['COLOR'])) {
				return $inherited['COLOR'];
			}

			return isset($this->mpdf->base_table_properties['COLOR']) ? $this->mpdf->base_table_properties['COLOR'] : null;
		}

		if ($inherit === 'BLOCK') {
			$saved = InheritedProperties::blockTextState($this->mpdf->blk, $this->getBlockLevel());
			$colorarray = isset($saved['colorarray']) ? $saved['colorarray'] : '';
		} else {
			$colorarray = $this->mpdf->colorarray;
		}

		return $colorarray ? $this->colorConverter->colAtoString($colorarray) : null;
	}

	/**
	 * Writes a border side's resolved width, style and colour into its shorthand, which the drawing code reads. The
	 * shorthand, resolved on its own, is the base: a keyword in it replaced the whole side, and a part declared after
	 * it is still among the element's properties
	 *
	 * @param string $key BORDER-TOP, BORDER-RIGHT, BORDER-BOTTOM or BORDER-LEFT
	 * @return void
	 */
	private function resolveBorderSide($key)
	{
		$parts = [];
		foreach (self::BORDER_PARTS as $part) {
			if (isset($this->cssProperties[$key . '-' . $part])) {
				$parts[$key . '-' . $part] = $this->cssProperties[$key . '-' . $part];
			}
		}

		$this->mergeBorderProperties($parts);
	}

	/**
	 * The value a set of properties gives one of them, where a part of a border side left out of it is read from the
	 * side's shorthand
	 *
	 * @param array $properties
	 * @param string $property
	 * @return string|null Null when the properties do not give it
	 */
	private function declaredValue(array $properties, $property)
	{
		if (isset($properties[$property])) {
			return $properties[$property];
		}

		if (!preg_match(self::BORDER_SIDE_PROPERTY, $property, $m) || !isset($m[2], $properties[$m[1]])) {
			return null;
		}

		// A shorthand of anything but a width, a style and a colour gives no part
		$border = preg_split('/\s+/', trim($properties[$m[1]]));

		return count($border) === 3 ? $border[array_search($m[2], self::BORDER_PARTS, true)] : null;
	}

	/**
	 * @param callable $path Gives the open elements from the document down to the element, or null for none
	 * @return array The properties merged for the element's parent, or none if it has no parent or they were not kept
	 */
	private function parentComputed(callable $path)
	{
		$elements = $path();
		if ($elements === null || count($elements) < 2) {
			return [];
		}

		$parent = $elements[count($elements) - 2];

		return $parent['computed'] !== null ? $parent['computed'] : [];
	}

	/**
	 * @param string $property Uppercased
	 * @return string|null The property's initial value, or null to leave the property unset
	 */
	private function initialValue($property)
	{
		if ($property === 'FONT-FAMILY') {
			return $this->mpdf->original_default_font;
		}

		if ($property === 'FONT-SIZE') {
			return $this->mpdf->original_default_font_size . 'pt';
		}

		return CssWideKeywords::initialValue($property);
	}

	/**
	 * Merges html's rules into body's CSS, and body's own rules again over them. mPDF has no html element: the
	 * document's frame stands for body, and html is matched as its parent, so html's declarations, its !important
	 * ones included, reach the text as a parent's would, and lose to body's. Both win over what SetDefaultBodyCSS() and
	 * the like set. The rules for any element, such as *, are among the rules of each.
	 *
	 * html's font size, read against the default font size, is the root's, which rem and body's own size are read
	 * against
	 *
	 * @return array[] The !important declarations of body's rules, which apply after body's style, and the properties
	 *                 html's rules give
	 */
	private function mergeDocumentRules()
	{
		$rules = $this->cssManager->getRules();
		$path = $this->mpdf->getDocumentPath();
		$initial = $this->mpdf->initial_font_size;
		$this->mpdf->root_font_size = $initial;

		$htmlProperties = [];
		list($html, $importantHtml) = $rules->documentDeclarations(true, $path);
		foreach (array_merge($html, $importantHtml) as $properties) {
			if (isset($properties['FONT-SIZE'])) {
				$size = $this->sizeConverter->convertFontSize($properties['FONT-SIZE'], $initial / Mpdf::SCALE, $initial);
				if ($size !== null) {
					$this->mpdf->root_font_size = $size;
					$properties['FONT-SIZE'] = $size . 'pt';
				}
			}
			$this->setMergedCss($properties, false);
			$htmlProperties = array_merge($htmlProperties, $properties);
		}

		list($body, $importantBody) = $rules->documentDeclarations(false, $path);
		$this->mergeEach($body, false);

		return [$importantBody, $htmlProperties];
	}

	/**
	 * Merges sets of properties, each over those before it
	 *
	 * @param array[] $sets
	 * @param int|false $dominance The border dominance each set's borders take in a table cell, or false outside one
	 * @return void
	 */
	private function mergeEach(array $sets, $dominance)
	{
		foreach ($sets as $properties) {
			$this->setMergedCss($properties, false, $dominance);
		}
	}

	/**
	 * @param array $attr HTML attributes, with CLASS uppercased
	 * @return string[] The element's classes
	 */
	private function classesOf(array $attr)
	{
		return isset($attr['CLASS']) ? preg_split('/\s+/', $attr['CLASS'], -1, PREG_SPLIT_NO_EMPTY) : [];
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
		return $this->preview('BLOCK', $tag, $attr);
	}

	/**
	 * The CSS an element of a table would be given if it were opened now in the innermost open element, without
	 * opening it. Tr reads it for the tbody that a row written straight into a table is put in
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes array
	 * @return array CSS properties that would be applied
	 */
	public function previewTableCss($tag, $attr)
	{
		return $this->preview('TABLE', $tag, $attr);
	}

	/**
	 * Merges an element's CSS without changing any state outside this object
	 *
	 * @param string $inherit Inheritance context (BLOCK, TABLE)
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes array
	 * @return array CSS properties that would be applied
	 */
	private function preview($inherit, $tag, $attr)
	{
		$this->sideEffects = false;
		$results = $this->merge($inherit, $tag, $attr);
		$this->sideEffects = true;

		return $results;
	}

	/**
	 * Merge table cascading CSS.
	 *
	 * Handles inheritance and cascading of CSS properties for tables.
	 *
	 * @param string $inherit Inheritance type (TOPTABLE, TABLE, BLOCK)
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @return void
	 */
	protected function mergeTableCascadingCss($inherit, $tag, $attr, $classes)
	{
		if (! in_array($inherit, [ 'TOPTABLE', 'TABLE' ], true) || !$this->sideEffects) {
			return;
		}

		if ($inherit === 'TOPTABLE') {
			// Save Cascading CSS e.g. "div.topic p" at this block level
			if (isset($this->mpdf->blk[$this->mpdf->blklvl]['cascadeCSS'])) {
				$this->cssManager->tablecascadeCSS[0] = $this->mpdf->blk[$this->mpdf->blklvl]['cascadeCSS'];
			} else {
				$this->cssManager->tablecascadeCSS[0] = $this->cssManager->cascadeCSS;
			}
		}

		// Cascade everything from last level that is not an actual property, or defined by current tag/attributes
		if (isset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1]) && is_array($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1])) {
			foreach ($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1] as $k => $v) {
				$this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl][$k] = $v;
			}
		}

		$this->mergeFullCssRules(
			$this->cssManager->cascadeCSS,
			$this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl],
			$tag,
			$classes,
			$attr['ID'],
			$attr['LANG']
		);

		// Cascading forward CSS
		if (isset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1])) {
			$this->mergeFullCssRules(
				$this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1],
				$this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl],
				$tag,
				$classes,
				$attr['ID'],
				$attr['LANG']
			);
		}
	}

	/**
	 * Merge block cascading CSS.
	 *
	 * Lifts the descendant rules that go through a block element into its level of the block stack, for the elements
	 * inside it.
	 *
	 * @param string $inherit Inheritance type (TOPTABLE, TABLE, BLOCK)
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @return void
	 */
	protected function mergeBlockCascadingCss($inherit, $tag, $attr, $classes)
	{
		if ($inherit !== 'BLOCK') {
			return;
		}

		$currentBlock = isset($this->mpdf->blk[$this->mpdf->blklvl]) ? $this->mpdf->blk[$this->mpdf->blklvl] : [];
		$currentBlockHasCascade = isset($currentBlock['cascadeCSS']) && is_array($currentBlock['cascadeCSS']);
		$currentBlock['cascadeCSS'] = $currentBlockHasCascade ? $currentBlock['cascadeCSS'] : [];

		$previousBlockLevel = $this->getBlockLevel();
		$previousBlock = isset($this->mpdf->blk[$previousBlockLevel]) ? $this->mpdf->blk[$previousBlockLevel] : [];
		$previousBlockHasCascade = isset($previousBlock['cascadeCSS']) && is_array($previousBlock['cascadeCSS']);
		$previousBlock['cascadeCSS'] = $previousBlockHasCascade ? $previousBlock['cascadeCSS'] : [];

		foreach ($previousBlock['cascadeCSS'] as $k => $v) {
			$currentBlock['cascadeCSS'][$k] = $v;
		}

		// Save Cascading CSS e.g. "div.topic p" at this block level
		$this->mergeFullCssRules(
			$this->cssManager->cascadeCSS,
			$currentBlock['cascadeCSS'],
			$tag,
			$classes,
			$attr['ID'],
			$attr['LANG']
		);

		// Cascading forward CSS
		$this->mergeFullCssRules(
			$previousBlock['cascadeCSS'],
			$currentBlock['cascadeCSS'],
			$tag,
			$classes,
			$attr['ID'],
			$attr['LANG']
		);

		// Set the new block info
		if ($this->sideEffects) {
			$this->mpdf->blk[$this->mpdf->blklvl] = $currentBlock;
		}
	}

	/**
	 * Merge the properties a block inherits from the block it is opened in.
	 *
	 * @param string $inherit Inheritance type (TOPTABLE, TABLE, BLOCK)
	 * @param string $tag HTML tag name
	 * @return void
	 */
	protected function mergeInheritedBlockProperties($inherit, $tag)
	{
		if ($inherit !== 'BLOCK') {
			return;
		}

		$previousBlockLevel = $this->getBlockLevel();
		$previousBlock = isset($this->mpdf->blk[$previousBlockLevel]) ? $this->mpdf->blk[$previousBlockLevel] : [];

		// Block properties which are inherited
		if (!empty($previousBlock['margin_collapse'])) {
			$this->cssProperties['MARGIN-COLLAPSE'] = 'COLLAPSE';
		}

		// custom tag, but follows CSS principle that border-collapse is inherited
		if (!empty($previousBlock['line_height'])) {
			$this->cssProperties['LINE-HEIGHT'] = $previousBlock['line_height'];
		}

		// mPDF 6
		if (!empty($previousBlock['line_stacking_strategy'])) {
			$this->cssProperties['LINE-STACKING-STRATEGY'] = $previousBlock['line_stacking_strategy'];
		}

		if (!empty($previousBlock['line_stacking_shift'])) {
			$this->cssProperties['LINE-STACKING-SHIFT'] = $previousBlock['line_stacking_shift'];
		}

		if (!empty($previousBlock['direction'])) {
			$this->cssProperties['DIRECTION'] = $previousBlock['direction'];
		}

		// mPDF 6  Lists
		if ($tag === 'LI' && !empty($previousBlock['list_style_type'])) {
			$this->cssProperties['LIST-STYLE-TYPE'] = $previousBlock['list_style_type'];
		}

		if (!empty($previousBlock['list_style_image'])) {
			$this->cssProperties['LIST-STYLE-IMAGE'] = $previousBlock['list_style_image'];
		}

		if (!empty($previousBlock['list_style_position'])) {
			$this->cssProperties['LIST-STYLE-POSITION'] = $previousBlock['list_style_position'];
		}

		if (!empty($previousBlock['align'])) {
			switch ($previousBlock['align']) {
				case 'L':
					$this->cssProperties['TEXT-ALIGN'] = 'left';
					break;

				case 'J':
					$this->cssProperties['TEXT-ALIGN'] = 'justify';
					break;

				case 'R':
					$this->cssProperties['TEXT-ALIGN'] = 'right';
					break;

				case 'C':
					$this->cssProperties['TEXT-ALIGN'] = 'center';
					break;
			}
		}

		if (!empty($previousBlock['bgcolorarray']) && $this->mpdf->ColActive) {
			// Doesn't officially inherit, but default value is transparent (?=inherited)
			$cor = $previousBlock['bgcolorarray'];
			$this->cssProperties['BACKGROUND-COLOR'] = $this->colorConverter->colAtoString($cor);
		}

		if (isset($previousBlock['text_indent'])) {
			$this->cssProperties['TEXT-INDENT'] = $previousBlock['text_indent'];
		}

		$saved = InheritedProperties::blockTextState($this->mpdf->blk, $previousBlockLevel);
		if ($saved !== null) {
			if ($this->mpdf->cssMode === CssMode::LEGACY) {
				// mPDF v7 did not hand a block's text shadow on to its child blocks
				unset($saved['textshadow']);
				$converted = $this->inlinePropertyConverter->convert($saved);
			} else {
				// Text decorations and vertical-align are not inherited, but child blocks still take them (#544)
				$converted = InheritedProperties::of(
					$this->inlinePropertyConverter->convert($saved),
					array_merge(InheritedProperties::TEXT, ['TEXT-DECORATION', 'VERTICAL-ALIGN'])
				);
			}
			$this->cssProperties = array_merge($this->cssProperties, $converted); // mPDF 5.7.1
		}
	}

	/**
	 * Merge inline HTML attributes e.g. .. ALIGN="CENTER"
	 *
	 * Converts HTML attributes to CSS properties.
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @return void
	 */
	protected function mergeInlineAttributes($tag, $attr)
	{
		if (!empty($attr['DIR'])) {
			$this->cssProperties['DIRECTION'] = $attr['DIR'];
		}

		if (!empty($attr['LANG'])) {
			$this->cssProperties['LANG'] = $attr['LANG'];
		}

		if (!empty($attr['COLOR'])) {
			$this->cssProperties['COLOR'] = $attr['COLOR'];
		}

		if ($tag !== 'INPUT') {
			if (!empty($attr['WIDTH'])) {
				$this->cssProperties['WIDTH'] = $attr['WIDTH'];
			}

			if (!empty($attr['HEIGHT'])) {
				$this->cssProperties['HEIGHT'] = $attr['HEIGHT'];
			}
		}

		if ($tag === 'FONT') {
			$this->cssProperties = array_merge($this->cssProperties, $this->presentationalHints->ofFont($attr));
		}

		if (!empty($attr['VALIGN'])) {
			$this->cssProperties['VERTICAL-ALIGN'] = $attr['VALIGN'];
		}

		if (!empty($attr['VSPACE'])) {
			$this->cssProperties['MARGIN-TOP'] = $attr['VSPACE'];
			$this->cssProperties['MARGIN-BOTTOM'] = $attr['VSPACE'];
		}

		if (!empty($attr['HSPACE'])) {
			$this->cssProperties['MARGIN-LEFT'] = $attr['HSPACE'];
			$this->cssProperties['MARGIN-RIGHT'] = $attr['HSPACE'];
		}
	}

	/**
	 * Merges the presentational attributes of an element as the standard cascade reads them: only on the elements
	 * HTML gives each to. mPDF's own tags take every attribute, as they do under the legacy cascade
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @return void
	 */
	private function mergePresentationalHints($tag, array $attr)
	{
		if (in_array($tag, PresentationalHints::OWN_TAGS, true)) {
			$this->mergeInlineAttributes($tag, $attr);
			return;
		}

		$hints = $this->presentationalHints->of($tag, $attr);
		if ($hints) {
			$this->setMergedCss($hints, false);
		}
	}

	/**
	 * Merge default CSS for the tag.
	 *
	 * @param string $tag HTML tag name
	 * @return void
	 */
	protected function mergeDefaultCss($tag)
	{
		if (!isset($this->mpdf->defaultCSS[$tag])) {
			return;
		}

		$zp = $this->normalizeProperties->normalize($this->mpdf->defaultCSS[$tag]);
		if (is_array($zp)) {  // Default overwrites Inherited
			$this->cssProperties = array_merge($this->cssProperties, $zp);  // !! Note other way round !!
			$this->mergeBorderProperties($zp);
		}
	}

	/**
	 * Merge table specific CSS (CELLSPACING, CELLPADDING, and the cells' border of a table with a border attribute).
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @return void
	 */
	protected function mergeTableSpecificCss($tag, $attr)
	{
		if (!in_array($tag, ['TABLE', 'TD', 'TH'], true)) {
			return;
		}

		// cellSpacing overwrites TABLE default but not specific CSS set on table
		if ($tag === 'TABLE') {
			$cellSpacing = isset($attr['CELLSPACING']) ? $attr['CELLSPACING'] : '';
			if ($cellSpacing !== '') {
				$this->cssProperties['BORDER-SPACING-H'] = $this->cssProperties['BORDER-SPACING-V'] = $cellSpacing;
			}
			return;
		}

		$tableCell = isset($this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]) ? $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]] : [];

		// cellPadding overwrites TD/TH default but not specific CSS set on cell
		$cellPadding = isset($tableCell['cell_padding']) ? $tableCell['cell_padding'] : '';
		if (!empty($cellPadding) || $cellPadding === '0') {
			$this->cssProperties['PADDING-LEFT'] = $cellPadding;
			$this->cssProperties['PADDING-RIGHT'] = $cellPadding;
			$this->cssProperties['PADDING-TOP'] = $cellPadding;
			$this->cssProperties['PADDING-BOTTOM'] = $cellPadding;
		}

		// Only the standard cascade marks a table's cells to take the border its border attribute gives them
		if (!empty($tableCell['cell_border'])) {
			$border = $this->presentationalHints->ofCellBorder();
			$this->setMergedCss($border, false);
		}
	}

	/**
	 * Merge stylesheet selectors.
	 *
	 * Applies CSS rules from stylesheets based on tag, class, ID,
	 *
	 * @param string $tag HTML tag
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @param string $languageCode Short language code (e.g. 'en')
	 * @return void
	 */
	protected function mergeStylesheetSelectors($tag, $attr, $classes, $languageCode)
	{
		// STYLESHEET TAG e.g. h1  p  div  table
		if (isset($this->cssManager->CSS[$tag])) {
			$zp = $this->cssManager->CSS[$tag];
			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			if (is_array($zp)) {
				$this->cssProperties = array_merge($this->cssProperties, $zp);
				$this->mergeBorderProperties($zp);
			}
		}

		// STYLESHEET CLASS e.g. .smallone{}  .redletter{}
		foreach ($classes as $class) {
			$zp = [];
			if (!empty($this->cssManager->CSS['CLASS>>' . $class])) {
				$zp = $this->cssManager->CSS['CLASS>>' . $class];
			}

			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			if (is_array($zp)) {
				$this->cssProperties = array_merge($this->cssProperties, $zp);
				$this->mergeBorderProperties($zp);
			}
		}

		// STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
		if ($tag === 'TR' || $tag === 'TD' || $tag === 'TH') {
			foreach ($this->matchingNthChildRules($this->cssManager->CSS, $tag) as $zp) {
				if ($tag === 'TD' || $tag === 'TH') {
					$this->setDominanceFromProperties($zp, 9);
				}

				if (is_array($zp)) {
					$this->cssProperties = array_merge($this->cssProperties, $zp);
					$this->mergeBorderProperties($zp);
				}
			}
		}

		// STYLESHEET LANG e.g. [lang=fr]{} or :lang(fr)
		if (isset($attr['LANG'])) {
			if (!empty($this->cssManager->CSS['LANG>>' . $attr['LANG']])) {
				$zp = $this->cssManager->CSS['LANG>>' . $attr['LANG']];
				if ($tag === 'TD' || $tag === 'TH') {
					$this->setDominanceFromProperties($zp, 9);
				}

				if (is_array($zp)) {
					$this->cssProperties = array_merge($this->cssProperties, $zp);
					$this->mergeBorderProperties($zp);
				}
			} elseif (!empty($this->cssManager->CSS['LANG>>' . $languageCode])) {
				$zp = $this->cssManager->CSS['LANG>>' . $languageCode];
				if ($tag === 'TD' || $tag === 'TH') {
					$this->setDominanceFromProperties($zp, 9);
				}

				if (is_array($zp)) {
					$this->cssProperties = array_merge($this->cssProperties, $zp);
					$this->mergeBorderProperties($zp);
				}
			}
		}

		// STYLESHEET ID e.g. #smallone{}  #redletter{}
		if (!empty($attr['ID']) && !empty($this->cssManager->CSS['ID>>' . $attr['ID']])) {
			$zp = $this->cssManager->CSS['ID>>' . $attr['ID']];
			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			if (is_array($zp)) {
				$this->cssProperties = array_merge($this->cssProperties, $zp);
				$this->mergeBorderProperties($zp);
			}
		}
	}

	/**
	 * Merge tag specific selectors (Tag.Class, Tag#ID, etc.).
	 *
	 * @param string $tag HTML tag
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @param string $languageCode Short language code (e.g. 'en')
	 * @return void
	 */
	protected function mergeTagSpecificSelectors($tag, $attr, $classes, $languageCode)
	{
		// STYLESHEET CLASS e.g. p.smallone{}  div.redletter{}
		foreach ($classes as $class) {
			$zp = [];
			if (!empty($this->cssManager->CSS[$tag . '>>CLASS>>' . $class])) {
				$zp = $this->cssManager->CSS[$tag . '>>CLASS>>' . $class];
			}

			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			if (is_array($zp)) {
				$this->cssProperties = array_merge($this->cssProperties, $zp);
				$this->mergeBorderProperties($zp);
			}
		}

		// STYLESHEET LANG e.g. [lang=fr]{} or :lang(fr)
		if (isset($attr['LANG'])) {
			if (!empty($this->cssManager->CSS[$tag . '>>LANG>>' . $attr['LANG']])) {
				$zp = $this->cssManager->CSS[$tag . '>>LANG>>' . $attr['LANG']];
				if ($tag === 'TD' || $tag === 'TH') {
					$this->setDominanceFromProperties($zp, 9);
				}

				if (is_array($zp)) {
					$this->cssProperties = array_merge($this->cssProperties, $zp);
					$this->mergeBorderProperties($zp);
				}
			} elseif (!empty($this->cssManager->CSS[$tag . '>>LANG>>' . $languageCode])) {
				$zp = $this->cssManager->CSS[$tag . '>>LANG>>' . $languageCode];
				if ($tag === 'TD' || $tag === 'TH') {
					$this->setDominanceFromProperties($zp, 9);
				}

				if (is_array($zp)) {
					$this->cssProperties = array_merge($this->cssProperties, $zp);
					$this->mergeBorderProperties($zp);
				}
			}
		}

		// STYLESHEET CLASS e.g. p#smallone{}  div#redletter{}
		if (isset($attr['ID']) && !empty($this->cssManager->CSS[$tag . '>>ID>>' . $attr['ID']])) {
			$zp = $this->cssManager->CSS[$tag . '>>ID>>' . $attr['ID']];
			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			if (is_array($zp)) {
				$this->cssProperties = array_merge($this->cssProperties, $zp);
				$this->mergeBorderProperties($zp);
			}
		}

		// STYLESHEET ID WITH CLASSES e.g. #smallone.note{}  p#smallone.note{}
		foreach ($this->idClassKeys($tag, $attr['ID'], $classes) as $key) {
			if (empty($this->cssManager->CSS[$key])) {
				continue;
			}

			$zp = $this->cssManager->CSS[$key];
			if ($tag === 'TD' || $tag === 'TH') {
				$this->setDominanceFromProperties($zp, 9);
			}

			$this->cssProperties = array_merge($this->cssProperties, $zp);
			$this->mergeBorderProperties($zp);
		}
	}

	/**
	 * Merge cascaded CSS properties (BLOCK, INLINE, TABLE).
	 *
	 * @param string $inherit Inheritance context
	 * @param string $tag HTML tag
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @param string $languageCode Short language code (e.g. 'en')
	 * @return void
	 */
	protected function mergeDescendantSelectors($inherit, $tag, $attr, $classes, $languageCode)
	{
		if ($inherit === 'TOPTABLE' || $inherit === 'TABLE') {
			$this->mergeTableDescendantSelectors($tag, $attr, $classes, $languageCode);
			return;
		}

		// Content of a table cell pushes no block level, so its descendant rules are the cell's
		if (isset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl])) {
			$this->mergeDescendantCss($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl], $tag, $attr, $classes, $languageCode);
			return;
		}

		$level = $this->getBlockLevel($inherit);
		if (!isset($this->mpdf->blk[$level]['cascadeCSS'])) {
			return;
		}

		$cascadeCSS = $this->mpdf->blk[$level]['cascadeCSS'];
		$this->mergeDescendantCss($cascadeCSS, $tag, $attr, $classes, $languageCode);

		if ($this->sideEffects) {
			$this->mpdf->blk[$level]['cascadeCSS'] = $cascadeCSS;
		}
	}

	/**
	 * Merge table cascaded CSS.
	 *
	 * @param string $tag HTML tag
	 * @param array $attr HTML attributes
	 * @param array $classes Array of class names
	 * @param string $languageCode Short language code (e.g. 'en')
	 * @return void
	 */
	protected function mergeTableDescendantSelectors($tag, $attr, $classes, $languageCode)
	{
		$node = isset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1]) ? $this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1] : [];
		if (empty($node)) {
			return;
		}

		// don't check for 'depth' and do set border dominance
		$this->setMergedCss($node[$tag], false, 9);
		foreach ($classes as $class) {
			$this->setMergedCss($node['CLASS>>' . $class], false, 9);
		}

		// STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
		if ($tag === 'TR' || $tag === 'TD' || $tag === 'TH') {
			foreach ($this->matchingNthChildRules($node, $tag) as $rule) {
				$this->setMergedCss($rule, false, 9);
			}
		}

		if ($attr['LANG'] !== '') {
			$this->setMergedCss($node[$this->langKey($node, 'LANG>>', $attr['LANG'], $languageCode)], false, 9);
		}

		$this->setMergedCss($node['ID>>' . $attr['ID']], false, 9);
		foreach ($classes as $class) {
			$this->setMergedCss($node[$tag . '>>CLASS>>' . $class], false, 9);
		}

		if ($attr['LANG'] !== '') {
			$this->setMergedCss($node[$this->langKey($node, $tag . '>>LANG>>', $attr['LANG'], $languageCode)], false, 9);
		}

		$this->setMergedCss($node[$tag . '>>ID>>' . $attr['ID']], false, 9);
		foreach ($this->idClassKeys($tag, $attr['ID'], $classes) as $key) {
			if (isset($node[$key])) {
				$this->setMergedCss($node[$key], false, 9);
			}
		}

		if ($this->sideEffects) {
			$this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl - 1] = $node;
		}
	}

	/**
	 * Apply descendant CSS rules.
	 *
	 * @param array $cascadeCSS
	 * @param string $tag
	 * @param array $attr
	 * @param array $classes
	 * @param string $languageCode Short language code (e.g. 'en')
	 */
	protected function mergeDescendantCss($cascadeCSS, $tag, $attr, $classes, $languageCode)
	{
		if (empty($cascadeCSS)) {
			return;
		}

		$this->setMergedCss($cascadeCSS[$tag]);
		foreach ($classes as $class) {
			$this->setMergedCss($cascadeCSS['CLASS>>' . $class]);
		}

		if ($attr['LANG'] !== '') {
			$this->setMergedCss($cascadeCSS[$this->langKey($cascadeCSS, 'LANG>>', $attr['LANG'], $languageCode)]);
		}

		$this->setMergedCss($cascadeCSS['ID>>' . $attr['ID']]);
		foreach ($classes as $class) {
			$this->setMergedCss($cascadeCSS[$tag . '>>CLASS>>' . $class]);
		}

		if ($attr['LANG'] !== '') {
			$this->setMergedCss($cascadeCSS[$this->langKey($cascadeCSS, $tag . '>>LANG>>', $attr['LANG'], $languageCode)]);
		}

		$this->setMergedCss($cascadeCSS[$tag . '>>ID>>' . $attr['ID']]);
		foreach ($this->idClassKeys($tag, $attr['ID'], $classes) as $key) {
			if (isset($cascadeCSS[$key])) {
				$this->setMergedCss($cascadeCSS[$key]);
			}
		}
	}

	/**
	 * The key of the descendant rule for an element's language: its full language (fr-ca) if a rule names it, or
	 * failing that its short language code (fr), the same fallback the simple lang rules make
	 *
	 * @param array $rules Descendant rules lifted from the ancestors
	 * @param string $prefix 'LANG>>', or the tag followed by '>>LANG>>'
	 * @param string $lang The element's lang attribute, lowercased
	 * @param string $languageCode Short language code (e.g. 'en')
	 * @return string
	 */
	private function langKey($rules, $prefix, $lang, $languageCode)
	{
		if ($languageCode !== '' && !isset($rules[$prefix . $lang]['depth'])) {
			return $prefix . $languageCode;
		}

		return $prefix . $lang;
	}

	/**
	 * The keys of the rules for an id with classes that can match an element, in the order they apply: for each
	 * combination of its classes, #id.class and then tag#id.class
	 *
	 * @param string $tag
	 * @param string $id
	 * @param string[] $classes Combinations of the element's classes, as merge() builds them
	 * @return string[]
	 */
	private function idClassKeys($tag, $id, $classes)
	{
		$keys = [];
		if ($id === '') {
			return $keys;
		}

		foreach ($classes as $class) {
			$keys[] = 'ID>>' . $id . '>>CLASS>>' . $class;
			$keys[] = $tag . '>>ID>>' . $id . '>>CLASS>>' . $class;
		}

		return $keys;
	}

	/**
	 * Merge CSS properties into target array.
	 *
	 * Internal method to merge CSS properties from source into target.
	 * Used for CSS cascading.
	 *
	 * @param array $property Source CSS properties
	 * @param array $target Target CSS properties (modified by reference)
	 * @return void
	 */
	protected function mergeCssProperties($property, &$target)
	{
		if (empty($property)) {
			return;
		}

		$target = $target ? Arrays::uniqueRecursiveMerge($target, $property) : $property;
	}

	/**
	 * Merge Nth-child CSS selectors.
	 *
	 * Handles :nth-child() pseudo-class logic for TR, TD, and TH tags.
	 *
	 * @param array $sourceSelectors Source CSS selector array
	 * @param array $targetProperties Target CSS properties (passed by reference)
	 * @param string $tag HTML tag name
	 * @return void
	 */
	protected function mergeNthChildCss($sourceSelectors, &$targetProperties, $tag)
	{
		if (!in_array($tag, ['TR', 'TH', 'TD'], true) || empty($sourceSelectors)) {
			return;
		}

		foreach ($this->matchingNthChildRules($sourceSelectors, $tag) as $rule) {
			$this->mergeCssProperties($rule, $targetProperties);
		}
	}

	/**
	 * The nth-child rules in a node of the stylesheet that match the row or cell being opened.
	 *
	 * Only the nth-child keys CssManager::readCss() recorded are looked up, each directly in the node, so a node must
	 * come from the stylesheet it read. The row or cell is counted by its place among its element siblings on the
	 * stack of open elements: a row within its thead, tbody or tfoot, or the tbody a row written straight into the
	 * table is put in, and a cell among the cells of its row, whatever grid columns a colspan or a rowspan takes up.
	 * Where several rules match, a later rule overrides an earlier one, so they are returned in the order the node
	 * holds them.
	 *
	 * @param array $node CssManager::$CSS, or a level of the descendant rules
	 * @param string $tag TR, TD or TH
	 * @return array[] The properties of each matching rule, by key
	 */
	private function matchingNthChildRules($node, $tag)
	{
		$formulas = array_intersect_key($this->cssManager->getNthChildFormulas($tag), $node);
		if (!$formulas) {
			return [];
		}

		$nthChild = $this->mpdf->getStyledElementNthChild();
		if ($nthChild === null) {
			return [];
		}

		$rules = [];
		foreach ($formulas as $key => $parts) {
			if ($this->selectorParser->matchesNthChild($parts, $nthChild - 1)) {
				$rules[$key] = $node[$key];
			}
		}

		// The index holds the keys in the order the whole stylesheet first used them, which need not be this node's
		return count($rules) > 1 ? array_intersect_key($node, $rules) : $rules;
	}

	/**
	 * Merge full CSS rules including tag, class, ID, and lang selectors.
	 *
	 * Applies CSS rules from various selector types (tag, class, ID, language)
	 * to the target CSS properties array. Handles CSS cascading and specificity.
	 *
	 * @param array $p Source CSS selector array
	 * @param array $t Target CSS properties (modified by reference)
	 * @param string $tag HTML tag name
	 * @param array $classes Array of class names
	 * @param string $id Element ID
	 * @param string $lang Language code
	 * @return void
	 */
	protected function mergeFullCssRules($p, &$t, $tag, $classes, $id, $lang)
	{
		// mPDF 6
		if (isset($p[$tag])) {
			$this->mergeCssProperties($p[$tag], $t);
		}

		// STYLESHEET CLASS e.g. .smallone{}  .redletter{}
		foreach ($classes as $class) {
			if (isset($p['CLASS>>' . $class])) {
				$this->mergeCssProperties($p['CLASS>>' . $class], $t);
			}
		}

		// STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
		$this->mergeNthChildCss($p, $t, $tag);

		// STYLESHEET CLASS e.g. [lang=fr]{} or :lang(fr)
		if (isset($lang) && isset($p['LANG>>' . $lang])) {
			$this->mergeCssProperties($p['LANG>>' . $lang], $t);
		}

		// STYLESHEET CLASS e.g. #smallone{}  #redletter{}
		if (isset($id) && isset($p['ID>>' . $id])) {
			$this->mergeCssProperties($p['ID>>' . $id], $t);
		}

		// STYLESHEET CLASS e.g. .smallone{}  .redletter{}
		foreach ($classes as $class) {
			if (isset($p[$tag . '>>CLASS>>' . $class])) {
				$this->mergeCssProperties($p[$tag . '>>CLASS>>' . $class], $t);
			}
		}

		// STYLESHEET CLASS e.g. [lang=fr]{} or :lang(fr)
		if (isset($lang) && isset($p[$tag . '>>LANG>>' . $lang])) {
			$this->mergeCssProperties($p[$tag . '>>LANG>>' . $lang], $t);
		}

		// STYLESHEET CLASS e.g. #smallone{}  #redletter{}
		if (isset($id) && isset($p[$tag . '>>ID>>' . $id])) {
			$this->mergeCssProperties($p[$tag . '>>ID>>' . $id], $t);
		}

		foreach ($this->idClassKeys($tag, $id, $classes) as $key) {
			if (isset($p[$key])) {
				$this->mergeCssProperties($p[$key], $t);
			}
		}
	}

	/**
	 * Merge inline style attribute CSS.
	 *
	 * @param string $tag HTML tag name
	 * @param array $attr HTML attributes
	 * @return void
	 */
	protected function mergeInlineStyle($tag, $attr)
	{
		// INLINE STYLE e.g. style="CSS:property"
		if (!isset($attr['STYLE'])) {
			return;
		}

		$zp = $this->inlineStyleParser->parse($attr['STYLE']);
		if ($tag === 'TD' || $tag === 'TH') {
			$this->setDominanceFromProperties($zp, 9);
		}

		if (is_array($zp)) {
			$this->cssProperties = array_merge($this->cssProperties, $zp);
			$this->mergeBorderProperties($zp);
		}
	}

	/**
	 * Merge CSS properties with existing properties.
	 *
	 * @param array $property Source CSS properties
	 * @param bool $strictMode Use default strict mode
	 * @param bool|int $borderDominanceLevel Border dominance level (or false)
	 * @return void
	 */
	protected function setMergedCss(&$property, $strictMode = true, $borderDominanceLevel = false)
	{
		if (!isset($property)) {
			return;
		}

		$depth = isset($property['depth']) ? $property['depth'] : 0;
		if ($depth < 2 && $strictMode) {
			return;
		}

		if ($borderDominanceLevel) {
			$this->setDominanceFromProperties($property, $borderDominanceLevel);
		}

		if (is_array($property)) {
			$this->cssProperties = array_merge($this->cssProperties, $property);
			$this->mergeBorderProperties($property);
		}
	}

	/**
	 * Merge borders into CSS properties.
	 *
	 * @param array $properties properties to merge
	 * @return void
	 */
	protected function mergeBorderProperties($properties)
	{
		$this->borderMerger->mergeBorderProperties($properties, $this->cssProperties, BorderMerger::initialColor($this->mpdf->cssMode));
	}

	/**
	 * Set border dominance level for table cells.
	 *
	 * Used in table rendering to determine which cell borders take
	 * precedence when cells share borders.
	 *
	 * @param array $prop CSS properties containing border definitions
	 * @param int $val Dominance level value
	 * @return void
	 */
	public function setDominanceFromProperties($prop, $val)
	{
		if (!$this->sideEffects) {
			return;
		}

		$this->borderMerger->setDominanceFromProperties($prop, $val);
	}

	/**
	 * Set border dominance level for a specific side.
	 *
	 * @param string $side T|R|B|L
	 * @param int $val Dominance value
	 * @throws InvalidArgumentException
	 */
	public function setBorderDominance($side, $val)
	{
		$this->borderMerger->setBorderDominance($side, $val);
	}

	/**
	 * Get border dominance level for a specific side.
	 *
	 * @param string $side T|R|B|L
	 * @return int Dominance value
	 */
	public function getBorderDominance($side)
	{
		return $this->borderMerger->getBorderDominance($side);
	}

	/**
	 * @return int
	 */
	protected function getBlockLevel($inherit = 'BLOCK')
	{
		if (!$this->sideEffects || $inherit !== 'BLOCK') {
			return $this->mpdf->blklvl;
		}

		return $this->mpdf->blklvl - 1;
	}
}
