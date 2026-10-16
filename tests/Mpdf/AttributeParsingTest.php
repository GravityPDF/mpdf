<?php

namespace Mpdf;

class AttributeParsingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * Writes a document, uncompressed, with its paths relative to tests/data, and outputs it
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string The PDF
	 */
	private function render($html, array $config = [])
	{
		return $this->write($html, $config)->Output('', 'S');
	}

	/**
	 * Writes a document, uncompressed, with its paths relative to tests/data
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return Mpdf The document, not yet output
	 */
	private function write($html, array $config = [])
	{
		$mpdf = new Mpdf($config);
		$mpdf->compress = false;
		$mpdf->SetBasePath(__DIR__ . '/../data/');
		$mpdf->WriteHTML($html);

		return $mpdf;
	}

	private function red($output)
	{
		return false !== strpos($output, '1.000 0.000 0.000 rg');
	}

	/**
	 * Core fonts write one glyph per pair of parentheses, spaced out and sometimes zero padded, so only the letters are kept
	 */
	private function drawnText($output)
	{
		$matches = [];
		preg_match_all('#\((.*?)\)\s*Tj#s', $output, $matches);

		return preg_replace('/[^A-Za-z]/', '', implode('', $matches[1]));
	}

	/**
	 * A closed <select> draws the text of whichever option is selected, then a dropdown marker
	 */
	private function selectedOption($output)
	{
		$drawn = $this->drawnText($output);

		return false !== strpos($drawn, 'One') ? 'One' : (false !== strpos($drawn, 'Two') ? 'Two' : $drawn);
	}

	private function pageSizes($output)
	{
		$matches = [];
		preg_match_all('#/MediaBox \[([^\]]*)\]#', $output, $matches);

		return array_values(array_unique($matches[1]));
	}

	private function select($selected)
	{
		return '<select><option value="1">One</option><option ' . $selected . ' value="2">Two</option></select>';
	}

	public function testABooleanAttributeNeedsNoValue()
	{
		$this->assertSame('Two', $this->selectedOption($this->render($this->select('selected'))));
	}

	public function testABooleanAttributeMayStillCarryAValue()
	{
		$this->assertSame('Two', $this->selectedOption($this->render($this->select('selected="selected"'))));
	}

	public function testTheFirstOptionWinsWhenNoneIsSelected()
	{
		$this->assertSame('One', $this->selectedOption($this->render($this->select(''))));
	}

	/**
	 * An unquoted value on a name holding a hyphen
	 */
	public function testAnUnquotedValueOnAHyphenatedAttribute()
	{
		$sizes = $this->pageSizes($this->render('a<pagebreak sheet-size=A5 />b'));

		$this->assertCount(2, $sizes);
		$this->assertSame('0 0 419.530 595.280', $sizes[1]);
	}

	public function testAQuotedValueOnAHyphenatedAttribute()
	{
		$sizes = $this->pageSizes($this->render('a<pagebreak sheet-size="A5" />b'));

		$this->assertCount(2, $sizes);
		$this->assertSame('0 0 419.530 595.280', $sizes[1]);
	}

	public function testASingleQuotedValueMayHoldDoubleQuotes()
	{
		$output = $this->render('<div style=\'font-family: "times"; color: #ff0000\'>x</div>');

		$this->assertStringContainsString('1.000 0.000 0.000 rg', $output);
	}

	public function testADoubleQuotedValueMayHoldSingleQuotes()
	{
		$output = $this->render('<div style="font-family: \'times\'; color: #ff0000">x</div>');

		$this->assertStringContainsString('1.000 0.000 0.000 rg', $output);
	}


	/**
	 * HTML lets whitespace sit either side of the equals sign
	 */
	public function testWhitespaceMaySurroundTheEqualsSign()
	{
		$sizes = $this->pageSizes($this->render('a<pagebreak sheet-size = "A5" />b'));

		$this->assertCount(2, $sizes);
		$this->assertSame('0 0 419.530 595.280', $sizes[1]);
	}

	public function testWhitespaceMaySurroundTheEqualsSignOfABareValue()
	{
		$sizes = $this->pageSizes($this->render('a<pagebreak sheet-size = A5 />b'));

		$this->assertCount(2, $sizes);
		$this->assertSame('0 0 419.530 595.280', $sizes[1]);
	}

	/**
	 * Attribute names are letter-led, so the slash closing an empty tag is not read as one
	 */
	public function testTheSlashOfAnEmptyTagIsNotAnAttribute()
	{
		$sizes = $this->pageSizes($this->render('a<pagebreak sheet-size="A5"/>b'));

		$this->assertCount(2, $sizes);
		$this->assertSame('0 0 419.530 595.280', $sizes[1]);
	}

	public function testABooleanAttributeMayComeLast()
	{
		$html = '<select><option value="1">One</option><option value="2" selected>Two</option></select>';

		$this->assertSame('Two', $this->selectedOption($this->render($html)));
	}

	/**
	 * The name that follows a valueless attribute has to be read as a name, not as its value
	 */
	public function testABooleanAttributeMayStandBetweenTwoOthers()
	{
		$html = '<select><option value="1">One</option><option class="x" selected value="2">Two</option></select>';

		$this->assertSame('Two', $this->selectedOption($this->render($html)));
	}

	public function testAttributesMayBeSeparatedByNewlines()
	{
		$output = $this->render("<div\n\tclass=\"x\"\n\tstyle=\"color: #ff0000\">x</div>");

		$this->assertTrue($this->red($output));
	}

	public function testAnEmptyValueDoesNotSwallowTheNextAttribute()
	{
		$output = $this->render('<div class="" style="color: #ff0000">x</div>');

		$this->assertTrue($this->red($output));
	}

	public function testTheLastOfTwoIdenticalAttributesWins()
	{
		$output = $this->render('<div style="color: #00ff00" style="color: #ff0000">x</div>');

		$this->assertTrue($this->red($output));
	}

	public function testAnAttributeNameIsCaseInsensitive()
	{
		$output = $this->render('<div STYLE="color: #ff0000">x</div>');

		$this->assertTrue($this->red($output));
	}

	/**
	 * Both the class attribute and the selector it matches are uppercased before comparison
	 */
	public function testAClassIsMatchedWhateverItsCase()
	{
		$output = $this->render('<style>.red { color: #ff0000 }</style><div class="rEd">x</div>');

		$this->assertTrue($this->red($output));
	}

	/**
	 * <pagefooter> reads unquoted values like any other tag
	 */
	public function testAPageFooterReadsUnquotedValues()
	{
		$html = '<pagefooter name=pf content-left=FOOT footer-style-left="color: #ff0000" />'
			. '<setpagefooter name=pf page=ALL value=on />body';

		$output = $this->render($html);

		$this->assertStringContainsString('FOOT', $this->drawnText($output));
		$this->assertTrue($this->red($output), 'the footer style was not applied');
	}

	/**
	 * A relative image path is resolved against the base path
	 */
	public function testARelativeImagePathIsResolved()
	{
		$this->assertStringContainsString('/Subtype /Image', $this->render('<img src=img/bayeux2.jpg />'));
	}

	/**
	 * A relative link is resolved against the base path, its entities decoded
	 */
	public function testARelativeLinkIsResolved()
	{
		$output = $this->render('<a href="page.html?a=1&amp;b=2">x</a>');

		$this->assertStringContainsString('/URI (' . __DIR__ . '/../data/page.html?a=1&b=2)', $output);
	}

	/**
	 * A " name=value" inside a quoted value is part of it, whichever tag it is on
	 *
	 * @dataProvider modes
	 *
	 * @param string $cssMode
	 */
	public function testANameAndValueInsideAQuotedValueStayInIt($cssMode)
	{
		$mpdf = $this->write('<bookmark content="Set x=1 first" /><bookmark content="see src=a.png now" /><bookmark content="a=\'b\' c" /><p>text</p>', ['cssMode' => $cssMode]);

		$this->assertSame(['Set x=1 first', 'see src=a.png now', "a='b' c"], array_column($mpdf->BMoutlines, 't'));
	}

	/**
	 * A text field draws the whole of a value holding " name=value"
	 */
	public function testATextFieldDrawsAValueHoldingANameAndValue()
	{
		$mpdf = $this->drawDocument('<input type="text" value="Set x=1 first">');

		$this->assertSame(['Set x=1 first'], $mpdf->drawnText);
	}

	/**
	 * A " href=" inside another attribute's value leaves the link its own href
	 *
	 * @dataProvider modes
	 *
	 * @param string $cssMode
	 */
	public function testAnHrefInsideATitleLeavesTheLinkItsOwn($cssMode)
	{
		$output = $this->render('<a title="see href=x now" href="https://example.com/a">link</a>', ['cssMode' => $cssMode]);

		$this->assertStringContainsString('/URI (https://example.com/a)', $output);
	}

	/**
	 * An apostrophe inside an unquoted value is part of it
	 */
	public function testAnUnquotedValueKeepsItsApostrophe()
	{
		$output = $this->render('<style>p[title="don\'t"] { color: #ff0000 }</style><p title=don\'t>x</p>');

		$this->assertTrue($this->red($output));
	}

	/**
	 * A data-name before the name does not take its place, nor does a ">" in a value end the tag
	 */
	public function testAPageHeaderAndFooterAreRegisteredUnderTheirName()
	{
		$mpdf = $this->write('<htmlpageheader title="a > b" data-name="x" name="main"><div>H</div></htmlpageheader>'
			. '<htmlpagefooter data-name="y" name=foot><div>F</div></htmlpagefooter><p>t</p>');

		$this->assertSame(['main'], array_keys($mpdf->pageHTMLheaders));
		$this->assertSame(['foot'], array_keys($mpdf->pageHTMLfooters));
	}

	/**
	 * A data-style before the style does not take its place, nor does a ">" in a value end the tag
	 *
	 * @dataProvider modes
	 *
	 * @param string $cssMode
	 */
	public function testTheBodyStyleIsReadFromItsOwnAttribute($cssMode)
	{
		$output = $this->render('<body title="a > b" data-style="color: #ff0000" style="color: #0000ff"><p>t</p></body>', ['cssMode' => $cssMode]);

		$this->assertFalse($this->red($output));
		$this->assertStringContainsString('0.000 0.000 1.000 rg', $output);
	}

	/**
	 * Body tags, and the direction and language they give the document
	 *
	 * @return array
	 */
	public static function bodyLangAndDirProvider()
	{
		return [
			'a data-dir and data-lang' => ['<body data-lang="fr" data-dir="rtl">', 'ltr', false],
			'unquoted' => ['<BODY LANG=fr DIR=RTL>', 'rtl', true],
		];
	}

	/**
	 * The body's dir and lang are read by name, however they are written. A p:lang(fr) rule colours the text red
	 *
	 * @dataProvider bodyLangAndDirProvider
	 *
	 * @param string $body
	 * @param string $directionality
	 * @param bool $french
	 */
	public function testTheBodyLangAndDirAreReadByName($body, $directionality, $french)
	{
		$mpdf = $this->write('<style>p:lang(fr) { color: #ff0000 }</style>' . $body . '<p>t</p></body>');

		$this->assertSame($directionality, $mpdf->directionality);
		$this->assertSame($french, $this->red($mpdf->Output('', 'S')));
	}

	/**
	 * Meta tags written in each way, and the keywords, author and subject they set
	 *
	 * @return array
	 */
	public static function metaProvider()
	{
		return [
			'a " name=value" in the content' => ['<meta name="keywords" content="set x=1 first">', 'keywords', 'set x=1 first'],
			'single quoted' => ['<meta name=\'author\' content=\'J "Q" Public\'>', 'author', 'J "Q" Public'],
			'the content first' => ['<meta content="A summary" name="description">', 'subject', 'A summary'],
			'unquoted' => ['<META NAME=keywords CONTENT=one>', 'keywords', 'one'],
			'a data-name before the name' => ['<meta data-name="x" name="author" content="Jo">', 'author', 'Jo'],
		];
	}

	/**
	 * A meta tag's name and content are read whole, in either order and however they are quoted
	 *
	 * @dataProvider metaProvider
	 *
	 * @param string $meta
	 * @param string $property
	 * @param string $expected
	 */
	public function testAMetaTagIsReadWhole($meta, $property, $expected)
	{
		$mpdf = $this->write('<html><head>' . $meta . '</head><body><p>t</p></body></html>');

		$this->assertSame($expected, $mpdf->$property);
	}

	/**
	 * A title holding " name=value" is not given quotes
	 */
	public function testATitleHoldingANameAndValueIsReadWhole()
	{
		$mpdf = $this->write('<html><head><title>Set a=1 first</title></head><body><p>t</p></body></html>');

		$this->assertSame('Set a=1 first', $mpdf->title);
	}

}
