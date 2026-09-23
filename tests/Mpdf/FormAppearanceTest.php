<?php

namespace Mpdf;

/**
 * Every active form widget carries its own appearance (#348), with its text shaped and ordered as page text is (#408),
 * so no document asks the viewer to redraw them or names the ZapfDingbats font it would redraw checkboxes with (#59).
 */
class FormAppearanceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A form never asks the viewer to redraw its widgets, whatever script its text is in, so it names no ZaDb font
	 *
	 * @dataProvider scripts
	 *
	 * @param string $html
	 */
	public function testViewerIsNotAskedToRedraw($html)
	{
		$form = $this->acroForm($this->render('<form>' . $html . '</form>', ['mode' => 'utf-8', 'useActiveForms' => true]));

		$this->assertStringNotContainsString('/NeedAppearances', $form);
		$this->assertStringNotContainsString('/ZaDb', $form);
	}

	/**
	 * Latin, Arabic, Hebrew and Thai field text, with a checkbox or radio button
	 *
	 * @return string[][]
	 */
	public function scripts()
	{
		$checkbox = ' <input type="checkbox" name="c" value="y" checked="checked" />';
		$radio = ' <input type="radio" name="r" value="a" checked="checked" />';

		return [
			'Latin value' => ['<input type="text" name="t" value="Hello" />' . $checkbox],
			'Arabic value' => ['<input type="text" name="t" value="مرحبا" style="font-family: dejavusans" />' . $checkbox],
			'Hebrew option' => ['<select name="s" style="font-family: dejavusans"><option value="1">שלום</option></select>' . $radio],
			'Thai caption' => ['<input type="submit" name="go" value="สวัสดี" style="font-family: garuda" />' . $radio],
		];
	}

	/**
	 * A text field's appearance draws its value with the glyphs, order and positioning the page draws it with when
	 * forms are not active
	 *
	 * @dataProvider shapedValues
	 *
	 * @param string $value
	 * @param string $font
	 */
	public function testAppearanceDrawsTheValueAsThePageDoes($value, $font)
	{
		$html = '<form><input type="text" name="t" value="' . $value . '" style="font-family: ' . $font . '; width: 80mm" /></form>';

		$page = $this->textShown($this->render($html, ['mode' => 'utf-8']));
		$appearance = $this->textShown($this->render($html, ['mode' => 'utf-8', 'useActiveForms' => true]));

		$this->assertCount(1, $page);
		$this->assertSame($page, $appearance);
	}

	/**
	 * Arabic, which joins and runs right to left, Hebrew, which only runs right to left, and Thai, whose marks are
	 * placed and whose words are marked for breaking
	 *
	 * @return string[][]
	 */
	public function shapedValues()
	{
		return [
			'Arabic' => ['مرحبا بالعالم', 'dejavusans'],
			'Hebrew' => ['שלום עולם', 'dejavusans'],
			'Thai' => ['สวัสดีครับ', 'garuda'],
		];
	}

	/**
	 * A text area with an auto font size is drawn at 12pt when its text fits, and smaller when it would otherwise run
	 * past the bottom, as a viewer sizes it
	 */
	public function testAutoSizedTextAreaFitsItsHeight()
	{
		$short = $this->render('<form><textarea name="a" rows="3" cols="30" style="font-size: auto">Short</textarea></form>', ['useActiveForms' => true]);
		$this->assertStringContainsString(' 12.000 Tf ', $short);

		$long = $this->render('<form><textarea name="a" rows="3" cols="30" style="font-size: auto">' . str_repeat('A few more words ', 20) . '</textarea></form>', ['useActiveForms' => true]);
		$this->assertSame(1, preg_match('/BT \/F\d+ ([\d.]+) Tf ET/', $long, $size));
		$this->assertLessThan(12, (float) $size[1]);

		preg_match_all('/BT [\d.]+ (-?[\d.]+) Td/', $long, $baselines);
		$this->assertGreaterThan(1, count($baselines[1]));
		$this->assertGreaterThanOrEqual(3, min(array_map('floatval', $baselines[1])));
	}

	/**
	 * Poppler reads a form with checkboxes and radio buttons without warning of an unknown font tag (#59), whatever
	 * script its text field is in
	 *
	 * @dataProvider values
	 *
	 * @param string $value the text field's value
	 */
	public function testPopplerReadsTheFormWithoutWarnings($value)
	{
		exec('pdftotext -v 2>&1', $version, $status);
		if ($status !== 0) {
			$this->markTestSkipped('pdftotext is not on the PATH');
		}

		$pdf = tempnam(sys_get_temp_dir(), 'mpdf');
		$text = $pdf . '.txt';
		file_put_contents($pdf, $this->render(
			'<form><input type="text" name="t" value="' . $value . '" style="font-family: dejavusans" />'
			. ' <input type="checkbox" name="c" value="y" checked="checked" /> <input type="radio" name="r" value="a" checked="checked" /></form>',
			['mode' => 'utf-8', 'useActiveForms' => true]
		));

		exec('pdftotext ' . escapeshellarg($pdf) . ' ' . escapeshellarg($text) . ' 2>&1', $errors);
		unlink($pdf);
		unlink($text);

		$this->assertSame([], $errors);
	}

	/**
	 * A Latin value, and an Arabic one, which is shaped and right to left
	 *
	 * @return string[][]
	 */
	public function values()
	{
		return ['Latin' => ['Hello'], 'Arabic' => ['مرحبا']];
	}

	/**
	 * Every widget of an ordinary document carries its appearance, which the viewer is not asked to redraw, and keeps
	 * the actions and flags PDF/A drops
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testWidgetsCarryAppearances($mode)
	{
		$pdf = $this->render($this->form(), ['mode' => $mode, 'useActiveForms' => true]);

		$this->assertStringNotContainsString('/NeedAppearances', $this->acroForm($pdf));

		$refs = $this->annotationRefs($pdf);
		$this->assertCount(10, $refs[0]);
		foreach ($refs[0] as $ref) {
			$this->assertStringContainsString('/AP <<', $this->object($pdf, $ref));
		}

		$widgets = implode('', $this->annotations($pdf));
		$this->assertStringContainsString('/F 2 ', $widgets);
		$this->assertStringContainsString('/F 0 ', $widgets);
		$this->assertStringContainsString('/S /SubmitForm', $widgets);
		$this->assertStringContainsString('/S /ResetForm', $widgets);
		$this->assertStringContainsString('/AA << /D ', $widgets);
	}

	/**
	 * Core fonts, whose text is Windows-1252, and embedded fonts, whose text is UTF-8
	 *
	 * @return string[][]
	 */
	public function modes()
	{
		return ['core fonts' => ['c'], 'embedded fonts' => ['utf-8']];
	}

	/**
	 * A text field's appearance shows its value as the field's font encodes it
	 *
	 * @dataProvider encodings
	 *
	 * @param string $mode
	 * @param string $shown the value as the appearance draws it
	 */
	public function testAppearanceShowsTheValue($mode, $shown)
	{
		$pdf = $this->render('<form><input type="text" name="t" value="Hello" /></form>', ['mode' => $mode, 'useActiveForms' => true]);

		$this->assertStringContainsString('(' . $shown . ') Tj', $pdf);
	}

	/**
	 * "Hello" in a core font and in an embedded one
	 *
	 * @return string[][]
	 */
	public function encodings()
	{
		return ['core fonts' => ['c', 'Hello'], 'embedded fonts' => ['utf-8', "\0H\0e\0l\0l\0o"]];
	}

	/**
	 * Every PDF/A widget with an area carries its appearance and no action, none is hidden from print, and the form
	 * does not ask the viewer to redraw them
	 */
	public function testPdfaWidgetsAreDrawnInAdvance()
	{
		$pdf = $this->render($this->form(), $this->pdfa(true));
		$form = $this->acroForm($pdf);

		$this->assertStringNotContainsString('/NeedAppearances', $form);
		$this->assertStringNotContainsString('/ZaDb', $form);
		$this->assertStringNotContainsString('/CO', $form);

		$refs = $this->annotationRefs($pdf);
		$this->assertCount(10, $refs[0]);
		foreach ($refs[0] as $ref) {
			$widget = $this->object($pdf, $ref);
			$this->assertStringNotContainsString('/AA', $widget);
			$this->assertMatchesRegularExpression('/\/F 4\b/', $widget);
			if (strpos($widget, '/Rect [ ') !== false && !preg_match('/\/Rect \[ ([\d.]+) ([\d.]+) \1 \2 \]/', $widget)) {
				$this->assertStringContainsString('/AP <<', $widget);
			}
		}

		// The text field shows its value, in UTF-16 as the embedded font is encoded
		$this->assertStringContainsString("(\0H\0e\0l\0l\0o) Tj", $pdf);
	}

	/**
	 * A PDF/A document with checkboxes and radio buttons is written without PDFAauto, as it no longer needs
	 * ZapfDingbats, and without warnings when nothing else had to change
	 */
	public function testPdfaCheckboxesNeedNoCoreFont()
	{
		$mpdf = $this->mpdf($this->pdfa(false));
		$mpdf->WriteHTML('<form><input type="checkbox" name="c" value="y" checked="checked" /> <input type="radio" name="r" value="a" />'
			. ' <input type="text" name="t" value="Hello" /> <select name="s"><option value="1">One</option></select></form>');

		$this->assertStringContainsString('/AcroForm', $this->output($mpdf));
		$this->assertSame([], $mpdf->PDFAXwarnings);
	}

	/**
	 * Without PDFAauto, a PDF/A document refuses a form that runs JavaScript, submits or resets itself, or hides a
	 * button from print, and says why
	 */
	public function testPdfaWarnsOfWhatItLeavesOut()
	{
		$mpdf = $this->mpdf($this->pdfa(false));
		$mpdf->WriteHTML($this->form());

		try {
			$this->output($mpdf);
			$this->fail('The document was written');
		} catch (MpdfException $e) {
			$this->assertSame('PDFA/PDFX warnings generated. See log for further details', $e->getMessage());
		}

		$this->assertContains('Form fields cannot run JavaScript, submit or reset the form in PDFA files (Action removed)', $mpdf->PDFAXwarnings);
		$this->assertContains('Form buttons are printed in PDFA files (noprint ignored)', $mpdf->PDFAXwarnings);
	}

	/**
	 * A PDF/X document draws its checkboxes and radio buttons without ZapfDingbats, which it cannot embed either
	 */
	public function testPdfxCheckboxesNeedNoCoreFont()
	{
		$pdf = $this->render(
			'<form><input type="checkbox" name="c" value="y" checked="checked" /> <input type="radio" name="r" value="a" /></form>',
			['mode' => 'utf-8', 'PDFX' => true, 'PDFXauto' => true, 'useActiveForms' => true]
		);

		$this->assertStringNotContainsString('/BaseFont /ZapfDingbats', $pdf);
	}

	/**
	 * A list box draws as many options as its size asks for, each whole and inside its own row, and nothing after
	 * them
	 *
	 * @dataProvider listBoxes
	 *
	 * @param string $mode
	 * @param int $rows the list box's size
	 * @param string $style
	 */
	public function testListBoxShowsItsRowsWhole($mode, $rows, $style)
	{
		$options = '';
		foreach (['Apple', 'Banana', 'Cherry', 'Damson', 'Elderberry', 'Fig'] as $i => $option) {
			$options .= '<option value="' . $i . '">' . $option . ' gjpqy</option>';
		}

		$mpdf = $this->mpdf(['mode' => $mode, 'useActiveForms' => true]);
		$mpdf->WriteHTML('<form><select name="s" size="' . $rows . '" multiple="multiple" style="' . $style . '">' . $options . '</select></form>');
		$desc = $mpdf->CurrentFont['desc'];
		$appearance = $this->choiceAppearance($this->output($mpdf));

		$this->assertSame(1, preg_match('/([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) re W n BT \/F\d+ ([\d.]+) Tf/', $appearance, $clip));
		list(, , $bottom, , $height, $size) = $clip;
		$row = $height / $rows;

		preg_match_all('/ 1 0 0 1 -?[\d.]+ (-?[\d.]+) Tm \(/', $appearance, $baselines);
		$this->assertCount($rows, $baselines[1]);
		foreach ($baselines[1] as $i => $baseline) {
			$rowBottom = $bottom + ($rows - 1 - $i) * $row;
			$this->assertGreaterThanOrEqual($rowBottom - 0.002, $baseline + $desc['Descent'] / 1000 * $size, 'Row ' . $i . ' reaches below its row');
			$this->assertLessThanOrEqual($rowBottom + $row + 0.002, $baseline + $desc['Ascent'] / 1000 * $size, 'Row ' . $i . ' reaches above its row');
		}
	}

	/**
	 * One, three and six rows, in a core and an embedded font, and in a size larger than the default
	 *
	 * @return mixed[][]
	 */
	public function listBoxes()
	{
		return [
			'one row' => ['utf-8', 1, ''],
			'three rows' => ['utf-8', 3, ''],
			'six rows, larger text' => ['utf-8', 6, 'font-size: 16pt'],
			'three rows, core font' => ['c', 3, ''],
		];
	}

	/**
	 * No choice field's appearance draws a drop-down arrow, and its text is clipped at the full width inside the
	 * border. Interactive viewers draw a combo box's button themselves, so an arrow in the appearance shows twice
	 *
	 * @dataProvider choiceFields
	 *
	 * @param string $select
	 */
	public function testAChoiceFieldAppearanceHasNoDropDownArrow($select)
	{
		$appearance = $this->choiceAppearance($this->render('<form>' . $select . '</form>', $this->pdfa(true)));

		$this->assertSame(1, preg_match('/ 0 0 ([\d.]+) [\d.]+ re f .*? ([\d.]+) [\d.]+ ([\d.]+) [\d.]+ re W n/', $appearance, $box));
		list(, $width, $border, $clipWidth) = $box;

		$this->assertDoesNotMatchRegularExpression('/ [\d.]+ [\d.]+ m [\d.]+ [\d.]+ l [\d.]+ [\d.]+ l f /', $appearance);
		$this->assertEqualsWithDelta($width - 2 * $border, (float) $clipWidth, 0.002);
	}

	/**
	 * A combo box, and list boxes of one and several rows
	 *
	 * @return string[][]
	 */
	public function choiceFields()
	{
		$options = '<option value="1">One</option><option value="2">Two</option>';

		return [
			'combo box' => ['<select name="s">' . $options . '</select>'],
			'one-row list box' => ['<select name="s" size="1" multiple="multiple">' . $options . '</select>'],
			'list box' => ['<select name="s" size="2">' . $options . '</select>'],
		];
	}

	/**
	 * The content of the appearance of a document's first choice field
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function choiceAppearance($pdf)
	{
		$this->assertSame(1, preg_match('/\/FT \/Ch.*?\/AP << \/N (\d+) 0 R/s', $pdf, $ref));
		$this->assertSame(1, preg_match('/stream\n(.*)\nendstream/s', $this->object($pdf, $ref[1]), $stream));

		return $stream[1];
	}

	/**
	 * Every kind of widget, with a script, a submit and a reset button, a button hidden from print and a hidden input
	 *
	 * @return string
	 */
	private function form()
	{
		return '<form action="https://example.com/submit">'
			. '<input type="text" name="t" value="Hello" onchange="app.alert(1)" /> <input type="password" name="p" value="secret" />'
			. ' <input type="hidden" name="h" value="x" /> <textarea name="a">Area</textarea>'
			. ' <input type="checkbox" name="c" value="y" checked="checked" /> <input type="radio" name="r" value="a" checked="checked" />'
			. ' <select name="s"><option value="1">One</option></select>'
			. ' <input type="submit" name="go" value="Send" /> <input type="reset" name="rs" value="Reset" />'
			. ' <input type="button" name="b" value="Push" onclick="app.alert(2)" noprint="noprint" />'
			. '</form>';
	}

	/**
	 * A PDF/A-2b configuration with active forms, in the embedded fonts PDF/A needs
	 *
	 * @param bool $auto whether PDFAauto is on
	 *
	 * @return mixed[]
	 */
	private function pdfa($auto)
	{
		return ['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => $auto, 'PDFAversion' => '2-B', 'useActiveForms' => true];
	}

	/**
	 * The operands of each text-showing operator in a document, whether a string shown whole or an array of glyphs
	 * and adjustments
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function textShown($pdf)
	{
		preg_match_all('/T[dm]\s+(?:0 Tc 0 Tw (\[.*?\]) TJ|(\(.*?\)) Tj)/s', $pdf, $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			return isset($match[2]) ? $match[2] : $match[1];
		}, $matches);
	}

	/**
	 * The document's interactive form dictionary
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function acroForm($pdf)
	{
		$this->assertSame(1, preg_match('/\/AcroForm <<.*?\n>>\n/s', $pdf, $form));

		return $form[0];
	}

}
