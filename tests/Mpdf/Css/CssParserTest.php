<?php

namespace Mpdf\Css;

use Mpdf\AssetFetcher;
use Mpdf\Cache;
use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
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
		$this->mpdf = new Mpdf(['cssMode' => \Mpdf\CssMode::LEGACY]);
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
	 * A rule the legacy parser cannot read is compiled for the matcher, and a simple rule it can read is not
	 *
	 * @dataProvider routedSelectors
	 *
	 * @param string $selector
	 * @param bool $compiled Whether it goes to the matcher
	 */
	public function testCompilesOnlyWhatTheLegacyParserCannotRead($selector, $compiled)
	{
		$this->parser->parse('<style>' . $selector . ' { color: blue; }</style>');

		$rules = $this->parser->getCompiledRules();
		if (!$compiled) {
			$this->assertSame([], $rules);

			return;
		}

		$this->assertCount(1, $rules);
		$this->assertSame(['COLOR' => 'blue'], $rules[0][1]);
		$this->assertFalse($rules[0][2]);
		$this->assertSame([], $this->parser->getCss());
		$this->assertSame([], $this->parser->getCascadeCss());
	}

	/**
	 * A selector, and whether it goes to the matcher rather than the legacy parser
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
		];
	}

	/**
	 * A descendant rule the legacy parser reads is compiled too, for the matcher to apply through the ancestors the
	 * legacy engine does not look at
	 *
	 * @dataProvider legacyDescendantSelectors
	 *
	 * @param string $selector
	 */
	public function testCompilesALegacyDescendantRuleToMatchThroughInlineAncestors($selector)
	{
		$this->parser->parse('<style>' . $selector . ' { color: blue; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(1, $rules);
		$this->assertSame(['COLOR' => 'blue'], $rules[0][1]);
		$this->assertTrue($rules[0][2]);
		$this->assertNotEmpty($this->parser->getCascadeCss());
	}

	/**
	 * A descendant selector the legacy parser reads
	 *
	 * @return array[]
	 */
	public function legacyDescendantSelectors()
	{
		return [
			'types' => ['span em'],
			'a class as the ancestor' => ['.x b'],
			'three levels' => ['div .x b'],
			'a cell nth-child' => ['table td:nth-child(odd)'],
			'lang' => ['div :lang(fr)'],
			'a lang attribute' => ['[lang=fr] b'],
		];
	}

	/**
	 * A simple :lang() rule the legacy parser reads is compiled too, for the matcher to apply to an element that
	 * inherits its language rather than having its own lang attribute
	 */
	public function testCompilesALegacyLangRuleToMatchAnInheritedLanguage()
	{
		$this->parser->parse('<style>p:lang(fr) { color: blue; } p[lang=fr] { color: red; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(1, $rules);
		$this->assertSame([['lang', ['fr']]], $rules[0][0]['compounds'][0]['pseudos']);
		$this->assertTrue($rules[0][2]);
		$this->assertArrayHasKey('P>>LANG>>fr', $this->parser->getCss());
	}

	/**
	 * A rule the legacy parser cannot read and the matcher cannot match either is dropped, as it was before
	 *
	 * @dataProvider droppedSelectors
	 *
	 * @param string $selector
	 */
	public function testDropsWhatNeitherCanRead($selector)
	{
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
			'pseudo-element' => ['p::before'],
			'last-child' => ['li:last-child'],
			'a tag outside allowedCSStags' => ['div > sup'],
			'the universal selector, which waits for #530' => ['div > *'],
			'the universal selector as an ancestor' => ['* + p'],
		];
	}

	/**
	 * A list is split into its selectors before either parser reads them, so commas inside parentheses and strings
	 * stay with their selector and each selector goes its own way
	 */
	public function testSplitsAListBeforeReadingItsSelectors()
	{
		$this->parser->parse('<style>li:nth-child( 2n + 1 ), div > p, h1 { color: blue; }</style>');

		$this->assertSame(['H1' => ['COLOR' => 'blue']], $this->parser->getCss());

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(2, $rules);
		$this->assertSame([['nth-child', 2, 1]], $rules[0][0]['compounds'][0]['pseudos']);
		$this->assertSame(['>'], $rules[1][0]['combinators']);
	}

	/**
	 * A compiled rule without declarations has nothing to apply and is not kept
	 */
	public function testDoesNotKeepAnEmptyRule()
	{
		$this->parser->parse('<style>div > p { }</style>');

		$this->assertSame([], $this->parser->getCompiledRules());
	}

	/**
	 * Each parse starts a new list of compiled rules, as it starts new stores for the legacy parser
	 */
	public function testStartsANewListOfCompiledRulesForEachParse()
	{
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
		$this->mpdf->cssMode = \Mpdf\CssMode::STANDARD;
		$this->parser->parse('<style>p { color: red; } .a, div .b { color: green; } li:first-child { color: blue; } @page { margin-left: 1cm; }</style>');

		$rules = $this->parser->getCompiledRules();
		$this->assertCount(4, $rules);
		$this->assertSame(
			[['COLOR' => 'red'], ['COLOR' => 'green'], ['COLOR' => 'green'], ['COLOR' => 'blue']],
			array_column($rules, 1)
		);
		$this->assertSame([false, false, false, false], array_column($rules, 2));
		$this->assertSame(['P', 'CLASS>>A', '@PAGE'], array_keys($this->parser->getCss()));
		$this->assertSame([], $this->parser->getCascadeCss());
	}
}
