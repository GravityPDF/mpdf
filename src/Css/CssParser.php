<?php

namespace Mpdf\Css;

use Mpdf\Utils\Arrays;
use Mpdf\Utils\Path;
use Mpdf\Mpdf;
use Mpdf\Cache;
use Mpdf\SizeConverter;
use Mpdf\Color\ColorConverter;
use Mpdf\AssetFetcher;

class CssParser
{
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
	 * @var AtRuleProcessor
	 */
	private $atRuleProcessor;

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
	 *              whether the legacy parser stores it too], in the order they were written
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
		$this->atRuleProcessor = new AtRuleProcessor($this->mediaQueryProcessor);
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
		$this->cascadeCSS = [];
		$this->compiledRules = [];

		$ind = 0;
		$css = '';

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
				$css .= $this->cssLoader->processExternalCssImports($stylesheetCss, $path, $externalCss, $externalCssCount);
			}

			$externalCssCount--;
			$ind++;
		}

		// CSS as <style> in HTML document
		$regexp = '/<style.*?>(.*?)<\/style>/si';
		if (preg_match_all($regexp, $html, $cssBlock)) {
			$css .= ' ' . $this->cssLoader->resolveBackgroundUrls(implode(' ', $cssBlock[1]));
		}

		$css = preg_replace('|/\*.*?\*/|s', ' ', $css);
		$css = preg_replace('/(<\!\-\-|\-\->)/s', ' ', $css);
		$css = $this->atRuleProcessor->process($css);
		$css = preg_replace('/[\s\n\r\t\f]/s', ' ', $css);
		$css = $this->cssLoader->processDataUriImages($css);
		$css = $this->inlineStyleParser->processUrlsInCss($css);

		$this->processCssString($css);

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
	 * @return array
	 */
	public function getCascadeCss()
	{
		return $this->cascadeCSS;
	}

	/**
	 * The rules of the last CSS parsed for the matcher: those whose selector the legacy parser cannot read, and the
	 * descendant and :lang() rules it stores, which the matcher applies where the legacy engine cannot match them.
	 * Under the standard cascade, every rule
	 *
	 * @return array[] Each [compiled selector, declarations, whether the legacy parser stores it too], in the order
	 *                 they were written
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
	 * @param string $css
	 * @return void
	 */
	private function processCssString($css)
	{
		preg_match_all('/(.*?)\{(.*?)\}/', $css, $styles);
		$count = count($styles[1]);
		for ($i = 0; $i < $count; $i++) {
			$classProperties = $this->parseCssProperties($styles[2][$i]);

			foreach ($this->selectorCompiler->splitList($styles[1][$i]) as $selector) {
				$this->processCssSelector($selector, $classProperties);
			}
		}
	}

	/**
	 * Process a CSS selector.
	 *
	 * @param string $written Selector string, as written
	 * @param array $classProperties CSS properties
	 * @return void
	 */
	private function processCssSelector($written, $classProperties)
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
			if ($tag && isset($this->css[$tag])) {
				$this->css[$tag] = Arrays::uniqueRecursiveMerge($this->css[$tag], $classProperties);
			} elseif ($tag) {
				$this->css[$tag] = $classProperties;
			}

			return;
		}

		if ($this->mpdf->cssCascade === 'standard') {
			$this->compileRule($written, $classProperties);

			// Simple rules are still stored by key, for what reads CssManager::$CSS directly: BODY, and SVG's classes
			$tag = $level === 1 ? $this->selectorParser->parseSimpleSelector($tags) : null;
			if ($tag !== null && $this->isLegacySelector([$tag])) {
				$this->storeSimpleRule($tag, $classProperties);
			}

			return;
		}

		if ($level === 1) {
			$tag = $this->selectorParser->parseSimpleSelector($tags);
			if (!$this->isLegacySelector($tag === null ? [] : [$tag])) {
				$this->compileRule($written, $classProperties);
				return;
			}

			// The legacy engine only matches :lang() against an element's own lang attribute, not one it inherits
			if (strpos($selector, ':LANG(') !== false) {
				$this->compileRule($written, $classProperties, true);
			}

			$this->storeSimpleRule($tag, $classProperties);

			return;
		}

		$cascade = $this->selectorParser->parseCascadedSelector($tags);
		if (!$this->isLegacySelector($cascade)) {
			$this->compileRule($written, $classProperties);
			return;
		}

		$cascadeCSS = &$this->cascadeCSS;
		foreach ($cascade as $tag) {
			$cascadeCSS = &$cascadeCSS[$tag];
			$this->indexStoredKey($tag);
		}

		$cascadeCSS = Arrays::uniqueRecursiveMerge($cascadeCSS, $classProperties);
		$cascadeCSS['depth'] = $level;

		// The legacy engine only looks for the ancestors a descendant rule names among blocks and table parts
		$this->compileRule($written, $classProperties, true);
	}

	/**
	 * Stores a rule whose selector is one compound the legacy parser reads, under its key
	 *
	 * @param string $key A key SelectorParser::parseSimpleSelector() made, e.g. P or CLASS>>A
	 * @param array $classProperties
	 * @return void
	 */
	private function storeSimpleRule($key, array $classProperties)
	{
		if (isset($this->css[$key])) {
			$this->css[$key] = Arrays::uniqueRecursiveMerge($this->css[$key], $classProperties);
		} else {
			$this->css[$key] = $classProperties;
		}

		$this->indexStoredKey($key);
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
	 * @param bool $legacy Whether the legacy parser stores it too
	 * @return void
	 */
	private function compileRule($selector, array $classProperties, $legacy = false)
	{
		if (!$classProperties) {
			return;
		}

		$compiled = $this->selectorCompiler->compile($selector);

		// The universal selector changes which elements existing documents style, so it waits for #530 and the
		// standard cascade option
		if ($compiled !== null && !$compiled['universal']) {
			$this->compiledRules[] = [$compiled, $classProperties, $legacy];
		}
	}

	/**
	 * Parse CSS property string into an array.
	 *
	 * @param string $rawStyles CSS style string (e.g. "color: red; font-size: 12px")
	 * @return array Associative array of CSS properties
	 */
	public function parseCssProperties($rawStyles)
	{
		$classProperties = [];
		$styles = explode(';', trim($rawStyles));

		foreach ($styles as $style) {
			if (empty(trim($style))) {
				continue;
			}

			// Changed to allow style="background: url('http://www.bpm1.com/bg.jpg')"
			$tmp = explode(':', $style, 2);
			$property = strtoupper(trim($tmp[0]));
			$value = isset($tmp[1]) ? $tmp[1] : '';

			$value = str_replace('%ZZ', ';', $value); // restore URL placeholder
			$value = preg_replace('/\s*!important/i', '', $value);
			$value = trim($value);

			if (empty($property) || strlen($value) === 0) {
				continue;
			}

			// Ignores -webkit-gradient so doesn't override -moz-
			if (($property === 'BACKGROUND-IMAGE' || $property === 'BACKGROUND') &&
				stripos($value, '-webkit-gradient') !== false
			) {
				continue;
			}

			// Dropped before it can replace an earlier declaration of the property in the same block
			if (!$this->normalizeProperties->canParse($property, $value)) {
				continue;
			}

			// A repeated property moves to its last place, so it is expanded after a shorthand written before it
			unset($classProperties[$property]);
			$classProperties[$property] = $value;
		}

		return $this->normalizeProperties->normalize($classProperties);
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
