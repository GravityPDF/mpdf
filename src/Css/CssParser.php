<?php

namespace Mpdf\Css;

use Mpdf\Utils\Arrays;
use Mpdf\Utils\Path;
use Mpdf\CssMode;
use Mpdf\Mpdf;
use Mpdf\Cache;
use Mpdf\SizeConverter;
use Mpdf\Color\ColorConverter;
use Mpdf\AssetFetcher;

class CssParser
{
	/**
	 * Whitespace, each character of which is read as a space in a selector or declaration
	 */
	const WHITESPACE = '/\s/';

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var CssLoader
	 */
	private $cssLoader;

	/**
	 * @var MediaQueryProcessor
	 */
	private $mediaQueryProcessor;

	/**
	 * @var StylesheetTokenizer
	 */
	private $tokenizer;

	/**
	 * @var CommentParser
	 */
	private $commentParser;

	/**
	 * @var InlineStyleParser
	 */
	private $inlineStyleParser;

	/**
	 * @var SelectorParser
	 */
	private $selectorParser;

	/**
	 * @var SelectorCompiler
	 */
	private $selectorCompiler;

	/**
	 * @var NormalizeProperties
	 */
	private $normalizeProperties;

	/**
	 * @var ShadowParser
	 */
	private $shadowParser;

	/**
	 * CSS for simple selectors.
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
	private $css = [];

	/**
	 * @var array[] The properties the !important declarations of the rules in $css set, under the same keys. Only
	 *              standard mode keeps them apart
	 */
	private $importantCss = [];

	/**
	 * CSS for cascaded selectors.
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
	private $cascadeCSS = [];

	/**
	 * @var array An index used to filter redundant class names before passing to Arrays::allUniqueSortedCombinations
	 */
	private $usedClassNames = [];

	/**
	 * @var int Maximum number of classes found in a single compound selector of a stored rule, e.g. 2 for .a.b
	 */
	private $maxClassDepth = 1;

	/**
	 * @var array[] The nth-child keys of stored rules by tag (TR, TD or TH), e.g. TD>>SELECTORNTHCHILD>>2N+1, each mapped
	 *              to its formula split as SelectorParser::matchesNthChild() takes it. The merger looks each key up
	 *              directly in a node of the stylesheet.
	 */
	private $nthChildFormulas = ['TR' => [], 'TD' => [], 'TH' => []];

	/**
	 * @var array[] The rules of the last CSS parsed for the matcher, each compiled: [compiled selector, declarations,
	 *              !important declarations], in the order they were written
	 */
	private $compiledRules = [];

	public function __construct(
		Mpdf $mpdf,
		Cache $cache,
		SizeConverter $sizeConverter,
		ColorConverter $colorConverter,
		AssetFetcher $assetFetcher
	) {
		$this->mpdf = $mpdf;
		$this->normalizeProperties = new NormalizeProperties($mpdf, $sizeConverter, $colorConverter);
		$this->mediaQueryProcessor = new MediaQueryProcessor($mpdf, $sizeConverter);
		$this->cssLoader = new CssLoader($mpdf, $assetFetcher, $cache, $this->mediaQueryProcessor);
		$this->tokenizer = new StylesheetTokenizer();
		$this->commentParser = new CommentParser();
		$this->inlineStyleParser = new InlineStyleParser($this->normalizeProperties);
		$this->selectorParser = new SelectorParser($mpdf);
		$this->selectorCompiler = new SelectorCompiler($mpdf);
		$this->shadowParser = new ShadowParser($mpdf, $sizeConverter, $colorConverter);
	}

