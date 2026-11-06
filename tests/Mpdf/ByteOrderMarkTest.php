<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * U+FEFF is a character mPDF drops, so a document that holds one where no text is left around it has to come out as
 * the same document without it
 */
class ByteOrderMarkTest extends TestCase
{
	use PageStreams;

	const BOM = "\xEF\xBB\xBF";

	/**
	 * What follows a text node that comes to nothing is drawn as if the node were not there, in a Unicode font and a
	 * core one
	 *
	 * @dataProvider documents
	 *
	 * @param string $html The document, with %s where U+FEFF goes
	 * @param string $mark How U+FEFF is written
	 */
	public function testATextNodeThatComesToNothingChangesNothing($html, $mark = self::BOM)
	{
		foreach (['', 'c'] as $mode) {
			$this->assertSame($this->page(sprintf($html, ''), $mode), $this->page(sprintf($html, $mark), $mode));
		}
	}

	/**
	 * U+FEFF at either end or in the middle is valid UTF-8, and the calls that validate their text do not draw it
	 *
	 * @dataProvider textCalls
	 *
	 * @param string $mode
	 * @param callable $write
	 */
	public function testAMarkInTextIsNotDrawn($mode, $write)
	{
		$expected = $this->drawn($mode, $write, 'Text');

		$this->assertSame($expected, $this->drawn($mode, $write, self::BOM . 'Text'));
		$this->assertSame($expected, $this->drawn($mode, $write, 'Te' . self::BOM . 'xt'));
		$this->assertSame($expected, $this->drawn($mode, $write, 'Text' . self::BOM));
	}

	/**
	 * The page content a direct call to write text draws
	 *
	 * @param string $mode
	 * @param callable $write
	 * @param string $text
	 *
	 * @return string[]
	 */
	private function drawn($mode, $write, $text)
	{
		$mpdf = $this->mpdf(['mode' => $mode]);
		$mpdf->AddPage();
		$write($mpdf, $text);

		return $this->pageContents($this->output($mpdf));
	}

	/**
	 * The calls that write text directly and validate it, in a core font and a Unicode one
	 *
	 * @return array
	 */
	public function textCalls()
	{
		$calls = [
			'WriteCell' => function ($mpdf, $text) {
				$mpdf->WriteCell(40, 5, $text);
			},
			'WriteText' => function ($mpdf, $text) {
				$mpdf->WriteText(20, 20, $text);
			},
			'MultiCell' => function ($mpdf, $text) {
				$mpdf->MultiCell(40, 5, $text);
			},
		];

		$cases = [];
		foreach ($calls as $name => $call) {
			$cases[$name . ', core font'] = ['c', $call];
			$cases[$name . ', Unicode font'] = ['', $call];
		}

		return $cases;
	}

	/**
	 * Invalid UTF-8 throws, with or without a leading byte order mark
	 *
	 * @dataProvider invalidUtf8
	 *
	 * @param string $html
	 */
	public function testInvalidUtf8StillThrows($html)
	{
		$this->expectException(MpdfException::class);

		$this->mpdf()->WriteHTML($html);
	}

	/**
	 * Strings that are not UTF-8, with and without a leading byte order mark
	 *
	 * @return array
	 */
	public function invalidUtf8()
	{
		return [
			'a stray byte' => ["<p>\xFF</p>"],
			'an overlong form' => ["<p>\xC0\x80</p>"],
			'a surrogate' => ["<p>\xED\xA0\x80</p>"],
			'a stray byte after a mark' => [self::BOM . "<p>\xFF</p>"],
		];
	}

	/**
	 * Documents with a U+FEFF that leaves no text before something other than text
	 *
	 * @return array
	 */
	public function documents()
	{
		$afterStyle = '<style>p{margin:0}</style>%s';
		$image = '<img src="' . $this->pngImage() . '" />';

		return [
			'image' => [$afterStyle . $image],
			'barcode' => [$afterStyle . '<barcode code="123" />'],
			'input' => [$afterStyle . '<input type="text" value="hello" />'],
			'dottab' => [$afterStyle . '<dottab />after'],
			'rule' => [$afterStyle . '<hr />after'],
			'meter' => [$afterStyle . '<meter value="1" max="2"></meter>'],
			'text circle' => [$afterStyle . '<textcircle r="10mm" top-text="abc" />'],
			'line break' => [$afterStyle . '<br />after'],
			'written as a numeric reference' => [$afterStyle . $image, '&#xFEFF;'],
			'then trimmed whitespace' => ["<p>a</p>%s \n " . $image],
			'in a table cell' => ['<table><tr><td>%s' . $image . '</td></tr></table>'],
			'before a table' => [$afterStyle . '<table><tr><td>' . $image . '</td></tr></table>'],
			'in the paragraph before' => ['<p>%s</p><p>' . $image . '</p>'],
			'in a span' => ['<span>%s</span>' . $image],
			'at the start, before text' => ['%s<p>Text</p>'],
			'at the start, before plain text' => ['%sText'],
			'at the start, before an image' => ['%s' . $image],
			'at the start, before a stylesheet' => ['%s<style>p{color:#f00}</style><p>Text</p>'],
			'at the start, before a document' => ['%s<!DOCTYPE html><html><head><title>T</title></head><body><p>Text</p></body></html>'],
			'at the start, then a line break' => ['%s' . "\n" . '<p>Text</p>'],
		];
	}

	/**
	 * The first page's content stream
	 *
	 * @param string $html
	 * @param string $mode
	 *
	 * @return string
	 */
	private function page($html, $mode)
	{
		$config = $mode === 'c' ? ['mode' => 'c'] : ['mode' => '', 'default_font' => 'dejavusanscondensed'];
		$pages = $this->pageContents($this->render($html, $config));

		return $pages[0];
	}
}
