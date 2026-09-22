<?php

namespace Mpdf\Ua;

/**
 * The ARIA attributes that relate one element to another.
 *
 * aria-owns and aria-controls become a /Ref to the elements they name. aria-flowto and
 * aria-activedescendant have no static PDF form, so they are reported as a warning.
 *
 * @group pdfua
 */
class AriaRelTest extends PdfUaTestCase
{

	/**
	 * aria-controls naming an element writes a /Ref to it and no internal _aria key.
	 *
	 * @return void
	 */
	public function testAriaControlsEmitsRef()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="panel">Panel content</p>'
			. '<div aria-controls="panel">Controller</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString(
			'/Ref [',
			$output,
			'A resolved aria-controls must emit a /Ref cross-reference on the struct element.'
		);
		$this->assertStringNotContainsString(
			'_aria',
			$output,
			'No internal ARIA marker key may leak into the output dictionaries.'
		);

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringNotContainsString(
			'no PDF/UA-1 representation',
			$warnings,
			'aria-controls maps to /Ref and must not warn about a missing representation.'
		);
	}

	/**
	 * aria-owns naming an element writes a /Ref to it.
	 *
	 * @return void
	 */
	public function testAriaOwnsEmitsRef()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="child">Owned content</p>'
			. '<div aria-owns="child">Owner</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString(
			'/Ref [',
			$output,
			'A resolved aria-owns must emit a /Ref cross-reference on the struct element.'
		);
		$this->assertStringNotContainsString('_aria', $output);
	}

	/**
	 * aria-controls writes its /Ref in strict mode too, without throwing.
	 *
	 * @return void
	 */
	public function testAriaControlsEmitsRefInStrict()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p id="panel">Panel content</p>'
			. '<div aria-controls="panel">Controller</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/Ref [', $output);
		$this->assertStringNotContainsString('_aria', $output);
	}

	/**
	 * aria-flowto is reported as a warning and leaves no internal _aria key in the document.
	 *
	 * @return void
	 */
	public function testAriaFlowtoWarnsAndDropsNothingSilently()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="next">Next region</p>'
			. '<div aria-flowto="next">Region</div>';
		$output = $this->getOutput($mpdf, $html);

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString(
			'no PDF/UA-1 representation',
			$warnings,
			'aria-flowto must be surfaced as a visible warning, not silently dropped.'
		);
		$this->assertStringContainsString('aria-flowto', $warnings);
		$this->assertStringNotContainsString(
			'_aria',
			$output,
			'No internal ARIA marker key may leak into the output dictionaries.'
		);
	}

	/**
	 * aria-activedescendant is only a warning in strict mode, since dropping it breaks no rule of PDF/UA-1.
	 *
	 * @return void
	 */
	public function testAriaActivedescendantWarnsInStrictWithoutThrowing()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p id="opt">Option one</p>'
			. '<div aria-activedescendant="opt">Listbox</div>';
		$output = $this->getOutput($mpdf, $html);

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString('no PDF/UA-1 representation', $warnings);
		$this->assertStringContainsString('aria-activedescendant', $warnings);
		$this->assertStringNotContainsString('_aria', $output);
	}
}