	/**
	 * Read and parse CSS from HTML content.
	 *
	 * @param string $html HTML content containing CSS
	 * @return string
	 */
	public function parse($html)
	{
		$this->css = [];
		$this->importantCss = [];
		$this->cascadeCSS = [];
		$this->compiledRules = [];

		$ind = 0;
		$stylesheets = [];

		$html = $this->mediaQueryProcessor->filterByMediaQuery($html, '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is');
		$html = $this->mediaQueryProcessor->filterByMediaQuery($html, '/<link[^>]*media=["\']([^"\'>]*)["\'].*?>/is');
		$html = $this->commentParser->removeCommentsFromStyleBlocks($html);
		$html = $this->commentParser->removeHtmlComments($html);

		$externalCss = $this->cssLoader->extractExternalStylesheetUrls($html);
		$externalCssCount = count($externalCss);
		while ($externalCssCount) {
			$path = htmlspecialchars_decode($externalCss[$ind]);
			$path = Path::relativeToAbsolutePath($path, $this->mpdf->basepath);
			if (strpos($path, '//') === false) { // mPDF 5.7.3
				$path = preg_replace('/\.css\?.*$/', '.css', $path);
			}

			$stylesheetCss = $this->cssLoader->loadStylesheet($path);
			if ($stylesheetCss) {
				$stylesheets[] = $this->cssLoader->processExternalCssImports($stylesheetCss, $path, $externalCss, $externalCssCount);
			}

			$externalCssCount--;
			$ind++;
		}

		// CSS as <style> in HTML document
		if (preg_match_all('/<style.*?>(.*?)<\/style>/si', $html, $cssBlock)) {
			foreach ($cssBlock[1] as $css) {
				$stylesheets[] = $this->cssLoader->resolveBackgroundUrls($css);
			}
		}

		// Each is read on its own, so a block or comment one leaves open ends with it
		foreach ($stylesheets as $css) {
			$this->processCssString($this->tokenizer->removeComments($css));
		}

		// Remove CSS (tags and content), if any (it can be <style> or <style type="txt/css">)
		$html = preg_replace('/<style.*?>(.*?)<\/style>/si', '', $html);

		return $html;
	}

	/**
	 * @return array
	 */
	public function getCss()
	{
		return $this->css;
	}

	/**
	 * The properties the !important declarations of the last CSS parsed set, under the keys getCss() uses. Legacy mode
	 * reads those declarations as any other, into getCss(), and leaves this empty
	 *
	 * @return array[]
	 */
	public function getImportantCss()
	{
		return $this->importantCss;
	}

	/**
	 * @return array
	 */
	public function getCascadeCss()
	{
		return $this->cascadeCSS;
	}

	/**
	 * The rules of the last CSS parsed for the matcher, in standard mode. Legacy mode compiles none
	 *
	 * @return array[] Each [compiled selector, declarations, !important declarations], in the order they were written
	 */
	public function getCompiledRules()
	{
		return $this->compiledRules;
	}

	/**
	 * @return array
	 */
	public function getUsedClassNames()
	{
		return array_keys($this->usedClassNames);
	}

	/**
	 * @return int
	 */
	public function getMaxClassDepth()
	{
		return $this->maxClassDepth;
	}

	/**
	 * The nth-child keys of stored rules for a tag
	 *
	 * @param string $tag TR, TD or TH
	 * @return array[] Each key, e.g. TD>>SELECTORNTHCHILD>>2N+1, mapped to its formula's parts for
	 *                 SelectorParser::matchesNthChild()
	 */
	public function getNthChildFormulas($tag)
	{
		return $this->nthChildFormulas[$tag];
	}

	/**
	 * Reads each rule in a list of rules, unwrapping the @media blocks that match, and @supports and @layer blocks.
	 *
	 * An @supports condition is taken to pass, as its rules are unwrapped, so an @supports not block, the fallback for
	 * engines without a feature, is left out. Every other at-rule is left out, @page rules apart.
	 *
	 * @param string $css A stylesheet, or the content of an at-rule's block, with its comments removed
	 * @return void
	 */
	private function processCssString($css)
	{
		foreach ($this->tokenizer->rules($css) as $rule) {
			list($name, $prelude, $block) = $rule;

			if ($block === null) {
				continue;
			}

			if ($name === null || $name === 'page') {
				$this->processRule($name === null ? $prelude : '@page ' . $prelude, $block);
			} elseif (($name === 'media' && $this->mediaQueryProcessor->matches($prelude))
				|| ($name === 'supports' && !preg_match('/^not\b/i', $prelude))
				|| $name === 'layer'
			) {
				$this->processCssString($block);
			}
		}
	}

