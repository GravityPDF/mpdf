<?php

namespace Mpdf;

/**
 * A run of spaces inside a quoted attribute value is part of the value, as it is in a browser: only the spaces between
 * tags are collapsed to one
 */
class AttributeWhitespaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * Each CSS mode
	 *
	 * @return array[]
	 */
	public static function modes()
	{
		return [
			'standard' => [['cssMode' => CssMode::STANDARD]],
			'legacy' => [['cssMode' => CssMode::LEGACY]],
		];
	}

	/**
	 * A bookmark is titled with its content as written
	 *
	 * @dataProvider modes
	 *
	 * @param array $config
	 */
	public function testABookmarkKeepsItsSpaces(array $config)
	{
		$mpdf = $this->drawDocument('<bookmark content="Total:   42" /><p>x</p>', $config);

		$this->assertSame('Total:   42', $mpdf->BMoutlines[0]['t']);
	}

	/**
	 * A text field shows its value as written, and the text between the tags is still collapsed
	 *
	 * @dataProvider modes
	 *
	 * @param array $config
	 */
	public function testATextFieldKeepsItsSpaces(array $config)
	{
		$mpdf = $this->drawDocument('<p><input type="text" value="a   b"></p><p>c   d</p>', $config);

		$this->assertSame(['a   b', 'c d'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * An index entry, an annotation, a watermark and a title turned into an annotation keep their spaces
	 *
	 * @dataProvider modes
	 *
	 * @param array $config
	 */
	public function testContentAndTitleAttributesKeepTheirSpaces(array $config)
	{
		$html = '<p>x<indexentry content="Index   entry" /><annotation content="Annotation   text" />'
			. '<watermarktext content="Water   mark" /><span title="Span   title">y</span></p>';

		$mpdf = $this->drawDocument($html, $config + ['title2annots' => true]);

		$this->assertSame('Index   entry', $mpdf->Reference[0]['t']);
		$this->assertSame(['Annotation   text', 'Span   title'], array_column($mpdf->PageAnnots[1], 'txt'));
		$this->assertSame('Water   mark', $mpdf->watermarkText);
	}

	/**
	 * Spaces, linefeeds and tabs between tags are collapsed, while a value and a pre block keep theirs
	 */
	public function testOnlyTheSpacesOutsideTagsAndPreAreCollapsed()
	{
		$mpdf = new Mpdf(['mode' => 'c']);

		$html = "<p   title=\"a   b\">\n\tc   d  </p><pre>e   f</pre>";

		$this->assertSame('<p   title="a   b"> c d </p><pre>e   f</pre>', $mpdf->AdjustHTML($html));
	}

}
