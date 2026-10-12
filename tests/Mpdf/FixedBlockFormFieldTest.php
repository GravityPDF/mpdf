<?php

namespace Mpdf;

/**
 * A form field in a position: fixed block, which standard cssMode draws on every page, is written as one field with a
 * widget on each page, so the pages share its value (#747)
 */
class FixedBlockFormFieldTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const PAGES = '<p>Page one</p><pagebreak /><p>Page two</p><pagebreak /><p>Page three</p>';

	/**
	 * A fixed block holding the given form fields
	 *
	 * @param string $fields
	 *
	 * @return string
	 */
	private function fixed($fields)
	{
		return '<div style="position: fixed; top: 10mm; right: 10mm; width: 60mm"><form>' . $fields . '</form></div>';
	}

	/**
	 * The document the fields render to, with active forms on
	 *
	 * @param string $html
	 * @param mixed[] $config
	 *
	 * @return string
	 */
	private function renderForm($html, $config = [])
	{
		return $this->render($html, $config + ['useActiveForms' => true]);
	}

	/**
	 * The field, its widget's type entries, and the entry holding its value
	 *
	 * @return string[][]
	 */
	public function fields()
	{
		return [
			'text field' => ['<input type="text" name="reviewer" value="Jo" size="20" />', '/FT /Tx', '/V (Jo)'],
			'textarea' => ['<textarea name="notes">Seen</textarea>', '/FT /Tx', '/V (Seen)'],
			'select' => ['<select name="grade"><option>A</option><option selected="selected">B</option></select>', '/FT /Ch', '/V  (B)'],
			'checkbox' => ['<input type="checkbox" name="agreed" value="yes" checked="checked" />', '/FT /Btn', '/V /yes'],
		];
	}

	/**
	 * The field is listed once, and its kids are the one widget each page lists. The field carries the type, name and
	 * value; the widgets point at it and carry none of them.
	 *
	 * @dataProvider fields
	 *
	 * @param string $field
	 * @param string $type
	 * @param string $value
	 */
	public function testFieldHasAWidgetOnEveryPage($field, $type, $value)
	{
		$pdf = $this->renderForm($this->fixed($field) . self::PAGES);

		$fields = $this->refs('Fields', $pdf);
		$this->assertCount(1, $fields);

		$parent = $this->object($pdf, $fields[0]);
		$this->assertStringContainsString($type . ' ', $parent);
		$this->assertStringContainsString($value, $parent);
		$this->assertStringNotContainsString('/Type /Annot', $parent);

		$annotations = $this->annotationRefs($pdf);
		$this->assertCount(3, $annotations);
		$this->assertSame(array_merge($annotations[0], $annotations[1], $annotations[2]), $this->refs('Kids', $parent));

		$rects = [];
		foreach ($annotations as $page) {
			$this->assertCount(1, $page);
			$widget = $this->object($pdf, $page[0]);
			$this->assertStringContainsString('/Parent ' . $fields[0] . ' 0 R', $widget);
			$this->assertStringNotContainsString('/T (', $widget);
			$this->assertStringNotContainsString('/FT ', $widget);
			$this->assertStringNotContainsString('/V ', $widget);
			$this->assertSame(1, preg_match('/\/Rect \[ ([^\]]+) \]/', $widget, $rect));
			$rects[] = $rect[1];
		}

		$this->assertCount(1, array_unique($rects), 'Each page places the widget where the block is');
	}

	/**
	 * A text field's scripts are written once, on the field, which joins the calculation order; its widgets run none of
	 * their own
	 */
	public function testScriptsBelongToTheField()
	{
		$pdf = $this->renderForm($this->fixed('<input type="text" name="total" value="0" onchange="event.value = 1" onformat="event.value = 2" />') . self::PAGES);

		$fields = $this->refs('Fields', $pdf);
		$this->assertCount(1, $fields);

		$parent = $this->object($pdf, $fields[0]);
		$this->assertSame(1, preg_match('/\/AA << \/F (\d+) 0 R \/C (\d+) 0 R >>/', $parent, $actions));
		$this->assertStringContainsString('/JS (event.value = 2)', $this->object($pdf, $actions[1]));
		$this->assertStringContainsString('/JS (event.value = 1)', $this->object($pdf, $actions[2]));
		$this->assertSame(2, substr_count($pdf, '/S /JavaScript'));
		$this->assertStringContainsString('/CO [' . $fields[0] . ' 0 R ]', $pdf);

		foreach ($this->refs('Kids', $parent) as $kid) {
			$this->assertStringNotContainsString('/AA', $this->object($pdf, $kid));
		}
	}

	/**
	 * A select's script is written once, on the field
	 */
	public function testChoiceScriptBelongsToTheField()
	{
		$pdf = $this->renderForm($this->fixed('<select name="grade" onchange="app.alert(1)"><option>A</option><option>B</option></select>') . self::PAGES);

		$parent = $this->object($pdf, $this->refs('Fields', $pdf)[0]);
		$this->assertSame(1, preg_match('/\/AA << \/V (\d+) 0 R >>/', $parent, $action));
		$this->assertStringContainsString('/JS (app.alert\\(1\\))', $this->object($pdf, $action[1]));
		$this->assertSame(1, substr_count($pdf, '/S /JavaScript'));
	}

	/**
	 * Pages added by a later WriteHTML() call get widgets of the same field
	 */
	public function testPagesWrittenLaterShareTheField()
	{
		$mpdf = $this->mpdf(['useActiveForms' => true]);
		$mpdf->WriteHTML($this->fixed('<input type="text" name="reviewer" value="" />') . '<p>Page one</p>');
		$mpdf->WriteHTML('<pagebreak /><p>Page two</p>');
		$pdf = $this->output($mpdf);

		$fields = $this->refs('Fields', $pdf);
		$this->assertCount(1, $fields);
		$this->assertCount(2, $this->refs('Kids', $this->object($pdf, $fields[0])));
	}

	/**
	 * Radio buttons and push buttons were already gathered by name, and stay one field each
	 */
	public function testButtonsStayOneFieldEach()
	{
		$pdf = $this->renderForm($this->fixed('<input type="radio" name="r" value="a" /><input type="radio" name="r" value="b" checked="checked" />'
			. '<input type="button" name="go" value="Go" />') . self::PAGES);

		$fields = $this->refs('Fields', $pdf);
		$this->assertCount(2, $fields);
		$this->assertCount(6, $this->refs('Kids', $this->object($pdf, $fields[0])));
		$this->assertCount(3, $this->refs('Kids', $this->object($pdf, $fields[1])));
	}

	/**
	 * A fixed block on a document of one page writes its field as a single widget, as before
	 */
	public function testOnePageKeepsASingleWidget()
	{
		$pdf = $this->renderForm($this->fixed('<input type="text" name="reviewer" value="" />') . '<p>Page one</p>');

		$fields = $this->refs('Fields', $pdf);
		$this->assertCount(1, $fields);
		$this->assertSame($this->annotationRefs($pdf)[0], $fields);
		$this->assertStringContainsString('/T (reviewer)', $this->object($pdf, $fields[0]));
		$this->assertStringNotContainsString('/Parent', implode('', $this->annotations($pdf)));
	}

	/**
	 * Legacy cssMode draws the fixed block on its first page only, so the field keeps its single widget
	 */
	public function testLegacyModeKeepsASingleWidget()
	{
		$pdf = $this->renderForm($this->fixed('<input type="text" name="reviewer" value="" />') . self::PAGES, ['cssMode' => 'legacy']);

		$this->assertSame([['9'], [], []], $this->annotationRefs($pdf));
		$this->assertSame(['9'], $this->refs('Fields', $pdf));
		$this->assertStringNotContainsString('/Parent', implode('', $this->annotations($pdf)));
	}
}
