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
		];
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

}
