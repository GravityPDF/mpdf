<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The child, adjacent sibling and general sibling combinators, and :first-child, :nth-child(), :first-of-type and
 * :nth-of-type() on any element, matched against the open elements. The legacy parser cannot read these selectors,
 * so their rules go to the matcher and are applied with the descendant rules.
 */
class StructuralSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLACK = '0.000 g';

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
			'child: a child matches, a grandchild does not' => [
				'div > p { color: #f00; }',
				'<div><p>child</p><section><p>grandchild</p></section></div><p>outside</p>',
				['child' => self::RED, 'grandchild' => self::BLACK, 'outside' => self::BLACK],
			],
			'child written with no spaces' => [
				'div>p { color: #f00; }',
				'<div><p>child</p></div>',
				['child' => self::RED],
			],
			'child of body: a top-level element matches, a nested one does not' => [
				'body > p { color: #f00; }',
				'<p>top</p><div><p>nested</p></div>',
				['top' => self::RED, 'nested' => self::BLACK],
			],
			'child chain' => [
				'div > section > p { color: #f00; }',
				'<div><section><p>chain</p></section></div><section><p>no div</p></section><div><article><section><p>deeper</p></section></article></div>',
				['chain' => self::RED, 'no div' => self::BLACK, 'deeper' => self::BLACK],
			],
			'child of an inline element' => [
				'p > em { color: #f00; }',
				'<p><em>direct</em> and <b><em>inside b</em></b></p>',
				['direct' => self::RED, 'inside b' => self::BLACK],
			],
			'adjacent sibling: only the next element' => [
				'h1 + p { color: #f00; }',
				'<p>before</p><h1>Title</h1><p>adjacent</p><p>second</p>',
				['before' => self::BLACK, 'adjacent' => self::RED, 'second' => self::BLACK],
			],
			'adjacent sibling: another element between them' => [
				'h1 + p { color: #f00; }',
				'<h1>Title</h1><div>between</div><p>after the div</p>',
				['after the div' => self::BLACK],
			],
			'adjacent sibling: text between them does not count' => [
				'h1 + p { color: #f00; }',
				'<div><h1>Title</h1> loose text <p>after text</p></div>',
				['after text' => self::RED],
			],
			'adjacent sibling: not across a parent' => [
				'h1 + p { color: #f00; }',
				'<h1>Title</h1><div><p>first in div</p></div>',
				['first in div' => self::BLACK],
			],
			'adjacent sibling: an empty element counts' => [
				'hr + p { color: #f00; } h2 + p { color: #00f; }',
				'<h2>Heading</h2><hr /><p>after rule</p>',
				['after rule' => self::RED],
			],
			'adjacent inline siblings' => [
				'b + i { color: #f00; }',
				'<p><b>bold</b> <i>next</i> <i>later</i></p>',
				['bold' => self::BLACK, 'next' => self::RED, 'later' => self::BLACK],
			],
			'general sibling: every later sibling' => [
				'h1 ~ p { color: #f00; }',
				'<p>before</p><h1>Title</h1><p>one</p><div>div</div><p>two</p><div><p>nested</p></div>',
				['before' => self::BLACK, 'one' => self::RED, 'two' => self::RED, 'nested' => self::BLACK],
			],
			'first-child' => [
				'li:first-child { color: #f00; }',
				'<ul><li>one</li><li>two</li></ul><ol><li>uno</li></ol>',
				['one' => self::RED, 'two' => self::BLACK, 'uno' => self::RED],
			],
			'first-child: an element of another tag before it' => [
				'p:first-child { color: #f00; }',
				'<div><h2>Heading</h2><p>second child</p></div><div><p>first child</p></div>',
				['second child' => self::BLACK, 'first child' => self::RED],
			],
			'first-child: an empty element before it' => [
				'p:first-child { color: #f00; }',
				'<div><img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" /><p>after img</p></div>',
				['after img' => self::BLACK],
			],
			'first-child on its own' => [
				':first-child { color: #f00; }',
				'<h1>Title</h1><div><p>first</p><p>second</p></div>',
				['Title' => self::RED, 'first' => self::RED, 'second' => self::BLACK],
			],
			'nth-child even' => [
				'li:nth-child(2n) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li><li>four</li></ul>',
				['one' => self::BLACK, 'two' => self::RED, 'three' => self::BLACK, 'four' => self::RED],
			],
			'nth-child odd' => [
				'li:nth-child(odd) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li></ul>',
				['one' => self::RED, 'two' => self::BLACK, 'three' => self::RED],
			],
			'nth-child with a number' => [
				'li:nth-child(3) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li><li>four</li></ul>',
				['two' => self::BLACK, 'three' => self::RED, 'four' => self::BLACK],
			],
			'nth-child with the first few' => [
				'li:nth-child(-n + 2) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li></ul>',
				['one' => self::RED, 'two' => self::RED, 'three' => self::BLACK],
			],
			'nth-child from a start' => [
				'li:nth-child(n+3) { color: #f00; }',
				'<ul><li>one</li><li>two</li><li>three</li><li>four</li></ul>',
				['two' => self::BLACK, 'three' => self::RED, 'four' => self::RED],
			],
			'nth-child counts every element, not only its own tag' => [
				'p:nth-child(2) { color: #f00; }',
				'<div><h2>Heading</h2><p>second child</p><p>third child</p></div>',
				['second child' => self::RED, 'third child' => self::BLACK],
			],
			'nth-child counts omitted end tags as closed' => [
				'li:nth-child(2) { color: #f00; }',
				'<ul><li>one<li>two<li>three</ul>',
				['one' => self::BLACK, 'two' => self::RED, 'three' => self::BLACK],
			],
			'first-of-type' => [
				'p:first-of-type { color: #f00; }',
				'<div><h2>Heading</h2><p>first p</p><p>second p</p></div>',
				['first p' => self::RED, 'second p' => self::BLACK],
			],
			'nth-of-type' => [
				'p:nth-of-type(2) { color: #f00; }',
				'<div><p>first p</p><h2>Heading</h2><p>second p</p><p>third p</p></div>',
				['first p' => self::BLACK, 'second p' => self::RED, 'third p' => self::BLACK],
			],
			'nth-of-type on an ancestor' => [
				'section:nth-of-type(2) p { color: #f00; }',
				'<div><section><p>in first</p></section><div>x</div><section><p>in second</p></section></div>',
				['in first' => self::BLACK, 'in second' => self::RED],
			],
			'nth-child on an ancestor' => [
				'li:nth-child(2) p { color: #f00; }',
				'<ul><li><p>in one</p></li><li><p>in two</p></li></ul>',
				['in one' => self::BLACK, 'in two' => self::RED],
			],
			'combinators and pseudo-classes chained' => [
				'div.card > ul li:first-child + li { color: #f00; }',
				'<div class="card"><ul><li>one</li><li>two</li><li>three</li></ul></div><div><ul><li>a</li><li>b</li></ul></div>',
				['one' => self::BLACK, 'two' => self::RED, 'three' => self::BLACK, 'b' => self::BLACK],
			],
			'a descendant combinator backtracks past an ancestor that fails' => [
				'div > p span > b { color: #f00; }',
				'<div><p><span><i><span><b>deep</b></span></i></span></p></div>',
				['deep' => self::RED],
			],
			'a general sibling combinator backtracks past a sibling that fails' => [
				'h2.a ~ p + p { color: #f00; }',
				'<div><h2 class="a">A</h2><h2>B</h2><p>one</p><p>two</p></div>',
				['one' => self::BLACK, 'two' => self::RED],
			],
			'nested lists' => [
				'ul > li > ul > li:first-child { color: #f00; }',
				'<ul><li>outer<ul><li>inner one</li><li>inner two</li></ul></li></ul>',
				['outer' => self::BLACK, 'inner one' => self::RED, 'inner two' => self::BLACK],
			],
			'table cells' => [
				'td:first-child { color: #f00; } td + td + td { color: #00f; }',
				'<table><tr><td>c1</td><td>c2</td><td>c3</td></tr></table>',
				['c1' => self::RED, 'c2' => self::BLACK, 'c3' => self::BLUE],
			],
			'a cell after a colspan is the next sibling' => [
				'td + td { color: #f00; }',
				'<table><tr><td colspan="2">wide</td><td>second cell</td></tr></table>',
				['wide' => self::BLACK, 'second cell' => self::RED],
			],
			"mPDF's own tags between cells and rows are not their siblings" => [
				'td + td { color: #f00; } tr + tr > td:nth-of-type(1) { color: #00f; }',
				'<table><tr><td>first</td><bookmark content="b" /><td>second</td></tr>'
				. '<tocentry content="t" /><tr><td>next row</td></tr></table>',
				['first' => self::BLACK, 'second' => self::RED, 'next row' => self::BLUE],
			],
			'a row written straight into a table sits in a tbody' => [
				'table > tbody > tr > td { color: #f00; } table > tr > td { color: #00f; }',
				'<table><tr><td>cell</td></tr></table>',
				['cell' => self::RED],
			],
			'rows of a thead and a tbody are counted apart' => [
				'tr:first-child > td { color: #f00; }',
				'<table><thead><tr><td>head</td></tr></thead><tbody><tr><td>body one</td></tr><tr><td>body two</td></tr></tbody></table>',
				['head' => self::RED, 'body one' => self::RED, 'body two' => self::BLACK],
			],
			'block and inline content of a cell' => [
				'td > p { color: #f00; } td > span { color: #00f; }',
				'<table><tr><td><p>block</p><span>inline</span></td></tr></table>',
				['block' => self::RED, 'inline' => self::BLUE],
			],
			'nested tables' => [
				'td > table td:first-child { color: #f00; }',
				'<table><tr><td>outer first</td><td><table><tr><td>inner first</td><td>inner second</td></tr></table></td></tr></table>',
				['outer first' => self::BLACK, 'inner first' => self::RED, 'inner second' => self::BLACK],
			],
			'a tag the legacy parser reads keeps its own rule, the matcher adds to it' => [
				'p { color: #00f; } div > p { color: #f00; }',
				'<div><p>child</p></div><p>top</p>',
				['child' => self::RED, 'top' => self::BLUE],
			],
			'a rule naming the universal selector waits for #530' => [
				'div > * { color: #f00; } * + p { color: #f00; }',
				'<div><p>child</p><p>next</p></div>',
				['child' => self::BLACK, 'next' => self::BLACK],
			],
			'a pseudo-class that needs the elements after it is still dropped' => [
				'li:last-child { color: #f00; }',
				'<ul><li>one</li><li>last</li></ul>',
				['last' => self::BLACK],
			],
		];
	}

	/**
	 * Rules using these selectors compete with the rest by specificity, then by position, and lose to the inline style
	 *
	 * @dataProvider precedence
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The colour the text "text" should be drawn in
	 */
	public function testAppliesMatchedRulesBySpecificityThenPosition($css, $html, $expected)
	{
		$colours = $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => CssMode::STANDARD]);

		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * Competing rules, a document, and the colour its text should be drawn in
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		$html = '<div id="outer" class="box"><p id="inner" class="note">text</p></div>';

		return [
			'the more specific wins, written first' => ['div > p.note { color: #f00; } div > p { color: #00f; }', $html, self::RED],
			'the more specific wins, written last' => ['div > p { color: #00f; } div > p.note { color: #f00; }', $html, self::RED],
			'a class beats any number of tags' => [
				'div > p:first-child { color: #f00; } body > div > p { color: #00f; }',
				$html,
				self::RED,
			],
			'an id beats any number of classes' => [
				'#outer > p { color: #f00; } .box > .note:first-child:nth-child(1) { color: #00f; }',
				$html,
				self::RED,
			],
			'the same specificity: the later wins' => ['div > p { color: #f00; } div > p { color: #00f; }', $html, self::BLUE],
			'the same specificity from different selectors: the later wins' => [
				'.box > p { color: #f00; } div > .note { color: #00f; }',
				$html,
				self::BLUE,
			],
			'the later of two stylesheets wins' => [
				'div > p { color: #f00; }</style><style>div > p { color: #00f; }',
				$html,
				self::BLUE,
			],
			'an id beats a child rule' => [
				'#inner { color: #00f; } div > p { color: #f00; }',
				$html,
				self::BLUE,
			],
			'a heavier descendant rule beats a child rule' => [
				'div > p { color: #f00; } #outer p.note { color: #00f; }',
				$html,
				self::BLUE,
			],
			'the inline style still wins' => [
				'div > p { color: #f00; }',
				'<div><p style="color: #00f">text</p></div>',
				self::BLUE,
			],
			'a rule filed under a class and one under a tag' => [
				'div > .note { color: #00f; } body > div > p { color: #f00; }',
				$html,
				self::BLUE,
			],
		];
	}

	/**
	 * A cell's border from a matched rule outranks the table's where the two meet and are alike, as the border of a
	 * cell rule does, so it is drawn over the table's
	 */
	public function testGivesACellBorderFromAMatchedRuleTheRankOfACellRule()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->SetCompression(false);
		$mpdf->WriteHTML(
			'<style>table { border-collapse: collapse; border: 1mm solid #00f; } tr > td:first-child { border-left: 1mm solid #f00; }</style>'
			. '<table><tr><td>a</td><td>b</td></tr></table>'
		);
		$pdf = $mpdf->Output('', 'S');

		$this->assertGreaterThan(strrpos($pdf, '0.000 0.000 1.000 RG'), strrpos($pdf, '1.000 0.000 0.000 RG'));
	}

	/**
	 * A kept block that runs onto the next page is laid out again from its start tag, and its children are
	 * counted once
	 */
	public function testCountsTheChildrenOfAKeptBlockOnceWhenItIsLaidOutAgain()
	{
		$filler = str_repeat('<p>filler</p>', 45);
		$mpdf = $this->drawDocument(
			'<style>div.kept > p:nth-child(3) { color: #f00; } div.kept > p + p + p + p { color: #00f; }</style>'
			. $filler
			. '<div class="kept" style="page-break-inside: avoid"><p>one</p><p>two</p><p>three</p><p>four</p><p>five</p></div>'
		);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$pages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));
		$this->assertSame(2, $pages['one'], 'The block should have moved to the next page');
		$this->assertSame(self::BLACK, $colours['two']);
		$this->assertSame(self::RED, $colours['three']);
		$this->assertSame(self::BLUE, $colours['four']);
		$this->assertSame(self::BLUE, $colours['five']);
	}

	/**
	 * A block closed and opened again around a forced page break keeps the rules its element matches
	 *
	 * @dataProvider pageBreakTypes
	 *
	 * @param string $type
	 */
	public function testKeepsTheRulesOfBlocksOpenedAgainAfterAPageBreak($type)
	{
		$mpdf = $this->drawDocument(
			'<style>section > div.x { color: #f00; } div.x > p:first-child { color: #00f; }</style>'
			. '<section><div class="x" style="box-decoration-break: clone"><p>before<pagebreak type="' . $type . '" />after</p><p>next</p></div></section>'
		);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$pages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));
		$this->assertSame(1, $pages['before']);
		$this->assertSame(2, $pages['after']);
		$this->assertSame(self::BLUE, $colours['before']);
		$this->assertSame(self::BLUE, $colours['after']);
		$this->assertSame(self::RED, $colours['next']);
	}

	/**
	 * The ways a forced page break closes the open blocks and opens them again
	 *
	 * @return array[]
	 */
	public function pageBreakTypes()
	{
		return [
			'every block' => ['cloneall'],
			'blocks with box-decoration-break: clone' => ['clonebycss'],
		];
	}

	/**
	 * A header is matched against its own elements, not the flow's elements open when it is set and measured
	 */
	public function testMatchesAHeaderAgainstItsOwnElements()
	{
		$mpdf = $this->drawDocument(
			'<style>div > p { color: #f00; } body > p { color: #00f; }</style>'
			. '<htmlpageheader name="h"><p>header p</p><div><p>header div p</p></div></htmlpageheader>'
			. '<div><p>flow one</p><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>flow two</p>'
			. '<pagebreak /><p>page two</p></div>',
			['setAutoTopMargin' => 'stretch']
		);
		$mpdf->Output('', 'S');

		$colours = [];
		foreach ($mpdf->drawnText as $i => $text) {
			$colours[trim($text)][] = $mpdf->drawnColours[$i];
		}

		$this->assertGreaterThan(1, count($colours['header p']), 'The header should be written on each page');
		$this->assertSame([self::BLUE], array_unique($colours['header p']));
		$this->assertSame([self::RED], array_unique($colours['header div p']));
		$this->assertSame([self::RED], $colours['flow two']);
		$this->assertSame([self::RED], $colours['page two']);
	}

	/**
	 * An element whose page-break-before closes and opens again the blocks around it is matched against its own
	 * elements once they are open again
	 */
	public function testMatchesAnElementThatStartsAPageInsideBlocks()
	{
		$mpdf = $this->drawDocument(
			'<style>p + p.x { color: #f00; } body > section { color: #00f; }</style>'
			. '<section><p>first</p><p class="x" style="page-break-before: always">second</p></section>'
		);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$pages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));
		$this->assertSame(2, $pages['second']);
		$this->assertSame(self::BLUE, $colours['first']);
		$this->assertSame(self::RED, $colours['second']);
	}

	/**
	 * A positioned block is matched against the elements it was written in, and its content against the block. The
	 * block's box comes from its rules once, not again for the <div> that stands in for it
	 */
	public function testMatchesAPositionedBlockAndItsContent()
	{
		$block = '<span class="wrap"><div class="box" style="position: absolute; top: 100mm; width: 80mm%s">'
			. 'loose<p>first</p><p>second</p></div></span>';
		$mpdf = $this->drawDocument(
			'<style>span.wrap > div.box { left: 60mm; padding-left: 10mm; color: #f00; }'
			. ' div.box > p { color: #00f; } div.box > p + p { color: #008000; }</style>'
			. sprintf($block, '')
		);
		$inline = $this->drawDocument(sprintf($block, '; left: 60mm; padding-left: 10mm'));

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$this->assertSame(self::RED, $colours['loose']);
		$this->assertSame(self::BLUE, $colours['first']);
		$this->assertSame(self::GREEN, $colours['second']);

		$this->assertEqualsWithDelta($inline->drawnBoxes[1][1], $mpdf->drawnBoxes[1][1], 0.001, 'The block should be where its inline style would put it');
	}

	/**
	 * Content written in several calls to WriteHTML() is matched against the elements the earlier calls left open
	 */
	public function testMatchesContentWrittenInParts()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<style>div.x > p + p { color: #f00; }</style>');
		$mpdf->WriteHTML('<div class="x"><p>one</p>', HTMLParserMode::HTML_BODY, true, false);
		$mpdf->WriteHTML('<p>two</p></div>', HTMLParserMode::HTML_BODY, false, true);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$this->assertSame(self::BLACK, $colours['one']);
		$this->assertSame(self::RED, $colours['two']);
	}

	/**
	 * The spans autoScriptToLang wraps a run of another script in are not elements of the document: they are not
	 * counted among their siblings or matched themselves
	 */
	public function testIgnoresTheSpansWrappedAroundARunOfAnotherScript()
	{
		$colours = $this->drawnColours(
			'<style>p > b:first-child { color: #f00; } p > i:nth-child(2) { color: #00f; } p > span { color: #008000; }</style>'
			. '<p>שלום <b>bold</b> שלום <i>italic</i> שלום</p>',
			['mode' => '', 'autoScriptToLang' => true, 'autoLangToFont' => true]
		);

		$this->assertSame(self::RED, $colours['bold']);
		$this->assertSame(self::BLUE, $colours['italic']);
		foreach ($colours as $text => $colour) {
			$this->assertNotSame(self::GREEN, $colour, sprintf('"%s" is drawn as a span', $text));
		}
	}
}
