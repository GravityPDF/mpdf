<?php

namespace Mpdf;

/**
 * An active form field's widget and appearance take its CSS border colour, width and style (#431)
 */
class ActiveFieldBorderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const STYLE = 'font-size: 14pt; color: #c00000; background-color: #ffffcc; border: 0.5mm solid #0000ff';

	/**
	 * The issue's text input has a 0.5mm (1.417pt) solid blue border in its /BS, its /MK and its appearance
	 */
	public function testTheIssuesTextInputTakesItsBorder()
	{
		$pdf = $this->render('<form><input type="text" name="s" value="Styled" style="' . self::STYLE . '" /></form>', ['useActiveForms' => true]);

		$widget = $this->widget($pdf, 's');
		$this->assertStringContainsString('/BS << /W 1.417 /S /S  >>', $widget);
		$this->assertStringContainsString('/MK <</BC [ 0.000 0.000 1.000 ] /BG [ 1.000 1.000 0.800 ]  >>', $widget);
		$this->assertStringContainsString('/DA (/F2 14 Tf 0.753 0.000 0.000 rg)', $widget);
		$this->assertStringContainsString('1.000 1.000 0.800 rg 0 0 84.643 20.803 re f 0.000 0.000 1.000 RG 1.417 w 0.709 0.709 83.226 19.386 re S', $pdf);
	}

	/**
	 * Every kind of field with a border and appearance takes the CSS border
	 *
	 * @dataProvider fields
	 *
	 * @param string $field named f
	 */
	public function testEachFieldTakesItsBorder($field)
	{
		$pdf = $this->render('<form>' . $field . '</form>', ['useActiveForms' => true]);

		$widget = $this->widget($pdf, 'f');
		$this->assertStringContainsString('/BS << /W 1.417 /S /S  >>', $widget);
		$this->assertMatchesRegularExpression('/\/MK <<\s?\/BC \[ 0\.000 0\.000 1\.000 \] \/BG \[ 1\.000 1\.000 0\.800 \]/', $widget);
		$this->assertMatchesRegularExpression('/1\.000 1\.000 0\.800 rg 0 0 [\d.]+ [\d.]+ re f 0\.000 0\.000 1\.000 RG 1\.417 w 0\.709 0\.709 /', $pdf);
	}

	/**
	 * One field of each kind, styled as the issue's text input
	 *
	 * @return string[][]
	 */
	public function fields()
	{
		$style = ' style="' . self::STYLE . '"';

		return [
			'textarea' => ['<textarea name="f"' . $style . '>Styled</textarea>'],
			'combo box' => ['<select name="f"' . $style . '><option>Styled</option></select>'],
			'list box' => ['<select name="f" size="3"' . $style . '><option>Styled</option></select>'],
			'submit button' => ['<input type="submit" name="f" value="Styled"' . $style . ' />'],
			'reset button' => ['<input type="reset" name="f" value="Styled"' . $style . ' />'],
			'button' => ['<input type="button" name="f" value="Styled"' . $style . ' />'],
		];
	}

	/**
	 * A field without CSS keeps mPDF's 1pt solid border in the default colour
	 */
	public function testAFieldWithoutStyleKeepsTheDefaults()
	{
		$pdf = $this->render('<form><input type="text" name="s" value="Plain" /></form>', ['useActiveForms' => true]);

		$widget = $this->widget($pdf, 's');
		$this->assertStringContainsString('/BS << /W 1 /S /S  >>', $widget);
		$this->assertStringContainsString('/MK <</BC [ 0.6 0.6 0.72 ] /BG [ 0.975 0.975 0.975 ]  >>', $widget);
		$this->assertStringContainsString('0.600 0.600 0.722 RG 1.000 w 0.500 0.500 ', $pdf);
	}

	/**
	 * Each CSS border style is written as the /BS style closest to it, and a dashed one is dashed in the appearance
	 *
	 * @dataProvider styles
	 *
	 * @param string $css
	 * @param string $bs the /S entry expected
	 * @param string $stroke how the appearance strokes the border
	 */
	public function testTheBorderStyleIsKept($css, $bs, $stroke)
	{
		$pdf = $this->render('<form><input type="text" name="s" value="x" style="border: 1pt ' . $css . ' #0000ff" /></form>', ['useActiveForms' => true]);

		$this->assertStringContainsString('/BS << /W 1.000 /S ' . $bs . '  >>', $this->widget($pdf, 's'));
		$this->assertStringContainsString($stroke, $pdf);
	}

	/**
	 * CSS border styles and the /BS styles they become
	 *
	 * @return string[][]
	 */
	public function styles()
	{
		$solid = '0.000 0.000 1.000 RG 1.000 w 0.500 0.500 ';

		return [
			'solid' => ['solid', '/S', $solid],
			'dashed' => ['dashed', '/D /D [3]', '0.000 0.000 1.000 RG 1.000 w [3] 0 d 0.500 0.500 '],
			'dotted' => ['dotted', '/D /D [1]', '0.000 0.000 1.000 RG 1.000 w [1] 0 d 0.500 0.500 '],
			'inset' => ['inset', '/I', $solid],
			'outset' => ['outset', '/B', $solid],
			'double' => ['double', '/S', $solid],
		];
	}

	/**
	 * A border of style none has no width and no border colour, and its appearance draws no border
	 *
	 * @dataProvider noBorders
	 *
	 * @param string $style
	 */
	public function testAFieldWithoutABorderDrawsNone($style)
	{
		$pdf = $this->render('<form><input type="text" name="s" value="x" style="' . $style . '" /></form>', ['useActiveForms' => true]);

		$widget = $this->widget($pdf, 's');
		$this->assertStringContainsString('/BS << /W 0 /S /S  >>', $widget);
		$this->assertStringNotContainsString('/BC', $widget);
		$this->assertStringNotContainsString(' re S', $pdf);
	}

	/**
	 * Ways CSS takes a border away
	 *
	 * @return string[][]
	 */
	public function noBorders()
	{
		return [
			'none' => ['border: none'],
			'hidden' => ['border-style: hidden'],
		];
	}

	/**
	 * A border colour or width given on its own keeps the rest of the default border
	 */
	public function testABorderLonghandKeepsTheRestOfTheDefault()
	{
		$pdf = $this->render('<form><input type="text" name="c" value="x" style="border-color: #0000ff" />'
			. '<input type="text" name="w" value="x" style="border-width: 2pt" /></form>', ['useActiveForms' => true]);

		$this->assertStringContainsString('/BS << /W 1 /S /S  >>', $this->widget($pdf, 'c'));
		$this->assertStringContainsString('/BC [ 0.000 0.000 1.000 ]', $this->widget($pdf, 'c'));
		$this->assertStringContainsString('/BS << /W 2.000 /S /S  >>', $this->widget($pdf, 'w'));
		$this->assertStringContainsString('/BC [ 0.6 0.6 0.72 ]', $this->widget($pdf, 'w'));
	}

	/**
	 * A PDF/A document restricted to CMYK writes the border colour in CMYK, in both /MK and the appearance
	 */
	public function testTheBorderColourFollowsTheOutputIntent()
	{
		$pdf = $this->render(
			'<form><input type="text" name="s" value="Styled" style="' . self::STYLE . '" /></form>',
			['mode' => 'utf-8', 'useActiveForms' => true, 'PDFA' => true, 'PDFAauto' => true, 'restrictColorSpace' => 3, 'ICCProfile' => __DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc']
		);

		$this->assertStringContainsString('/BC [ 1.000 1.000 0.000 0.000 ]', $this->widget($pdf, 's'));
		$this->assertStringContainsString('1.000 1.000 0.000 0.000 K 1.417 w', $pdf);
	}

	/**
	 * The widget annotation of the field with a name
	 *
	 * @param string $pdf
	 * @param string $name
	 *
	 * @return string
	 */
	private function widget($pdf, $name)
	{
		foreach ($this->annotationRefs($pdf)[0] as $ref) {
			$annotation = $this->object($pdf, $ref);
			if (strpos($annotation, '/T (' . $name . ')') !== false) {
				return $annotation;
			}
		}

		$this->fail('The field ' . $name . ' should have a widget');
	}

}
