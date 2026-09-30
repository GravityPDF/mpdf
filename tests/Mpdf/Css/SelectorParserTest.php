<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;

class SelectorParserTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	private $mpdf;
	private $parser;

	public function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
		$this->parser = new SelectorParser($this->mpdf);
	}

	public function tear_down()
	{
		unset($this->parser, $this->mpdf);
		parent::tear_down();
	}

	public function testParsePageSelector()
	{
		$tags = ['@PAGE'];
		$expected = '@PAGE';
		$this->assertEquals($expected, $this->parser->parsePageSelector($tags));
		$this->assertFalse((bool) $this->mpdf->mirrorMargins);

		$tags = ['@PAGE', ':LEFT'];
		$expected = '@PAGE>>PSEUDO>>LEFT';
		$this->assertEquals($expected, $this->parser->parsePageSelector($tags));
		$this->assertTrue($this->mpdf->mirrorMargins);

		$tags = ['@PAGE', 'Named'];
		$expected = '@PAGE>>NAMED>>Named';
		$this->assertEquals($expected, $this->parser->parsePageSelector($tags));
		$this->assertTrue((bool) $this->mpdf->mirrorMargins); // once on, it doesnt turn off
	}

	public function testParseSimpleSelector()
	{
		$this->assertEquals('CLASS>>foo', $this->parser->parseSimpleSelector(['.foo']));
		$this->assertEquals('ID>>bar', $this->parser->parseSimpleSelector(['#bar']));
		$this->assertEquals('DIV', $this->parser->parseSimpleSelector(['DIV']));
		$this->assertEquals('DIV>>CLASS>>foo', $this->parser->parseSimpleSelector(['DIV.foo']));
		$this->assertEquals('DIV>>ID>>bar', $this->parser->parseSimpleSelector(['DIV#bar']));
		$this->assertNull($this->parser->parseSimpleSelector(['']));
	}

	public function testParseCascadedSelector()
	{
		$tags = ['DIV', 'P'];
		$expected = ['DIV', 'P'];
		$this->assertEquals($expected, $this->parser->parseCascadedSelector($tags));

		$tags = ['DIV.foo', '#bar'];
		$expected = ['DIV>>CLASS>>foo', 'ID>>bar'];
		$this->assertEquals($expected, $this->parser->parseCascadedSelector($tags));
	}

	/**
	 * A selector with a part that cannot be parsed gives no levels, wherever that part is, rather than the ones before it
	 *
	 * @dataProvider unparseableCascadedSelectors
	 *
	 * @param string[] $tags
	 */
	public function testParseCascadedSelectorGivesNothingForAPartItCannotParse($tags)
	{
		$this->assertSame([], $this->parser->parseCascadedSelector($tags));
	}

	/**
	 * Descendant selectors, split on whitespace as CssParser splits them, with a part mPDF cannot match
	 *
	 * @return array[]
	 */
	public function unparseableCascadedSelectors()
	{
		return [
			'first' => [['[HREF]', 'DIV', 'P']],
			'in the middle' => [['DIV', '*', 'P']],
			'last, after two parts' => [['DIV', 'P', '>', 'SPAN']],
			'last, after one part' => [['TABLE', 'TR:LAST-CHILD']],
			'a class with a pseudo-class' => [['DIV', 'P.C:HOVER']],
			'an id with a pseudo-class' => [['DIV', '#I:FIRST-CHILD']],
		];
	}

	/**
	 * An id written with classes gives one key whatever order they are written in: the tag, the id, then the classes
	 * in alphabetical order
	 *
	 * @dataProvider compoundSelectors
	 *
	 * @param string $selector
	 * @param string $expected
	 */
	public function testParseSimpleSelectorGivesAnIdWithClassesOneKey($selector, $expected)
	{
		$this->assertSame($expected, $this->parser->parseSimpleSelector([$selector]));
		$this->assertSame(['DIV', $expected], $this->parser->parseCascadedSelector(['DIV', $selector]));
	}

	/**
	 * Parts naming an id with classes, and the key each gives
	 *
	 * @return array[]
	 */
	public function compoundSelectors()
	{
		return [
			'tag, id, class' => ['P#I.C', 'P>>ID>>I>>CLASS>>C'],
			'tag, class, id' => ['P.C#I', 'P>>ID>>I>>CLASS>>C'],
			'id, class' => ['#I.C', 'ID>>I>>CLASS>>C'],
			'class, id' => ['.C#I', 'ID>>I>>CLASS>>C'],
			'two classes around the id' => ['P.B#I.A', 'P>>ID>>I>>CLASS>>A.B'],
			'tag, two classes, id' => ['P.A.B#I', 'P>>ID>>I>>CLASS>>A.B'],
			'the same class twice' => ['.C.C', 'CLASS>>C'],
			'the same id twice' => ['#I#I.C', 'ID>>I>>CLASS>>C'],
		];
	}

	/**
	 * A part with a class or id that mPDF cannot match gives no key, so the rule is dropped
	 *
	 * @dataProvider malformedCompoundSelectors
	 *
	 * @param string $selector
	 */
	public function testParseSimpleSelectorGivesNothingForAMalformedClassOrId($selector)
	{
		$this->assertNull($this->parser->parseSimpleSelector([$selector]));
	}

	/**
	 * Parts with a class or id followed by something that is not a class or id, or with two ids
	 *
	 * @return array[]
	 */
	public function malformedCompoundSelectors()
	{
		return [
			'a class with a pseudo-class' => ['.C:HOVER'],
			'a tag and class with a pseudo-class' => ['P.C:FIRST-CHILD'],
			'an id with an attribute' => ['#I[TITLE]'],
			'a tag and id with a pseudo-element' => ['P#I::BEFORE'],
			'a child combinator without spaces' => ['.A>.B'],
			'two ids' => ['#I#J'],
			'an empty class' => ['.A..B'],
			'a bare dot' => ['.'],
			'a bare hash' => ['#'],
		];
	}

	/**
	 * An nth-child part keeps its argument as the key's formula. A part with anything after its argument, or with an
	 * argument that is not a formula, is not parsed, so the rule is dropped rather than applied to every cell the formula
	 * at its start names.
	 *
	 * @dataProvider nthChildSelectors
	 *
	 * @param string $part
	 * @param string|null $expected
	 */
	public function testParseSimpleSelectorNthChild($part, $expected)
	{
		$this->assertSame($expected, $this->parser->parseSimpleSelector([$part]));
	}

	/**
	 * nth-child parts as CssParser hands them over, upper-cased with the spaces taken out of the argument, and their keys,
	 * or null for a part mPDF cannot match
	 *
	 * @return array[]
	 */
	public function nthChildSelectors()
	{
		return [
			'a number' => ['TD:NTH-CHILD(2)', 'TD>>SELECTORNTHCHILD>>2'],
			'a signed number' => ['TH:NTH-CHILD(+2)', 'TH>>SELECTORNTHCHILD>>+2'],
			'odd' => ['TR:NTH-CHILD(ODD)', 'TR>>SELECTORNTHCHILD>>ODD'],
			'even' => ['TR:NTH-CHILD(EVEN)', 'TR>>SELECTORNTHCHILD>>EVEN'],
			'an+b' => ['TD:NTH-CHILD(2N+1)', 'TD>>SELECTORNTHCHILD>>2N+1'],
			'-n+b' => ['TD:NTH-CHILD(-N+3)', 'TD>>SELECTORNTHCHILD>>-N+3'],
			'n' => ['TD:NTH-CHILD(N)', 'TD>>SELECTORNTHCHILD>>N'],
			'a pseudo-class after it' => ['TD:NTH-CHILD(2):NOT(.X)', null],
			'a second nth-child after it' => ['TD:NTH-CHILD(2):NTH-CHILD(ODD)', null],
			'an of selector' => ['TD:NTH-CHILD(2OF.X)', null],
			'an unfinished formula' => ['TD:NTH-CHILD(2N+)', null],
			'no argument' => ['TD:NTH-CHILD()', null],
		];
	}

	public function testNthchild_WithOdd()
	{
		$this->assertTrue($this->parser->matchesNthChild(['ODD'], 0)); // row 1
		$this->assertFalse($this->parser->matchesNthChild(['ODD'], 1)); // row 2
		$this->assertTrue($this->parser->matchesNthChild(['ODD'], 2)); // row 3
		$this->assertFalse($this->parser->matchesNthChild(['ODD'], 3)); // row 4
	}

	public function testNthchild_WithEven()
	{
		$this->assertFalse($this->parser->matchesNthChild(['EVEN'], 0)); // row 1
		$this->assertTrue($this->parser->matchesNthChild(['EVEN'], 1)); // row 2
		$this->assertFalse($this->parser->matchesNthChild(['EVEN'], 2)); // row 3
		$this->assertTrue($this->parser->matchesNthChild(['EVEN'], 3)); // row 4
	}

	public function testNthchild_WithSpecificNumber()
	{
		$this->assertFalse($this->parser->matchesNthChild(['', '3'], 0)); // row 1
		$this->assertFalse($this->parser->matchesNthChild(['', '3'], 1)); // row 2
		$this->assertTrue($this->parser->matchesNthChild(['', '3'], 2)); // row 3
		$this->assertFalse($this->parser->matchesNthChild(['', '3'], 3)); // row 4
	}

	public function testNthchild_With2nPlus1()
	{
		$formula = ['', '', '2', '+1'];
		$this->assertTrue($this->parser->matchesNthChild($formula, 0)); // row 1
		$this->assertFalse($this->parser->matchesNthChild($formula, 1)); // row 2
		$this->assertTrue($this->parser->matchesNthChild($formula, 2)); // row 3
		$this->assertFalse($this->parser->matchesNthChild($formula, 3)); // row 4
	}

	public function testNthchild_With3nPlus2()
	{
		$formula = ['', '', '3', '+2'];
		$this->assertFalse($this->parser->matchesNthChild($formula, 0)); // row 1
		$this->assertTrue($this->parser->matchesNthChild($formula, 1)); // row 2
		$this->assertFalse($this->parser->matchesNthChild($formula, 2)); // row 3
		$this->assertFalse($this->parser->matchesNthChild($formula, 3)); // row 4
		$this->assertTrue($this->parser->matchesNthChild($formula, 4)); // row 5
	}

	public function testNthchild_WithNegativeFormula()
	{
		$formula = ['', '', '-', '+3'];
		$this->assertTrue($this->parser->matchesNthChild($formula, 0)); // row 1
		$this->assertTrue($this->parser->matchesNthChild($formula, 1)); // row 2
		$this->assertTrue($this->parser->matchesNthChild($formula, 2)); // row 3
		$this->assertFalse($this->parser->matchesNthChild($formula, 3)); // row 4
	}
}
