<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Reading selectors as written, before they are uppercased, into compound selectors, combinators and a specificity
 */
class SelectorCompilerTest extends TestCase
{

	/**
	 * @var SelectorCompiler
	 */
	private $compiler;

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * A compiler reading the tags of a default configuration
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
		$this->compiler = new SelectorCompiler($this->mpdf);
	}

	/**
	 * Lets go of the compiler and its Mpdf
	 */
	protected function tear_down()
	{
		unset($this->compiler, $this->mpdf);

		parent::tear_down();
	}

	/**
	 * A list is split at the commas between its selectors only
	 *
	 * @dataProvider lists
	 *
	 * @param string $list
	 * @param string[] $expected
	 */
	public function testSplitsAListAtItsTopLevelCommas($list, array $expected)
	{
		$this->assertSame($expected, $this->compiler->splitList($list));
	}

	/**
	 * A selector list and the selectors it holds
	 *
	 * @return array[]
	 */
	public function lists()
	{
		return [
			'plain list' => ['h1, h2 ,h3', ['h1', 'h2', 'h3']],
			'one selector' => ['div p', ['div p']],
			'commas inside :is()' => [':is(h1, h2) + p, div', [':is(h1, h2) + p', 'div']],
			'commas inside :not()' => ['p:not(.a, .b), li', ['p:not(.a, .b)', 'li']],
			'nested parentheses' => [':is(:not(.a, .b), p), q', [':is(:not(.a, .b), p)', 'q']],
			'comma in a double-quoted attribute value' => ['[title="a,b"], p', ['[title="a,b"]', 'p']],
			'comma in a single-quoted attribute value' => ["[title='a,b'],p", ["[title='a,b']", 'p']],
			'quote inside the other kind of quotes' => ['[title="it\'s, here"], p', ['[title="it\'s, here"]', 'p']],
			'escaped comma' => ['.a\,b, p', ['.a\,b', 'p']],
			'escaped quote inside a string' => ['[title="a\",b"], p', ['[title="a\",b"]', 'p']],
			'empty member' => ['h1,,h2', ['h1', '', 'h2']],
		];
	}

	/**
	 * A selector is read into its compounds, the combinators between them and its specificity
	 *
	 * @dataProvider selectors
	 *
	 * @param string $selector
	 * @param array[] $compounds The tag, ids, classes and pseudo-classes of each compound, left to right
	 * @param string[] $combinators
	 */
	public function testCompilesASelector($selector, array $compounds, array $combinators)
	{
		$compiled = $this->compiler->compile($selector);

		$this->assertNotNull($compiled);
		$this->assertSame($combinators, $compiled['combinators']);
		$this->assertCount(count($compounds), $compiled['compounds']);
		foreach ($compounds as $i => $compound) {
			$compound += ['attributes' => []];
			$actual = array_intersect_key($compiled['compounds'][$i], $compound);
			ksort($compound);
			ksort($actual);
			$this->assertSame($compound, $actual, sprintf('Compound %d of "%s"', $i, $selector));
		}
	}

	/**
	 * A selector, the parts of each of its compounds and its combinators
	 *
	 * @return array[]
	 */
	public function selectors()
	{
		$tag = function ($tag) {
			return ['tag' => $tag, 'ids' => [], 'classes' => [], 'pseudos' => []];
		};

		return [
			'type' => ['p', [$tag('P')], []],
			'type in any case' => ['Blockquote', [$tag('BLOCKQUOTE')], []],
			'child' => ['div > p', [$tag('DIV'), $tag('P')], ['>']],
			'child with no spaces' => ['div>p', [$tag('DIV'), $tag('P')], ['>']],
			'adjacent sibling' => ['h1+p', [$tag('H1'), $tag('P')], ['+']],
			'general sibling with extra spaces' => ["h1 \t ~ \n p", [$tag('H1'), $tag('P')], ['~']],
			'descendant over several spaces' => ['div   p', [$tag('DIV'), $tag('P')], [' ']],
			'a chain of combinators' => [
				'ul > li + li ~ li a',
				[$tag('UL'), $tag('LI'), $tag('LI'), $tag('LI'), $tag('A')],
				['>', '+', '~', ' '],
			],
			'classes and ids uppercased, in the order written' => [
				'p.Note#Main.b',
				[['tag' => 'P', 'ids' => ['MAIN'], 'classes' => ['NOTE', 'B'], 'pseudos' => []]],
				[],
			],
			'class with an escaped colon' => ['.md\:flex', [['tag' => null, 'classes' => ['MD:FLEX']]], []],
			'id with a hex escape for a leading digit' => ['#\31 23', [['tag' => null, 'ids' => ['123']]], []],
			'class with a hex escape for a non-ASCII letter' => ['.caf\e9', [['classes' => ['CAFé']]], []],
			'class written in UTF-8' => ['.café', [['classes' => ['CAFé']]], []],
			'first-child' => ['li:first-child', [['tag' => 'LI', 'pseudos' => [['nth-child', 0, 1]]]], []],
			'first-of-type' => ['p:First-Of-Type', [['tag' => 'P', 'pseudos' => [['nth-of-type', 0, 1]]]], []],
			'nth-child with spaces in its argument' => [
				'li:nth-child( 2n + 1 )',
				[['tag' => 'LI', 'pseudos' => [['nth-child', 2, 1]]]],
				[],
			],
			'nth-of-type' => ['p:nth-of-type(3)', [['tag' => 'P', 'pseudos' => [['nth-of-type', 0, 3]]]], []],
			'pseudo-class with no type' => [':first-child', [['tag' => null, 'pseudos' => [['nth-child', 0, 1]]]], []],
			'lang with one range' => [':lang(fr)', [['pseudos' => [['lang', ['fr']]]]], []],
			'lang with ranges in any case, some quoted' => [
				'p:lang(FR, "de-AT" , en)',
				[['tag' => 'P', 'pseudos' => [['lang', ['fr', 'de-at', 'en']]]]],
				[],
			],
			'attribute in a chain' => [
				'div[data-role="card"] > a[href]',
				[['tag' => 'DIV', 'attributes' => [['DATA-ROLE', '=', 'card', false]]], ['tag' => 'A', 'attributes' => [['HREF', '', '', false]]]],
				['>'],
			],
		];
	}

	/**
	 * An attribute selector is read into its name, operator, value and whether the value is compared
	 * case-insensitively
	 *
	 * @dataProvider attributeSelectors
	 *
	 * @param string $selector
	 * @param array $expected
	 */
	public function testReadsAnAttributeSelector($selector, array $expected)
	{
		$compiled = $this->compiler->compile($selector);

		$this->assertNotNull($compiled);
		$this->assertSame([$expected], $compiled['compounds'][0]['attributes']);
	}

	/**
	 * An attribute selector, and what it is read into
	 *
	 * @return array[]
	 */
	public function attributeSelectors()
	{
		return [
			'presence' => ['[data-x]', ['DATA-X', '', '', false]],
			'presence with spaces inside the brackets' => ['[ data-x ]', ['DATA-X', '', '', false]],
			'name in any case' => ['[Data-X]', ['DATA-X', '', '', false]],
			'equals an identifier' => ['[data-x=Card]', ['DATA-X', '=', 'Card', false]],
			'equals a double-quoted string with a comma and a space' => ['[title="a, b"]', ['TITLE', '=', 'a, b', false]],
			'equals a single-quoted string' => ["[title='it is']", ['TITLE', '=', 'it is', false]],
			'a string with an escaped quote' => ['[title="say \\"hi\\""]', ['TITLE', '=', 'say "hi"', false]],
			'a string with a hex escape' => ['[title="caf\\e9"]', ['TITLE', '=', 'café', false]],
			'spaces around the operator' => ['[data-x = "y"]', ['DATA-X', '=', 'y', false]],
			'one of a list' => ['[rel~=nofollow]', ['REL', '~=', 'nofollow', true]],
			'a language and its subtags' => ['[lang|=en]', ['LANG', '|=', 'en', true]],
			'starts with' => ['[href^="http"]', ['HREF', '^=', 'http', false]],
			'ends with' => ['[href$=".pdf"]', ['HREF', '$=', '.pdf', false]],
			'contains' => ['[title*=draft]', ['TITLE', '*=', 'draft', false]],
			'an attribute HTML compares case-insensitively' => ['[type=checkbox]', ['TYPE', '=', 'checkbox', true]],
			'the i flag' => ['[data-x="y" i]', ['DATA-X', '=', 'y', true]],
			'the I flag with no space before it' => ['[data-x="y"I]', ['DATA-X', '=', 'y', true]],
			'a value compared case-insensitively, held lowercased' => ['[type=Text]', ['TYPE', '=', 'text', true]],
			'a value with the i flag, held lowercased' => ['[data-x="Yes" i]', ['DATA-X', '=', 'yes', true]],
			'the s flag' => ['[type="text" s]', ['TYPE', '=', 'text', false]],
			'the s flag on a class, whose value the tokenizer uppercases' => ['[class="a" s]', ['CLASS', '=', 'a', true]],
			'an id' => ['[id=main]', ['ID', '=', 'main', true]],
		];
	}

	/**
	 * :not(), :is() and :where() hold their arguments compiled. :is() and :where() leave out an argument they cannot
	 * read, and :where() is held as :is()
	 *
	 * @dataProvider selectorArguments
	 *
	 * @param string $selector
	 * @param string $name The name it is held under
	 * @param array[] $subjects The tag and classes of the last compound of each argument kept
	 */
	public function testReadsTheSelectorsAPseudoClassTakes($selector, $name, array $subjects)
	{
		$compiled = $this->compiler->compile($selector);

		$this->assertNotNull($compiled);
		$pseudo = $compiled['compounds'][0]['pseudos'][0];
		$this->assertSame($name, $pseudo[0]);
		$this->assertSame($subjects, array_map(function ($argument) {
			$subject = $argument['compounds'][count($argument['compounds']) - 1];

			return [$subject['tag'], $subject['classes']];
		}, $pseudo[1]));
	}

	/**
	 * A selector using :not(), :is() or :where(), the name its pseudo-class is held under, and its arguments
	 *
	 * @return array[]
	 */
	public function selectorArguments()
	{
		return [
			'not with one class' => ['p:not(.a)', 'not', [[null, ['A']]]],
			'not with a list, spaces and all' => ['p:not( .a ,.b )', 'not', [[null, ['A']], [null, ['B']]]],
			'not with a complex selector' => ['p:not(div > p)', 'not', [['P', []]]],
			'is' => [':is(h1, h2)', 'is', [['H1', []], ['H2', []]]],
			'where' => [':where(h1, .x)', 'is', [['H1', []], [null, ['X']]]],
			'is leaves out what it cannot read' => [':is(h1, a:hover, ::before, h2)', 'is', [['H1', []], ['H2', []]]],
			'is nested in is' => [':is(:is(h1), p)', 'is', [[null, []], ['P', []]]],
			'not with a comma inside an attribute value' => ['p:not([title="a, b"])', 'not', [[null, []]]],
			'not with a closing parenthesis inside an attribute value' => ['p:not([title=")"], .x)', 'not', [[null, []], [null, ['X']]]],
		];
	}

	/**
	 * A selector mPDF cannot match, or that is not valid, compiles to nothing
	 *
	 * @dataProvider unmatchableSelectors
	 *
	 * @param string $selector
	 */
	public function testRejectsASelectorItCannotMatch($selector)
	{
		$this->assertNull($this->compiler->compile($selector));
	}

	/**
	 * A selector that is not valid, or uses something mPDF cannot match
	 *
	 * @return array[]
	 */
	public function unmatchableSelectors()
	{
		return [
			'empty' => [''],
			'a combinator at the end' => ['div >'],
			'a combinator at the start' => ['> p'],
			'two combinators together' => ['div > + p'],
			'a tag mPDF does not style' => ['sup'],
			'a tag mPDF does not style as an ancestor' => ['sup > b'],
			'a pseudo-element' => ['p::before'],
			'a pseudo-element written with one colon is not a pseudo-class mPDF knows' => ['p:before'],
			'a pseudo-class with no meaning in a PDF' => ['a:hover'],
			'a pseudo-class that needs the elements after it' => ['li:last-child'],
			'nth-child with an argument it cannot read' => ['li:nth-child(2n+)'],
			'nth-child with "of" and a selector' => ['li:nth-child(2 of .a)'],
			'nth-child left open' => ['li:nth-child(2'],
			'a namespace' => ['svg|p'],
			'a class with no name' => ['p.'],
			'a class starting with a digit' => ['.1a'],
			'an id with no name' => ['#'],
			'a stray closing parenthesis' => ['p)'],
			'a brace left from a nested at-rule' => ['} p'],
			'an at-rule' => ['@font-face'],
			'a percentage from a keyframes block' => ['50%'],
			'an attribute left open' => ['[data-x'],
			'an attribute with no name' => ['[]'],
			'an attribute with an operator mPDF does not know' => ['[data-x!=y]'],
			'an attribute with no value after its operator' => ['[data-x=]'],
			'an attribute value that is a number' => ['[data-x=1]'],
			'an attribute with a flag mPDF does not know' => ['[data-x=y q]'],
			'an attribute string left open' => ['[data-x="y]'],
			'an attribute with a namespace' => ['[xml|lang]'],
			'lang with no range' => [':lang()'],
			'lang with an empty range between commas' => [':lang(fr,,de)'],
			'lang with no parentheses' => [':lang'],
			'not with an argument it cannot read' => ['p:not(.a, a:hover)'],
			'not with a pseudo-element' => ['p:not(::before)'],
			'not left open' => ['p:not(.a'],
			'not with nothing' => ['p:not()'],
			'is with nothing it can read' => [':is(a:hover, ::before)'],
			'where with nothing' => [':where()'],
			'a stray comma in not' => ['p:not(.a,)'],
		];
	}

	/**
	 * Specificity counts ids; then classes and pseudo-classes; then tags. The universal selector counts nothing
	 *
	 * @dataProvider specificities
	 *
	 * @param string $selector
	 * @param int[] $expected
	 */
	public function testCountsSpecificity($selector, array $expected)
	{
		$this->assertSame($expected, $this->compiler->compile($selector)['specificity']);
	}

	/**
	 * A selector and its specificity
	 *
	 * @return array[]
	 */
	public function specificities()
	{
		return [
			'type' => ['p', [0, 0, 1]],
			'universal' => ['*', [0, 0, 0]],
			'two types' => ['div > p', [0, 0, 2]],
			'class' => ['.a', [0, 1, 0]],
			'class on a type' => ['p.a', [0, 1, 1]],
			'id' => ['#i', [1, 0, 0]],
			'every kind' => ['div#i.a.b p:first-child', [1, 3, 2]],
			'pseudo-class on the universal selector' => ['*:nth-of-type(2)', [0, 1, 0]],
			'two ids' => ['#a #b', [2, 0, 0]],
			'attribute' => ['[data-x]', [0, 1, 0]],
			'attribute on a type' => ['a[href^="http"]', [0, 1, 1]],
			'lang' => ['p:lang(fr)', [0, 1, 1]],
			'attributes, classes and pseudo-classes together' => ['li.a[data-x]:first-child:lang(en)', [0, 4, 1]],
			'not counts as its argument' => ['p:not(.a)', [0, 1, 1]],
			'not counts as its most specific argument' => ['p:not(.a, #b, div span)', [1, 0, 1]],
			'is counts as its most specific argument' => [':is(h1, .a.b, div p) + p', [0, 2, 1]],
			'is with a type and an id' => [':is(p, #x)', [1, 0, 0]],
			'where counts nothing' => [':where(#a, .b) > p', [0, 0, 1]],
			'where inside is' => [':is(:where(#a), .b)', [0, 1, 0]],
			'is leaves out what it cannot read from its specificity too' => [':is(.a, #b:hover)', [0, 1, 0]],
			'nested' => ['li:not(:is(.a, #b)):nth-child(2)', [1, 1, 1]],
		];
	}

	/**
	 * Whether a selector names the universal selector, which waits for #530
	 */
	public function testMarksASelectorThatNamesTheUniversalSelector()
	{
		$this->assertTrue($this->compiler->compile('*')['universal']);
		$this->assertTrue($this->compiler->compile('div > *')['universal']);
		$this->assertTrue($this->compiler->compile('* + p')['universal']);
		$this->assertFalse($this->compiler->compile('div > :first-child')['universal']);
		$this->assertTrue($this->compiler->compile('p:not(*)')['universal']);
		$this->assertTrue($this->compiler->compile(':is(h1, *) + p')['universal']);
		$this->assertFalse($this->compiler->compile(':is(h1, h2) + p')['universal']);
	}

	/**
	 * An an+b argument is read into a and b
	 *
	 * @dataProvider nthArguments
	 *
	 * @param string $argument
	 * @param int[]|null $expected
	 */
	public function testReadsAnNthArgument($argument, $expected)
	{
		$this->assertSame($expected, $this->compiler->parseNth($argument));
	}

	/**
	 * An an+b argument, and a and b, or null when it is not valid
	 *
	 * @return array[]
	 */
	public function nthArguments()
	{
		return [
			'odd' => ['odd', [2, 1]],
			'even in capitals' => ['EVEN', [2, 0]],
			'a number' => ['3', [0, 3]],
			'a signed number' => ['+3', [0, 3]],
			'n' => ['n', [1, 0]],
			'-n+3' => ['-n+3', [-1, 3]],
			'2n+1' => ['2n+1', [2, 1]],
			'2n - 1 with spaces' => ['2n - 1', [2, -1]],
			'+3n' => ['+3n', [3, 0]],
			'space inside an+b' => ['2 n', null],
			'sign with nothing after it' => ['2n+', null],
			'a word' => ['first', null],
		];
	}

	/**
	 * The tags a type selector may name follow Mpdf::$allowedCSStags as it is when the selector is read
	 */
	public function testFollowsTheAllowedTagsWhenTheyChange()
	{
		$this->assertNull($this->compiler->compile('sup'));

		$this->mpdf->allowedCSStags .= '|SUP';

		$this->assertNotNull($this->compiler->compile('sup'));
	}
}
