<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The universal selector *, alone, with a class, an id or an attribute, on either side of a combinator, and inside
 * :is(), :where() and :not(). In the standard CSS mode it matches every element of the document, body included, and
 * weighs nothing. mPDF's own tags that take CSS, <barcode>, <dottab> and <textcircle>, are only reached by a rule that
 * names their tag, or an id or class they have. In the legacy mode rules using it are dropped, as they were before.
 */
class UniversalSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	/** @var string[] The contexts document() writes the cases in */
	private static $contexts = ['block', 'inline', 'table cell', 'list item', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'];

	/**
	 * Each piece of text is drawn in the colour the rules leave it in, in every context, and where the context puts
	 * it. The document is closed, so its header and footer are drawn on the page
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {O} for another tag
	 * @param array[] $groups As cases() gives them
	 */
	public function testMatchesInEveryContext($context, $css, array $groups)
	{
		$mpdf = $this->drawDocument($this->document($context, $css, $groups), ['cssMode' => CssMode::STANDARD]);
		$mpdf->OutputBinaryData();

		$expected = $this->expectedColours($groups);
		$this->assertDrawnInColours($expected, $this->keyedByText($mpdf, $mpdf->drawnColours));
		$this->assertDrawnInContext($context, $mpdf, array_keys($expected));
	}

	/**
	 * Every case in every context
	 *
	 * @return array[]
	 */
	public function casesInContexts()
	{
		$data = [];
		foreach (self::$contexts as $context) {
			foreach ($this->cases() as $name => $case) {
				$data[$context . ': ' . $name] = [$context, $case[0], $case[1]];
			}
		}

		return $data;
	}

	/**
	 * Rules, with the elements they are matched against: those that match, and near misses that must not. A rule
	 * that could match the element each group is written in is kept to its children with .g >, so the children do
	 * not inherit the colour from it
	 *
	 * @return array[] Each [css, groups]. A group is [the class of the element it is written in, its children], and a
	 *                 child is [T or O for its tag, its text, the colour it should be drawn in, its attributes]
	 */
	private function cases()
	{
		$r = self::RED;
		$g = self::GREEN;
		$b = self::BLUE;
		$k = self::BLACK;

		return [
			'alone' => [
				'* { color: #f00; }',
				[['g', [['T', 'subject', $r], ['O', 'another tag', $r]]]],
			],
			'loses to a type selector written before it' => [
				'{T} { color: #00f; } * { color: #f00; }',
				[['g', [['T', 'named by the type', $b], ['O', 'not named', $r]]]],
			],
			'loses to a class written before it' => [
				'.c { color: #008000; } * { color: #f00; }',
				[['g', [['T', 'with the class', $g, 'class="c"'], ['T', 'without the class', $r]]]],
			],
			'with a class' => [
				'*.c { color: #f00; }',
				[['g', [['T', 'with the class', $r, 'class="c"'], ['T', 'without the class', $k], ['O', 'another tag with it', $r, 'class="c"']]]],
			],
			'with an id' => [
				'*#i { color: #f00; }',
				[['g', [['T', 'with the id', $r, 'id="i"'], ['T', 'without the id', $k]]]],
			],
			'with an attribute' => [
				'*[title] { color: #f00; }',
				[['g', [['T', 'with a title', $r, 'title="t"'], ['O', 'without a title', $k]]]],
			],
			'with a pseudo-class' => [
				'.g > *:first-child { color: #f00; }',
				[['g', [['T', 'first child', $r], ['T', 'second child', $k]]]],
			],
			'as the subject of a descendant combinator' => [
				'.in * { color: #f00; }',
				[['in', [['T', 'inside', $r], ['O', 'another tag inside', $r]]], ['out', [['T', 'outside', $k]]]],
			],
			'as an ancestor' => [
				'* {T} { color: #f00; }',
				[['g', [['T', 'a descendant of any', $r], ['O', 'another tag', $k]]]],
			],
			'as the subject of a child combinator' => [
				'.in > * { color: #f00; }',
				[['in', [['T', 'a child of in', $r]]], ['out', [['T', 'a child of out', $k]]]],
			],
			'as the parent' => [
				'* > {T} { color: #f00; }',
				[['g', [['T', 'a child of any', $r], ['O', 'another tag', $k]]]],
			],
			'as the sibling before' => [
				'.g > * + {T} { color: #f00; }',
				[['g', [['T', 'first', $k], ['T', 'after a sibling', $r], ['O', 'another tag after a sibling', $k]]]],
			],
			'as the sibling after' => [
				'.g > {O} + * { color: #f00; }',
				[['g', [['O', 'another tag', $k], ['T', 'after the other tag', $r], ['T', 'after the subject tag', $k]]]],
			],
			'on both sides of a general sibling combinator' => [
				'.g > * ~ * { color: #f00; }',
				[['g', [['T', 'the first', $k], ['O', 'the second', $r], ['T', 'the third', $r]]]],
			],
			'in is' => [
				'.g > :is(*) + * { color: #f00; }',
				[['g', [['T', 'the first', $k], ['O', 'the second', $r]]]],
			],
			'in where, which weighs nothing' => [
				'{T} { color: #f00; } :where(*) { color: #00f; }',
				[['g', [['T', 'named by the type', $r], ['O', 'not named', $b]]]],
			],
			'in not, which then matches nothing' => [
				'{T}:not(*) { color: #f00; } :not(*) { color: #f00; }',
				[['g', [['T', 'subject', $k], ['O', 'another tag', $k]]]],
			],
			'with a class in not' => [
				'{T}:not(*.c) { color: #f00; }',
				[['g', [['T', 'without the class', $r], ['T', 'with the class', $k, 'class="c"']]]],
			],
		];
	}

	/**
	 * @param string $context
	 *
	 * @return string[] The tag of the subjects in a context, another tag for their siblings, and the start, with %s
	 *                  for its class, and the end of the element each group is written in
	 */
	private function tags($context)
	{
		switch ($context) {
			case 'inline':
				return ['em', 'b', '<p class="%s">', '</p>'];
			case 'table cell':
				return ['td', 'th', '<table><tr class="%s">', '</tr></table>'];
			case 'list item':
				return ['li', 'div', '<ul class="%s">', '</ul>'];
			default:
				return ['p', 'h5', '<div class="%s">', '</div>'];
		}
	}

	/**
	 * A document with each group in its own parent, in a context, under a stylesheet naming the tags the context
	 * gives them
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {O} for the other tag
	 * @param array[] $groups As cases() gives them
	 *
	 * @return string
	 */
	private function document($context, $css, array $groups)
	{
		list($subject, $other, $open, $close) = $this->tags($context);

		$html = '';
		foreach ($groups as $group) {
			$html .= sprintf($open, $group[0]);
			foreach ($group[1] as $child) {
				$tag = $child[0] === 'T' ? $subject : $other;
				$html .= '<' . $tag . (isset($child[3]) ? ' ' . $child[3] : '') . '>' . $child[1] . '</' . $tag . '> ';
			}
			$html .= $close;
		}

		// The groups of the table cell context are tables already. Put in a cell, they would sit under a td that the
		// rules naming the subjects' tag match too, and that the cells would inherit from
		if ($context !== 'table cell') {
			$html = $this->inContext($context, $html);
		}

		return '<style>' . str_replace(['{T}', '{O}'], [$subject, $other], $css) . '</style>' . $html;
	}

	/**
	 * @param array[] $groups As cases() gives them
	 *
	 * @return array<string, string> The text of each child and the colour it should be drawn in
	 */
	private function expectedColours(array $groups)
	{
		$expected = [];
		foreach ($groups as $group) {
			foreach ($group[1] as $child) {
				$expected[$child[1]] = $child[2];
			}
		}

		return $expected;
	}

	/**
	 * In the legacy CSS mode rules using the universal selector are dropped: every case in every context is drawn as
	 * it is with those rules taken out
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $context
	 * @param string $css
	 * @param array[] $groups
	 */
	public function testTheLegacyModeDropsThem($context, $css, array $groups)
	{
		$without = preg_replace('/[^{}]*\*[^{}]*\{[^}]*\}/', '', $css);
		$this->assertNotSame($css, $without);

		$dropped = $this->drawDocument($this->document($context, $without, $groups), ['cssMode' => CssMode::LEGACY]);
		$dropped->OutputBinaryData();
		$written = $this->drawDocument($this->document($context, $css, $groups), ['cssMode' => CssMode::LEGACY]);
		$written->OutputBinaryData();

		$this->assertSame($dropped->drawnText, $written->drawnText);
		$this->assertSame($dropped->drawnColours, $written->drawnColours);
		$this->assertSame($dropped->drawnBoxes, $written->drawnBoxes);
	}

	/**
	 * The universal selector reaches html and body: text written straight into the body takes its colour, and body's
	 * own rules outweigh it wherever they are written. html is body's parent, so rules that need body to have a
	 * parent, or html to be there, reach it, and text takes their colour from one or the other. Rules that need body to
	 * have a sibling do not reach it
	 *
	 * @dataProvider bodyRules
	 *
	 * @param string $css
	 * @param string $expected The colour of the text written straight into the body
	 */
	public function testReachesBody($css, $expected)
	{
		$colours = $this->drawnColours('<style>' . $css . '</style>before<p>paragraph</p>after', ['cssMode' => CssMode::STANDARD]);

		$this->assertSame($expected, $colours['before']);
		$this->assertSame($expected, $colours['after']);
	}

	/**
	 * Rules, and the colour of the text written straight into the body
	 *
	 * @return array[]
	 */
	public function bodyRules()
	{
		return [
			'the universal selector' => ['* { color: #f00; }', self::RED],
			'a body rule written after it' => ['* { color: #f00; } body { color: #00f; }', self::BLUE],
			'a body rule written before it' => ['body { color: #00f; } * { color: #f00; }', self::BLUE],
			'the later of two' => ['* { color: #00f; } * { color: #f00; }', self::RED],
			'an important universal rule over a body rule' => ['* { color: #f00 !important; } body { color: #00f; }', self::RED],
			'an important body rule over an important universal rule' => ['body { color: #00f !important; } * { color: #f00 !important; }', self::BLUE],
			'not another tag' => [':not(p) { color: #f00; }', self::RED],
			'not body, which html is' => ['*:not(body) { color: #f00; }', self::RED],
			'a child of anything, as body is of html' => ['* > * { color: #f00; }', self::RED],
			'a descendant of anything, as body is of html' => ['* * { color: #f00; }', self::RED],
			'a child of anything but html' => [':not(html) > * { color: #f00; }', self::BLACK],
			'a sibling of anything' => ['* + *, * ~ * { color: #f00; }', self::BLACK],
			'with a class it does not have' => ['*.c { color: #f00; }', self::BLACK],
		];
	}

	/**
	 * The universal selector reaches html, so its font size is the one rem is read against
	 */
	public function testSetsTheSizeRemIsReadAgainst()
	{
		$mpdf = $this->drawDocument('<style>* { font-size: 20pt; } p.rem { font-size: 1rem; } p.half { font-size: 0.5rem; }</style><p class="rem">rem</p><p class="half">half</p>', ['cssMode' => CssMode::STANDARD]);
		$sizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);

		$this->assertEqualsWithDelta(20, $sizes['rem'], 0.001);
		$this->assertEqualsWithDelta(10, $sizes['half'], 0.001);
	}

	/**
	 * A universal rule's background reaches body, which paints the page
	 */
	public function testPaintsThePageFromAUniversalBackground()
	{
		$html = '<style>* { background-color: #ffff00; }</style><p>text</p>';
		$this->assertNotEmpty($this->drawDocument($html, ['cssMode' => CssMode::STANDARD])->bodyBackgroundColor);

		$mpdf = $this->drawDocument($html, ['cssMode' => CssMode::LEGACY]);
		$this->assertEmpty($mpdf->bodyBackgroundColor);
	}

	/**
	 * A reset that zeroes every margin and padding reaches the default margins of paragraphs, headings, lists and
	 * blockquotes, a list's indent and a cell's padding, as in a browser: the text is drawn where it is drawn with the
	 * same declarations written on each element. In the legacy mode it is dropped, and the text is drawn where it is
	 * with no stylesheet. The table sets the line-height the legacy mode gives it, which the standard mode would take
	 * from body
	 */
	public function testAResetReachesTheDefaultMarginsAndPadding()
	{
		$html = '<style>table { line-height: 1.2; }</style><h1%1$s>Heading</h1><p%1$s>First paragraph</p><p%1$s>Second paragraph</p>'
			. '<ul%1$s><li%1$s>Bullet</li></ul><ol%1$s><li%1$s>Number</li></ol>'
			. '<dl%1$s><dt%1$s>Term</dt><dd%1$s>Description</dd></dl><blockquote%1$s>Quote</blockquote>'
			. '<table%1$s><tr%1$s><td%1$s>Cell</td></tr></table><p%1$s>Last paragraph</p>';
		$reset = 'margin: 0; padding: 0';

		$plain = $this->drawDocument(sprintf($html, ''), ['cssMode' => CssMode::STANDARD]);
		$inline = $this->drawDocument(sprintf($html, ' style="' . $reset . '"'), ['cssMode' => CssMode::STANDARD]);
		$document = '<style>* { ' . $reset . '; }</style>' . sprintf($html, '');
		$universal = $this->drawDocument($document, ['cssMode' => CssMode::STANDARD]);
		$legacy = $this->drawDocument($document, ['cssMode' => CssMode::LEGACY]);

		$this->assertNotEquals($plain->drawnBoxes, $inline->drawnBoxes, 'The declarations should move the text');
		$this->assertSame($inline->drawnText, $universal->drawnText);
		$this->assertEquals($inline->drawnBoxes, $universal->drawnBoxes);
		$this->assertEquals($inline->drawnY, $universal->drawnY);
		$this->assertEquals($plain->drawnBoxes, $legacy->drawnBoxes);
		$this->assertEquals($plain->drawnY, $legacy->drawnY);
	}

	/**
	 * Rules that do not name mPDF's own tags leave a barcode, a dot tab and a text circle as they are drawn without
	 * them. The page is otherwise the same, so its content is compared whole
	 *
	 * @dataProvider rulesNotNamingMpdfTags
	 *
	 * @param string $css
	 */
	public function testLeavesMpdfOwnTagsAlone($css)
	{
		$this->assertSame($this->ownTagsPage(''), $this->ownTagsPage($css));
	}

	/**
	 * Rules that reach every child of the paragraph the tags are written in, which holds nothing else
	 *
	 * @return array[]
	 */
	public function rulesNotNamingMpdfTags()
	{
		$everything = '{ display: none; margin: 5mm; padding: 5mm; border: 1mm solid #f00; color: #f00; background-color: #ff0;'
			. ' vertical-align: top; font-size: 20pt; font-weight: bold; outdent: 20mm; visibility: hidden; }';

		return [
			'the universal selector' => ['p > * ' . $everything],
			'the universal selector with a pseudo-class' => ['*:empty ' . $everything],
			'a pseudo-class alone' => [':empty ' . $everything],
			'an attribute alone' => ['[code], [r], [outdent] ' . $everything],
			'not another tag' => ['p > :not(b) ' . $everything],
			'a sibling' => ['p > * + *, p > * ~ * ' . $everything],
		];
	}

	/**
	 * A rule that names one of mPDF's own tags by its tag, or by an id or class it has, still reaches it
	 *
	 * @dataProvider rulesNamingMpdfTags
	 *
	 * @param string $css
	 */
	public function testStillAppliesRulesThatNameMpdfOwnTags($css)
	{
		$this->assertNotSame($this->ownTagsPage(''), $this->ownTagsPage($css));
	}

	/**
	 * Rules that name one of mPDF's own tags
	 *
	 * @return array[]
	 */
	public function rulesNamingMpdfTags()
	{
		return [
			'the text circle by its tag' => ['textcircle { display: none; }'],
			'the dot tab by its tag' => ['p > dottab { outdent: 20mm; }'],
			'the barcode by its class' => ['*.code { display: none; }'],
			'the barcode by its id' => ['#code { display: none; }'],
		];
	}

	/**
	 * @param string $css
	 *
	 * @return string[] The content of each page of a paragraph holding a barcode, a dot tab and a text circle, written
	 *                  in the standard mode under a stylesheet
	 */
	private function ownTagsPage($css)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML(
			'<style>' . $css . '</style><p><barcode id="code" class="code" code="12345" type="C39" />'
			. '<dottab outdent="2mm" /><textcircle r="15mm" top-text="Top text" style="font-size: 10pt" /></p>'
		);

		return $mpdf->pages;
	}

	/**
	 * The spans mPDF wraps a run of another script in are not the document's elements, and the universal selector
	 * does not reach them any more than a type selector does
	 */
	public function testLeavesTheSpansAroundARunOfAnotherScriptAlone()
	{
		$html = '<p style="color: #00f">שלום <b>bold</b> שלום</p>';
		$config = ['mode' => '', 'autoScriptToLang' => true, 'autoLangToFont' => true, 'cssMode' => CssMode::STANDARD];

		$colours = $this->drawnColours('<style>p > * { color: #f00; }</style>' . $html, $config);

		$this->assertSame(self::RED, $colours['bold']);
		foreach ($colours as $text => $colour) {
			if ($text !== 'bold') {
				$this->assertSame(self::BLUE, $colour, sprintf('"%s" is drawn as a child of the paragraph', $text));
			}
		}
	}

	/**
	 * The universal selector weighs nothing: every rule naming a tag, class, id, attribute or pseudo-class outweighs
	 * it, and between universal rules the later one wins
	 *
	 * @dataProvider precedence
	 *
	 * @param string $css
	 * @param string $expected The colour of the text "subject"
	 */
	public function testPrecedence($css, $expected)
	{
		$html = '<div><p class="c" id="i" title="t">subject</p></div>';

		$this->assertSame($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => CssMode::STANDARD])['subject']);
	}

	/**
	 * Competing rules, and the colour they leave the subject in
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		return [
			'a type written before' => ['p { color: #008000; } * { color: #f00; }', self::GREEN],
			'a class written before' => ['.c { color: #008000; } * { color: #f00; }', self::GREEN],
			'an id written before' => ['#i { color: #008000; } * { color: #f00; }', self::GREEN],
			'an attribute written before' => ['[title] { color: #008000; } * { color: #f00; }', self::GREEN],
			'a pseudo-class written before' => [':first-child { color: #008000; } * { color: #f00; }', self::GREEN],
			'a class with the universal selector against a type' => ['*.c { color: #008000; } p { color: #f00; }', self::GREEN],
			'a universal chain against a type' => ['* > * { color: #f00; } p { color: #008000; }', self::GREEN],
			'a universal chain against a class' => ['* div > * { color: #f00; } .c { color: #008000; }', self::GREEN],
			'a universal chain weighs as its type: the later wins' => ['p { color: #f00; } div > * { color: #008000; }', self::GREEN],
			'a universal chain with two types against one' => ['body div > * { color: #008000; } p { color: #f00; }', self::GREEN],
			'where against the universal selector: the later wins' => ['* { color: #f00; } :where(p) { color: #008000; }', self::GREEN],
			'the universal selector against where: the later wins' => [':where(p) { color: #f00; } * { color: #008000; }', self::GREEN],
			'two universal rules: the later wins' => ['* { color: #f00; } * { color: #008000; }', self::GREEN],
		];
	}
}
