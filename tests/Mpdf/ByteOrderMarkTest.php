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
	 * What follows a text node that comes to nothing is drawn as if the node were not there
	 *
	 * @dataProvider documents
	 *
	 * @param string $html The document, with %s where U+FEFF goes
	 * @param string $mark How U+FEFF is written
	 */
	public function testATextNodeThatComesToNothingChangesNothing($html, $mark = self::BOM)
	{
		$this->assertSame($this->page(sprintf($html, '')), $this->page(sprintf($html, $mark)));
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
		];
	}

	/**
	 * The first page's content stream
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function page($html)
	{
		$pages = $this->pageContents($this->render($html, ['mode' => '', 'default_font' => 'dejavusanscondensed']));

		return $pages[0];
	}
}
