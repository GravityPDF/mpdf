<?php

namespace Mpdf;

/**
 * The CSS outline properties describe a line around the box. mPDF draws no box outline, and does not stroke the
 * element's text for them either; its own text-outline properties still do.
 */
class OutlinePropertiesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The uncompressed document for a div styled with the given declarations
	 *
	 * @param string $style
	 *
	 * @return string
	 */
	private function render($style)
	{
		$mpdf = new Mpdf();
		$mpdf->compress = false;
		$mpdf->WriteHTML('<div style="' . $style . '">Outlined text</div>');

		return $mpdf->Output('', 'S');
	}

	/**
	 * outline-width and outline-color leave the text filled and unstroked
	 *
	 * @dataProvider outlineProvider
	 *
	 * @param string $style
	 */
	public function testOutlineDoesNotStrokeText($style)
	{
		$pdf = $this->render($style);

		$this->assertStringContainsString(' 0 Tr ', $pdf);
		$this->assertStringNotContainsString(' 2 Tr ', $pdf);
	}

	/**
	 * Declarations of the CSS outline properties
	 *
	 * @return array
	 */
	public function outlineProvider()
	{
		return [
			'longhands' => ['outline-style: solid; outline-width: 1mm; outline-color: #cc0000'],
			'width alone' => ['outline-width: thick'],
		];
	}

	/**
	 * text-outline-width and text-outline-color stroke the text in that width and colour
	 */
	public function testTextOutlineStrokesText()
	{
		$pdf = $this->render('text-outline-width: 0.2mm; text-outline-color: #cc0000');

		$this->assertMatchesRegularExpression('/ ' . preg_quote(sprintf('%.3F', 0.2 * Mpdf::SCALE), '/') . ' w\s+0\.800 0\.000 0\.000 RG\s+2 Tr /', $pdf);
	}

	/**
	 * outline-color does not recolour a text outline set by mPDF's own properties
	 */
	public function testOutlineColorLeavesTextOutlineColour()
	{
		$pdf = $this->render('text-outline-width: 0.2mm; text-outline-color: #cc0000; outline-color: #0000cc');

		$this->assertMatchesRegularExpression('/0\.800 0\.000 0\.000 RG\s+2 Tr /', $pdf);
	}

	/**
	 * The text-outline shorthand strokes the text too
	 */
	public function testTextOutlineShorthandStrokesText()
	{
		$this->assertStringContainsString(' 2 Tr ', $this->render('text-outline: 0.2mm #cc0000'));
	}

}
