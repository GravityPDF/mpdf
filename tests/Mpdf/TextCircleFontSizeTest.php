<?php

namespace Mpdf;

/**
 * A text circle that neither its tag nor the CSS gives a font-size is drawn at the size of the text around it, as
 * though that size had been set on it, rather than raising warnings over the size it was never given
 */
class TextCircleFontSizeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @dataProvider providerContexts
	 *
	 * @param string $mode A CssMode constant
	 * @param string $html The document, with {style} where the text circle's style attribute goes
	 * @param string $size The size around the text circle, as a Tf operator writes it
	 */
	public function testATextCircleWithoutAFontSizeDrawsAtTheSizeAroundIt($mode, $html, $size)
	{
		$pages = $this->drawnPages($mode, $html, '');
		$this->assertStringContainsString(' ' . $size . ' Tf', $pages[0]);
		$this->assertSame($this->drawnPages($mode, $html, ' style="font-size: ' . $size . 'pt"'), $pages);
	}

	/**
	 * Draw the document, asserting it raises nothing, and hand back its page content streams
	 *
	 * @param string $mode A CssMode constant
	 * @param string $html The document, with {style} where the text circle's style attribute goes
	 * @param string $style What goes in place of {style}
	 *
	 * @return string[]
	 */
	private function drawnPages($mode, $html, $style)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html, $style) {
			$mpdf->WriteHTML(str_replace('{style}', $style, $html));
		}, ['cssMode' => $mode]);

		return $this->pages($pdf);
	}

	/**
	 * @return array[] Each CSS mode with a text circle at the top of the document, in a paragraph and in a table cell
	 */
	public function providerContexts()
	{
		$contexts = [
			'document' => ['<textcircle r="30mm" top-text="Top" bottom-text="Bottom"{style} />', '11.000'],
			'paragraph' => ['<p style="font-size: 14pt"><textcircle r="30mm" top-text="Top"{style} /></p>', '14.000'],
			'table cell' => ['<table><tr><td style="font-size: 9pt"><textcircle r="10mm" top-text="Top"{style} /></td></tr></table>', '9.000'],
		];

		$cases = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach ($contexts as $name => $context) {
				$cases[$mode . ', ' . $name] = array_merge([$mode], $context);
			}
		}

		return $cases;
	}
}
