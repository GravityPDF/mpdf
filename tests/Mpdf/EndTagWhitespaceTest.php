<?php

namespace Mpdf;

/**
 * An end tag with whitespace or attributes before its ">", such as "</style >", ends its element as "</style>" does,
 * as it does in a browser
 */
class EndTagWhitespaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * HTML holding an end tag written "</name{end}>", with the configuration to draw it in
	 *
	 * @return array[]
	 */
	public static function documents()
	{
		return [
			'style' => ['<style>.a { color: red; }</style{end}><p class="a">Middle</p><style>.b { color: blue; }</style><p class="b">Last</p>', []],
			'title' => ['<html><head><title>The title</title{end}></head><body><p>Body</p><title>Later</title></body></html>', []],
			'pre' => ["<pre>line 1\nline 2\n    indented</pre{end}><p>After</p>", []],
			'textarea' => ["<form><textarea rows=\"4\">line 1\nline 2\nline 3</textarea{end}></form>", []],
			'empty textarea' => ['<form><textarea name="t"></textarea{end}><p>After</p></form>', []],
			'page header' => ['<htmlpageheader name="h">Header</htmlpageheader{end}><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>Body</p>', []],
			'page footer' => ['<htmlpagefooter name="f">Footer</htmlpagefooter{end}><sethtmlpagefooter name="f" value="on" /><p>Body</p>', []],
			'caption' => ['<table border="1"><caption>The caption</caption{end}><tr><td>Cell</td></tr></table>', []],
			'heading kept with its table' => [str_repeat('<p>Filler</p>', 26) . '<h2>Heading</h2{end}><table>' . str_repeat('<tr><td>Row</td></tr>', 30) . '</table>', ['use_kwt' => true]],
			'nested list' => ['<ol type="A"><li>One<ol type="A"><li>Inner</li></ol{end}></li>' . str_repeat('<li>Item</li>', 20) . '</ol>', ['list_auto_mode' => 'mpdf']],
			'charset' => ["<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=windows-1252\"></head{end}><body><p>Caf\xe9</p></body></html>", ['allow_charset_conversion' => true]],
		];
	}

	/**
	 * Inline SVG holding an end tag written "</name{end}>". SVG is read as XML, where only whitespace may come before
	 * the ">"
	 *
	 * @return string[]
	 */
	public static function svgDocuments()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="80" height="40">';

		return [
			'inline svg' => $svg . '<rect width="40" height="40" fill="red"/><text x="5" y="20">SVG</text></svg{end}><p>Middle</p>'
				. $svg . '<rect width="40" height="40" fill="blue"/></svg><p>Last</p>',
			'svg gradient' => $svg . '<defs><linearGradient id="a"><stop offset="0" stop-color="red"/><stop offset="1" stop-color="blue"/></linearGradient{end}>'
				. '<linearGradient id="b" xlink:href="#a"/></defs><rect width="80" height="40" fill="url(#b)"/></svg>',
		];
	}

	/**
	 * Whitespace before the ">" of an end tag, which a browser and XML both ignore
	 *
	 * @return string[]
	 */
	public static function whitespaceEndings()
	{
		return ['a space' => ' ', 'a newline' => "\n"];
	}

	/**
	 * Each HTML document with whitespace or an attribute before its end tag's ">", and each SVG with whitespace
	 *
	 * @return array[]
	 */
	public static function documentsAndEndings()
	{
		$cases = [];
		foreach (self::documents() as $element => $document) {
			foreach (self::whitespaceEndings() + ['an attribute' => ' class="x"'] as $ending => $end) {
				$cases[$element . ', ' . $ending] = [$document[0], $document[1], $end];
			}
		}

		foreach (self::svgDocuments() as $element => $html) {
			foreach (self::whitespaceEndings() as $ending => $end) {
				$cases[$element . ', ' . $ending] = [$html, [], $end];
			}
		}

		return $cases;
	}

	/**
	 * The document comes out exactly as it does with the end tag written plainly
	 *
	 * @dataProvider documentsAndEndings
	 *
	 * @param string $html
	 * @param array $config
	 * @param string $end
	 */
	public function testAnEndTagWithSomethingBeforeItsBracketEndsTheElement($html, array $config, $end)
	{
		$this->assertSame($this->document(str_replace('{end}', '', $html), $config), $this->document(str_replace('{end}', $end, $html), $config));
	}

	/**
	 * The title is read from a <title> whose end tag has a space before its ">"
	 */
	public function testTheTitleIsRead()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<html><head><title>The title</title ></head><body><p>Body</p><title>Later</title></body></html>');

		$this->assertSame('The title', $mpdf->title);
	}

	/**
	 * An SVG file whose last end tag has a space before its ">" is recognised as one
	 */
	public function testAnSvgFileIsRecognised()
	{
		$guesser = new Image\ImageTypeGuesser();

		$this->assertSame('svg', $guesser->guess('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"></svg >'));
	}

	/**
	 * Empty rows, and the sections and tables they leave empty, are removed whatever comes before their end tags' ">"
	 */
	public function testEmptyTablePartsAreRemoved()
	{
		$html = '<table><thead><tr></tr ></thead ><tbody><tr><td>x</td></tr></tbody><tfoot> <tr> </tr class="x"> </tfoot ></table>'
			. '<table><thead><tr> </tr ></thead ></table >';

		$this->assertSame('<table><tbody><tr><td>x</td></tr></tbody></table>', $this->mpdf()->AdjustHTML($html));
	}

	/**
	 * The whitespace between list items, and between their end tags and their lists', is removed whatever comes
	 * before those end tags' ">"
	 */
	public function testWhitespaceBetweenListEndTagsIsRemoved()
	{
		$html = '<ul><li>a</li > <li>b<ol><li>c</li > </ol > </li > </ul>';

		$this->assertSame('<ul><li>a</li ><li>b<ol><li>c</li ></ol ></li ></ul>', $this->mpdf()->AdjustHTML($html));
	}

	/**
	 * A document drawn with output that does not change from one run to the next, so two can be compared whole
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string The PDF
	 */
	private function document($html, array $config)
	{
		return $this->render($html, $config + ['creationDate' => 946684800, 'exposeVersion' => false]);
	}

}
