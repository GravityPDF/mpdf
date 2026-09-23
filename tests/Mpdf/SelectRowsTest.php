<?php

namespace Mpdf;

/**
 * A select is laid out as many rows tall as HTML would draw it: a multiple select with no size is a list box four
 * rows tall, and an explicit size is kept (GravityPDF/mpdf#425)
 */
class SelectRowsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A select's attributes, the attributes of a select it should be as tall as, and whether forms are active
	 *
	 * @return mixed[]
	 */
	public function selects()
	{
		$selects = [
			'multiple with no size' => ['multiple', 'size="4"'],
			'multiple="multiple" with no size' => ['multiple="multiple"', 'size="4"'],
			'multiple with a size of zero' => ['multiple size="0"', 'size="4"'],
			'multiple with a size of one' => ['multiple size="1"', ''],
			'multiple with a size of two' => ['multiple size="2"', 'size="2"'],
			'multiple with a size of six' => ['multiple size="6"', 'size="6"'],
			'no size' => ['', 'size="1"'],
		];

		$cases = [];
		foreach ($selects as $name => $select) {
			$cases[$name . ', static'] = [$select[0], $select[1], false];
			$cases[$name . ', active'] = [$select[0], $select[1], true];
		}

		return $cases;
	}

	/**
	 * @dataProvider selects
	 *
	 * @param string $attributes
	 * @param string $sameAs
	 * @param bool $active
	 */
	public function testASelectIsAsTallAsTheRowsHtmlGivesIt($attributes, $sameAs, $active)
	{
		$this->assertEqualsWithDelta($this->height($sameAs, $active), $this->height($attributes, $active), 0.001);
	}

	/**
	 * A multiple select with no size is taller than a drop-down in both modes
	 */
	public function testAMultipleSelectWithNoSizeIsTallerThanADropDown()
	{
		$this->assertGreaterThan($this->height('', false) + 1, $this->height('multiple', false));
		$this->assertGreaterThan($this->height('', true) + 1, $this->height('multiple', true));
	}

	/**
	 * An active multiple select with no size gets a widget four rows tall
	 */
	public function testAnActiveMultipleSelectWithNoSizeHasAWidgetFourRowsTall()
	{
		$this->assertEqualsWithDelta($this->widgetHeight('size="4"'), $this->widgetHeight('multiple'), 0.001);
	}

	/**
	 * How far a line holding only the select advances the page, in mm
	 *
	 * @param string $attributes
	 * @param bool $active
	 *
	 * @return float
	 */
	private function height($attributes, $active)
	{
		$mpdf = $this->mpdf(['useActiveForms' => $active]);
		$top = $mpdf->y;
		$mpdf->WriteHTML($this->select($attributes));

		return $mpdf->y - $top;
	}

	/**
	 * The height of the active select's widget /Rect, in points
	 *
	 * @param string $attributes
	 *
	 * @return float
	 */
	private function widgetHeight($attributes)
	{
		$pdf = $this->render($this->select($attributes), ['useActiveForms' => true]);
		$this->assertSame(1, preg_match('/\/Rect \[\s*[\d.]+ ([\d.]+) [\d.]+ ([\d.]+)\s*\][^>]*?\/FT \/Ch\b/s', $pdf, $rect), 'The select should be written as a choice field');

		return $rect[2] - $rect[1];
	}

	/**
	 * A form holding one select with two options
	 *
	 * @param string $attributes
	 *
	 * @return string
	 */
	private function select($attributes)
	{
		return '<form><select name="choice" ' . $attributes . '><option>A</option><option>B</option></select></form>';
	}
}
