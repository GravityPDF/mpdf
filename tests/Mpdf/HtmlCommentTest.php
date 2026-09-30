<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * An HTML comment is removed without leaving anything in its place, as a browser does, in both parser modes and both
 * CSS modes: a document with a comment draws what it draws without it
 */
class HtmlCommentTest extends TestCase
{

	/**
	 * The text drawn, and where each line of it is drawn, are those of the same document written as a browser reads it
	 *
	 * @dataProvider comments
	 *
	 * @param string $html
	 * @param string $asRead The same document as a browser reads it, with no comment in it
	 * @param int $mode The parser mode, an HTMLParserMode constant
	 * @param string $cssMode
	 */
	public function testACommentLeavesNothingBehind($html, $asRead, $mode, $cssMode)
	{
		$drawn = $this->draw($html, $mode, $cssMode);

		$this->assertNotEmpty($drawn[0]);
		$this->assertSame($this->draw($asRead, $mode, $cssMode), $drawn);
	}

	/**
	 * Each document with a comment, and the same document as a browser reads it, in each parser mode and CSS mode
	 *
	 * @return array[] Each [html, as read, parser mode, CSS mode]
	 */
	public function comments()
	{
		$documents = [
			'between two words' => ['<p>a<!-- x -->b</p>', '<p>ab</p>'],
			'at the start of a text run' => ['<p><b>a</b><!-- x -->b</p>', '<p><b>a</b>b</p>'],
			'at the end of a text run' => ['<p>a<!-- x --><b>b</b></p>', '<p>a<b>b</b></p>'],
			'between inline elements' => ['<p><b>a</b><!-- x --><i>b</i></p>', '<p><b>a</b><i>b</i></p>'],
			'over several lines' => ["<p>a<!--\nx\n-->b</p>", '<p>ab</p>'],
			'with line breaks around it' => ["<p>a\n<!-- x -->\nb</p>", "<p>a\nb</p>"],
			'inside pre, between two words' => ["<pre>a<!-- x -->b\nc</pre>", "<pre>ab\nc</pre>"],
			'inside pre, keeping the line breaks around it' => ["<pre>a\n<!-- x -->\nb</pre>", "<pre>a\n\nb</pre>"],
			'inside a table cell' => ['<table><tr><td>a<!-- x -->b</td></tr></table>', '<table><tr><td>ab</td></tr></table>'],
			'mPDF content, whose markers go and whose content stays' => ['<p>a<!--mpdf <b>b</b> mpdf-->c</p>', '<p>a <b>b</b> c</p>'],
			'a comment inside mPDF content' => ['<p>a<!--mpdf b<!-- x -->c mpdf-->d</p>', '<p>a bc d</p>'],
			'a conditional comment' => ['<p>a<!--[if mso]>x<![endif]-->b</p>', '<p>ab</p>'],
			'a conditional comment that shows its content' => ['<p>a<!--[if !mso]><!-->b<!--<![endif]-->c</p>', '<p>abc</p>'],
			'a comment marker in a script' => ['<script>var s = "<!--";</script><p>a<!-- x -->b</p>', '<p>ab</p>'],
			'a script tag in a comment' => ['<p>a<!-- <script> -->b</p><script>x</script><p>c</p>', '<p>ab</p><p>c</p>'],
		];

		$cases = [];
		foreach ($documents as $name => $document) {
			foreach ($this->modes() as $modeName => $mode) {
				$cases[$name . ', ' . $modeName] = array_merge($document, $mode);
			}
		}

		return $cases;
	}

	/**
	 * Each parser mode that reads a document, with each CSS mode
	 *
	 * @return array[] Each [parser mode, CSS mode]
	 */
	private function modes()
	{
		return [
			'default mode, standard' => [HTMLParserMode::DEFAULT_MODE, CssMode::STANDARD],
			'default mode, legacy' => [HTMLParserMode::DEFAULT_MODE, CssMode::LEGACY],
			'body mode, standard' => [HTMLParserMode::HTML_BODY, CssMode::STANDARD],
			'body mode, legacy' => [HTMLParserMode::HTML_BODY, CssMode::LEGACY],
		];
	}

	/**
	 * @param string $html
	 * @param int $mode The parser mode, an HTMLParserMode constant
	 * @param string $cssMode
	 *
	 * @return array[] The text of each line drawn, and the page, left and right edges and top of each
	 */
	private function draw($html, $mode, $cssMode)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => $cssMode]);
		$mpdf->WriteHTML($html, $mode);

		return [$mpdf->drawnText, $mpdf->drawnBoxes];
	}

}
