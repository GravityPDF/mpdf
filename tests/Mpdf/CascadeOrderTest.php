<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The order the rules that set the same property of an element are applied in, under each value of cssMode. The
 * legacy cascade applies them in a fixed order of selector groups; the standard cascade by specificity, then by the
 * order they are written in, as a browser does.
 */
class CascadeOrderTest extends TestCase
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
	public function testAppliesTheRulesInTheCascadesOrder($cascade, $css, $html, array $expected)
	{
		$this->assertDrawnInColours($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => $cascade]));
	}

	/**
	 * Each row of precedence() once for each cascade, with that cascade's colours
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
	 * Rules that compete for an element's colour, a document for them, and the colours its text is drawn in by the
	 * legacy cascade, as measured, and by the standard cascade, as the Selectors and Cascade specifications give them
	 *
	 * @return array[] Each [css, html, legacy colours, standard colours]
	 */
	private function precedenceTable()
	{
		return [
			'an id beats a tag with a class' => [
				'#i { color: #008000; } p.c { color: #f00; }',
				'<p id="i" class="c">id</p><p class="c">no id</p>',
				['id' => self::RED, 'no id' => self::RED],
				['id' => self::GREEN, 'no id' => self::RED],
			],
			'an id beats a descendant rule' => [
				'#i { color: #008000; } div p { color: #f00; }',
				'<div><p id="i">id</p><p>no id</p></div>',
				['id' => self::RED, 'no id' => self::RED],
				['id' => self::GREEN, 'no id' => self::RED],
			],
			'a class beats a descendant rule of tags' => [
				'.c { color: #008000; } div p { color: #f00; }',
				'<div><p class="c">class</p><p>no class</p></div>',
				['class' => self::RED, 'no class' => self::RED],
				['class' => self::GREEN, 'no class' => self::RED],
			],
			'a class beats body p, as CMS stylesheets write it' => [
				'body p { color: #f00; } .c { color: #008000; }',
				'<p class="c">class</p><p>no class</p>',
				['class' => self::RED, 'no class' => self::RED],
				['class' => self::GREEN, 'no class' => self::RED],
			],
			'two classes beat a tag with a class' => [
				'p.c { color: #f00; } .c.d { color: #008000; }',
				'<p class="c d">both</p><p class="c">one</p>',
				['both' => self::RED, 'one' => self::RED],
				['both' => self::GREEN, 'one' => self::RED],
			],
			'a heavier descendant rule beats one lifted from a nearer ancestor' => [
				'.c p { color: #008000; } div div p { color: #f00; }',
				'<div class="c"><div><p>classed ancestor</p></div></div><div><div><p>plain ancestors</p></div></div>',
				['classed ancestor' => self::RED, 'plain ancestors' => self::RED],
				['classed ancestor' => self::GREEN, 'plain ancestors' => self::RED],
			],
			'an id ancestor beats nearer tag ancestors' => [
				'#o p { color: #008000; } div div p { color: #f00; }',
				'<div id="o"><div><p>id ancestor</p></div></div><div><div><p>plain ancestors</p></div></div>',
				['id ancestor' => self::RED, 'plain ancestors' => self::RED],
				['id ancestor' => self::GREEN, 'plain ancestors' => self::RED],
			],
			'classes apply in the order they are written, not alphabetically' => [
				'.b { color: #008000; } .a { color: #f00; }',
				'<p class="a b">a b</p><p class="b a">b a</p><p class="b">b only</p>',
				['a b' => self::GREEN, 'b a' => self::GREEN, 'b only' => self::GREEN],
				['a b' => self::RED, 'b a' => self::RED, 'b only' => self::GREEN],
			],
			'a selector written twice takes each of its places' => [
				'.a { color: #f00; } .b { color: #008000; } .a { color: #00f; }',
				'<p class="a b">a b</p><p class="b">b only</p>',
				['a b' => self::GREEN, 'b only' => self::GREEN],
				['a b' => self::BLUE, 'b only' => self::GREEN],
			],
			'a later stylesheet wins at the same specificity' => [
				'.b { color: #008000; } </style><style> .a { color: #f00; }',
				'<p class="a b">both</p>',
				['both' => self::GREEN],
				['both' => self::RED],
			],
			'an id with a class beats a tag with an id' => [
				'#i.c { color: #008000; } p#i { color: #f00; }',
				'<p id="i" class="c">both</p><p id="i">id only</p>',
				['both' => self::GREEN, 'id only' => self::RED],
				['both' => self::GREEN, 'id only' => self::RED],
			],
			'an id beats a child rule' => [
				'#inner { color: #008000; } div > p { color: #f00; }',
				'<div><p id="inner">id</p><p>no id</p></div>',
				['id' => self::RED, 'no id' => self::RED],
				['id' => self::GREEN, 'no id' => self::RED],
			],
			'an id beats an attribute selector' => [
				'[data-x] { color: #f00; } #i { color: #008000; }',
				'<p id="i" data-x="1">both</p><p data-x="1">attribute only</p>',
				['both' => self::RED, 'attribute only' => self::RED],
				['both' => self::GREEN, 'attribute only' => self::RED],
			],
			':not() counts its argument, so the later rule wins' => [
				'p:not(.x) { color: #008000; } p.y { color: #f00; }',
				'<p class="y">y</p><p>neither</p>',
				['y' => self::GREEN, 'neither' => self::GREEN],
				['y' => self::RED, 'neither' => self::GREEN],
			],
			':where() counts nothing' => [
				':where(#o) p { color: #f00; } div p { color: #008000; }',
				'<div id="o"><p>inside</p></div>',
				['inside' => self::RED],
				['inside' => self::GREEN],
			],
			'the inline style beats an id' => [
				'#i { color: #f00; }',
				'<p id="i" style="color: #008000">inline</p><p id="i">rule</p>',
				['inline' => self::GREEN, 'rule' => self::RED],
				['inline' => self::GREEN, 'rule' => self::RED],
			],
			'class names match whatever their case' => [
				'.Foo { color: #008000; }',
				'<p class="foo">lower</p><p class="FOO">upper</p>',
				['lower' => self::GREEN, 'upper' => self::GREEN],
				['lower' => self::GREEN, 'upper' => self::GREEN],
			],
		];
	}

	/**
	 * A stylesheet in a later WriteHTML() call comes after those before it, as a later <style> block does
	 */
	public function testAStylesheetWrittenLaterComesLater()
	{
		$mpdf = $this->drawDocument('<style>.b { color: #008000; }</style>', ['cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML('<style>.a { color: #f00; }</style><p class="a b">both</p>');

		$this->assertSame(self::RED, $mpdf->drawnColours[0]);
	}

	/**
	 * The default stylesheet is the user agent's under the standard cascade, so an author's rule beats it whatever the
	 * specificity: `ul { margin-bottom }` reaches a nested list over the default `ul ul { margin-bottom: 0 }`. The
	 * legacy cascade applies the descendant rule last
	 *
	 * @dataProvider cascades
	 *
	 * @param string $cascade
	 * @param bool $authorWins
	 */
	public function testAnAuthorRuleBeatsTheDefaultStylesheet($cascade, $authorWins)
	{
		$mpdf = $this->drawDocument('<style>ul { margin: 0 0 20mm 0; }</style><ul><li>outer<ul><li>inner</li></ul></li><li>next</li></ul>', ['cssMode' => $cascade]);
		$y = $this->keyedByText($mpdf, $mpdf->drawnY);

		$this->assertEqualsWithDelta($authorWins ? 20 : 0, $y['next'] - $y['inner'] - ($y['inner'] - $y['outer']), 0.5);
	}

	/**
	 * Each cascade, and whether an author's rule beats a more specific rule of the default stylesheet under it
	 *
	 * @return array[]
	 */
	public function cascades()
	{
		return [
			CssMode::LEGACY => [CssMode::LEGACY, false],
			CssMode::STANDARD => [CssMode::STANDARD, true],
		];
	}

	/**
	 * A presentational attribute is an author rule of no specificity under the standard cascade: it beats the built-in
	 * defaults, as `<hr color>` over the grey of an hr, and loses to any stylesheet rule. The legacy cascade applies the
	 * attributes before the defaults
	 *
	 * @dataProvider attributes
	 *
	 * @param string $cascade
	 * @param string $html
	 * @param string $expected The stroke colour the rule is drawn in
	 */
	public function testAPresentationalAttributeComesAfterTheDefaults($cascade, $html, $expected)
	{
		$page = $this->pages($this->render($html, ['cssMode' => $cascade]))[0];

		$this->assertStringContainsString($expected, $page);
	}

	/**
	 * Each cascade with an hr coloured by its attribute, with and without a stylesheet rule for it
	 *
	 * @return array[]
	 */
	public function attributes()
	{
		$grey = '0.533 0.533 0.533 RG';
		$red = '1.000 0.000 0.000 RG';
		$blue = '0.000 0.000 1.000 RG';

		return [
			'legacy: the default beats the attribute' => [CssMode::LEGACY, '<hr color="#ff0000" />', $grey],
			'legacy: a rule beats the attribute' => [CssMode::LEGACY, '<style>hr { color: #00f; }</style><hr color="#ff0000" />', $blue],
			'standard: the attribute beats the default' => [CssMode::STANDARD, '<hr color="#ff0000" />', $red],
			'standard: a rule beats the attribute' => [CssMode::STANDARD, '<style>hr { color: #00f; }</style><hr color="#ff0000" />', $blue],
		];
	}

	/**
	 * `<img vspace>` beats the default margin of an image under the standard cascade, and moves what follows down
	 */
	public function testVspaceBeatsTheDefaultMarginOfAnImage()
	{
		$html = '<img src="' . __DIR__ . '/../data/img/tiger.jpg" width="20" vspace="30" /><p>after</p>';
		$legacy = $this->drawDocument($html, ['cssMode' => CssMode::LEGACY]);
		$standard = $this->drawDocument($html, ['cssMode' => CssMode::STANDARD]);

		$this->assertEqualsWithDelta(2 * 30 * 25.4 / 96, $standard->drawnY[0] - $legacy->drawnY[0], 0.1);
	}

	/**
	 * What Mpdf::SetDefaultBodyCSS() writes styles the document under the standard cascade too
	 */
	public function testTheBodyTakesWhatTheApiWritesForIt()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->SetDefaultBodyCSS('color', '#008000');
		$mpdf->WriteHTML('<p>body text</p>');

		$this->assertSame(self::GREEN, $mpdf->drawnColours[0]);
	}

	/**
	 * A stylesheet rule for body sets the document's font size under the standard cascade, and a rule naming body as
	 * a parent reaches its children
	 */
	public function testBodyRulesStyleTheDocument()
	{
		$mpdf = $this->drawDocument('<style>body { font-size: 20pt; } body > p.c { color: #008000; }</style><p>plain</p><p class="c">classed</p>', ['cssMode' => CssMode::STANDARD]);
		$sizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);
		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);

		$this->assertEquals(20, $sizes['plain']);
		$this->assertSame(self::BLACK, $colours['plain']);
		$this->assertSame(self::GREEN, $colours['classed']);
	}

	/**
	 * The default stylesheet still applies under the standard cascade where no author rule competes: its `ul ul`
	 * takes the margins off a nested list
	 */
	public function testTheDefaultStylesheetApplies()
	{
		$html = '<ul><li>outer<ul><li>inner</li></ul></li><li>next</li></ul>';
		$legacy = $this->drawDocument($html, ['cssMode' => CssMode::LEGACY]);
		$standard = $this->drawDocument($html, ['cssMode' => CssMode::STANDARD]);

		$this->assertSame($legacy->drawnY, $standard->drawnY);
	}

	/**
	 * A class rule still styles the text of an SVG under the standard cascade, whose classes are looked up in
	 * CssManager::$CSS
	 */
	public function testSvgTextFindsItsClassRules()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="20"><text class="t" x="0" y="15">Hi</text></svg>';
		$html = '<style>.t { fill: #00ff00; }</style><img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" />';

		$this->assertStringContainsString('0.000 1.000 0.000 rg', $this->render($html, ['cssMode' => CssMode::STANDARD, 'svgClasses' => true]));
	}

	/**
	 * @page rules set the page's margins under the standard cascade, as they do under the legacy one
	 */
	public function testPageRulesStillApply()
	{
		$mpdf = $this->drawDocument('<style>@page { margin-left: 50mm; }</style><p>text</p>', ['cssMode' => CssMode::STANDARD]);

		$this->assertEqualsWithDelta(50, $mpdf->drawnBoxes[0][1], 0.01);
	}

	/**
	 * A document whose rules never compete for a property is drawn the same by both cascades: its tables' collapsed
	 * borders take the cell rules' dominance, and its lists, headings, links and inline styles the same values
	 *
	 * @dataProvider uncontestedDocuments
	 *
	 * @param string $html
	 */
	public function testADocumentWhoseRulesDoNotCompeteIsDrawnAlike($html)
	{
		$legacy = $this->pages($this->render($html, ['cssMode' => CssMode::LEGACY]));
		$standard = $this->pages($this->render($html, ['cssMode' => CssMode::STANDARD]));

		$this->assertSame($legacy, $standard);
	}

	/**
	 * Documents in which no two rules set the same property of an element
	 *
	 * @return array[]
	 */
	public function uncontestedDocuments()
	{
		return [
			'collapsed borders of cells, rows and the table' => ['<style>table { border-collapse: collapse; border: 1mm solid #00f; } td { border: 0.5mm solid #f00; } td.x { border-top: 2mm solid #0a0; }</style>'
				. '<table><tr><td>a</td><td class="x">b</td></tr><tr><td class="x">c</td><td style="border-bottom: 1.5mm dotted #000">d</td></tr></table>'],
			'a border from a cell rule against one as wide from the border attribute, which the rule wins' => ['<style>td.x { border: 0.264583mm solid #f00; }</style>'
				. '<table border="1" style="border-collapse: collapse"><tr><td>a</td><td class="x">b</td><td>c</td></tr></table>'],
			'separate borders and padding' => ['<style>table { border-spacing: 2mm; } td { border: 0.3mm solid #000; padding: 3mm; } th { background-color: #eee; }</style>'
				. '<table cellpadding="4"><tr><th>h</th></tr><tr><td>a</td></tr></table>'],
			'headings, lists and links' => ['<style>h2 { color: #800; } li { font-style: italic; } a { text-decoration: none; }</style>'
				. '<h2>Title</h2><ol><li>one</li><li>two <a href="https://example.com/">link</a></li></ol>'],
			'descendant rules and inline styles' => ['<style>.box p { margin: 5mm; } .box { border: 0.3mm solid #000; }</style>'
				. '<div class="box"><p>boxed</p><p style="color: #00f">inline</p></div>'],
		];
	}

	/**
	 * Any value but legacy and standard is refused when the document is made
	 */
	public function testRefusesAnUnknownCascade()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('cssMode (browser) is not valid. (Use: standard or legacy)');

		new Mpdf(['cssMode' => 'browser']);
	}
}
