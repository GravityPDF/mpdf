<?php

namespace Mpdf\Html;

/**
 * Tags reads a value quoted after "=" whole, so a "<" or ">" inside it stays in the tag
 */
class TagsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * HTML and the pieces it splits into, text and tag content alternating
	 *
	 * @return array
	 */
	public static function splitProvider()
	{
		return [
			'plain tags' => ['a<b>c</b>d', ['a', 'b', 'c', '/b', 'd']],
			'a ">" in double quotes' => ['<p title="1 > 0">x</p>', ['', 'p title="1 > 0"', 'x', '/p', '']],
			'a "<" and ">" in single quotes' => ["<p title='a<b>c'>x", ['', "p title='a<b>c'", 'x']],
			'a space before the quote' => ['<p title= "a>b">x', ['', 'p title= "a>b"', 'x']],
			'a quote not after "=" is ordinary' => ["<p title=don't>x</p>", ['', "p title=don't", 'x', '/p', '']],
			'an unclosed quote is ordinary' => ['<p title="a>x</p>', ['', 'p title="a', 'x', '/p', '']],
		];
	}

	/**
	 * HTML is split at its tags, a quoted value kept whole inside its tag
	 *
	 * @dataProvider splitProvider
	 *
	 * @param string $html
	 * @param string[] $expected
	 */
	public function testSplit($html, array $expected)
	{
		$this->assertSame($expected, Tags::split($html));
	}

	/**
	 * A tag not enabled is dropped whole, however its values are quoted, and an enabled one is left as it was written,
	 * whatever its values hold
	 */
	public function testRemoveUnsupported()
	{
		$html = '<p title="a>b">before <custom data-x="a>b" data-y=\'<p>\'>inside</custom> <B>after</B><br/></p>';

		$this->assertSame(
			'<p title="a>b">before inside <B>after</B><br/></p>',
			Tags::removeUnsupported($html, '<p><b><br>')
		);
	}

	/**
	 * A tag whose name only starts with an enabled one is not kept
	 */
	public function testRemoveUnsupportedMatchesWholeNames()
	{
		$this->assertSame('xy', Tags::removeUnsupported('<pre>x</pre><p>y', '<pr>'));
	}


	/**
	 * The content of a start tag, and the name and attributes it reads as
	 *
	 * @return array
	 */
	public static function attributesProvider()
	{
		return [
			'no attributes' => ['p', ['P', []]],
			'each value form' => ['td a="1" b=\'2\' c=3 d', ['TD', ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '']]],
			'spaces around "="' => ["p a = \"1\"\nb =2", ['P', ['A' => '1', 'B' => '2']]],
			'a "name=value" inside a value' => ['bookmark content="Set x=1 first" /', ['BOOKMARK', ['CONTENT' => 'Set x=1 first']]],
			'an "href=" inside a value' => ['a title="see href=x now" href="y"', ['A', ['TITLE' => 'see href=x now', 'HREF' => 'y']]],
			'a ">" and quotes inside a value' => ['p title="a > \'b\'" c=\'"d"\'', ['P', ['TITLE' => "a > 'b'", 'C' => '"d"']]],
			'an apostrophe in an unquoted value' => ["p title=don't", ['P', ['TITLE' => "don't"]]],
			'a stray quote' => ['b class=x"', ['B', ['CLASS' => 'x']]],
			'an empty value' => ['p title="" class=x', ['P', ['TITLE' => '', 'CLASS' => 'x']]],
			'the later of two' => ['p a="1" a="2"', ['P', ['A' => '2']]],
			'the later of two in another case' => ['Meta Name="a" NAME="b"', ['META', ['NAME' => 'b']]],
			'a name ending in another' => ['body data-style="a" style="b"', ['BODY', ['DATA-STYLE' => 'a', 'STYLE' => 'b']]],
			'a trailing "/"' => ['br/', ['BR', []]],
		];
	}

	/**
	 * A start tag reads as its name and its attributes, each value as it was written between its quotes
	 *
	 * @dataProvider attributesProvider
	 *
	 * @param string $content
	 * @param array $expected
	 */
	public function testAttributes($content, array $expected)
	{
		$this->assertSame($expected, Tags::attributes($content));
	}

	/**
	 * HTML, the offset of a <g>'s start tag in it, and the element found there
	 *
	 * @return array
	 */
	public static function elementAtProvider()
	{
		return [
			'no nesting' => ['x<g a="1">y</g>z', 1, ['<g a="1">y</g>', 'g a="1"', 'y', '</g>']],
			'one nested' => ['<g id="a"><g>b</g>c</g>d', 0, ['<g id="a"><g>b</g>c</g>', 'g id="a"', '<g>b</g>c', '</g>']],
			'two levels' => ['<g><g><g>a</g></g>b</g>', 0, ['<g><g><g>a</g></g>b</g>', 'g', '<g><g>a</g></g>b', '</g>']],
			'an inner one' => ['<g><g>a</g>b</g>', 3, ['<g>a</g>', 'g', 'a', '</g>']],
			'one inside that closes itself' => ['<g><g/>a</g>b</g>', 0, ['<g><g/>a</g>', 'g', '<g/>a', '</g>']],
			'one that closes itself' => ['<g id="r"/>a</g>', 0, ['<g id="r"/>', 'g id="r"/', '', '']],
			'tags in a value' => ['<g title="<g>"><g>a</g>b</G>c</g>', 0, ['<g title="<g>"><g>a</g>b</G>', 'g title="<g>"', '<g>a</g>b', '</G>']],
			'a name that only starts the same' => ['<g><gx>a</gx>b</g>', 0, ['<g><gx>a</gx>b</g>', 'g', '<gx>a</gx>b', '</g>']],
			'never closed' => ['<g><g>a</g>', 0, null],
			'another tag at the offset' => ['<p><g>a</g>', 0, null],
		];
	}

	/**
	 * The element at the offset ends at the end tag that closes it, an element of the same name nested inside it
	 * kept whole
	 *
	 * @dataProvider elementAtProvider
	 *
	 * @param string $html
	 * @param int $offset
	 * @param string[]|null $expected
	 */
	public function testElementAt($html, $offset, $expected)
	{
		$this->assertSame($expected, Tags::elementAt($html, 'g', $offset));
	}

	/**
	 * HTML and how it reads once the tags named br, input and bookmark close themselves
	 *
	 * @return array
	 */
	public static function selfClosingProvider()
	{
		return [
			'not closed' => ['<br>', '<br />'],
			'closed without a space' => ['<br/>', '<br />'],
			'already closed' => ['<br />', '<br />'],
			'a ">" in a value' => ['<input value="a > b">', '<input value="a > b" />'],
			'a ">" in a value, already closed' => ['<bookmark content="a > b" />', '<bookmark content="a > b" />'],
			'a "/" in a value' => ['<input value="a/b">', '<input value="a/b" />'],
			'another tag' => ['<p title="a > b">', '<p title="a > b">'],
			'one inside another tag\'s value' => ['<p title="<br> <br/>"><br>', '<p title="<br> <br/>"><br />'],
			'a name that only starts with one of them' => ['<brand>', '<brand>'],
		];
	}

	/**
	 * HTML, and what is left of it once each "<b>" outside a tag is removed
	 *
	 * @return array[]
	 */
	public static function replaceOutsideTagsProvider()
	{
		return [
			'between tags' => ['<p>a<b>b</p>', '<p>ab</p>'],
			'inside a value' => ['<p title="a<b>b">c</p><b>', '<p title="a<b>b">c</p>'],
			'inside a value quoted with apostrophes' => ['<p title=\'<b>\'><b>c</p>', '<p title=\'<b>\'>c</p>'],
			'inside a comment, which is not a tag' => ['<!-- <b> -->', '<!--  -->'],
			'a "<" that starts no tag' => ['a < b<b>', 'a < b'],
		];
	}

	/**
	 * A pattern is replaced between tags, and not inside another tag's attribute value
	 *
	 * @dataProvider replaceOutsideTagsProvider
	 *
	 * @param string $html
	 * @param string $expected
	 */
	public function testReplaceOutsideTags($html, $expected)
	{
		$this->assertSame($expected, Tags::replaceOutsideTags($html, '<b>', ''));
	}

	/**
	 * Each tag named is written as one that closes itself, and a value quoted in it is left whole
	 *
	 * @dataProvider selfClosingProvider
	 *
	 * @param string $html
	 * @param string $expected
	 */
	public function testCloseSelfClosing($html, $expected)
	{
		$this->assertSame($expected, Tags::closeSelfClosing($html, 'br|input|bookmark'));
	}

	/**
	 * HTML and how it reads once each outermost <svg> is written as "[start tag content|inner|end tag]"
	 *
	 * @return array
	 */
	public static function replaceElementsProvider()
	{
		return [
			'none' => ['<p>x</p>', '<p>x</p>'],
			'one' => ['a<svg w="1">b</svg>c', 'a[svg w="1"|b|</svg>]c'],
			'two in a row' => ['<svg>a</svg><svg>b</svg>', '[svg|a|</svg>][svg|b|</svg>]'],
			'one nested' => ['<svg><svg>a</svg>b</svg>c', '[svg|<svg>a</svg>b|</svg>]c'],
			'two deep' => ['<svg><svg><svg>a</svg></svg>b</svg>', '[svg|<svg><svg>a</svg></svg>b|</svg>]'],
			'in another case' => ['<SVG>a</Svg >', '[SVG|a|</Svg >]'],
			'a start tag never closed' => ['<svg>a<svg>b</svg>', '<svg>a[svg|b|</svg>]'],
			'a stray end tag' => ['a</svg><svg>b</svg>', 'a</svg>[svg|b|</svg>]'],
			'one in a value' => ['<svg><a title="<svg>">a</a></svg>', '[svg|<a title="<svg>">a</a>|</svg>]'],
			'one that closes itself, then one' => ['a<svg/>b<svg>c</svg>d', 'a<svg/>b[svg|c|</svg>]d'],
			'one that closes itself, nested' => ['<svg>a<svg/>b</svg>c', '[svg|a<svg/>b|</svg>]c'],
			'one that closes itself before a nested one' => ['<svg><svg /><svg>a</svg>b</svg>c', '[svg|<svg /><svg>a</svg>b|</svg>]c'],
			'a name starting with svg' => ['<svg><svg-icon></svg-icon></svg>', '[svg|<svg-icon></svg-icon>|</svg>]'],
		];
	}

	/**
	 * Each outermost element is replaced, an end tag closing the nearest start tag of its name still open
	 *
	 * @dataProvider replaceElementsProvider
	 *
	 * @param string $html
	 * @param string $expected
	 */
	public function testReplaceElements($html, $expected)
	{
		$replaced = Tags::replaceElements($html, 'svg', function ($element) {
			$this->assertSame('<' . $element[1] . '>' . $element[2] . $element[3], $element[0]);

			return '[' . $element[1] . '|' . $element[2] . '|' . $element[3] . ']';
		});

		$this->assertSame($expected, $replaced);
	}

}
