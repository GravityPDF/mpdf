<?php

namespace Mpdf\Ua;

/**
 * Tagging of sticky notes, form widgets, barcodes, watermarks and textcircle.
 *
 * @group pdfua
 */
class AnnotationsAndMiscTest extends PdfUaTestCase
{

	/**
	 * A sticky note sits in an Annot struct element (ISO 14289-1 §7.18.1); Note is for footnotes
	 * and would need an /ID.
	 */
	public function testAnnotationProducesAnnotStructElement()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Text<annotation content="Review this section" title="Editor"/></p>'
		);
		$this->assertStringContainsString('/S /Annot', $output);
	}

	/**
	 * A sticky note carries the /StructParent that finds its struct element in the ParentTree.
	 */
	public function testAnnotationHasStructParentKey()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Text<annotation content="A note" title="Author"/></p>'
		);
		$this->assertStringContainsString('/StructParent', $output);
	}

	/**
	 * A sticky note is flagged /F 28: Print, NoZoom and NoRotate.
	 */
	public function testAnnotationHasFlagF28()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Text<annotation content="Flag test" title="Tester"/></p>'
		);
		$this->assertStringContainsString('/F 28', $output);
	}

	/**
	 * A sticky note's Annot struct element points at the annotation with an OBJR.
	 */
	public function testAnnotationAnnotStructElementHasObjr()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Text<annotation content="Objr test" title="Reviewer"/></p>'
		);
		$this->assertStringContainsString('/Type /OBJR', $output);
	}

	/**
	 * A file attachment annotation is tagged Annot like a sticky note, since only hidden, Popup
	 * and off-page annotations may stay out of the structure tree (ISO 14289-1 §7.18.1).
	 */
	public function testFileAttachmentAnnotationProducesAnnotStructElement()
	{
		$mpdf = $this->makeMpdf(['allowAnnotationFiles' => true]);
		$mpdf->WriteHTML('<p>See attached</p>');
		$mpdf->Annotation(
			'Attached file',
			0,
			0,
			'Paperclip',
			'Reviewer',
			'',
			1,
			false,
			'',
			__DIR__ . '/../../data/img/issue1609.png'
		);
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /Annot', $output);
		$this->assertStringContainsString('/StructParent', $output);
		$this->assertStringContainsString('/Type /OBJR', $output);
	}

	/**
	 * A barcode carries data, so it is tagged Figure rather than drawn as an artifact.
	 */
	public function testBarcodeProducesFigureStructElement()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<barcode code="9780954224608" type="EAN13"/>'
		);
		$this->assertStringContainsString('/S /Figure', $output);
	}

	/**
	 * Without an aria-label, a barcode's /Alt is "Barcode: <code>".
	 */
	public function testBarcodeAltTextContainsCode()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<barcode code="9780954224608" type="EAN13"/>'
		);
		$this->assertStringContainsString('/Alt', $output);
		// "Barcode" in UTF-16BE
		$this->assertStringContainsString("\x00B\x00a\x00r\x00c\x00o\x00d\x00e", $output);
	}

	/**
	 * A barcode is drawn inside a balanced Figure BDC/EMC.
	 */
	public function testBarcodeBdcEmcWrapsRender()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<barcode code="9780954224608" type="EAN13"/>'
		);
		$this->assertStringContainsString('/Figure', $output);
		$this->assertStringContainsString('BDC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A barcode in columns is still tagged Figure: the column buffer replays its BDC/EMC intact.
	 */
	public function testBarcodeInsideColumnsProducesFigure()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<columns column-count="2" column-gap="5" />'
			. '<p>Left column text.</p>'
			. '<barcode code="9780954224608" type="EAN13"/>'
			. '<p>More column text to keep the flow going.</p>'
			. '<columns column-count="1" />'
		);
		$this->assertStringContainsString('/S /Figure', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A text watermark is a Background artifact.
	 */
	public function testWatermarkTextIsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = true;
		$output = $this->getOutput($mpdf, '<p>Body text</p>');
		$this->assertStringContainsString('/Artifact', $output);
		$this->assertStringContainsString('/Type /Background', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An image watermark drawn in front of the page content is a Background artifact.
	 */
	public function testWatermarkImageFrontIsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetWatermarkImage(__DIR__ . '/../../data/img/issue1609.png');
		$mpdf->showWatermarkImage = true;
		$mpdf->watermarkImgBehind = false;
		$output = $this->getOutput($mpdf, '<p>Body text</p>');
		$this->assertStringContainsString('/Artifact', $output);
		$this->assertStringContainsString('/Type /Background', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An image watermark drawn behind the page content, which is spliced in at the background
	 * marker, is a Background artifact.
	 */
	public function testWatermarkImageBehindIsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetWatermarkImage(__DIR__ . '/../../data/img/issue1609.png');
		$mpdf->showWatermarkImage = true;
		$mpdf->watermarkImgBehind = true;
		$output = $this->getOutput($mpdf, '<p>Body text</p>');
		$this->assertStringContainsString('/Artifact', $output);
		$this->assertStringContainsString('/Type /Background', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An active text input is tagged Form.
	 */
	public function testTextInputProducesFormStructElement()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post"><input type="text" name="fname" title="First Name"/></form>'
		);
		$this->assertStringContainsString('/S /Form', $output);
	}

	/**
	 * A text input's widget carries the /StructParent that finds its Form element in the ParentTree.
	 */
	public function testTextInputHasStructParentKey()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post"><input type="text" name="fname" title="First Name"/></form>'
		);
		$this->assertStringContainsString('/StructParent', $output);
	}

	/**
	 * A text input's Form struct element points at its widget with an OBJR.
	 */
	public function testTextInputFormStructElementHasObjr()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post"><input type="text" name="fname" title="First Name"/></form>'
		);
		$this->assertStringContainsString('/Type /OBJR', $output);
	}

	/**
	 * Each widget has a Form struct element of its own (ISO 14289-1 §7.18.4).
	 */
	public function testMultipleWidgetsProduceMultipleFormStructElements()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post">'
			. '<input type="text" name="first" title="First Name"/>'
			. '<input type="text" name="last" title="Last Name"/>'
			. '</form>'
		);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($output, '/S /Form'),
			'Each widget annotation must have its own /S /Form struct element'
		);
	}

	/**
	 * Each radio button in a group has a Form struct element of its own.
	 *
	 * Their appearances are drawn with paths, since ZapfDingbats is a core font and cannot be embedded.
	 */
	public function testRadioGroupKidsProduceFormStructElements()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post">'
			. '<input type="radio" name="colour" value="red" title="Red"/>'
			. '<input type="radio" name="colour" value="blue" title="Blue"/>'
			. '</form>'
		);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($output, '/S /Form'),
			'Each radio kid widget must have its own /S /Form struct element'
		);
	}

	/**
	 * Text drawn round a circle is real text and is tagged Span.
	 */
	public function testTextCircleProducesSpanStructElement()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<textcircle top-text="Top Label" r="20" style="font-size: 12pt"/>'
		);
		$this->assertStringContainsString('/S /Span', $output);
	}

	/**
	 * A textcircle's Span carries /ActualText, the top text, divider and bottom text read in order.
	 */
	public function testTextCircleHasActualText()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<textcircle top-text="Hello" bottom-text="World" divider=" | " r="20" style="font-size: 12pt"/>'
		);
		$this->assertStringContainsString('/ActualText', $output);
	}

	/**
	 * A field's widget carries its title as /TU, the name assistive technology announces in
	 * place of the field name /T.
	 *
	 * @group pdfua
	 */
	public function testWidgetAnnotationHasTuEntry()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<form method="post"><input type="text" name="email" title="Email address"/></form>'
		);
		$this->assertStringContainsString('/TU', $output);
		$this->assertStringContainsString(
			"/TU (\xfe\xff\x00E\x00m\x00a\x00i\x00l",
			$output,
			'/TU must be followed by a UTF-16BE string starting with BOM and "Email"'
		);
	}

	/**
	 * A sticky note's /StructParent key leads through the ParentTree to an Annot struct element.
	 *
	 * @group pdfua
	 */
	public function testAnnotationStructParentRoundTrip()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Text<annotation content="Note text" title="Author"/></p>'
		);

		$found = preg_match('/\/StructParent (\d+)/', $output, $spMatch);
		$this->assertSame(1, $found, '/StructParent must appear on the annotation dict');
		$spIndex = (int) $spMatch[1];

		// Pages map to an array of refs and annotations to a single ref, so /Nums holds nested
		// brackets and only `]>>` closes it
		$numsFound = preg_match('/\/Nums \[(.*?)\]\s*>>/s', $output, $numsMatch);
		$this->assertSame(1, $numsFound, '/Nums array must exist in the ParentTree');
		$numsContent = $numsMatch[1];

		// Anchored on whitespace so a ref inside a page's array cannot match
		$entryPattern = '/(^|\s)' . $spIndex . '\s+(\d+)\s+0\s+R\b/m';
		$entryFound = preg_match($entryPattern, $numsContent, $entryMatch);
		$this->assertSame(
			1,
			$entryFound,
			'ParentTree must have a single-ref entry at index ' . $spIndex . ' for the annotation'
		);
		$structElemObjNum = (int) $entryMatch[2];

		$objPattern = '/' . $structElemObjNum . '\s+0\s+obj\s*<<([^>]+(?:>[^>]+)*?)>>/';
		$objFound = preg_match($objPattern, $output, $objMatch);
		$this->assertSame(
			1,
			$objFound,
			'Struct element object ' . $structElemObjNum . ' must be present in the PDF output'
		);
		$this->assertStringContainsString(
			'/S /Annot',
			$objMatch[0],
			'The struct element referenced by ParentTree[' . $spIndex . '] must have /S /Annot'
		);
	}

	/**
	 * Asserts every BDC and BMC in the output has an EMC.
	 *
	 * @param string $output Raw PDF bytes
	 * @return void
	 */
	private function assertBdcEmcBalanced($output)
	{
		$bdcCount = preg_match_all('/\bBDC\b/', $output);
		$bmcCount = preg_match_all('/\bBMC\b/', $output);
		$emcCount = preg_match_all('/\bEMC\b/', $output);
		$this->assertEquals(
			$bdcCount + $bmcCount,
			$emcCount,
			sprintf('BDC(%d)+BMC(%d) must equal EMC(%d)', $bdcCount, $bmcCount, $emcCount)
		);
	}
}
