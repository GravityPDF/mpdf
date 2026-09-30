<?php

namespace Mpdf\Css;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Splitting a stylesheet into rules and a block into declarations, with the braces, semicolons and comment markers
 * inside strings, url() and escapes left as text
 */
class StylesheetTokenizerTest extends TestCase
{

	/**
	 * @var StylesheetTokenizer
	 */
	private $tokenizer;

	/**
	 * A tokenizer, which keeps no state between calls
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tokenizer = new StylesheetTokenizer();
	}

	/**
	 * Release the tokenizer
	 */
	protected function tear_down()
	{
		unset($this->tokenizer);

		parent::tear_down();
	}

	/**
	 * Each comment, and each HTML comment marker, becomes a space, and those inside strings and url() stay
	 *
	 * @dataProvider comments
	 *
	 * @param string $css
	 * @param string $expected
	 */
	public function testRemoveComments($css, $expected)
	{
		$this->assertSame($expected, $this->tokenizer->removeComments($css));
	}

	/**
	 * Stylesheets with comments, or with text that looks like one, and what is left of each
	 *
	 * @return array[]
	 */
	public function comments()
	{
		return [
			'no comment' => ['p { color: red }', 'p { color: red }'],
			'a comment' => ['p { /* a */ color: red }', 'p {   color: red }'],
			'two comments' => ['/* a */p/* b */{}', ' p {}'],
			'a comment across lines' => ["/* a\n b */p {}", ' p {}'],
			'a comment with quotes and braces in it' => ['/* it\'s "} { */ p {}', '  p {}'],
			'a comment left open' => ['p {} /* a { b', 'p {}  '],
			'a comment with only an asterisk after the opener' => ['/*/ p {} */q {}', ' q {}'],
			'a comment with asterisks in it' => ['/** a **/p {}', ' p {}'],

			'a comment opener in a double-quoted string' => ['q { quotes: "/*" "x" } p {} q { quotes: "*/" }', 'q { quotes: "/*" "x" } p {} q { quotes: "*/" }'],
			'a comment in a single-quoted string' => ["q { quotes: '/* x */' }", "q { quotes: '/* x */' }"],
			'a comment opener after an escaped quote' => ['q { quotes: "\\"/*" } p {}', 'q { quotes: "\\"/*" } p {}'],
			'a comment in an unquoted url()' => ['p { background: url(http://a/*b*/c.png) }', 'p { background: url(http://a/*b*/c.png) }'],
			'a comment after url() in upper case' => ['p { background: URL(a.png) /* b */ }', 'p { background: URL(a.png)   }'],
			'a comment in a function whose name ends in url' => ['p { x: myurl(a /* b */) }', 'p { x: myurl(a  ) }'],
			'an escaped comment opener' => ['.a\\/* { color: red } */', '.a\\/* { color: red } */'],
			'a string left open at a line break' => ["q { quotes: \"a /* b\n/* c */ }", "q { quotes: \"a /* b\n  }"],

			'HTML comment markers' => ['<!-- p {} -->', '  p {}  '],
			'HTML comment markers in a string' => ['q { quotes: "<!--" "-->" }', 'q { quotes: "<!--" "-->" }'],
		];
	}

	/**
	 * A stylesheet splits into its rules at the braces and semicolons outside strings, url() and escapes
	 *
	 * @dataProvider stylesheets
	 *
	 * @param string $css
	 * @param array[] $expected
	 */
	public function testRules($css, array $expected)
	{
		$this->assertSame($expected, $this->tokenizer->rules($css));
	}

