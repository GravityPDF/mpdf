<?php

namespace Mpdf\Css;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Removing HTML comments from a document before it is read
 */
class CommentParserTest extends TestCase
{

	/**
	 * Each comment goes with nothing left in its place, the mPDF markers go and leave what is between them, and the
	 * content of a script is left as it is
	 *
	 * @dataProvider documents
	 *
	 * @param string $html
	 * @param string $expected
	 */
	public function testRemoveHtmlComments($html, $expected)
	{
		$this->assertSame($expected, (new CommentParser())->removeHtmlComments($html));
	}

	/**
	 * Documents with comments, and what is left of each
	 *
	 * @return array[] Each [html, expected]
	 */
	public function documents()
	{
		return [
			'between two words' => ['a<!-- x -->b', 'ab'],
			'an element holding only a comment is left empty' => ['<p><!-- x --></p>', '<p></p>'],
			'over several lines, keeping the line breaks around it' => ["a\n<!--\nx\n-->\nb", "a\n\nb"],
			'mPDF content' => ['a<!--mpdf <b>b</b> mpdf-->c', 'a <b>b</b> c'],
			'mPDF markers in capitals' => ['a<!--MPDF b MPDF-->c', 'a b c'],
			'a conditional comment' => ['a<!--[if mso]><p>x</p><![endif]-->b', 'ab'],
			'a conditional comment that shows its content' => ['a<!--[if !mso]><!-->b<!--<![endif]-->c', 'abc'],
			'a comment marker in a script' => ['<script>s = "<!--";</script>a<!-- x -->b', '<script>s = "<!--";</script>ab'],
			'a script tag in a comment' => ['a<!-- <script> -->b<script>x</script>', 'ab<script>x</script>'],
			'no comment' => ['<p>a b</p>', '<p>a b</p>'],
		];
	}

}
