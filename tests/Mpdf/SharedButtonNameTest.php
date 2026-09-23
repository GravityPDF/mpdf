<?php

namespace Mpdf;

use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Push buttons that share a name are written as one field whose kids are the widgets, and each widget keeps its own
 * action, caption and icon (#443)
 */
class SharedButtonNameTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Two script buttons named go become one field named go with the two widgets as kids. Neither widget has a name of
	 * its own, and each runs its own script.
	 */
	public function testButtonsThatShareANameAreKidsOfOneField()
	{
		$pdf = $this->render('<form>'
			. '<input type="button" name="go" value="First" onclick="app.alert(\'first\')" />'
			. '<input type="button" name="go" value="Second" onclick="app.alert(\'second\')" />'
			. '</form>', ['mode' => 'c', 'useActiveForms' => true]);

		$fields = $this->fields($pdf);
		$this->assertCount(1, $fields);

		$field = $this->object($pdf, $fields[0]);
		$this->assertStringContainsString('/FT /Btn /Ff 65536 /T (go)', $field);
		$this->assertStringNotContainsString('/Type /Annot', $field);

		$kids = $this->kids($field);
		$this->assertSame($this->annotationRefs($pdf)[0], $kids);

		foreach (['First' => 'first', 'Second' => 'second'] as $caption => $script) {
			$widget = $this->object($pdf, array_shift($kids));
			$this->assertStringContainsString('/Parent ' . $fields[0] . ' 0 R', $widget);
			$this->assertStringNotContainsString('/T (', $widget);
			$this->assertStringContainsString('/CA (' . $caption . ')', $widget);

			$this->assertSame(1, preg_match('/\/AA << \/D (\d+) 0 R >>/', $widget, $action));
			$this->assertStringContainsString("/JS (app.alert\\('" . $script . "'\\))", $this->object($pdf, $action[1]));
		}
	}

	/**
	 * Submit, reset, script and image buttons that share a name are gathered into the one field, and a button with a
	 * name of its own stays a single field and widget named for itself
	 */
	public function testEveryKindOfPushButtonIsGathered()
	{
		$pdf = $this->render('<form action="https://example.com/">'
			. '<input type="submit" name="act" value="Save" /> <input type="reset" name="act" value="Clear" />'
			. ' <input type="button" name="act" value="Run" onclick="app.alert(1)" /> <input type="image" name="act" src="' . $this->icon('16x9') . '" />'
			. ' <input type="submit" name="lone" value="Alone" /> <input type="radio" name="r" value="a" />'
			. '</form>', ['mode' => 'c', 'useActiveForms' => true]);

		$fields = $this->fields($pdf);
		$this->assertCount(3, $fields);

		$lone = $this->object($pdf, $fields[0]);
		$this->assertStringContainsString('/Subtype /Widget', $lone);
		$this->assertStringContainsString('/T (lone)', $lone);
		$this->assertStringNotContainsString('/Parent', $lone);

		$this->assertStringContainsString('/T (r)', $this->object($pdf, $fields[1]));

		$field = $this->object($pdf, $fields[2]);
		$this->assertStringContainsString('/T (act)', $field);
		$this->assertCount(4, $this->kids($field));
		$this->assertCount(6, $this->annotationRefs($pdf)[0]);
	}

	/**
	 * Two image buttons with one name each show their own icon, rather than both showing the last one
	 */
	public function testImageButtonsThatShareANameKeepTheirIcons()
	{
		$pdf = $this->render('<form>'
			. '<input type="image" name="pic" src="' . $this->icon('16x9') . '" /> <input type="image" name="pic" src="' . $this->icon('9x16') . '" />'
			. '</form>', ['mode' => 'c', 'useActiveForms' => true]);

		$sizes = [];
		foreach ($this->kids($this->object($pdf, $this->fields($pdf)[0])) as $kid) {
			$this->assertSame(1, preg_match('/\/I (\d+) 0 R/', $this->object($pdf, $kid), $icon));
			preg_match('/\/Width (\d+).*?\/Height (\d+)/s', $this->object($pdf, $icon[1]), $size);
			$sizes[] = $size[2] > $size[1] ? 'tall' : 'wide';
		}

		$this->assertSame(['wide', 'tall'], $sizes);
	}

	/**
	 * A PDF/A document gathers the buttons the same way, and writes neither their scripts nor their submit and reset
	 * actions
	 */
	public function testPdfaButtonsThatShareANameCarryNoActions()
	{
		$pdf = $this->render('<form action="https://example.com/">'
			. '<input type="button" name="go" value="First" onclick="app.alert(1)" /> <input type="submit" name="go" value="Second" />'
			. '</form>', ['mode' => 'utf-8', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B', 'useActiveForms' => true]);

		$fields = $this->fields($pdf);
		$this->assertCount(1, $fields);

		foreach ($this->kids($this->object($pdf, $fields[0])) as $kid) {
			$widget = $this->object($pdf, $kid);
			$this->assertStringContainsString('/AP << /N << /Push ', $widget);
			$this->assertStringNotContainsString('/AA', $widget);
		}

		$this->assertStringNotContainsString('/JS', $pdf);
	}

	/**
	 * Buttons in a block measured before it is placed, to keep it on one page, are gathered once each
	 */
	public function testMeasuredButtonsAreGatheredOnce()
	{
		$pdf = $this->render($this->filler(22) . '<form>' . $this->keptBlock(12, '<input type="button" name="go" value="First" onclick="app.alert(1)" />'
			. ' <input type="button" name="go" value="Second" onclick="app.alert(2)" />') . '</form>', ['mode' => 'c', 'useActiveForms' => true]);

		$fields = $this->fields($pdf);
		$this->assertCount(1, $fields);

		$kids = $this->kids($this->object($pdf, $fields[0]));
		$this->assertCount(2, $kids);
		$this->assertSame($kids, array_merge([], ...$this->annotationRefs($pdf)));
		$this->assertSame([], $this->annotationRefs($pdf)[0], 'The block moved to the second page');
		$this->assertSame(2, substr_count($pdf, '/JS (app.alert\\('));
	}

	/**
	 * A named button in a header, drawn on every page, is one field with a widget on each page
	 */
	public function testHeaderButtonIsOneFieldAcrossPages()
	{
		$mpdf = $this->mpdf(['mode' => 'c', 'useActiveForms' => true]);
		$mpdf->SetHTMLHeader('<form><input type="button" name="top" value="Top" onclick="app.alert(1)" /></form>');
		$mpdf->WriteHTML('<p>One</p><pagebreak /><p>Two</p>');
		$pdf = $this->output($mpdf);

		$fields = $this->fields($pdf);
		$this->assertCount(1, $fields);

		$refs = $this->annotationRefs($pdf);
		$this->assertCount(1, $refs[0]);
		$this->assertCount(1, $refs[1]);
		$this->assertSame(array_merge($refs[0], $refs[1]), $this->kids($this->object($pdf, $fields[0])));
	}

	/**
	 * A document whose buttons are gathered into one field can still be imported
	 */
	public function testGatheredButtonsImport()
	{
		$pdf = $this->render('<form><input type="button" name="go" value="First" onclick="app.alert(1)" />'
			. ' <input type="submit" name="go" value="Second" /></form>', ['mode' => 'c', 'useActiveForms' => true]);

		$mpdf = $this->mpdf();
		$this->assertSame(1, $mpdf->setSourceFile(StreamReader::createByString($pdf)));
		$mpdf->useTemplate($mpdf->importPage(1));

		$this->assertStringStartsWith('%PDF-', $this->output($mpdf));
	}

	/**
	 * The objects the document's /AcroForm lists in /Fields
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function fields($pdf)
	{
		$this->assertSame(1, preg_match('/\/Fields \[([^\]]*)\]/', $pdf, $list));
		preg_match_all('/(\d+) 0 R/', $list[1], $refs);

		return $refs[1];
	}

	/**
	 * The objects a field lists in /Kids
	 *
	 * @param string $field
	 *
	 * @return string[]
	 */
	private function kids($field)
	{
		$this->assertSame(1, preg_match('/\/Kids \[([^\]]*)\]/', $field, $list));
		preg_match_all('/(\d+) 0 R/', $list[1], $refs);

		return $refs[1];
	}

	/**
	 * A landscape or portrait PNG
	 *
	 * @param string $ratio '16x9' or '9x16'
	 *
	 * @return string
	 */
	private function icon($ratio)
	{
		return __DIR__ . '/../data/img/ratio-' . $ratio . '.png';
	}
}
