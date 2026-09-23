<?php

namespace Mpdf;

/**
 * A field's text too wide for it is drawn in a smaller font rather than cut off, down to a minimum size past which it
 * is trimmed (#433). This holds for text inputs, combo boxes and push buttons, drawn into the page or as active
 * fields. The text is Courier at 10pt, so "ABCDEFGHIJ" is 60pt (21.17mm) wide.
 */
class FormFieldShrinkTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A text input drawn into the page shows as much of its value as fits, at the size that fits it. The value has
	 * the field's CSS width to itself.
	 *
	 * @dataProvider staticFields
	 *
	 * @param string $width the field's CSS width
	 * @param string $drawn the text drawn
	 * @param float $size the size it is drawn at, in points
	 */
	public function testStaticFieldDrawsTheValueAtTheSizeThatFits($width, $drawn, $size)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($this->field($width));

		$this->assertSame([$drawn], $mpdf->drawnText);
		$this->assertEqualsWithDelta($size, $mpdf->drawnFontSize[0], 0.001);
	}

	/**
	 * A value that fits inside the padding, one wider than the field, and one too wide even at the minimum size
	 *
	 * @return mixed[][]
	 */
	public function staticFields()
	{
		return [
			'fits' => ['21.5mm', 'ABCDEFGHIJ', 10],
			'shrunk' => ['15mm', 'ABCDEFGHIJ', 7.086],
			'trimmed at the minimum size' => ['5mm', 'ABC', 6],
		];
	}

	/**
	 * In a table shrunk to fit the page, a value is measured against the padding shrunk with it, as Cell() draws it,
	 * so a value that fits keeps the size of the text beside it
	 */
	public function testStaticFieldInAShrunkTableKeepsTheSizeOfAValueThatFits()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<table style="font-family: courier; font-size: 10pt"><tr><td>' . $this->field('21.5mm') . '</td>'
			. '<td style="white-space: nowrap">' . str_repeat('X', 150) . '</td></tr></table>');

		$this->assertSame('ABCDEFGHIJ', $mpdf->drawnText[0]);
		$this->assertLessThan(10, $mpdf->drawnFontSize[1]);
		$this->assertEqualsWithDelta($mpdf->drawnFontSize[1], $mpdf->drawnFontSize[0], 0.0001);
	}

	/**
	 * A value in a script that is shaped is measured as it is drawn, and drawn whole in a smaller font
	 */
	public function testShapedValueIsDrawnWholeInASmallerFont()
	{
		$value = 'مرحبا بالعالم';

		$wide = new TextRecordingMpdf(['mode' => 'utf-8']);
		$wide->WriteHTML('<form><input type="text" name="t" value="' . $value . '" style="font-family: dejavusans; width: 100mm" /></form>');

		$narrow = new TextRecordingMpdf(['mode' => 'utf-8']);
		$narrow->WriteHTML('<form><input type="text" name="t" value="' . $value . '" style="font-family: dejavusans; width: 15mm" /></form>');

		$this->assertSame($wide->drawnText, $narrow->drawnText);
		$this->assertLessThan($wide->drawnFontSize[0], $narrow->drawnFontSize[0]);
	}

	/**
	 * A select drawn into the page shows its choice by the same rule. Its choice has the field's width less the
	 * drop-down arrow, 1.4em wide, and 1.2mm of spacing on each side.
	 *
	 * @dataProvider staticSelects
	 *
	 * @param float $width the field's width, in mm
	 * @param string $drawn the text drawn
	 * @param float $size the size it is drawn at, in points
	 */
	public function testStaticSelectDrawsItsChoiceAtTheSizeThatFits($width, $drawn, $size)
	{
		$mpdf = $this->drawnInto('TextRecordingMpdf', []);
		$this->service($mpdf, 'form')->print_ob_select(['text' => 'ABCDEFGHIJ', 'OTLdata' => false], $width, 10, 'ABCDEFGHIJ', 'L', 1, 'ltr');

		$this->assertSame($drawn, $mpdf->drawnText[0]);
		$this->assertEqualsWithDelta($size, $mpdf->drawnFontSize[0], 0.001);
		$this->assertEqualsWithDelta(10, $mpdf->drawnFontSize[1], 0.001, 'The arrow keeps the field\'s size');
	}

	/**
	 * A choice that fits, one wider than the room, and one too wide even at the minimum size
	 *
	 * @return mixed[][]
	 */
	public function staticSelects()
	{
		return [
			'fits' => [29, 'ABCDEFGHIJ', 10],
			'shrunk' => [22, 'ABCDEFGHIJ', 6.926],
			'trimmed at the minimum size' => [12, 'ABC', 6],
		];
	}

	/**
	 * A select's choice was shaped when it was read, so it is cut together with its OTL data rather than shaped again
	 */
	public function testStaticSelectCutsAShapedChoiceWithItsOtlData()
	{
		$mpdf = $this->drawnInto('TextRecordingMpdf', ['mode' => 'utf-8'], 'dejavusans');
		$otl = $this->service($mpdf, 'otl');
		$choice = $otl->applyOTL('مرحبا بالعالم', $mpdf->CurrentFont['useOTL']);
		$OTLdata = $otl->OTLdata;

		$this->service($mpdf, 'form')->print_ob_select(['text' => $choice, 'OTLdata' => $OTLdata], 12, 10, $choice, 'R', 1, 'rtl');

		$drawn = $mpdf->drawnText[0];
		$this->assertLessThan(mb_strlen($choice, 'UTF-8'), mb_strlen($drawn, 'UTF-8'));
		$this->assertCount(mb_strlen($drawn, 'UTF-8'), $mpdf->drawnOTLdata[0]['char_data']);
		$this->assertEqualsWithDelta(6, $mpdf->drawnFontSize[0], 0.001);
	}

	/**
	 * A push button drawn into the page shows its caption by the same rule, in its width less 2.5mm of spacing on each
	 * side. Its caption carries a space at either end, so " ABCDEFGHIJ " is 72pt (25.4mm) wide.
	 *
	 * @dataProvider staticButtons
	 *
	 * @param float $width the button's width, in mm
	 * @param string $drawn the text drawn
	 * @param float $size the size it is drawn at, in points
	 */
	public function testStaticButtonDrawsItsCaptionAtTheSizeThatFits($width, $drawn, $size)
	{
		$mpdf = $this->drawnInto('TextRecordingMpdf', []);
		$this->service($mpdf, 'form')->print_ob_button(['subtype' => 'SUBMIT'], $width, 10, ' ABCDEFGHIJ ', 'L', 1, 'ltr');

		$this->assertSame([$drawn], $mpdf->drawnText);
		$this->assertEqualsWithDelta($size, $mpdf->drawnFontSize[0], 0.001);
	}

	/**
	 * A caption that fits, one wider than the button, and one too wide even at the minimum size
	 *
	 * @return mixed[][]
	 */
	public function staticButtons()
	{
		return [
			'fits' => [30.5, ' ABCDEFGHIJ ', 10],
			'shrunk' => [23, ' ABCDEFGHIJ ', 7.086],
			'trimmed at the minimum size' => [10, ' AB', 6],
		];
	}

	/**
	 * A value too wide for an active text field leaves the viewer to size the text, and the appearance draws as much
	 * of it as fits at the size that fits it. The field keeps its whole value. An active field is 2.4mm wider than its
	 * CSS width, and its appearance keeps 3pt clear on each side.
	 *
	 * @dataProvider activeFields
	 *
	 * @param string $width the field's CSS width
	 * @param string $da the font size /DA names
	 * @param string $size the font size the appearance draws in
	 * @param string $drawn the text the appearance draws
	 */
	public function testActiveFieldIsSizedToFit($width, $da, $size, $drawn)
	{
		$pdf = $this->render($this->field($width), ['useActiveForms' => true]);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertStringContainsString('/V (ABCDEFGHIJ)', $widget);
		$this->assertStringContainsString('/DV (ABCDEFGHIJ)', $widget);
		$this->assertAppearance($widget, $pdf, $da, $size, $drawn);
	}

	/**
	 * A value that fits the appearance, one wider than it, and one too wide even at the minimum size
	 *
	 * @return string[][]
	 */
	public function activeFields()
	{
		return [
			'fits' => ['25mm', '10', '10.000', 'ABCDEFGHIJ'],
			'shrunk' => ['15mm', '0', '7.220', 'ABCDEFGHIJ'],
			'trimmed at the minimum size' => ['5mm', '0', '6.000', 'ABCD'],
		];
	}

	/**
	 * An active combo box's choice is fitted as a text field's value is, and leaves the viewer to size it too. Its
	 * options are kept whole.
	 *
	 * @dataProvider activeComboBoxes
	 *
	 * @param float $width the field's width, in mm
	 * @param string $da the font size /DA names
	 * @param string $size the font size the appearance draws in
	 * @param string $drawn the text the appearance draws
	 */
	public function testActiveComboBoxIsSizedToFit($width, $da, $size, $drawn)
	{
		$mpdf = $this->drawnInto('Mpdf', ['useActiveForms' => true]);
		$this->service($mpdf, 'form')->print_ob_select([
			'fieldname' => 's',
			'items' => [['exportValue' => 'v', 'content' => 'ABCDEFGHIJ', 'selected' => true]],
		], $width, 7, 'ABCDEFGHIJ', 'L', 1, 'ltr');
		$pdf = $this->output($mpdf);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertStringContainsString('/Opt [ [ (v) (ABCDEFGHIJ) ] ]', $widget);
		$this->assertAppearance($widget, $pdf, $da, $size, $drawn);
	}

	/**
	 * A choice that fits the appearance, one wider than it, and one too wide even at the minimum size, in a combo box
	 * whose appearance keeps 3pt clear on each side
	 *
	 * @return mixed[][]
	 */
	public function activeComboBoxes()
	{
		return [
			'fits' => [23.5, '10', '10.000', 'ABCDEFGHIJ'],
			'shrunk' => [18, '0', '7.503', 'ABCDEFGHIJ'],
			'trimmed at the minimum size' => [7, '0', '6.000', 'ABC'],
		];
	}

	/**
	 * An active push button's caption is fitted as a text field's value is. Its caption cannot be edited, so /DA names
	 * the size the appearance draws in, for a viewer that redraws it. The caption the viewer is given is whole.
	 *
	 * @dataProvider activeButtons
	 *
	 * @param float $width the button's width, in mm
	 * @param string $da the font size /DA names
	 * @param string $size the font size the appearance draws in
	 * @param string $drawn the text the appearance draws
	 */
	public function testActivePushButtonIsSizedToFit($width, $da, $size, $drawn)
	{
		$mpdf = $this->drawnInto('Mpdf', ['useActiveForms' => true]);
		$this->service($mpdf, 'form')->print_ob_button(['subtype' => 'BUTTON', 'fieldname' => 'b', 'value' => 'ABCDEFGHIJ', 'title' => ''], $width, 7, '', 'L', 1, 'ltr');
		$pdf = $this->output($mpdf);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertStringContainsString('/CA (ABCDEFGHIJ)', $widget);
		$this->assertAppearance($widget, $pdf, $da, $size, $drawn);
	}

	/**
	 * A caption that fits the appearance, one wider than it, and one too wide even at the minimum size, in a button
	 * whose appearance keeps 3pt clear on each side
	 *
	 * @return string[][]
	 */
	public function activeButtons()
	{
		return [
			'fits' => [23.5, '10', '10.000', 'ABCDEFGHIJ'],
			'shrunk' => [18, '7.503', '7.503', 'ABCDEFGHIJ'],
			'trimmed at the minimum size' => [7, '6', '6.000', 'ABC'],
		];
	}

	/**
	 * A button that shows an icon draws no caption, so /DA keeps the field's size however narrow the button
	 */
	public function testIconButtonKeepsTheFieldsSize()
	{
		$pdf = $this->render('<form><input type="image" name="i" src="' . $this->pngImage() . '" width="2mm" style="font-family: courier; font-size: 10pt" /></form>', ['useActiveForms' => true]);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertMatchesRegularExpression('/\/DA \(\/F\d+ 10 Tf /', $widget);
	}

	/**
	 * A form with one Courier text input holding "ABCDEFGHIJ"
	 *
	 * @param string $width its CSS width
	 *
	 * @return string
	 */
	private function field($width)
	{
		return '<form><input type="text" name="t" value="ABCDEFGHIJ" style="font-family: courier; font-size: 10pt; width: ' . $width . '" /></form>';
	}

	/**
	 * A document on its first page, in a font at 10pt, for a field to be drawn into directly. Selects and buttons are
	 * sized to their text when read from HTML, so only a width given this way leaves them too narrow.
	 *
	 * @param string $class Mpdf or TextRecordingMpdf
	 * @param mixed[] $config
	 * @param string $font
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function drawnInto($class, array $config, $font = 'courier')
	{
		$class = 'Mpdf\\' . $class;
		$mpdf = new $class($config + ['mode' => 'c']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('');
		$mpdf->SetFont($font, '', 10);

		return $mpdf;
	}

	/**
	 * One of the services a document keeps to itself, such as its form fields or its OTL shaper
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param string $name
	 *
	 * @return object
	 */
	private function service(Mpdf $mpdf, $name)
	{
		$service = new \ReflectionProperty('Mpdf\Mpdf', $name);
		if (PHP_VERSION_ID < 80100) {
			$service->setAccessible(true);
		}

		return $service->getValue($mpdf);
	}

	/**
	 * The widget names a font size in /DA, and its appearance draws the given text at another
	 *
	 * @param string $widget the widget's dictionary
	 * @param string $pdf the whole document, which holds its appearance stream
	 * @param string $da
	 * @param string $size
	 * @param string $drawn
	 */
	private function assertAppearance($widget, $pdf, $da, $size, $drawn)
	{
		$this->assertMatchesRegularExpression('/\/DA \(\/F\d+ ' . preg_quote($da, '/') . ' Tf /', $widget);
		$this->assertMatchesRegularExpression('/\/F\d+ ' . preg_quote($size, '/') . ' Tf ET [\d.]+ g BT 1 0 0 1 [\d.]+ [\d.]+ Tm \(' . $drawn . '\) Tj/', $pdf);
	}

}
