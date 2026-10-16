<?php

namespace Mpdf;

/**
 * A <title> or <meta> written inside a comment, script, stylesheet or textarea does not set the PDF's title or author
 */
class MetaTagsInRawTextTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Documents with a <title> or <meta> hidden in content that holds no elements, and the title and author they should
	 * give the PDF
	 *
	 * @return array[]
	 */
	public static function documents()
	{
		return [
			'a title in a script' => ['<script>var t = "<title>Hidden</title>";</script><title>Real</title><p>Text</p>', 'Real', null],
			'an author in a script' => ['<script>var m = \'<meta name="author" content="Hidden">\';</script><p>Text</p>', null, null],
			'a title in a stylesheet' => ['<style>/* <title>Hidden</title> */ p { color: red; }</style><title>Real</title><p>Text</p>', 'Real', null],
			'a title in a textarea' => ['<textarea><title>Hidden</title></textarea><title>Real</title><p>Text</p>', 'Real', null],
			'a title in a comment with a ">" before it' => ['<!-- a > b <title>Hidden</title> --><title>Real</title><p>Text</p>', 'Real', null],
			'a script holding "<!--" before the document title' => ['<script>var s = "<!--";</script><title>Real</title><meta name="author" content="Jane"><p>Text</p><!-- x -->', 'Real', 'Jane'],
			'a stylesheet holding "<!--" before the document title' => ['<style>p::after { content: "<!--"; }</style><title>Real</title><p>Text</p><!-- x -->', 'Real', null],
			'the document title and author alone' => ['<html><head><title>Real</title><meta name="author" content="Jane"></head><body><p>Text</p></body></html>', 'Real', 'Jane'],
		];
	}

	/**
	 * The PDF's title and author are the document's own, or none
	 *
	 * @dataProvider documents
	 *
	 * @param string $html
	 * @param string|null $title
	 * @param string|null $author
	 */
	public function testTheTitleAndAuthorAreTheDocumentsOwn($html, $title, $author)
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		$this->assertSame($title, $mpdf->title);
		$this->assertSame($author, $mpdf->author);
	}

}
