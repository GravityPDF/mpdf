<?php

namespace Mpdf;

use setasign\Fpdi\PdfParser\StreamReader;

/**
 * A radio group is written as one field holding the value, with a widget for each radio button as its kids, and each
 * checkbox and radio widget names its appearances once (#450)
 */
class RadioGroupFieldTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Every checkbox and radio widget, switched on or off, has one /AP entry
	 *
	 * @dataProvider configs
	 *
	 * @param mixed[] $config
	 */
	public function testEachWidgetHasOneAppearanceEntry($config)
	{
		$pdf = $this->render('<form><input type="checkbox" name="c" value="y" checked="checked" /> <input type="checkbox" name="d" value="n" />'
			. ' <input type="radio" name="r" value="a" checked="checked" /> <input type="radio" name="r" value="b" /></form>', $config);

		$widgets = $this->widgets($pdf);
		$this->assertCount(4, $widgets);

		foreach ($widgets as $widget) {
			$this->assertSame(1, substr_count($widget, '/AP '), $widget);
		}
	}

	/**
	 * An ordinary document and a PDF/A-2b one
	 *
	 * @return mixed[][]
	 */
	public function configs()
	{
		return [
			'ordinary' => [['mode' => 'c', 'useActiveForms' => true]],
			'PDF/A-2b' => [['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B', 'useActiveForms' => true]],
		];
	}

	/**
	 * The group is a field, not an annotation: it carries the name and the value of the button switched on, and each
	 * radio button is a widget showing whether it is that one. mPDF can import the document.
	 */
	public function testGroupFieldCarriesTheValue()
	{
		$pdf = $this->render('<form><input type="radio" name="r" value="a" /> <input type="radio" name="r" value="b" checked="checked" /></form>', [
			'mode' => 'c',
			'useActiveForms' => true,
		]);

		$this->assertSame(1, preg_match('/\/Fields \[(\d+) 0 R \]/', $pdf, $field));
		$group = $this->object($pdf, $field[1]);

		$this->assertStringContainsString('/V /b ', $group);
		$this->assertStringContainsString('/DV /b ', $group);
		$this->assertStringContainsString('/T (r)', $group);
		foreach (['/Type', '/Subtype', '/Rect', '/NM', '/M '] as $key) {
			$this->assertStringNotContainsString($key, $group);
		}

		$widgets = $this->widgets($pdf);
		$this->assertNotContains($field[1], $this->annotationRefs($pdf)[0]);

		foreach (['/Off', '/b'] as $i => $state) {
			$this->assertStringContainsString('/Parent ' . $field[1] . ' 0 R', $widgets[$i]);
			$this->assertStringContainsString('/AS ' . $state . ' ', $widgets[$i]);
			$this->assertStringNotContainsString('/V ', $widgets[$i]);
			$this->assertStringNotContainsString('/DV ', $widgets[$i]);
		}

		$mpdf = $this->mpdf();
		$this->assertSame(1, $mpdf->setSourceFile(StreamReader::createByString($pdf)), 'The document can be imported');
		$mpdf->useTemplate($mpdf->importPage(1));
		$this->assertStringStartsWith('%PDF-', $this->output($mpdf));
	}

	/**
	 * A group with no button switched on has the value Off
	 */
	public function testGroupWithNothingCheckedIsOff()
	{
		$pdf = $this->render('<form><input type="radio" name="r" value="a" /> <input type="radio" name="r" value="b" /></form>', [
			'mode' => 'c',
			'useActiveForms' => true,
		]);

		$this->assertSame(1, preg_match('/\/Fields \[(\d+) 0 R \]/', $pdf, $field));
		$this->assertStringContainsString('/V /Off ', $this->object($pdf, $field[1]));
	}

	/**
	 * The annotation objects the first page lists, one string each
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function widgets($pdf)
	{
		$widgets = [];
		foreach ($this->annotationRefs($pdf)[0] as $ref) {
			$widgets[] = $this->object($pdf, $ref);
		}

		return $widgets;
	}
}