	/**
	 * Stylesheets, and the rules in each
	 *
	 * @return array[]
	 */
	public function stylesheets()
	{
		return [
			'nothing' => ['', []],
			'whitespace' => [" \n\t", []],
			'one rule' => ['p { color: red }', [[null, 'p ', ' color: red ']]],
			'two rules' => ['p{color:red}h1{color:blue}', [[null, 'p', 'color:red'], [null, 'h1', 'color:blue']]],
			'an empty block' => ['p {}', [[null, 'p ', '']]],
			'a selector list' => ['h1, h2 { color: red }', [[null, 'h1, h2 ', ' color: red ']]],

			'braces in a string' => ['q { quotes: "}" "{"; } p { color: red }', [[null, 'q ', ' quotes: "}" "{"; '], [null, ' p ', ' color: red ']]],
			'an unbalanced brace in a string' => ["q { quotes: '}'; } p { color: red }", [[null, 'q ', " quotes: '}'; "], [null, ' p ', ' color: red ']]],
			'an escaped quote in a string' => ['q { quotes: "\\"}" "x" } p {}', [[null, 'q ', ' quotes: "\\"}" "x" '], [null, ' p ', '']]],
			'an escaped backslash before the closing quote' => ['q { quotes: "\\\\" "}" } p {}', [[null, 'q ', ' quotes: "\\\\" "}" '], [null, ' p ', '']]],
			'an escaped line break in a string' => ["q { quotes: \"a\\\n}\" } p {}", [[null, 'q ', " quotes: \"a\\\n}\" "], [null, ' p ', '']]],
			'a string left open at a line break' => ["q { quotes: \"a}\n} p { color: red }", [[null, 'q ', " quotes: \"a}\n"], [null, ' p ', ' color: red ']]],
			'a string left open at the end' => ['q { quotes: "a}', [[null, 'q ', ' quotes: "a}']]],
			'a brace in an attribute selector' => ['a[title="{"] { color: red } p {}', [[null, 'a[title="{"] ', ' color: red '], [null, ' p ', '']]],
			'an escaped brace in a selector' => ['.a\\{b { color: red } p {}', [[null, '.a\\{b ', ' color: red '], [null, ' p ', '']]],
			'an escaped colon in a selector' => ['.sm\\:hidden { display: none }', [[null, '.sm\\:hidden ', ' display: none ']]],

			'braces and a semicolon in an unquoted url()' => ['q { background: url(a};{b.png) } p {}', [[null, 'q ', ' background: url(a};{b.png) '], [null, ' p ', '']]],
			'an escaped parenthesis in an unquoted url()' => ['q { background: url(a\\)}.png) } p {}', [[null, 'q ', ' background: url(a\\)}.png) '], [null, ' p ', '']]],
			'a brace in a quoted url()' => ['q { background: url( "a}.png" ) } p {}', [[null, 'q ', ' background: url( "a}.png" ) '], [null, ' p ', '']]],
			'an unquoted url() in upper case' => ['q { background: URL(a}.png) } p {}', [[null, 'q ', ' background: URL(a}.png) '], [null, ' p ', '']]],
			'a function whose name ends in url' => ['q { x: myurl(a}b) } p {}', [[null, 'q ', ' x: myurl(a'], [null, 'b) } p ', '']]],
			'an unquoted url() left open' => ['q { background: url(a.png } p {}', [[null, 'q ', ' background: url(a.png } p {}']]],

			'a block left open' => ['p { color: red } q { color: blue', [[null, 'p ', ' color: red '], [null, ' q ', ' color: blue']]],
			'a selector with no block' => ['p { color: red } q', [[null, 'p ', ' color: red ']]],
			'a semicolon before a selector' => ['p {} ; q { color: red }', [[null, 'p ', ''], [null, ' ; q ', ' color: red ']]],
			'a brace closing no block' => ['p {} } q { color: red }', [[null, 'p ', ''], [null, ' } q ', ' color: red ']]],

			'a statement at-rule' => ['@charset "UTF-8"; p {}', [['charset', '"UTF-8"', null], [null, ' p ', '']]],
			'a semicolon in a string in a statement' => ['@import "a;b.css"; p {}', [['import', '"a;b.css"', null], [null, ' p ', '']]],
			'a statement at-rule left open' => ['p {} @import url(a.css)', [[null, 'p ', ''], ['import', 'url(a.css)', null]]],
			'a block at-rule' => ['@media print { p { color: red } }', [['media', 'print', ' p { color: red } ']]],
			'an at-rule name in upper case' => ['@MEDIA print {}', [['media', 'print', '']]],
			'an at-rule with a vendor prefix' => ['@-webkit-keyframes x { from {} }', [['-webkit-keyframes', 'x', ' from {} ']]],
			'an at-rule without a space before its prelude' => ['@page:first { margin: 0 }', [['page', ':first', ' margin: 0 ']]],
			'nested at-rules' => [
				'@supports (display: grid) { @media print { p { color: red } } } h1 {}',
				[['supports', '(display: grid)', ' @media print { p { color: red } } '], [null, ' h1 ', '']],
			],
			'a brace in a string in an at-rule prelude' => ['@supports (content: "}") { p {} } h1 {}', [['supports', '(content: "}")', ' p {} '], [null, ' h1 ', '']]],
			'an at sign in a declaration' => ['p { background: url(a@2x.png) } h1 {}', [[null, 'p ', ' background: url(a@2x.png) '], [null, ' h1 ', '']]],
			'an at-rule after a semicolon in a selector' => ['; @media print { p {} }', [[null, '; @media print ', ' p {} ']]],

			'a byte order mark before an at-rule' => ["\xEF\xBB\xBF@charset \"UTF-8\"; p {}", [['charset', '"UTF-8"', null], [null, ' p ', '']]],
			'a byte order mark after whitespace, before a rule' => [" \xEF\xBB\xBFp {}", [[null, 'p ', '']]],
		];
	}

