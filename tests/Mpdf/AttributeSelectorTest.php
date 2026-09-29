<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Attribute selectors, with every operator and the i and s flags, and :lang() matching the language an element has
 * or inherits, from an ancestor or from the <html> or <body> tag
 */
class AttributeSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLACK = '0.000 g';
	const LINK = '0.000 0.000 1.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';

	/** An image whose path, as written, has slashes in it */
	const IMAGE = __DIR__ . '/../data/img/ratio-16x9.png';

	/**
	 * Each piece of text named is drawn in the colour the rules leave it in
	 *
	 * @dataProvider selectors
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Pieces of text and the colour each should be drawn in
	 */
	public function testMatchesTheElementsTheSelectorNames($css, $html, array $expected)
	{
		$colours = $this->drawnColours('<style>' . $css . '</style>' . $html);

		foreach ($expected as $text => $colour) {
			$this->assertArrayHasKey($text, $colours, sprintf('"%s" is not drawn', $text));
			$this->assertSame($colour, $colours[$text], sprintf('"%s" is drawn in the wrong colour', $text));
		}
	}

	/**
	 * A rule, a document for it, and the colours its text should be drawn in: elements that match, and near misses
	 * that must not
	 *
	 * @return array[]
	 */
	public function selectors()
	{
		return [
			'presence' => [
				'[data-x] { color: #f00; }',
				'<p data-x="1">with</p><p data-x>bare</p><p data-y="1">without</p>',
				['with' => self::RED, 'bare' => self::RED, 'without' => self::BLACK],
			],
			'presence on a type' => [
				'p[title] { color: #f00; }',
				'<p title="t">p with</p><div title="t">div with</div>',
				['p with' => self::RED, 'div with' => self::BLACK],
			],
			'equals, case-sensitively' => [
				'p[data-status="paid"] { color: #f00; }',
				'<p data-status="paid">paid</p><p data-status="Paid">capital</p><p data-status="paid in full">longer</p>',
				['paid' => self::RED, 'capital' => self::BLACK, 'longer' => self::BLACK],
			],
			'equals with the i flag' => [
				'p[data-status="paid" i] { color: #f00; }',
				'<p data-status="PAID">upper</p>',
				['upper' => self::RED],
			],
			'an attribute HTML compares case-insensitively' => [
				'ol[type="a"] > li { color: #f00; }',
				'<ol type="A"><li>lettered</li></ol><ol type="1"><li>numbered</li></ol>',
				['lettered' => self::RED, 'numbered' => self::BLACK],
			],
			'the same attribute with the s flag' => [
				'ol[type="a" s] > li { color: #f00; }',
				'<ol type="A"><li>upper</li></ol><ol type="a"><li>lower</li></ol>',
				['upper' => self::BLACK, 'lower' => self::RED],
			],
			'an id in any case' => [
				'[id=Main] { color: #f00; }',
				'<p id="main">main</p><p id="mainly">mainly</p>',
				['main' => self::RED, 'mainly' => self::BLACK],
			],
			'one word of a list' => [
				'p[data-tags~="urgent"] { color: #f00; }',
				'<p data-tags="new urgent">listed</p><p data-tags="urgently">part</p>',
				['listed' => self::RED, 'part' => self::BLACK],
			],
			'a class as a list' => [
				'[class~=b] { color: #f00; }',
				'<p class="a b c">in list</p><p class="ab">joined</p>',
				['in list' => self::RED, 'joined' => self::BLACK],
			],
			'a language and its subtags' => [
				'[lang|=en] { color: #f00; }',
				'<p lang="en">en</p><p lang="en-GB">en-GB</p><p lang="eng">eng</p>',
				['en' => self::RED, 'en-GB' => self::RED, 'eng' => self::BLACK],
			],
			'starts with' => [
				'a[href^="https://"] { color: #f00; }',
				'<p><a href="https://example.com/">secure</a> <a href="http://example.com/">plain</a></p>',
				['secure' => self::RED, 'plain' => self::LINK],
			],
			'starts with, on a relative link as written, not as the base path resolves it' => [
				'a[href^="docs/"] { color: #f00; }',
				'<base href="https://example.com/site/"><p><a href="docs/guide.html">relative</a> <a href="/docs/guide.html">rooted</a></p>',
				['relative' => self::RED, 'rooted' => self::LINK],
			],
			'ends with' => [
				'a[href$=".pdf"] { color: #f00; }',
				'<p><a href="https://example.com/a.pdf">pdf</a> <a href="https://example.com/a.PDF">upper</a> <a href="https://example.com/a.pdf.html">html</a></p>',
				['pdf' => self::RED, 'upper' => self::LINK, 'html' => self::LINK],
			],
			'ends with, with the i flag' => [
				'a[href$=".pdf" i] { color: #f00; }',
				'<p><a href="https://example.com/a.PDF">upper</a></p>',
				['upper' => self::RED],
			],
			'contains' => [
				'a[href*="example"] { color: #f00; }',
				'<p><a href="https://www.example.com/">has</a> <a href="https://www.other.com/">lacks</a></p>',
				['has' => self::RED, 'lacks' => self::LINK],
			],
			'a link and an anchor with no href' => [
				'a[href] { color: #f00; }',
				'<p><a href="#top">link</a> <a name="top">anchor</a></p>',
				['link' => self::RED, 'anchor' => self::BLACK],
			],
			'a value with an entity' => [
				'p[title="Tom & Jerry"] { color: #f00; }',
				'<p title="Tom &amp; Jerry">entity</p><p title="Tom and Jerry">words</p>',
				['entity' => self::RED, 'words' => self::BLACK],
			],
			'a value with a comma and a space in the same list as another selector' => [
				'p[title="a, b"], p.other { color: #f00; }',
				'<p title="a, b">titled</p><p class="other">other</p><p title="a">short</p>',
				['titled' => self::RED, 'other' => self::RED, 'short' => self::BLACK],
			],
			'the next sibling of an element with an attribute' => [
				'h2[id] + p { color: #f00; }',
				'<h2 id="s1">One</h2><p>after id</p><h2>Two</h2><p>after none</p>',
				['after id' => self::RED, 'after none' => self::BLACK],
			],
			'a table cell' => [
				'td[data-status="overdue"] { color: #f00; }',
				'<table><tr><td data-status="overdue">late</td><td data-status="paid">done</td></tr></table>',
				['late' => self::RED, 'done' => self::BLACK],
			],
			'the next sibling of an element hidden with display: none' => [
				'[data-hidden="yes"] + p { color: #f00; }',
				'<div data-hidden="yes" style="display: none"><p>hidden</p></div><p>after hidden</p>'
				. '<div data-hidden="no" style="display: none"><p>hidden</p></div><p>after shown</p>',
				['after hidden' => self::RED, 'after shown' => self::BLACK],
			],
			'the next sibling of an image whose path has a slash in it, not its child' => [
				'img[src$=".png"] + b { color: #f00; } img[src$=".png"] b { color: #00f; } img[src$=".jpg"] ~ i { color: #f00; }',
				'<p><img src="' . self::IMAGE . '" width="5mm"> <b>after png</b> <i>not after jpg</i></p>',
				['after png' => self::RED, 'not after jpg' => self::BLACK],
			],
			'an attribute on an inline ancestor, from a rule the legacy parser reads' => [
				'[lang=fr] b { color: #f00; }',
				'<p><span lang="fr"><b>in span</b></span> <b>outside</b></p>',
				['in span' => self::RED, 'outside' => self::BLACK],
			],
		];
	}

	/**
	 * :lang() matches the language an element has or inherits, the document's included
	 *
	 * @dataProvider languages
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Pieces of text and the colour each should be drawn in
	 */
	public function testMatchesTheLanguageAnElementHasOrInherits($css, $html, array $expected)
	{
		$this->testMatchesTheElementsTheSelectorNames($css, $html, $expected);
	}

	/**
	 * A :lang() rule, a document for it, and the colours its text should be drawn in
	 *
	 * @return array[]
	 */
	public function languages()
	{
		return [
			'inherited from a block' => [
				'p:lang(fr) { color: #f00; }',
				'<div lang="fr"><p>inherits</p></div><p>none</p>',
				['inherits' => self::RED, 'none' => self::BLACK],
			],
			'its own, as before' => [
				'p:lang(fr) { color: #f00; }',
				'<p lang="fr">own</p><p lang="de">other</p>',
				['own' => self::RED, 'other' => self::BLACK],
			],
			'inherited with a region' => [
				':lang(fr) > b { color: #f00; }',
				'<p lang="fr-CA"><b>regional</b></p><p lang="fy"><b>frisian</b></p>',
				['regional' => self::RED, 'frisian' => self::BLACK],
			],
			'from the html tag' => [
				'p:lang(de) { color: #f00; }',
				'<html lang="de"><body><p>document</p><p lang="en">english</p></body></html>',
				['document' => self::RED, 'english' => self::BLACK],
			],
			'from the body tag' => [
				'p:lang(de) { color: #f00; }',
				'<html><body lang="de"><p>body</p></body></html>',
				['body' => self::RED],
			],
			'in a table cell, from the table' => [
				'td:lang(fr) { color: #f00; }',
				'<table lang="fr"><tr><td>cell</td></tr></table><table><tr><td>plain</td></tr></table>',
				['cell' => self::RED, 'plain' => self::BLACK],
			],
			'inside an inline element, from a rule the legacy parser reads' => [
				'div :lang(fr) { color: #f00; }',
				'<div><p><span lang="fr">french span</span> plain</p></div>',
				['french span' => self::RED],
			],
			'one of several' => [
				'p:lang(de, fr) { color: #f00; }',
				'<div lang="fr"><p>french</p></div><div lang="it"><p>italian</p></div>',
				['french' => self::RED, 'italian' => self::BLACK],
			],
		];
	}

	/**
	 * An attribute or :lang() counts as a class towards specificity
	 *
	 * @dataProvider precedence
	 *
	 * @param string $css
	 * @param string $expected The colour the text "text" should be drawn in
	 */
	public function testCountsAttributesAsClasses($css, $expected)
	{
		$colours = $this->drawnColours(
			'<style>' . $css . '</style><div lang="fr"><p class="a" data-x="1" data-y="1">text</p></div>'
		);

		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * Competing rules and the colour "text" should be drawn in
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		return [
			'an attribute and a class, the later wins' => ['div > p[data-x] { color: #f00; } div > p.a { color: #008000; }', self::GREEN],
			'a class and an attribute, the later wins' => ['div > p.a { color: #008000; } div > p[data-x] { color: #f00; }', self::RED],
			'two attributes beat one class' => ['div > p[data-x][data-y] { color: #f00; } div > p.a { color: #008000; }', self::RED],
			'lang and a class, the later wins' => ['div > p:lang(fr) { color: #f00; } div > p.a { color: #008000; }', self::GREEN],
		];
	}

	/**
	 * An image's own rules match the path in its src attribute, which has slashes in it, as written
	 */
	public function testMatchesAnImageByItsPath()
	{
		$html = '<p><img src="' . self::IMAGE . '" style="height: 5mm"> after</p>';
		$bare = $this->drawDocument($html);
		$matched = $this->drawDocument('<style>p img[src$="16x9.png"] { width: 40mm; }</style>' . $html);
		$missed = $this->drawDocument('<style>p img[src$=".jpg"], p img[src^="/nowhere/"] { width: 40mm; }</style>' . $html);

		$this->assertEqualsWithDelta($bare->drawnBoxes[0][1] + 40 - 5 * 16 / 9, $matched->drawnBoxes[0][1], 0.1, 'The image should be 40mm wide');
		$this->assertEqualsWithDelta($bare->drawnBoxes[0][1], $missed->drawnBoxes[0][1], 0.001, 'The image should keep its width');
	}

	/**
	 * An element inside one hidden with display: none keeps its attributes as written, so selectors that look at
	 * it as an earlier sibling or an ancestor match them
	 *
	 * @dataProvider hiddenSelectors
	 *
	 * @param string $selector
	 * @param bool $expected Whether it matches the span still open inside the hidden block
	 */
	public function testMatchesTheAttributesOfHiddenElements($selector, $expected)
	{
		$this->assertSame($expected, $this->matchesLastOpenElement(
			'<div style="display: none" lang="fr"><p data-state="Draft/One" title="a, b">hidden</p><span data-x="1">',
			$selector
		));
	}

	/**
	 * A selector, and whether it matches the span inside the hidden block
	 *
	 * @return array[]
	 */
	public function hiddenSelectors()
	{
		return [
			'its own attribute' => ['span[data-x="1"]', true],
			'another value' => ['span[data-x="2"]', false],
			'the attribute of an earlier sibling' => ['p[data-state="Draft/One"] + span', true],
			'the same value in another case' => ['p[data-state="draft/one"] + span', false],
			'a value with a comma and a space' => ['[title="a, b"] ~ span', true],
			'the language it inherits' => ['span:lang(fr)', true],
			'another language' => ['span:lang(de)', false],
		];
	}

	/**
	 * A header is matched against its own elements, and inherits the language of the <html> tag
	 */
	public function testMatchesAHeaderAgainstItsOwnElements()
	{
		$mpdf = $this->drawDocument(
			'<html lang="de"><head><style>p[data-slot="left"] { color: #f00; } p:lang(de) + p[data-slot] { color: #00f; }'
			. ' p:lang(fr) { color: #008000; }</style></head><body>'
			. '<htmlpageheader name="h"><p data-slot="left">header left</p><p data-slot="right">header right</p>'
			. '<p>header plain</p></htmlpageheader>'
			. '<sethtmlpageheader name="h" value="on" show-this-page="1" /><p data-slot="flow">flow</p>'
			. '</body></html>',
			['setAutoTopMargin' => 'stretch']
		);
		$mpdf->Output('', 'S');

		$colours = array_combine(array_map('trim', $mpdf->drawnText), $mpdf->drawnColours);
		$this->assertSame(self::RED, $colours['header left']);
		$this->assertSame(self::BLUE, $colours['header right']);
		$this->assertSame(self::BLACK, $colours['header plain']);
		$this->assertSame(self::BLACK, $colours['flow']);
	}

	/**
	 * A positioned block is matched by its attributes against the elements it was written in, and its content
	 * inherits the language of the element it was written in
	 */
	public function testMatchesAPositionedBlockAndItsContent()
	{
		$colours = $this->drawnColours(
			'<style>div[data-box="note"] { color: #f00; } div[data-box="note"] > p[data-x] { color: #00f; }'
			. ' p:lang(fr) { color: #008000; }</style>'
			. '<div data-box="note" style="position: absolute; top: 100mm; left: 20mm; width: 80mm">loose<p data-x>marked</p><p>plain</p></div>'
			. '<section lang="fr-CA"><div style="position: absolute; top: 150mm; left: 20mm; width: 80mm"><p>french</p></div></section>'
			. '<div data-box="other" style="position: absolute; top: 200mm; left: 20mm; width: 80mm"><p data-x>other</p></div>'
		);

		$this->assertSame(self::RED, $colours['loose']);
		$this->assertSame(self::BLUE, $colours['marked']);
		$this->assertSame(self::RED, $colours['plain']);
		$this->assertSame(self::GREEN, $colours['french']);
		$this->assertSame(self::BLACK, $colours['other']);
	}

	/**
	 * A kept block that runs onto the next page is matched again from its start tag, with the same attributes and
	 * language
	 */
	public function testMatchesAKeptBlockLaidOutAgain()
	{
		$mpdf = $this->drawDocument(
			'<style>div[data-kept] > p[data-n="2"] { color: #f00; } p:lang(fr) { color: #00f; }</style>'
			. str_repeat('<p>filler</p>', 45)
			. '<div data-kept="yes" lang="fr" style="page-break-inside: avoid"><p data-n="1" lang="en">one</p><p data-n="2">two</p><p data-n="3">three</p></div>'
		);

		$colours = array_combine(array_map('trim', $mpdf->drawnText), $mpdf->drawnColours);
		$pages = array_combine(array_map('trim', $mpdf->drawnText), array_column($mpdf->drawnBoxes, 0));
		$this->assertSame(2, $pages['one'], 'The block should have moved to the next page');
		$this->assertSame(self::BLACK, $colours['one']);
		$this->assertSame(self::RED, $colours['two']);
		$this->assertSame(self::BLUE, $colours['three']);
	}
}
