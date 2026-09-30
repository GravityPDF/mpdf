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
		$this->mpdf = new Mpdf();
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
}