	/**
	 * Stores the declarations of a rule under each selector in its list
	 *
	 * @param string $selectors
	 * @param string $declarations
	 * @return void
	 */
	private function processRule($selectors, $declarations)
	{
		list($classProperties, $important) = $this->parseCssPropertiesByImportance($declarations);

		if (strpbrk($selectors, "\t\n\r\f\v") !== false) {
			$selectors = preg_replace(self::WHITESPACE, ' ', $selectors);
		}

		foreach ($this->selectorCompiler->splitList($selectors) as $selector) {
			$this->processCssSelector($selector, $classProperties, $important);
		}
	}

	/**
	 * Process a CSS selector.
	 *
	 * @param string $written Selector string, as written
	 * @param array $classProperties CSS properties
	 * @param array $important The properties its !important declarations set, which only standard mode reads apart
	 * @return void
	 */
	private function processCssSelector($written, $classProperties, array $important)
	{
		$selector = strtoupper($written);

		// store classes in an index for faster lookups
		if (strpos($selector, '.') !== false && preg_match_all('/\.([a-zA-Z0-9_\-]+)/', $selector, $matches)) {
			foreach ($matches[1] as $className) {
				$this->usedClassNames[$className] = true;
			}
		}

		// Close up each nth-child argument, e.g. (2N + 1), so the selector still splits into its parts on whitespace
		$selector = preg_replace_callback('/NTH-CHILD\(([^)]*)\)/', function ($m) {
			return 'NTH-CHILD(' . preg_replace('/\s+/', '', $m[1]) . ')';
		}, $selector);

		$tags = preg_split('/\s+/', trim($selector));
		$level = count($tags);
		if (trim($tags[0]) === '@PAGE') {
			$tag = $this->selectorParser->parsePageSelector($tags);
			if ($tag) {
				$this->storeByKey($tag, $classProperties, $important);
			}

			return;
		}

		$tag = $level === 1 ? $this->selectorParser->parseSimpleSelector($tags) : null;
		$simple = $tag !== null && $this->isLegacySelector([$tag]);

		// Legacy mode applies simple rules from these keys, and standard mode keeps them for what reads
		// CssManager::$CSS directly: BODY, and SVG's classes
		if ($simple) {
			$this->storeByKey($tag, $classProperties, $important);
			$this->indexStoredKey($tag);
		}

		if ($this->mpdf->cssMode === CssMode::STANDARD) {
			$this->compileRule($written, $classProperties, $important);

			return;
		}

		// Legacy mode drops a rule the legacy parser cannot read
		if ($level === 1) {
			return;
		}

		$cascade = $this->selectorParser->parseCascadedSelector($tags);
		if (!$this->isLegacySelector($cascade)) {
			return;
		}

		$cascadeCSS = &$this->cascadeCSS;
		foreach ($cascade as $tag) {
			$cascadeCSS = &$cascadeCSS[$tag];
			$this->indexStoredKey($tag);
		}

		$cascadeCSS = Arrays::uniqueRecursiveMerge($cascadeCSS, $classProperties);
		$cascadeCSS['depth'] = $level;
	}

	/**
	 * Stores a rule under its key: an @page rule, or one whose selector is one compound the legacy parser reads
	 *
	 * @param string $key A key SelectorParser made, e.g. P, CLASS>>A or @PAGE
	 * @param array $classProperties
	 * @param array $important The properties its !important declarations set
	 * @return void
	 */
	private function storeByKey($key, array $classProperties, array $important)
	{
		$this->css[$key] = Arrays::uniqueRecursiveMerge(isset($this->css[$key]) ? $this->css[$key] : [], $classProperties);

		if ($important) {
			$this->importantCss[$key] = Arrays::uniqueRecursiveMerge(isset($this->importantCss[$key]) ? $this->importantCss[$key] : [], $important);
		}
	}

