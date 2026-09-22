<?php

namespace Mpdf\Ua;

/**
 * The accessible name aria-labelledby and aria-describedby take from the text of the element they point at.
 *
 * An empty /Alt replaces the content of the element it sits on for assistive technology
 * (ISO 32000-1 Table 322), so a target with no text must never produce one.
 *
 * @group pdfua
 */
class AriaNameResolutionTest extends PdfUaTestCase
{

	/**
	 * @param string $utf8
	 *
	 * @return string The text as StructureWriter writes /Alt and /E: UTF-16BE behind a byte order mark
	 */
	private function utf16Be($utf8)
	{
		return "\xfe\xff" . mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
	}

	/**
	 * @return string An /Alt holding only a byte order mark, which hides the content it is on
	 */
	private function emptyAltLiteral()
	{
		return '/Alt (' . "\xfe\xff" . ')';
	}

	/**
	 * aria-labelledby pointing at a paragraph gives the element the paragraph's text as its /Alt.
	 *
	 * @return void
	 */
	public function testAriaLabelledbyResolvesToTargetText()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="cap">The caption</p>'
			. '<div aria-labelledby="cap">Region content here</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString(
			$this->utf16Be('The caption'),
			$output,
			'aria-labelledby must resolve to the target element\'s real text as /Alt.'
		);
		$this->assertStringNotContainsString(
			$this->emptyAltLiteral(),
			$output,
			'A resolved aria-labelledby must never leave a BOM-only empty /Alt.'
		);

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringNotContainsString('Unresolved ARIA reference', $warnings);
		$this->assertStringNotContainsString('resolved to empty text', $warnings);
	}

	/**
	 * aria-describedby pointing at a paragraph gives the element the paragraph's text as its /E.
	 *
	 * @return void
	 */
	public function testAriaDescribedbyResolvesToTargetText()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="desc">Long description text</p>'
			. '<p aria-describedby="desc">Body paragraph.</p>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString(
			$this->utf16Be('Long description text'),
			$output,
			'aria-describedby must resolve to the target element\'s real text as /E.'
		);
	}

	/**
	 * In PDFUAauto mode an aria-labelledby naming a missing id warns and writes no empty /Alt.
	 *
	 * @return void
	 */
	public function testUnresolvedTargetWarnsInAutoNeverEmptyAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<div aria-labelledby="missing">Region content here</div>');

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString('Unresolved ARIA reference', $warnings);
		$this->assertStringContainsString('missing', $warnings);
		$this->assertStringNotContainsString(
			$this->emptyAltLiteral(),
			$output,
			'An unresolved aria-labelledby must never emit a BOM-only empty /Alt.'
		);
	}

	/**
	 * In strict mode an aria-labelledby naming a missing id throws.
	 *
	 * @return void
	 */
	public function testUnresolvedTargetThrowsInStrict()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException(\Mpdf\MpdfException::class);
		$this->getOutput($mpdf, '<div aria-labelledby="missing">Region content here</div>');
	}

	/**
	 * In PDFUAauto mode an aria-labelledby pointing at an element with no text warns and writes no empty /Alt.
	 *
	 * @return void
	 */
	public function testEmptyTargetWarnsInAutoNeverEmptyAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<div id="cap"></div><div aria-labelledby="cap">Region content here</div>';
		$output = $this->getOutput($mpdf, $html);

		$warnings = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString('resolved to empty text', $warnings);
		$this->assertStringNotContainsString(
			$this->emptyAltLiteral(),
			$output,
			'An empty-text aria-labelledby target must never emit a BOM-only empty /Alt.'
		);
	}

	/**
	 * In strict mode an aria-labelledby pointing at an element with no text throws.
	 *
	 * @return void
	 */
	public function testEmptyTargetThrowsInStrict()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException(\Mpdf\MpdfException::class);
		$this->getOutput($mpdf, '<div id="cap"></div><div aria-labelledby="cap">Region content here</div>');
	}

	/**
	 * The lines of a target that wraps are joined by a space, not run together.
	 *
	 * @return void
	 */
	public function testMultiLineTargetJoinsTextWithSpaces()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="cap" style="width: 30mm;">Hello world '
			. 'Second line follows here</p>'
			. '<div aria-labelledby="cap">Region</div>';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString(
			$this->utf16Be('Hello world Second'),
			$output,
			'Wrapped target lines must be joined by a space, not concatenated.'
		);
	}
}
