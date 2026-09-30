<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Declarations marked !important, under each value of cssMode. The standard cascade applies them after the inline
 * style: those of the document's stylesheets by specificity and then position, then those of the inline style, then
 * those of the default stylesheet. The legacy cascade reads them as any other declaration.
 */
class ImportantDeclarationTest extends TestCase
{

	use DrawnStyles;
	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * Each piece of text named is drawn in the colour the cascade leaves it in
	 *
	 * @dataProvider precedence
	 *
	 * @param string $cascade legacy or standard
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Pieces of text and the colour each should be drawn in
	 */
	public function testAppliesImportantDeclarationsInTheCascadesOrder($cascade, $css, $html, array $expected)
	{
		$this->assertDrawnInColours($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => $cascade]));
	}

	/**
	 * Each row of precedenceTable() once for each cascade, with that cascade's colours
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		$cases = [];
		foreach ($this->precedenceTable() as $name => $row) {
			list($css, $html, $legacy, $standard) = $row;
			$cases[$name . ' (legacy)'] = [CssMode::LEGACY, $css, $html, $legacy];
			$cases[$name . ' (standard)'] = [CssMode::STANDARD, $css, $html, $standard];
		}

		return $cases;
	}

	/**
	 * Rules that compete for an element's colour with at least one declaration marked !important, a document with an
	 * element each rule reaches and a near miss only one of them reaches, and the colours its text is drawn in by the
	 * legacy cascade, as measured, and by the standard cascade, as CSS Cascade gives them
	 *
	 * @return array[] Each [css, html, legacy colours, standard colours]
	 */
	private function precedenceTable()
	{
		return [
			'an important class beats an id' => [
				'.c { color: #008000 !important; } #i { color: #f00; }',
				'<p id="i" class="c">both</p><p id="i">id only</p>',
				['both' => self::RED, 'id only' => self::RED],
				['both' => self::GREEN, 'id only' => self::RED],
			],
			'an important tag beats an id' => [
				'p { color: #008000 !important; } #i { color: #f00; }',
				'<p id="i">p with the id</p><div id="i">div with the id</div>',
				['p with the id' => self::RED, 'div with the id' => self::RED],
				['p with the id' => self::GREEN, 'div with the id' => self::RED],
			],
			'an important rule beats the inline style' => [
				'.c { color: #008000 !important; }',
				'<p class="c" style="color: #f00">both</p><p style="color: #f00">inline only</p>',
				['both' => self::RED, 'inline only' => self::RED],
				['both' => self::GREEN, 'inline only' => self::RED],
			],
			'an important inline style beats an important rule' => [
				'#i { color: #f00 !important; }',
				'<p id="i" style="color: #008000 !important">important inline</p><p id="i" style="color: #00f">plain inline</p><p id="i">rule only</p>',
				['important inline' => self::GREEN, 'plain inline' => self::BLUE, 'rule only' => self::RED],
				['important inline' => self::GREEN, 'plain inline' => self::RED, 'rule only' => self::RED],
			],
			'an important declaration beats a later one of the block that is not' => [
				'.c { color: #008000 !important; color: #f00; }',
				'<p class="c">class</p>',
				['class' => self::RED],
				['class' => self::GREEN],
			],
			'an important inline declaration beats a later one of the style that is not' => [
				'',
				'<p style="color: #008000 !important; color: #f00">inline</p>',
				['inline' => self::RED],
				['inline' => self::GREEN],
			],
			'the more specific of two important rules wins' => [
				'.c.d { color: #008000 !important; } p.c { color: #f00 !important; }',
				'<p class="c d">both</p><p class="c">one class</p>',
				['both' => self::RED, 'one class' => self::RED],
				['both' => self::GREEN, 'one class' => self::RED],
			],
			'the later of two important rules as specific wins' => [
				'.b { color: #f00 !important; } .a { color: #008000 !important; } .b { color: #00f; }',
				'<p class="b a">b a</p><p class="b">b only</p>',
				['b a' => self::BLUE, 'b only' => self::BLUE],
				['b a' => self::GREEN, 'b only' => self::RED],
			],
			'an important rule of a later stylesheet wins at the same specificity' => [
				'.a { color: #008000 !important; } </style><style> .b { color: #f00 !important; } .b { color: #00f; }',
				'<p class="a b">both</p>',
				['both' => self::BLUE],
				['both' => self::RED],
			],
			'an important rule inside a matching @media' => [
				'@media print { .c { color: #008000 !important; } } @media screen { .c { color: #00f !important; } } #i { color: #f00; }',
				'<p id="i" class="c">both</p><p id="i">id only</p>',
				['both' => self::RED, 'id only' => self::RED],
				['both' => self::GREEN, 'id only' => self::RED],
			],
			'an important descendant rule beats an id' => [
				'div .c { color: #008000 !important; } #i { color: #f00; }',
				'<div><p id="i" class="c">inside</p></div><p id="i" class="c">outside</p>',
				['inside' => self::GREEN, 'outside' => self::RED],
				['inside' => self::GREEN, 'outside' => self::RED],
			],
			'the flag in capitals, spaced from the bang' => [
				'.c { color: #008000 ! IMPORTANT; } #i { color: #f00; }',
				'<p id="i" class="c">both</p>',
				['both' => self::RED],
				['both' => self::GREEN],
			],
			'the flag against the value, in mixed case' => [
				'.c { color: #008000!Important ; } #i { color: #f00; }',
				'<p id="i" class="c">both</p>',
				['both' => self::RED],
				['both' => self::GREEN],
			],
			'the flag spaced in an inline style' => [
				'.c { color: #f00 !important; }',
				'<p class="c" style="color: #008000 !  important">both</p>',
				['both' => self::GREEN],
				['both' => self::GREEN],
			],
		];
	}

	/**
	 * A shorthand marked !important expands into longhands that are all important: each beats the same longhand
	 * from a more specific rule and one written after it in the same block
	 *
	 * @dataProvider shorthands
	 *
	 * @param string $cascade
	 * @param string $css
	 * @param string $html
	 * @param string[] $drawn Operators the page draws
	 * @param string[] $notDrawn Operators it does not
	 */
	public function testAnImportantShorthandExpandsWithItsFlag($cascade, $css, $html, array $drawn, array $notDrawn)
	{
		$page = $this->pages($this->render('<style>' . $css . '</style>' . $html, ['cssMode' => $cascade, 'mode' => 'c']))[0];

		foreach ($drawn as $operator) {
			$this->assertStringContainsString($operator, $page);
		}
		foreach ($notDrawn as $operator) {
			$this->assertStringNotContainsString($operator, $page);
		}
	}

	/**
	 * The border and background shorthands against longhands of a more specific rule, and of the same block after
	 * them, under each cascade
	 *
	 * @return array[]
	 */
	public function shorthands()
	{
		$green = '0.000 0.502 0.000 RG';
		$red = '1.000 0.000 0.000 RG';
		$greenFill = '0.000 0.502 0.000 rg';
		$redFill = '1.000 0.000 0.000 rg';

		$border = '.c { border: 1mm solid #008000 !important; border-top-color: #f00; } #i { border-bottom-color: #f00; border-left: 1mm solid #f00; }';
		$background = '.c { background: #008000 !important; } #i { background-color: #f00; }';

		return [
			'border (legacy)' => [CssMode::LEGACY, $border, '<div id="i" class="c">boxed</div>', [$green, $red], []],
			'border (standard)' => [CssMode::STANDARD, $border, '<div id="i" class="c">boxed</div>', [$green], [$red]],
			'border of an inline element (standard)' => [CssMode::STANDARD, $border, '<p><span id="i" class="c">boxed</span></p>', [$green], [$red]],
			'border of a table cell (standard)' => [CssMode::STANDARD, $border, '<table><tr><td id="i" class="c">boxed</td></tr></table>', [$green], [$red]],
			'background (legacy)' => [CssMode::LEGACY, $background, '<div id="i" class="c">filled</div>', [$redFill], [$greenFill]],
			'background (standard)' => [CssMode::STANDARD, $background, '<div id="i" class="c">filled</div>', [$greenFill], [$redFill]],
			'background of a table cell (standard)' => [CssMode::STANDARD, $background, '<table><tr><td id="i" class="c">filled</td></tr></table>', [$greenFill], [$redFill]],
		];
	}

	/**
	 * The font shorthand marked !important sets the size over a more specific rule's, and the weight it resets to
	 * normal: the text is drawn at 14pt in Times Roman. The legacy cascade applies the id's 10pt bold
	 *
	 * @dataProvider cascades
	 *
	 * @param string $cascade
	 * @param float $size The size the text is drawn at
	 * @param string $font The core font it is drawn in
	 */
	public function testAnImportantFontShorthandExpandsWithItsFlag($cascade, $size, $font)
	{
		$html = '<style>.c { font: 14pt serif !important; } #i { font-size: 10pt; font-weight: bold; }</style><p id="i" class="c">text</p>';

		$this->assertEquals($size, $this->drawDocument($html, ['cssMode' => $cascade])->drawnFontSize[0]);
		$this->assertStringContainsString('/BaseFont /' . $font, $this->render($html, ['cssMode' => $cascade, 'mode' => 'c']));
	}

	/**
	 * Each cascade with the font size and core font the text is drawn in
	 *
	 * @return array[]
	 */
	public function cascades()
	{
		return [
			CssMode::LEGACY => [CssMode::LEGACY, 10, 'Times-Bold'],
			CssMode::STANDARD => [CssMode::STANDARD, 14, 'Times-Roman'],
		];
	}

	/**
	 * The important declarations of the default stylesheet beat the document's, of its stylesheets and its inline
	 * styles alike, as a user agent's do an author's. Its declarations that are not important lose to the document's.
	 * The legacy cascade reads the default stylesheet first and the flag as nothing, so the document wins
	 *
	 * @dataProvider defaultStylesheet
	 *
	 * @param string $cascade
	 * @param array<string, string> $colours
	 * @param float $size The font size the body is drawn at
	 */
	public function testTheDefaultStylesheetsImportantDeclarationsComeLast($cascade, array $colours, $size)
	{
		$mpdf = $this->drawDocument(
			'<style>body { font-size: 8pt !important; } p.ua { color: #f00 !important; } #i { color: #f00 !important; } p.plain { color: #008000; }</style>'
			. '<p id="i" class="ua" style="color: #00f !important">default rule</p><p id="i">document rule</p><p class="plain">plain default rule</p>',
			['cssMode' => $cascade, 'defaultCssFile' => __DIR__ . '/../data/css/important-default.css']
		);

		$this->assertDrawnInColours($colours, $this->keyedByText($mpdf, $mpdf->drawnColours));
		$this->assertEquals($size, $mpdf->drawnFontSize[0]);
	}

	/**
	 * Each cascade, the colours it draws each paragraph in, and the font size of the body
	 *
	 * @return array[]
	 */
	public function defaultStylesheet()
	{
		return [
			CssMode::LEGACY => [CssMode::LEGACY, ['default rule' => self::BLUE, 'document rule' => self::RED, 'plain default rule' => self::GREEN], 8],
			CssMode::STANDARD => [CssMode::STANDARD, ['default rule' => self::GREEN, 'document rule' => self::RED, 'plain default rule' => self::GREEN], 9],
		];
	}

	/**
	 * An important declaration of a body rule beats the style attribute of the body, and an important one of the
	 * style attribute beats both
	 *
	 * @dataProvider bodies
	 *
	 * @param string $cascade
	 * @param string $css
	 * @param string $style The body's style attribute
	 * @param string $expected The colour the text is drawn in
	 */
	public function testTheBodysStyleMeetsImportantBodyRules($cascade, $css, $style, $expected)
	{
		$colours = $this->drawnColours('<html><head><style>' . $css . '</style></head><body style="' . $style . '"><p>text</p></body></html>', ['cssMode' => $cascade]);

		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * Each cascade with a body rule and a style attribute, one of them marked important
	 *
	 * @return array[]
	 */
	public function bodies()
	{
		$rule = 'body { color: #008000 !important; }';
		$style = 'color: #008000 !important';

		return [
			'an important rule (legacy)' => [CssMode::LEGACY, $rule, 'color: #f00', self::RED],
			'an important rule (standard)' => [CssMode::STANDARD, $rule, 'color: #f00', self::GREEN],
			'an important rule against an important style (legacy)' => [CssMode::LEGACY, 'body { color: #f00 !important; }', $style, self::GREEN],
			'an important rule against an important style (standard)' => [CssMode::STANDARD, 'body { color: #f00 !important; }', $style, self::GREEN],
			'a plain rule against a plain style (standard)' => [CssMode::STANDARD, 'body { color: #f00; }', 'color: #008000', self::GREEN],
		];
	}

	/**
	 * An important declaration of an @page rule beats a later one that is not, under the standard cascade
	 *
	 * @dataProvider pageMargins
	 *
	 * @param string $cascade
	 * @param float $left The left margin of the page
	 */
	public function testAnImportantPageRuleBeatsALaterOne($cascade, $left)
	{
		$mpdf = $this->drawDocument('<style>@page { margin-left: 50mm !important; } @page { margin-left: 10mm; margin-right: 10mm; }</style><p>text</p>', ['cssMode' => $cascade]);

		$this->assertEqualsWithDelta($left, $mpdf->drawnBoxes[0][1], 0.01);
	}

	/**
	 * Each cascade with the left margin it gives the page
	 *
	 * @return array[]
	 */
	public function pageMargins()
	{
		return [
			CssMode::LEGACY => [CssMode::LEGACY, 10],
			CssMode::STANDARD => [CssMode::STANDARD, 50],
		];
	}

	/**
	 * An important declaration of a class rule beats a later one that is not in the text of an SVG, which looks its
	 * classes up in CssManager::$CSS
	 *
	 * @dataProvider svgFills
	 *
	 * @param string $cascade
	 * @param string $fill The fill operator the text is drawn with
	 */
	public function testAnImportantClassRuleFillsSvgText($cascade, $fill)
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="20"><text class="t" x="0" y="15">Hi</text></svg>';
		$html = '<style>.t { fill: #00ff00 !important; } .t { fill: #ff0000; }</style><img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" />';

		$this->assertStringContainsString($fill, $this->render($html, ['cssMode' => $cascade, 'svgClasses' => true]));
	}

	/**
	 * Each cascade with the fill it gives the SVG's text
	 *
	 * @return array[]
	 */
	public function svgFills()
	{
		return [
			CssMode::LEGACY => [CssMode::LEGACY, '1.000 0.000 0.000 rg'],
			CssMode::STANDARD => [CssMode::STANDARD, '0.000 1.000 0.000 rg'],
		];
	}

	/**
	 * An important cell rule sets the border dominance of the sides it gives a border, as every cell rule does, and an
	 * important inline style of a cell does too
	 *
	 * @dataProvider cellBorders
	 *
	 * @param string $cascade
	 * @param string $html A table left open in a cell given a left border
	 */
	public function testAnImportantCellBorderTakesACellRulesDominance($cascade, $html)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $cascade]);
		$mpdf->WriteHTML($html, HTMLParserMode::DEFAULT_MODE, true, false);

		$manager = new \ReflectionProperty($mpdf, 'cssManager');
		if (PHP_VERSION_ID < 80100) {
			$manager->setAccessible(true);
		}

		$this->assertSame(9, $manager->getValue($mpdf)->getBorderDominance('L'));
		$this->assertSame(0, $manager->getValue($mpdf)->getBorderDominance('R'));
	}

	/**
	 * Each cascade with a cell whose left border comes from an important rule, and from an important inline style
	 *
	 * @return array[]
	 */
	public function cellBorders()
	{
		$rule = '<style>td.x { border-left: 1mm solid #008000 !important; }</style><table><tr><td class="x">';
		$style = '<table><tr><td style="border-left: 1mm solid #008000 !important">';

		return [
			'a rule (legacy)' => [CssMode::LEGACY, $rule],
			'a rule (standard)' => [CssMode::STANDARD, $rule],
			'an inline style (legacy)' => [CssMode::LEGACY, $style],
			'an inline style (standard)' => [CssMode::STANDARD, $style],
		];
	}
}
