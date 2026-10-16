<?php

namespace Mpdf;

/**
 * A "<" or ">" inside a quoted attribute value belongs to the value, as it does in a browser: it does not end the tag,
 * and it reaches the tag unchanged
 */
class AngleBracketInAttributeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;
	use PageStreams;

	const BLUE = '0.000 0.000 1.000 rg';

	/**
	 * Each CSS mode, with autoScriptToLang off and on
	 *
	 * @return array[]
	 */
	public static function modesAndScripts()
	{
		return [
			'standard' => [['mode' => '', 'cssMode' => CssMode::STANDARD]],
			'standard, autoScriptToLang' => [['mode' => '', 'cssMode' => CssMode::STANDARD, 'autoScriptToLang' => true, 'autoLangToFont' => true]],
			'legacy' => [['mode' => '', 'cssMode' => CssMode::LEGACY]],
			'legacy, autoScriptToLang' => [['mode' => '', 'cssMode' => CssMode::LEGACY, 'autoScriptToLang' => true, 'autoLangToFont' => true]],
		];
	}

	/**
	 * A ">" inside a quoted string of a style attribute leaves the rest of the declarations, and the rest of the
	 * document, as they are
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAGreaterThanSignInsideAStyleAttribute(array $config)
	{
		$drawn = $this->drawnColours('<p style="font-family: \'a>b\', serif; color: blue">Blue text</p><p>Second</p>', $config);

		$this->assertSame(['Blue text', 'Second'], array_keys($drawn));
		$this->assertSame(self::BLUE, $drawn['Blue text']);
	}

	/**
	 * A ">" in an attribute before the style attribute, on text in two scripts, leaves both drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAGreaterThanSignInsideATitleBeforeTheStyle(array $config)
	{
		$drawn = $this->drawnColours('<p title="1 > 0" style="color: blue">Blue text שלום</p><p>Second</p>', $config);

		$text = implode(' ', array_keys($drawn));
		$this->assertMatchesRegularExpression('/^Blue text .*\p{Hebrew}{4}.* Second$/u', $text);
		$this->assertSame(self::BLUE, reset($drawn));
	}

	/**
	 * Each CSS mode with autoScriptToLang off and on, and core fonts, where characters such as "Σ" are swapped for
	 * the symbol font's only between tags
	 *
	 * @return array[]
	 */
	public static function modesAndFonts()
	{
		return self::modesAndScripts() + ['core fonts' => [['mode' => 'c']]];
	}

	/**
	 * A link keeps the "<" and ">" in its URL, and what follows them
	 *
	 * @dataProvider modesAndFonts
	 *
	 * @param array $config
	 */
	public function testALinkKeepsTheAngleBracketsInItsUrl(array $config)
	{
		$mpdf = $this->drawDocument('<a href="https://example.com/?q=a>Σ&amp;r=c<d">link</a>', $config);
		$mpdf->compress = false;

		$this->assertStringContainsString('/URI (https://example.com/?q=a>Σ&r=c<d)', $mpdf->Output('', 'S'));
	}

	/**
	 * A ">" inside a quoted value of a tag that closes itself, written with or without its "/", reaches the tag
	 * unchanged
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testASelfClosingTagKeepsTheGreaterThanSign(array $config)
	{
		$mpdf = $this->drawDocument('<bookmark content="Profit > Loss" /><bookmark content="a > b"><p>text</p>', $config);

		$this->assertSame(['Profit > Loss', 'a > b'], array_column($mpdf->BMoutlines, 't'));
	}

	/**
	 * An unsupported tag is dropped whole, however its attribute values are quoted, and what is between it and its end
	 * tag is drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnUnsupportedTagIsDroppedWhole(array $config)
	{
		$drawn = $this->drawnColours('<p>before <custom data-x="a>b" data-y=\'c>d\'>inside</custom> after</p>', $config);

		$this->assertSame(['before inside after'], array_keys($drawn));
	}

	/**
	 * A tag that DisableTags() has turned off is dropped like any other unsupported one
	 */
	public function testADisabledTagIsDropped()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->DisableTags('<b>');
		$mpdf->WriteHTML('<p>plain <b title="x>y">not bold</b></p>');

		$this->assertSame(['plain not bold'], array_map('trim', $mpdf->drawnText));
		$this->assertSame([''], $mpdf->drawnFontStyles);
	}

	/**
	 * An annotation keeps the newline after a ">" in its content
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnAnnotationKeepsItsNewlinesAfterAGreaterThanSign(array $config)
	{
		$mpdf = $this->drawDocument("<p>text<annotation content=\"a > b\nsecond line\" /></p>", $config);

		$this->assertSame("a > b\nsecond line", $mpdf->PageAnnots[1][0]['txt']);
	}

	/**
	 * A caption is drawn above a table whose attribute holds a ">", as it is above one without
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testACaptionIsDrawnAboveATableWithAGreaterThanSign(array $config)
	{
		$drawn = $this->drawnColours('<table border="1" summary="a > b"><caption>The caption</caption><tr><td>cell</td></tr></table>', $config);

		$this->assertSame(['The caption', 'cell'], array_keys($drawn));
	}

	/**
	 * A heading's title, with and without a ">"
	 *
	 * @return array[]
	 */
	public static function headingTitles()
	{
		return [
			'plain' => ['a b'],
			'a ">"' => ['a > b'],
		];
	}

	/**
	 * With use_kwt, a heading at the foot of a page goes to the next one with the table after it, whatever its title
	 *
	 * @dataProvider headingTitles
	 *
	 * @param string $title
	 */
	public function testAHeadingIsKeptWithItsTable($title)
	{
		$rows = str_repeat('<tr><td>Row</td></tr>', 30);
		$pdf = $this->render($this->filler(26) . '<h2 title="' . $title . '">Heading</h2><table>' . $rows . '</table>', ['use_kwt' => true]);

		$this->assertOnlyOnPage(1, 1, 'Heading', array_map([$this, 'drawnText'], $this->pages($pdf)), 'the heading');
	}

	/**
	 * A "<svg>" inside an attribute value is not taken for an inline SVG: the text after it, and the SVG itself, are
	 * still drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnSvgTagInsideAnAttributeIsNotAnSvg(array $config)
	{
		$mpdf = $this->drawDocument('<p>Before</p><a href="#x" title="draw <svg>">link</a><p>Middle</p>'
			. '<svg xmlns="http://www.w3.org/2000/svg" class="logo" width="40" height="40"><rect width="40" height="40" fill="red"/></svg><p>After</p>', $config);

		$this->assertSame(['Before', 'link', 'Middle', 'After'], array_map('trim', $mpdf->drawnText));
		$this->assertCount(1, $mpdf->formobjects);
	}

	/**
	 * An SVG in a data URI in a style attribute reaches the CSS parser whole, and nothing of it is drawn as text
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnSvgInAStyleAttributeStaysInIt(array $config)
	{
		$svg = '<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;10&quot; height=&quot;10&quot;><rect width=&quot;10&quot; height=&quot;10&quot;/></svg>';
		$mpdf = $this->drawDocument('<div style="background-image: url(\'data:image/svg+xml;utf8,' . $svg . '\')">box</div>', $config);

		$this->assertSame(['box'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * With svgAutoFont, the text of an SVG element with a ">" in an attribute is still drawn
	 */
	public function testSvgTextWithAGreaterThanSignInAnAttributeIsDrawn()
	{
		$mpdf = new Mpdf(['autoScriptToLang' => true, 'autoLangToFont' => true, 'svgAutoFont' => true]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50">'
			. '<text x="5" y="30" font-size="20" data-note="a > b">Hello שלום</text></svg>');

		$this->assertMatchesRegularExpression('/\/Subtype \/Form[^\n]*\n.*?stream\n[^\n]*?BT/s', $mpdf->Output('', 'S'));
	}

	/**
	 * An index entry's sub-entry is split at the ":" in its text, not one in a ">" in a tag's attribute before it
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnIndexEntryIsSplitAtTheColonInItsText(array $config)
	{
		$mpdf = $this->drawDocument('<p>x<indexentry content="&lt;span title=&quot;ratio &gt; 1:2&quot;&gt;Mammals&lt;/span&gt;:Elephants" /></p>'
			. '<indexinsert usedivletters="off" />', $config);

		$drawn = array_map(function ($text) {
			return preg_replace('/\s+/', ' ', trim($text));
		}, $mpdf->drawnText);

		$this->assertSame(['x', 'Mammals', '- Elephants 1'], $drawn);
	}

	/**
	 * The base path is read from a <base> tag's href, not a data-href after it or one cut at a ">"
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testTheBaseHrefIsReadByName(array $config)
	{
		$mpdf = $this->drawDocument('<html><head><base title="a > b" href="https://example.com/docs/" data-href="https://example.com/x/"></head>'
			. '<body><p>t</p></body></html>', $config);

		$this->assertSame('https://example.com/docs/', $mpdf->basepath);
	}

	/**
	 * <html> tags, and the direction and language they give the document
	 *
	 * @return array[]
	 */
	public static function htmlLangAndDir()
	{
		return [
			'a ">" before them' => ['<html title="a > b" data-lang="de" lang="fr" dir="rtl">', 'rtl', true],
			'unquoted' => ['<HTML LANG=fr DIR=RTL>', 'rtl', true],
			'a data-lang and data-dir' => ['<html data-lang="fr" data-dir="rtl">', 'ltr', false],
		];
	}

	/**
	 * The document's lang and dir are read from the <html> tag by name. A p:lang(fr) rule colours the text blue
	 *
	 * @dataProvider htmlLangAndDir
	 *
	 * @param string $html
	 * @param string $directionality
	 * @param bool $french
	 */
	public function testTheHtmlLangAndDirAreReadByName($html, $directionality, $french)
	{
		$mpdf = $this->drawDocument('<style>p:lang(fr) { color: blue }</style>' . $html . '<body><p>Text</p></body></html>');

		$this->assertSame($directionality, $mpdf->directionality);
		$this->assertSame($french, $this->keyedByText($mpdf, $mpdf->drawnColours)['Text'] === self::BLUE);
	}

	/**
	 * A stylesheet is linked whatever order its attributes come in, past a ">" in another attribute
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAStylesheetLinkIsReadPastAGreaterThanSign(array $config)
	{
		$css = 'data:text/css,p { color: blue; }';
		$drawn = $this->drawnColours('<link title="a > b" href="' . $css . '" rel="stylesheet"><p>Blue text</p>', $config);

		$this->assertSame(self::BLUE, $drawn['Blue text']);
	}

	/**
	 * A <style> block with a ">" in its title is read, and kept for the medium its media attribute names rather than a
	 * data-media after it
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAStyleBlockIsReadPastAGreaterThanSign(array $config)
	{
		$drawn = $this->drawnColours('<style title="a > b" media="print" data-media="screen">p { color: blue; }</style><p>Blue text</p>', $config + ['CSSselectMedia' => 'print']);

		$this->assertSame(self::BLUE, $drawn['Blue text']);
	}

	/**
	 * A "<pre>" inside an attribute value does not start a pre block: the real one after it keeps its line breaks
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAPreTagInsideAnAttributeIsNotAPreBlock(array $config)
	{
		$mpdf = $this->drawDocument("<p><a title=\"see <pre>\">link</a></p><pre>first\nsecond</pre>", $config);

		$this->assertSame(['link', 'first', 'second'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * A "<style>" inside an attribute value is not a style block, so an HTML comment after it is still not drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAStyleTagInsideAnAttributeIsNotAStyleBlock(array $config)
	{
		$mpdf = $this->drawDocument('<p title="a <style> b">Text</p><!-- hidden --><style>p { color: blue; }</style>', $config);

		$this->assertSame(['Text'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * An element whose name starts with svg, such as <svg-icon>, is not an inline SVG
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testACustomElementNamedLikeSvgIsNotAnSvg(array $config)
	{
		$mpdf = $this->drawDocument('<svg-icon name="x"></svg-icon><p>Middle</p>'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="red"/></svg><p>After</p>', $config);

		$this->assertSame(['Middle', 'After'], array_map('trim', $mpdf->drawnText));
		$this->assertCount(1, $mpdf->formobjects);
	}

	/**
	 * A title with attributes, one of them holding a ">", is the document's title
	 */
	public function testATitleWithAttributesIsRead()
	{
		$mpdf = $this->drawDocument('<html><head><title lang="en" data-note="a > b">The title</title></head><body><p>t</p></body></html>');

		$this->assertSame('The title', $mpdf->title);
	}

}
