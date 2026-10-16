<?php

namespace Mpdf;

/**
 * A "<" or ">" inside a quoted attribute value belongs to the value, as it does in a browser: it does not end the tag,
 * and it reaches the tag unchanged
 */
class AngleBracketInAttributeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	const BLUE = '0.000 0.000 1.000 rg';

	/**
	 * Each CSS mode, with autoScriptToLang off and on
	 *
	 * @return array[]
	 */
	public static function modesAndScripts()
	{
		return [
			'standard' => [['mode' => '', 'cssMode' => CssMode::STANDARD]],
			'standard, autoScriptToLang' => [['mode' => '', 'cssMode' => CssMode::STANDARD, 'autoScriptToLang' => true, 'autoLangToFont' => true]],
			'legacy' => [['mode' => '', 'cssMode' => CssMode::LEGACY]],
			'legacy, autoScriptToLang' => [['mode' => '', 'cssMode' => CssMode::LEGACY, 'autoScriptToLang' => true, 'autoLangToFont' => true]],
		];
	}

	/**
	 * A ">" inside a quoted string of a style attribute leaves the rest of the declarations, and the rest of the
	 * document, as they are
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAGreaterThanSignInsideAStyleAttribute(array $config)
	{
		$drawn = $this->drawnColours('<p style="font-family: \'a>b\', serif; color: blue">Blue text</p><p>Second</p>', $config);

		$this->assertSame(['Blue text', 'Second'], array_keys($drawn));
		$this->assertSame(self::BLUE, $drawn['Blue text']);
	}

	/**
	 * A ">" in an attribute before the style attribute, on text in two scripts, leaves both drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAGreaterThanSignInsideATitleBeforeTheStyle(array $config)
	{
		$drawn = $this->drawnColours('<p title="1 > 0" style="color: blue">Blue text שלום</p><p>Second</p>', $config);

		$text = implode(' ', array_keys($drawn));
		$this->assertMatchesRegularExpression('/^Blue text .*\p{Hebrew}{4}.* Second$/u', $text);
		$this->assertSame(self::BLUE, reset($drawn));
	}

	/**
	 * Each CSS mode with autoScriptToLang off and on, and core fonts, where characters such as "Σ" are swapped for
	 * the symbol font's only between tags
	 *
	 * @return array[]
	 */
	public static function modesAndFonts()
	{
		return self::modesAndScripts() + ['core fonts' => [['mode' => 'c']]];
	}

	/**
	 * A link keeps the "<" and ">" in its URL, and what follows them
	 *
	 * @dataProvider modesAndFonts
	 *
	 * @param array $config
	 */
	public function testALinkKeepsTheAngleBracketsInItsUrl(array $config)
	{
		$mpdf = $this->drawDocument('<a href="https://example.com/?q=a>Σ&amp;r=c<d">link</a>', $config);
		$mpdf->compress = false;

		$this->assertStringContainsString('/URI (https://example.com/?q=a>Σ&r=c<d)', $mpdf->Output('', 'S'));
	}

	/**
	 * A ">" inside a quoted value of a tag that closes itself, written with or without its "/", reaches the tag
	 * unchanged
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testASelfClosingTagKeepsTheGreaterThanSign(array $config)
	{
		$mpdf = $this->drawDocument('<bookmark content="Profit > Loss" /><bookmark content="a > b"><p>text</p>', $config);

		$this->assertSame(['Profit > Loss', 'a > b'], array_column($mpdf->BMoutlines, 't'));
	}

	/**
	 * An unsupported tag is dropped whole, however its attribute values are quoted, and what is between it and its end
	 * tag is drawn
	 *
	 * @dataProvider modesAndScripts
	 *
	 * @param array $config
	 */
	public function testAnUnsupportedTagIsDroppedWhole(array $config)
	{
		$drawn = $this->drawnColours('<p>before <custom data-x="a>b" data-y=\'c>d\'>inside</custom> after</p>', $config);

		$this->assertSame(['before inside after'], array_keys($drawn));
	}

	/**
	 * A tag that DisableTags() has turned off is dropped like any other unsupported one
	 */
	public function testADisabledTagIsDropped()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->DisableTags('<b>');
		$mpdf->WriteHTML('<p>plain <b title="x>y">not bold</b></p>');

		$this->assertSame(['plain not bold'], array_map('trim', $mpdf->drawnText));
		$this->assertSame([''], $mpdf->drawnFontStyles);
	}

}