	/**
	 * A block splits into its declarations at the semicolons outside strings, url(), escapes and nested blocks
	 *
	 * @dataProvider blocks
	 *
	 * @param string $block
	 * @param array[] $expected
	 */
	public function testDeclarations($block, array $expected)
	{
		$this->assertSame($expected, $this->tokenizer->declarations($block));
	}

	/**
	 * Blocks, and the declarations in each
	 *
	 * @return array[]
	 */
	public function blocks()
	{
		return [
			'nothing' => ['', []],
			'one declaration' => [' color: red ', [[' color', ' red ']]],
			'two declarations' => ['color:red;margin:0', [['color', 'red'], ['margin', '0']]],
			'a trailing semicolon' => ['color: red;', [['color', ' red']]],
			'empty declarations' => [' ; ;color: red;; ', [['color', ' red']]],
			'a colon in the value' => ['background: url(http://a/b.png)', [['background', ' url(http://a/b.png)']]],
			'a part with no colon' => ['color red; margin: 0', [[' margin', ' 0']]],
			'a declaration with no value' => ['color:; margin: 0', [['color', ''], [' margin', ' 0']]],

			'a semicolon in a double-quoted string' => ['font-family: "a;b"; color: red', [['font-family', ' "a;b"'], [' color', ' red']]],
			'a semicolon in a single-quoted string' => ["font-family: 'a;color:red'; margin: 0", [['font-family', " 'a;color:red'"], [' margin', ' 0']]],
			'a semicolon after an escaped quote' => ['quotes: "\\";" "x"; color: red', [['quotes', ' "\\";" "x"'], [' color', ' red']]],
			'a semicolon in an unquoted url()' => ['background: url(data:image/png;base64,AA==); color: red', [['background', ' url(data:image/png;base64,AA==)'], [' color', ' red']]],
			'a semicolon in a quoted url()' => ['background: url("a;b.png"); color: red', [['background', ' url("a;b.png")'], [' color', ' red']]],
			'an escaped semicolon' => ['font-family: a\\;b; color: red', [['font-family', ' a\\;b'], [' color', ' red']]],
			'a string left open at a line break' => ["quotes: \"a;\nb; color: red", [['quotes', " \"a;\nb"], [' color', ' red']]],
			'a brace closing no block' => ['color: red }; margin: 0', [['color', ' red }'], [' margin', ' 0']]],

			'an at-rule with a block' => [
				'margin: 10mm; @top-center { content: "x"; } margin-bottom: 20mm; @bottom-left { content: "}" }',
				[['margin', ' 10mm'], [' margin-bottom', ' 20mm']],
			],
			'a statement at-rule' => ['@import "a.css"; color: red', [[' color', ' red']]],
			'an at-rule with a colon in its prelude' => ['@media (min-width: 1px) { color: blue } color: red', [[' color', ' red']]],
			'a nested rule' => ['color: red; &:hover { color: blue; } margin: 0', [['color', ' red'], [' margin', ' 0']]],
			'a declaration with a block in its value' => ['color: red { x: y } margin: 0', [[' margin', ' 0']]],
			'nested blocks' => ['@a { @b { color: blue } color: blue } color: red', [[' color', ' red']]],
			'a nested block left open' => ['color: red; @a { color: blue', [['color', ' red']]],
		];
	}
}
