<?php

namespace Mpdf\Ua;

/**
 * With useActiveForms off, a form control is only drawn, with no field behind it, so its box is
 * marked as an artifact. StaticFormFieldValueTest covers the value drawn in it.
 *
 * @group pdfua
 */
class LegacyFormArtifactTaggingTest extends PdfUaTestCase
{

	/**
	 * A PDF/UA document in auto mode with useActiveForms off.
	 *
	 * @param array $extraConfig
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function makeLegacyMpdf($extraConfig = [])
	{
		$defaults = [
			'PDFUA' => true,
			'PDFUAauto' => true,
			'title' => 'Legacy Form Test',
			'mode' => 'en-GB',
			'useActiveForms' => false,
		];
		$mpdf = new \Mpdf\Mpdf(array_merge($defaults, $extraConfig));
		$mpdf->compress = false;
		return $mpdf;
	}

	/**
	 * The document with the HTML written, as PDF bytes.
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param string     $html
	 *
	 * @return string
	 */
	private function render(\Mpdf\Mpdf $mpdf, $html)
	{
		$mpdf->WriteHTML($html);
		return $mpdf->Output(null, 'S');
	}

	/**
	 * The control's box was drawn as an artifact, and nothing in the structure tree points at a
	 * widget, since there is none.
	 *
	 * @param string $output PDF bytes
	 * @param string $widget The control, for the assertion messages
	 */
	private function assertLegacyArtifactContract($output, $widget)
	{
		$this->assertNotEmpty($output, $widget . ': PDF must be produced');
		$this->assertStringContainsString(
			'/Artifact BMC',
			$output,
			$widget . ': legacy chrome must be wrapped in /Artifact BMC'
		);
		$this->assertStringContainsString(
			'EMC',
			$output,
			$widget . ': /Artifact BMC must be closed with EMC'
		);
		$this->assertStringNotContainsString(
			'/Subtype /Widget',
			$output,
			$widget . ': legacy path must not emit a Widget annotation'
		);
		$this->assertStringNotContainsString(
			'/S /Annot',
			$output,
			$widget . ': legacy path must not emit an Annot struct kid'
		);
	}

	/**
	 * A text input is drawn as an artifact.
	 */
	public function testInputTextWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render($mpdf, '<p>Name: <input type="text" name="x" value="Jane" /></p>');
		$this->assertLegacyArtifactContract($out, '<input type=text>');
	}

	/**
	 * A textarea is drawn as an artifact.
	 */
	public function testTextareaWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render(
			$mpdf,
			'<p>Notes:</p><textarea name="n" rows="3" cols="20">First line</textarea>'
		);
		$this->assertLegacyArtifactContract($out, '<textarea>');
	}

	/**
	 * A select is drawn as an artifact.
	 */
	public function testSelectWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render(
			$mpdf,
			'<p>Pick: <select name="s"><option>Alpha</option><option selected>Beta</option></select></p>'
		);
		$this->assertLegacyArtifactContract($out, '<select>');
	}

	/**
	 * A checkbox is drawn as an artifact.
	 */
	public function testCheckboxWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render(
			$mpdf,
			'<p><input type="checkbox" name="c" checked /> agree</p>'
		);
		$this->assertLegacyArtifactContract($out, '<input type=checkbox>');
	}

	/**
	 * A radio button is drawn as an artifact.
	 */
	public function testRadioWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render(
			$mpdf,
			'<p><input type="radio" name="r" value="a" checked /> option A</p>'
		);
		$this->assertLegacyArtifactContract($out, '<input type=radio>');
	}

	/**
	 * A submit button is drawn as an artifact.
	 */
	public function testButtonWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$out = $this->render(
			$mpdf,
			'<p><input type="submit" name="b" value="Send" /></p>'
		);
		$this->assertLegacyArtifactContract($out, '<input type=submit>');
	}

	/**
	 * An image button is drawn as an artifact.
	 */
	public function testImageButtonWrappedInArtifact()
	{
		$mpdf = $this->makeLegacyMpdf();
		$png = 'data:image/png;base64,'
			. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$out = $this->render(
			$mpdf,
			'<p><input type="image" name="ib" src="' . $png . '" width="20" height="20" /></p>'
		);
		$this->assertLegacyArtifactContract($out, '<input type=image>');
	}

	/**
	 * Strict mode accepts useActiveForms off, as the drawn controls are tagged correctly.
	 */
	public function testStrictModeLegacyFormsDoNotThrow()
	{
		$mpdf = $this->makeLegacyMpdf(['PDFUAauto' => false]);
		$out = $this->render($mpdf, '<p><input type="text" name="x" /></p>');
		$this->assertLegacyArtifactContract($out, 'strict-mode <input type=text>');
	}

	/**
	 * Auto mode leaves useActiveForms off without warning about it.
	 */
	public function testAutoModeRecordsNoUseActiveFormsWarning()
	{
		$mpdf = $this->makeLegacyMpdf();
		$this->render($mpdf, '<p><input type="text" name="x" /></p>');
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			$this->assertStringNotContainsString(
				'useActiveForms',
				$w,
				'No useActiveForms warning should be emitted by the legacy path'
			);
		}
	}

	/**
	 * With useActiveForms on, a text input is a widget annotation that a Form element in the
	 * structure tree refers to.
	 */
	public function testActiveFormsStillProduceTaggedAnnotations()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$mpdf->WriteHTML('<p>Name: <input type="text" name="x" value="J" /></p>');
		$out = $mpdf->Output(null, 'S');

		$this->assertStringContainsString(
			'/Subtype /Widget',
			$out,
			'Active-form path must emit a Widget annotation'
		);
		$this->assertStringContainsString(
			'/S /Form',
			$out,
			'Active-form path must emit a /Form struct kid referencing the Widget'
		);
	}

	/**
	 * With useActiveForms on, a select is a widget annotation that a Form element refers to.
	 */
	public function testActiveFormsSelectStillTagged()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$mpdf->WriteHTML(
			'<p>Pick: <select name="s"><option>Alpha</option><option selected>Beta</option></select></p>'
		);
		$out = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/Subtype /Widget', $out);
		$this->assertStringContainsString('/S /Form', $out);
	}

	/**
	 * Without PDFUA a document with drawn form controls makes no PDF/UA claim.
	 */
	public function testNonPdfuaLegacyFormsNotWrapped()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p><input type="text" name="x" value="J" /></p>');
		$out = $mpdf->Output(null, 'S');

		$this->assertStringNotContainsString('<pdfuaid:part>1</pdfuaid:part>', $out);
	}
}
