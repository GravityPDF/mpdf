<?php

namespace Mpdf\Css;

use Mpdf\AssetFetcher;
use Mpdf\Cache;
use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\CssMode;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class CssParserTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	/** @var CssParser */
	private $parser;

	/** @var Mpdf */
	private $mpdf;

	protected function set_up()
	{
		parent::set_up();

		$logger = new NullLogger();
		// The legacy cascade stores rules by key and in the descendant tree as well as compiling them
		$this->mpdf = new Mpdf(['cssMode' => CssMode::LEGACY]);
		$this->mpdf->setLogger($logger);

		$assetFetcher = $this->getMockBuilder(AssetFetcher::class)
			->disableOriginalConstructor()
			->getMock();

		$cache = $this->getMockBuilder(Cache::class)
			->disableOriginalConstructor()
			->getMock();

		$sizeConverter = new SizeConverter($this->mpdf->dpi, $this->mpdf->default_font_size, $this->mpdf, $logger);
		$colorModeConverter = new ColorModeConverter();
		$colorSpaceRestrictor = new ColorSpaceRestrictor($this->mpdf, $colorModeConverter);
		$colorConverter = new ColorConverter($this->mpdf, $colorModeConverter, $colorSpaceRestrictor);

		$this->parser = new CssParser(
			$this->mpdf,
			$cache,
			$sizeConverter,
			$colorConverter,
			$assetFetcher
		);
	}

	public function tear_down()
	{
		unset($this->mpdf, $this->parser);

		parent::tear_down();
	}

	public function testParseCssProperties()
	{
		$css = 'color: red; font-size: 14px';
		$result = $this->parser->parseCssProperties($css);

		$this->assertEquals('red', $result['COLOR']);
		$this->assertEquals('14px', $result['FONT-SIZE']);
	}

	/**
	 * A declaration mPDF cannot read is dropped, so an earlier one of the same property in the block still applies
	 */
	public function testParseCssPropertiesDropsADeclarationItCannotRead()
	{
		$result = $this->parser->parseCssProperties('width: 50%; width: calc(100% - 10mm); color: #00f; color: bogus; margin: 10vw 1,5mm');

		$this->assertSame('50%', $result['WIDTH']);
		$this->assertSame('#00f', $result['COLOR']);
		$this->assertArrayNotHasKey('MARGIN-TOP', $result);
	}

	public function testParseSimpleSelector()
	{
		$html = '<style>p { color: red; }</style>';
		$parsedHtml = $this->parser->parse($html);

		$this->assertIsString($parsedHtml);
		$this->assertStringNotContainsString('<style>', $parsedHtml);

		$css = $this->parser->getCss();

		$this->assertEquals('red', $css['P']['COLOR']);
	}

	public function testParseCascadedSelector()
	{
		$html = '<style>div p { color: blue; }</style>';
		$this->parser->parse($html);

		$cascade = $this->parser->getCascadeCss();
		$this->assertArrayHasKey('P', $cascade['DIV']);
		
		$properties = $cascade['DIV']['P'];
		$this->assertEquals('blue', $properties['COLOR']);
		$this->assertEquals(2, $properties['depth']);
	}

	/**
	 * Each nth-child in a selector keeps its own argument, closed up so the selector splits into its parts on whitespace
	 */
	public function testEachNthChildInASelectorKeepsItsOwnArgument()
	{
		$this->parser->parse('<style>table tr:nth-child(2n + 1) td:nth-child(odd) { color: blue; }</style>');

		$cascade = $this->parser->getCascadeCss();
		$properties = $cascade['TABLE']['TR>>SELECTORNTHCHILD>>2N+1']['TD>>SELECTORNTHCHILD>>ODD'];
		$this->assertEquals('blue', $properties['COLOR']);
		$this->assertEquals(3, $properties['depth']);
	}

	/**
	 * The class depth is the most classes one compound selector of a stored rule names, not the most in a whole selector
	 *
	 * @dataProvider classDepths
	 *
	 * @param string $css
	 * @param int $expected
	 */
	public function testMaxClassDepthIsCountedPerCompound($css, $expected)
	{
		$this->parser->parse('<style>' . $css . '</style>');

		$this->assertSame($expected, $this->parser->getMaxClassDepth());
	}

	/**
	 * Stylesheets and their class depth
	 *
	 * @return array[]
	 */
	public function classDepths()
	{
		return [
			'no classes' => ['p { color: red; }', 1],
			'one class in each of three compounds' => ['.a .b .c p { color: red; }', 1],
			'two classes on one element' => ['p.a.b { color: red; }', 2],
			'two classes on one ancestor' => ['div .a.b p { color: red; }', 2],
			'three classes in a rule that is dropped' => ['div > .a.b.c { color: red; }', 1],
		];
	}

	/**
	 * A rule that never matches in a PDF, or names a pseudo-element, is dropped in either mode without its classes
	 * being counted: they are kept out of the class names the merger looks up and out of the class depth
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testARuleThatNeverMatchesRecordsNothing($mode)
	{
		$this->mpdf->cssMode = $mode;
		$this->parser->parse('<style>
			a.x.y.z:hover, .nav .item:focus, .btn:active span, a.seen:visited, .menu:focus-within .sub, .note:target,
			p.intro::first-line, .quote::before, li.item::marker, .field::placeholder, .text::selection { color: red; }
			.kept { color: blue; }
		</style>');

		$this->assertSame(['KEPT'], $this->parser->getUsedClassNames());
		$this->assertSame(1, $this->parser->getMaxClassDepth());
		$this->assertSame(['CLASS>>KEPT' => ['COLOR' => 'blue']], $this->parser->getCss());
		$this->assertSame([], $this->parser->getCascadeCss());
		$this->assertCount($mode === CssMode::STANDARD ? 1 : 0, $this->parser->getCompiledRules());
	}

	/**
	 * The classes of every rule stored for the legacy merger are counted, whether they are on the element or on an
	 * ancestor
	 */
	public function testCountsTheClassesOfStoredRules()
	{
		$this->parser->parse('<style>.a.b { color: red; } div.c p { color: red; } p#i.d { color: red; } div > .e { color: red; }</style>');

		$used = $this->parser->getUsedClassNames();
		sort($used);

		$this->assertSame(['A', 'B', 'C', 'D'], $used);
	}

	/**
	 * Each CSS mode
	 *
	 * @return array[]
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD], CssMode::LEGACY => [CssMode::LEGACY]];
	}

	/**
	 * The nth-child keys of stored rules are recorded for each of TR, TD and TH, and those of dropped rules are not
	 */
	public function testNthChildKeysOfStoredRulesAreRecorded()
	{
		$this->parser->parse('<style>
			tr:nth-child(odd) { color: red; }
			table td:nth-child(2n + 1) { color: red; }
			td:first-child, td:nth-child(2n+1) { color: red; }
			th:nth-child(3):not(.x) { color: red; }
		</style>');

		$this->assertSame(['TR>>SELECTORNTHCHILD>>ODD'], array_keys($this->parser->getNthChildFormulas('TR')));
		$this->assertSame(['TD>>SELECTORNTHCHILD>>2N+1', 'TD>>SELECTORNTHCHILD>>1'], array_keys($this->parser->getNthChildFormulas('TD')));
		$this->assertSame([], $this->parser->getNthChildFormulas('TH'));
	}

	/**
	 * Legacy mode compiles no rule. It keeps a rule the legacy parser reads, and drops one only the matcher could
	 * read, as mPDF did before the matcher
	 *
	 * @dataProvider routedSelectors
	 *
	 * @param string $selector
	 * @param bool $matcherOnly Whether only the matcher reads it
	 */
	public function testLegacyModeDropsWhatTheLegacyParserCannotRead($selector, $matcherOnly)
	{
		$this->parser->parse('<style>' . $selector . ' { color: blue; }</style>');

		$this->assertSame([], $this->parser->getCompiledRules());
		$this->assertSame($matcherOnly, $this->parser->getCss() === [] && $this->parser->getCascadeCss() === []);
	}

	/**
	 * Standard mode compiles every rule, whichever parser reads it
	 *
	 * @dataProvider routedSelectors
	 *
	 * @param string $selector
	 */
	public function testStandardModeCompilesEveryRule($selector)
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>' . $selector . ' { color: blue; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(1, $rules);
		$this->assertSame(['COLOR' => 'blue'], $rules[0][1]);
	}

	/**
	 * A selector, and whether only the matcher reads it, not the legacy parser
	 *
	 * @return array[]
	 */
	public function routedSelectors()
	{
		return [
			'type' => ['p', false],
			'class on a type' => ['p.a', false],
			'lang attribute' => ['p[lang=fr]', false],
			'row nth-child' => ['tr:nth-child(2n)', false],
			'child' => ['div > p', true],
			'child with no spaces' => ['div>p', true],
			'adjacent sibling' => ['h1 + p', true],
			'general sibling' => ['h1 ~ p', true],
			'first-child' => ['li:first-child', true],
			'nth-child on an element outside a table' => ['li:nth-child(2n)', true],
			'nth-child outside a table as an ancestor' => ['li:nth-child(2) p', true],
			'first-of-type' => ['p:first-of-type', true],
			'attribute presence' => ['[data-x]', true],
			'attribute value on a type' => ['a[href^="http"]', true],
			'lang attribute with a hyphen match' => ['p[lang|=fr]', true],
			'lang pseudo-class in a combinator chain' => ['div > :lang(fr)', true],
			'root' => [':root', true],
			'html' => ['html', true],
			'link' => ['a:link', true],
			'any-link' => [':any-link', true],
			'not hover' => ['p:not(:hover)', true],
		];
	}

	/**
	 * A rule the legacy parser cannot read and the matcher cannot match either is dropped in standard mode too
	 *
	 * @dataProvider droppedSelectors
	 *
	 * @param string $selector
	 */
	public function testDropsWhatNeitherCanRead($selector)
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>' . $selector . ' { color: blue; }</style>');

		$this->assertSame([], $this->parser->getCompiledRules());
		$this->assertSame([], $this->parser->getCss());
		$this->assertSame([], $this->parser->getCascadeCss());
	}

	/**
	 * A selector neither the legacy parser nor the matcher can match
	 *
	 * @return array[]
	 */
	public function droppedSelectors()
	{
		return [
			'hover' => ['a:hover'],
			'visited' => ['a:visited'],
			'focus, active, focus-within, focus-visible and target' => ['a:focus, a:active, form:focus-within input, a:focus-visible, h2:target'],
			'hover on an ancestor' => ['nav li:hover a'],
			'link and hover' => ['a:link:hover'],
			'pseudo-element' => ['p::before'],
			'marker, selection and placeholder' => ['li::marker, p::selection, input::placeholder'],
			'first-line and first-letter' => ['p::first-line, p:first-letter'],
			'has' => ['li:has(> a)'],
			'a tag outside allowedCSStags' => ['div > sup'],
			'the universal selector, which waits for #530' => ['div > *'],
			'the universal selector as an ancestor' => ['* + p'],
		];
	}

	/**
	 * A list is split into its selectors before either parser reads them, so commas inside parentheses and strings
	 * stay with their selector and each selector is compiled whole
	 */
	public function testSplitsAListBeforeReadingItsSelectors()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>li:nth-child( 2n + 1 ), div > p, h1 { color: blue; }</style>');

		$this->assertSame(['H1' => ['COLOR' => 'blue']], $this->parser->getCss());

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(3, $rules);
		$this->assertSame([['nth-child', 2, 1]], $rules[0][0]['compounds'][0]['pseudos']);
		$this->assertSame(['>'], $rules[1][0]['combinators']);
		$this->assertSame('H1', $rules[2][0]['compounds'][0]['tag']);
	}

	/**
	 * A compiled rule without declarations has nothing to apply and is not kept
	 */
	public function testDoesNotKeepAnEmptyRule()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>div > p { }</style>');

		$this->assertSame([], $this->parser->getCompiledRules());
	}

	/**
	 * Each parse starts a new list of compiled rules, as it starts new stores for the legacy parser
	 */
	public function testStartsANewListOfCompiledRulesForEachParse()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>div > p { color: blue; }</style>');
		$this->parser->parse('<style>h1 + p { color: red; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(1, $rules);
		$this->assertSame(['+'], $rules[0][0]['combinators']);
	}

	/**
	 * Under the standard cascade every rule is compiled, in the order it is written, the matcher applying all of
	 * them. The simple ones the legacy parser reads are still stored by key, for what reads CssManager::$CSS directly,
	 * but no descendant rule goes into the legacy tree
	 */
	public function testCompilesEveryRuleUnderTheStandardCascade()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>p { color: red; } .a, div .b { color: green; } li:first-child { color: blue; } @page { margin-left: 1cm; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(4, $rules);
		$this->assertSame(
			[['COLOR' => 'red'], ['COLOR' => 'green'], ['COLOR' => 'green'], ['COLOR' => 'blue']],
			array_column($rules, 1)
		);
		$this->assertSame(['P', 'CLASS>>A', '@PAGE'], array_keys($this->parser->getCss()));
		$this->assertSame([], $this->parser->getCascadeCss());
	}

	/**
	 * Under the standard cascade each rule keeps its !important declarations apart from the others, compiled and
	 * under the key of a rule stored by key, @page rules included
	 */
	public function testKeepsImportantDeclarationsApartUnderTheStandardCascade()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>p { color: red !important; margin: 1mm; } div > p { font-size: 9pt ! IMPORTANT; } @page { margin-left: 1cm !important; } p { color: blue !important; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(3, $rules);
		$this->assertSame(['MARGIN-TOP' => '1mm', 'MARGIN-RIGHT' => '1mm', 'MARGIN-BOTTOM' => '1mm', 'MARGIN-LEFT' => '1mm'], $rules[0][1]);
		$this->assertSame(['COLOR' => 'red'], $rules[0][2]);
		$this->assertSame([], $rules[1][1]);
		$this->assertSame(['FONT-SIZE' => '9pt'], $rules[1][2]);
		$this->assertSame(['COLOR' => 'blue'], $rules[2][2]);

		$this->assertSame(['MARGIN-TOP' => '1mm', 'MARGIN-RIGHT' => '1mm', 'MARGIN-BOTTOM' => '1mm', 'MARGIN-LEFT' => '1mm'], $this->parser->getCss()['P']);
		$this->assertSame([], $this->parser->getCss()['@PAGE']);
		$this->assertSame(['P' => ['COLOR' => 'blue'], '@PAGE' => ['MARGIN-LEFT' => '1cm']], $this->parser->getImportantCss());
	}

	/**
	 * Under the legacy cascade a declaration marked !important is stored with the others, and the one after it
	 * replaces it
	 */
	public function testReadsTheFlagAsNothingUnderTheLegacyCascade()
	{
		$this->parser->parse('<style>p { color: red !important; color: green; margin-top: 1mm ! important; }</style>');

		$this->assertSame(['COLOR' => 'green', 'MARGIN-TOP' => '1mm'], $this->parser->getCss()['P']);
		$this->assertSame([], $this->parser->getImportantCss());
	}

	/**
	 * Each at-rule is unwrapped, kept or left out whole, in either cssMode, and the rules around it are stored as
	 * they are written
	 *
	 * @dataProvider atRulesInEachMode
	 *
	 * @param string $mode
	 * @param string $css
	 * @param array[] $expected Each key stored, with the values of the properties to check
	 */
	public function testAtRules($mode, $css, array $expected)
	{
		$this->mpdf->cssMode = $mode;
		$this->parser->parse('<style>' . $css . '</style>');

		$this->assertSame($expected, $this->storedValues($expected));
	}

	/**
	 * Each stylesheet in atRules() in each cssMode
	 *
	 * @return array[]
	 */
	public function atRulesInEachMode()
	{
		return $this->inEachMode($this->atRules());
	}

	/**
	 * Stylesheets with an at-rule in them, and the keys each stores
	 *
	 * @return array[]
	 */
	private function atRules()
	{
		$p = ['P' => ['COLOR' => 'red']];
		$h1 = ['H1' => ['COLOR' => 'blue']];
		$h1p = ['H1' => ['COLOR' => 'blue'], 'P' => ['COLOR' => 'red']];

		return [
			'no at-rule' => ['p { color: red }', $p],

			'@charset' => ['@charset "UTF-8"; p { color: red }', $p],
			'@namespace' => ['@namespace svg url(http://www.w3.org/2000/svg); p { color: red }', $p],
			'@import' => ['@import url("a.css") screen; @import "b.css"; p { color: red }', $p],
			'@import with semicolons in its url()' => ['@import url(https://fonts.example/css2?family=Inter:wght@300;400;500&display=swap); p { color: red }', $p],
			'@layer statement' => ['@layer base, theme; p { color: red }', $p],

			'@keyframes' => ['@keyframes spin { from { opacity: 0 } to { opacity: 1 } } p { color: red }', $p],
			'prefixed @keyframes' => ['@-webkit-keyframes spin { 0% { opacity: 0 } } p { color: red }', $p],
			'@container' => ['@container (min-width: 1px) { h1 { color: blue } } p { color: red }', $p],
			'@font-feature-values' => ['@font-feature-values Font { @swash { fancy: 1 } } p { color: red }', $p],
			'@font-face' => ['@font-face { font-family: x; src: url(x.ttf) } p { color: red }', $p],
			'an empty unknown block' => ['@unknown {} p { color: red }', $p],

			'@supports' => ['@supports (display: grid) { h1 { color: blue } } p { color: red }', $h1p],
			'@supports not' => ['@supports not (display: grid) { h1 { color: blue } } p { color: red }', $p],
			'@supports with not inside it' => ['@supports (display: grid) and (not (display: inline-grid)) { h1 { color: blue } }', $h1],
			'@layer block' => ['@layer base { h1 { color: blue } } p { color: red }', $h1p],
			'@layer block with no name' => ['@layer { h1 { color: blue } }', $h1],

			'@media for print' => ['@media print { h1 { color: blue } } p { color: red }', $h1p],
			'@media for screen' => ['@media screen { h1 { color: blue } } p { color: red }', $p],
			'@media in upper case' => ['@MEDIA print { h1 { color: blue } }', $h1],
			'an empty @media block' => ['@media print {} p { color: red }', $p],
			'@media inside @supports' => ['@supports (display: grid) { @media print { h1 { color: blue } } @media screen { h2 { color: blue } } }', $h1],
			'@supports inside @media' => ['@media print { @supports (display: grid) { h1 { color: blue } } h2 { color: blue } }', $h1 + ['H2' => ['COLOR' => 'blue']]],
			'@media inside @media' => ['@media print { @media all { h1 { color: blue } } }', $h1],
			'@keyframes inside @media' => ['@media print { @keyframes spin { from { opacity: 0 } } h1 { color: blue } }', $h1],
			'@media left open' => ['p { color: red } @media print { h1 { color: blue }', ['P' => ['COLOR' => 'red'], 'H1' => ['COLOR' => 'blue']]],

			'@page' => ['@page { margin-top: 10mm } p { color: red }', ['@PAGE' => ['MARGIN-TOP' => '10mm'], 'P' => ['COLOR' => 'red']]],
			'@page with a pseudo page' => ['@page :first { margin-top: 10mm }', ['@PAGE>>PSEUDO>>FIRST' => ['MARGIN-TOP' => '10mm']]],
			'@page with a pseudo page and no space' => ['@page:first { margin-top: 10mm }', ['@PAGE>>PSEUDO>>FIRST' => ['MARGIN-TOP' => '10mm']]],
			'@page inside @media' => ['@media print { @page { margin-top: 10mm } }', ['@PAGE' => ['MARGIN-TOP' => '10mm']]],
			'@page with margin boxes' => [
				'@page { margin-top: 10mm; @top-center { content: "}" } margin-bottom: 20mm; @bottom-left { content: "y" } } p { color: red }',
				['@PAGE' => ['MARGIN-TOP' => '10mm', 'MARGIN-BOTTOM' => '20mm'], 'P' => ['COLOR' => 'red']],
			],

			'a brace in a string in a prelude' => ['@supports (content: "}") { h1 { color: blue } } p { color: red }', $h1p],
			'a brace in a string in a removed block' => ['@keyframes x { from { content: "}" } } p { color: red }', $p],
			'a brace in a string in an unwrapped block' => ['@layer { h1 { content: \'{\'; color: blue } } p { color: red }', $h1p],
			'a quote escaped in a string' => ['@keyframes x { from { content: "\"}" } } p { color: red }', $p],
			'a semicolon in a string in a statement' => ['@import "a;b.css"; p { color: red }', $p],
			'an escaped brace' => ['@keyframes x { from { content: \} } } p { color: red }', $p],
			'an at sign in a declaration' => ['h1 { color: blue; background: url(a@2x.png) } p { color: red }', $h1p],
			'a string left open at the end of a line' => ["@keyframes x { from { content: \"} } }\n} } p { color: red }", $p],
			'a statement left open' => ['p { color: red } @import "a.css"', $p],
		];
	}

	/**
	 * Braces, semicolons and comment markers inside strings, url() and escapes are part of the declaration or selector
	 * they are in, in either cssMode, and the rules around them are stored as they are written
	 *
	 * @dataProvider constructsInEachMode
	 *
	 * @param string $mode
	 * @param string $css
	 * @param array[] $expected Each key stored, with the values of the properties to check
	 */
	public function testStringsUrlsAndEscapes($mode, $css, array $expected)
	{
		$this->mpdf->cssMode = $mode;
		$this->parser->parse('<style>' . $css . '</style>');

		$this->assertSame($expected, $this->storedValues($expected));
	}

	/**
	 * Each stylesheet in constructs() in each cssMode
	 *
	 * @return array[]
	 */
	public function constructsInEachMode()
	{
		return $this->inEachMode($this->constructs());
	}

	/**
	 * Stylesheets with braces, semicolons or comment markers in strings, url() or escapes, and the keys each stores
	 *
	 * @return array[]
	 */
	private function constructs()
	{
		$quotes = function ($value) {
			return ['Q' => ['QUOTES' => $value], 'P' => ['COLOR' => 'red']];
		};

		return [
			'braces in a string' => ['q { quotes: "}" "{" } p { color: red }', $quotes('"}" "{"')],
			'an unbalanced brace in a string' => ['q { quotes: "}" } p { color: red }', $quotes('"}"')],
			'a semicolon in a string' => ['q { quotes: ";" ";" } p { color: red }', $quotes('";" ";"')],
			'a declaration in a string' => [
				'p { color: red; font-family: \'x;color:blue;y\', monospace }',
				['P' => ['COLOR' => 'red', 'FONT-FAMILY' => 'monospace']],
			],
			'an escaped quote in a string' => ['q { quotes: "\"}" "x" } p { color: red }', $quotes('"\"}" "x"')],
			'a comment opener in a string' => ['q { quotes: "/*" "x" } p { color: red } q { quotes: "*/" "x" }', $quotes('"*/" "x"')],
			'a comment in a string' => ['q { quotes: "/* x */" "y" } p { color: red }', $quotes('"/* x */" "y"')],
			'a comment with a quote in it' => ['/* it\'s } */ q { quotes: "x" "y" } p { color: red }', $quotes('"x" "y"')],
			'a string left open at a line break' => ["q { quotes: \"a}b\n; } p { color: red }", ['Q' => [], 'P' => ['COLOR' => 'red']]],

			'an escaped brace in a selector' => ['.a\{b { color: blue } p { color: red }', ['P' => ['COLOR' => 'red']]],
			'a brace in an attribute selector' => ['q[title="{"] { color: blue } p { color: red }', ['P' => ['COLOR' => 'red']]],

			'braces and a semicolon in an unquoted url()' => [
				'div { background-image: url(http://example.com/a};{b.png) } p { color: red }',
				['DIV' => ['BACKGROUND-IMAGE' => 'http://example.com/a%7D;%7Bb.png'], 'P' => ['COLOR' => 'red']],
			],
			'a semicolon in a quoted url()' => [
				'div { background-image: url("data:image/svg+xml;utf8,<svg></svg>"); color: blue } p { color: red }',
				['DIV' => ['BACKGROUND-IMAGE' => 'data:image/svg+xml;utf8,<svg></svg>', 'COLOR' => 'blue'], 'P' => ['COLOR' => 'red']],
			],
			'a percent sign and ZZ in a url()' => ['div { background-image: url(http://example.com/a%ZZb.png) }', ['DIV' => ['BACKGROUND-IMAGE' => 'http://example.com/a%ZZb.png']]],

			'a rule nested in a block' => [
				'q { color: blue; & b { color: green } margin-top: 1mm } p { color: red }',
				['Q' => ['COLOR' => 'blue', 'MARGIN-TOP' => '1mm'], 'P' => ['COLOR' => 'red']],
			],
			'an at-rule nested in a block' => [
				'q { color: blue; @media print { color: green } margin-top: 1mm } p { color: red }',
				['Q' => ['COLOR' => 'blue', 'MARGIN-TOP' => '1mm'], 'P' => ['COLOR' => 'red']],
			],
			'whitespace across lines' => ["p\n{ margin:\n1mm\t2mm; }", ['P' => ['MARGIN-TOP' => '1mm', 'MARGIN-RIGHT' => '2mm']]],
		];
	}

	/**
	 * In standard mode, a selector with escapes in it reaches the compiler whole, which reads each escape as the
	 * character it stands for
	 */
	public function testASelectorWithEscapesReachesTheCompilerWhole()
	{
		$this->mpdf->cssMode = CssMode::STANDARD;
		$this->parser->parse('<style>.sm\\:hidden { color: blue } .a\\{b\\;c { color: green }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(2, $rules);
		$this->assertSame(['SM:HIDDEN'], $rules[0][0]['compounds'][0]['classes']);
		$this->assertSame(['COLOR' => 'blue'], $rules[0][1]);
		$this->assertSame(['A{B;C'], $rules[1][0]['compounds'][0]['classes']);
		$this->assertSame(['COLOR' => 'green'], $rules[1][1]);
	}

	/**
	 * A block, comment or string left open in one stylesheet ends with it, and the next stylesheet is read from its
	 * start
	 *
	 * @dataProvider stylesheetsLeftOpen
	 *
	 * @param string $first
	 */
	public function testEachStylesheetIsReadOnItsOwn($first)
	{
		$this->parser->parse('<style>' . $first . '</style><style>p { color: red }</style>');

		$this->assertSame(['COLOR' => 'red'], $this->parser->getCss()['P']);
	}

	/**
	 * Stylesheets that leave something open at their end
	 *
	 * @return array[]
	 */
	public function stylesheetsLeftOpen()
	{
		return [
			'a block' => ['h1 { color: blue'],
			'a comment' => ['/* h1 { color: blue }'],
			'a string' => ['h1 { font-family: "a'],
		];
	}

	/**
	 * A byte order mark at the start of a stylesheet, as one read from a file may have, is not part of its first rule
	 */
	public function testAByteOrderMarkIsNotPartOfTheFirstRule()
	{
		$this->parser->parse("<style> \xEF\xBB\xBF@charset \"UTF-8\"; p { color: red }</style><style>\xEF\xBB\xBFh1 { color: blue }</style>");

		$this->assertSame(['P' => ['COLOR' => 'red'], 'H1' => ['COLOR' => 'blue']], $this->parser->getCss());
	}

	/**
	 * Each case in each cssMode
	 *
	 * @param array[] $cases
	 *
	 * @return array[] Each case with the mode before its arguments, named for both
	 */
	private function inEachMode(array $cases)
	{
		$data = [];
		foreach ([CssMode::LEGACY, CssMode::STANDARD] as $mode) {
			foreach ($cases as $name => $case) {
				$data[$mode . ': ' . $name] = array_merge([$mode], $case);
			}
		}

		return $data;
	}

	/**
	 * The keys the last CSS parsed stored, each with the values of the properties named for it
	 *
	 * @param array[] $properties The properties to read for each key, keyed by their names
	 *
	 * @return array[] Each key stored, with those of the properties named for it that it has
	 */
	private function storedValues(array $properties)
	{
		$stored = [];
		foreach ($this->parser->getCss() as $key => $values) {
			$stored[$key] = array_intersect_key($values, isset($properties[$key]) ? $properties[$key] : []);
		}

		return $stored;
	}
}