	/**
	 * Record what the merger needs to know about the key of one compound selector of a stored rule: how many classes
	 * it names, and its nth-child formula.
	 *
	 * @param string $key A key SelectorParser::parseSimpleSelector() made, e.g. P>>CLASS>>A.B or TD>>SELECTORNTHCHILD>>2N+1
	 * @return void
	 */
	private function indexStoredKey($key)
	{
		$classes = strpos($key, 'CLASS>>');
		if ($classes !== false) {
			$this->maxClassDepth = max($this->maxClassDepth, substr_count($key, '.', $classes) + 1);
		}

		if (preg_match('/^(TR|TD|TH)>>SELECTORNTHCHILD>>(.*)$/', $key, $m) && !isset($this->nthChildFormulas[$m[1]][$key])) {
			preg_match('/^' . SelectorParser::NTH_CHILD_FORMULA . '$/', $m[2], $parts);
			$this->nthChildFormulas[$m[1]][$key] = $parts;
		}
	}

	/**
	 * Whether the legacy parser reads a selector into levels the merger applies. It keeps an nth-child level for any
	 * tag, but the merger only looks one up for a table row or cell
	 *
	 * @param string[] $levels The keys SelectorParser gave for the selector's parts, none if it could not read one
	 * @return bool
	 */
	private function isLegacySelector(array $levels)
	{
		foreach ($levels as $level) {
			$nthChild = strpos($level, '>>SELECTORNTHCHILD>>');
			if ($nthChild !== false && !in_array(substr($level, 0, $nthChild), ['TR', 'TD', 'TH'], true)) {
				return false;
			}
		}

		return (bool) $levels;
	}

	/**
	 * Compiles a rule for the matcher, if its selector is one it can match
	 *
	 * @param string $selector As written
	 * @param array $classProperties
	 * @param array $important The properties its !important declarations set
	 * @return void
	 */
	private function compileRule($selector, array $classProperties, array $important)
	{
		if (!$classProperties && !$important) {
			return;
		}

		$compiled = $this->selectorCompiler->compile($selector);

		// The universal selector changes which elements existing documents style, so it waits for #530
		if ($compiled !== null && !$compiled['universal']) {
			$this->compiledRules[] = [$compiled, $classProperties, $important];
		}
	}

	/**
	 * Parse CSS property string into an array.
	 *
	 * @param string $rawStyles The declarations of a block, with its comments removed (e.g. "color: red; font-size: 12px")
	 * @return array Associative array of CSS properties
	 */
	public function parseCssProperties($rawStyles)
	{
		return $this->inlineStyleParser->parseDeclarations($this->declarationsOf($rawStyles));
	}

	/**
	 * The properties the normal declarations of a block set, and those its !important ones set. Legacy mode reads a
	 * declaration marked !important as any other
	 *
	 * @param string $rawStyles The declarations of a block, with its comments removed
	 * @return array[] [normal properties, important properties]
	 */
	private function parseCssPropertiesByImportance($rawStyles)
	{
		if ($this->mpdf->cssMode === CssMode::STANDARD) {
			return $this->inlineStyleParser->parseDeclarationsByImportance($this->declarationsOf($rawStyles));
		}

		return [$this->parseCssProperties($rawStyles), []];
	}

	/**
	 * @param string $rawStyles The declarations of a block, with its comments removed
	 * @return string[][] Each [name, value], with the whitespace in the value read as spaces and its data URI images
	 *                    stored
	 */
	private function declarationsOf($rawStyles)
	{
		$declarations = $this->tokenizer->declarations($rawStyles);
		foreach ($declarations as &$declaration) {
			if (strpbrk($declaration[1], "\t\n\r\f\v") !== false) {
				$declaration[1] = preg_replace(self::WHITESPACE, ' ', $declaration[1]);
			}

			if (stripos($declaration[1], 'url(data:') !== false) {
				$declaration[1] = $this->cssLoader->processDataUriImages($declaration[1]);
			}
		}
		unset($declaration);

		return $declarations;
	}

	/**
	 * Parse inline CSS style attribute.
	 *
	 * @param string $html CSS string from style attribute
	 * @return array Parsed CSS properties
	 */
	public function parseInlineCss($html)
	{
		return $this->inlineStyleParser->parse($html);
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
	public function parseBoxShadow($value)
	{
		return $this->shadowParser->parseBoxShadow($value);
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
	public function parseTextShadow($value)
	{
		return $this->shadowParser->parseTextShadow($value);
	}
}
