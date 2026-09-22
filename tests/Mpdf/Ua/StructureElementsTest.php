<?php

namespace Mpdf\Ua;

/**
 * The struct element types HTML elements are tagged with, and the attributes carried on them.
 *
 * @group pdfua
 */
class StructureElementsTest extends PdfUaTestCase
{

	/**
	 * h1 is tagged H1.
	 */
	public function testH1ProducesH1StructElement()
	{
		$output = $this->getOutput($this->makeMpdf(), '<h1>Heading</h1>');
		$this->assertStringContainsString('/S /H1', $output);
	}

	/**
	 * h2 is tagged H2; an h1 comes first because a document may not open on a lower heading.
	 */
	public function testH2ProducesH2StructElement()
	{
		$output = $this->getOutput($this->makeMpdf(), '<h1>Title</h1><h2>Heading</h2>');
		$this->assertStringContainsString('/S /H2', $output);
	}

	/**
	 * p is tagged P.
	 */
	public function testParagraphProducesPStructElement()
	{
		$output = $this->getOutput($this->makeMpdf(), '<p>Paragraph</p>');
		$this->assertStringContainsString('/S /P', $output);
	}

	/**
	 * A paragraph's text is marked /P with an MCID in the content stream.
	 */
	public function testParagraphProducesPBdc()
	{
		$output = $this->getOutput($this->makeMpdf(), '<p>Hello</p>');
		$this->assertStringContainsString('/P <</MCID', $output);
		$this->assertStringContainsString('BDC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * blockquote is tagged with the standard type's spelling, BlockQuote.
	 */
	public function testBlockquoteProducesBlockQuoteStructType()
	{
		$output = $this->getOutput($this->makeMpdf(), '<blockquote>Quote</blockquote>');
		$this->assertStringContainsString('/S /BlockQuote', $output);
		$this->assertStringNotContainsString('/S /BLOCKQUOTE', $output);
	}

	/**
	 * A lang attribute on a block sets /Lang on its struct element.
	 */
	public function testLangAttributeProducesLangOnStructElement()
	{
		$output = $this->getOutput($this->makeMpdf(), '<p lang="fr">Bonjour</p>');
		// Matched in UTF-16BE: a bare "fr" is also found in the beginbfrange of a font's CMap
		$this->assertStringContainsString('/Lang', $output);
		$utf16BeFr = "\xfe\xff\x00f\x00r";
		$this->assertStringContainsString($utf16BeFr, $output);
	}

	/**
	 * ul and li are tagged L and LI.
	 */
	public function testUnorderedListProducesLStructElement()
	{
		$output = $this->getOutput($this->makeMpdf(), '<ul><li>Item</li></ul>');
		$this->assertStringContainsString('/S /L', $output);
		$this->assertStringContainsString('/S /LI', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A dl inside a dd wraps its own term and definition in an LI, as the outer list does, since
	 * an LBody must sit in an LI.
	 */
	public function testNestedDefinitionListCreatesImplicitLiPerLevel()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<dl><dt>Outer term</dt><dd>Outer def'
			. '<dl><dt>Inner term</dt><dd>Inner def</dd></dl>'
			. '</dd></dl>'
		);
		$this->assertSame(2, substr_count($output, '/S /LI'), 'each <dl> level must contribute its own implicit LI');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * abbr is tagged Span with its title as the /E expansion.
	 */
	public function testAbbrTitleProducesExpansionAttribute()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p><abbr title="HyperText Markup Language">HTML</abbr></p>'
		);
		$this->assertStringContainsString('/S /Span', $output);
		$this->assertStringContainsString('/E', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A span with its own lang mid-paragraph is tagged Span with that /Lang (Matterhorn 11-001).
	 */
	public function testInlineSpanLangAttributeProducesLangOnStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>Plain English. <span lang="fr">bonjour</span> tail.</p>'
		);
		$this->assertStringContainsString('/S /Span', $output);
		$utf16BeFr = "\xfe\xff\x00f\x00r";
		$this->assertStringContainsString($utf16BeFr, $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A span with aria-label is tagged Span with the label as /Alt.
	 */
	public function testInlineSpanAriaLabelProducesAltOnStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>Status: <span aria-label="warning sign">!</span> see below.</p>'
		);
		$this->assertStringContainsString('/S /Span', $output);
		$this->assertStringContainsString('/Alt', $output);
		$utf16BeAlt = "\xfe\xff\x00w\x00a\x00r\x00n\x00i\x00n\x00g";
		$this->assertStringContainsString($utf16BeAlt, $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * fieldset is tagged Sect, so its content is not left untagged.
	 */
	public function testFieldsetProducesSectStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<fieldset><p>Body content inside fieldset.</p></fieldset>'
		);
		$this->assertStringContainsString('/S /Sect', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * form is tagged Div, the Form type being for each widget.
	 */
	public function testFormContainerProducesDivStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<form><p>Form body content.</p></form>'
		);
		$this->assertStringContainsString('/S /Div', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * scope="rowgroup" becomes /Scope /Row, since PDF has no row group scope.
	 */
	public function testThScopeRowgroupMapsToScopeRow()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<table>'
			. '<tr><th scope="rowgroup">Group A</th><th scope="col">C1</th></tr>'
			. '<tr><td>data</td><td>data</td></tr>'
			. '</table>'
		);
		$this->assertStringContainsString('/S /TH', $output);
		$this->assertStringContainsString('/Scope /Row', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * scope="colgroup" becomes /Scope /Column, not Both.
	 */
	public function testThScopeColgroupMapsToScopeColumn()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<table>'
			. '<tr><th scope="colgroup">G</th><th scope="col">C1</th></tr>'
			. '<tr><td>data</td><td>data</td></tr>'
			. '</table>'
		);
		$this->assertStringContainsString('/Scope /Column', $output);
		$this->assertStringNotContainsString('/Scope /Both', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A div with role="heading" and aria-level="2" is tagged H2.
	 */
	public function testRoleHeadingOverridesTag()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<h1>Title</h1><div role="heading" aria-level="2">Custom Heading</div>'
		);
		$this->assertStringContainsString('/S /H2', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * role="presentation" draws the content as an artifact.
	 */
	public function testRolePresentationProducesArtifact()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<div role="presentation">Decorative</div>'
		);
		$this->assertStringContainsString('BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A floated div is tagged Div like any other: floating says where content goes, not that it
	 * is decorative (Matterhorn 01-001).
	 */
	public function testFloatedDivIsTaggedNotArtifact()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<div style="float:left; width:54%;">Real floated content.</div>'
		);
		$this->assertStringContainsString('/S /Div', $output);
		$this->assertStringContainsString('/Div <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A floated div with role="presentation" is still drawn as an artifact.
	 */
	public function testFloatedDivWithRolePresentationStaysArtifact()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<div style="float:left; width:54%;" role="presentation">Decorative float.</div>'
		);
		$this->assertStringContainsString('BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * aria-hidden="true" on a block draws its content as an artifact.
	 */
	public function testAriaHiddenProducesArtifactBmc()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<div aria-hidden="true"><p>Hidden from AT</p></div>'
		);
		$this->assertStringContainsString('BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A link is tagged Link.
	 */
	public function testLinkProducesLinkStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p><a href="https://example.com">Click here</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * OverWrite() is refused in PDF/UA mode.
	 */
	public function testOverWriteThrowsInPdfuaMode()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf->OverWrite('/tmp/non-existent.pdf', 'foo', 'bar');
	}

	/**
	 * SetProtection() with no permissions gets 'extract' added in auto mode, so bit 10 of the
	 * encryption dictionary's /P is set.
	 */
	public function testEncryptionForcesExtractPermission()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection([], '', 'owner_pass');
		$mpdf->WriteHTML('<p>Encrypted</p>');
		$output = $mpdf->Output(null, 'S');
		preg_match('/\/Filter \/Standard.*?\/P (-?\d+)/s', $output, $m);
		if (!empty($m[1])) {
			$pValue = (int) $m[1];
			// /P may be written negative; bit 10 is 0x200
			$pValue32 = $pValue & 0xFFFFFFFF;
			$this->assertTrue(
				($pValue32 & 0x200) !== 0,
				'Extract permission bit (bit 10) must be set. /P = ' . $pValue
			);
		}
		$this->assertStringContainsString('pdfuaid:part', $output);
	}

	/**
	 * Marked content stays balanced round a paragraph drawn in a layer.
	 */
	public function testBdcEmcBalanceWithOcgLayers()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<p>Before layer</p>');
		$mpdf->BeginLayer(1);
		$mpdf->WriteHTML('<p>Inside layer</p>');
		$mpdf->EndLayer();
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /P', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Image() given alt text tags the image Figure.
	 */
	public function testImageMethodWithAlt()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$imgFile = __DIR__ . '/../../data/img/bayeux2.jpg';
		if (!file_exists($imgFile)) {
			$this->markTestSkipped('Test image not available');
		}
		$mpdf->Image($imgFile, 10, 10, 50, 50, '', '', true, true, false, true, true, 'A company logo');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /Figure', $output);
		$this->assertStringContainsString('/Figure <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Image() given empty alt text draws the image as an artifact.
	 */
	public function testImageMethodWithEmptyAlt()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$imgFile = __DIR__ . '/../../data/img/bayeux2.jpg';
		if (!file_exists($imgFile)) {
			$this->markTestSkipped('Test image not available');
		}
		$mpdf->Image($imgFile, 10, 10, 50, 50, '', '', true, true, false, true, true, '');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/Artifact BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Image() given no alt text draws the image as an artifact and warns in auto mode.
	 */
	public function testImageMethodWithoutAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->AddPage();
		$imgFile = __DIR__ . '/../../data/img/bayeux2.jpg';
		if (!file_exists($imgFile)) {
			$this->markTestSkipped('Test image not available');
		}
		$mpdf->Image($imgFile, 10, 10, 50, 50);
		$output = $mpdf->Output(null, 'S');
		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty($warnings, 'Warning should be added when $alt is null');
		$this->assertStringContainsString('/Artifact BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * AutosizeText() tags its text as a Span.
	 */
	public function testAutosizeTextProducesSpanStructElement()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$mpdf->AutosizeText('Hello', 50, 'DejaVuSans', '');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /Span', $output);
		$this->assertStringContainsString('/Span <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * abbr is tagged Span with its title as the /E expansion.
	 */
	public function testAbbrProducesSpanWithExpansionText()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p><abbr title="World Wide Web Consortium">W3C</abbr></p>'
		);
		$this->assertStringContainsString('/S /Span', $output);
		$this->assertStringContainsString('/E', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * ruby, rb and rt are tagged with the standard Ruby, RB and RT types rather than as Spans.
	 */
	public function testRubyAnnotationProducesRubyStructElements()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p><ruby><rb>kanji</rb><rt>furigana</rt></ruby></p>'
		);
		$this->assertStringContainsString('/S /Ruby', $output);
		$this->assertStringContainsString('/S /RB', $output);
		$this->assertStringContainsString('/S /RT', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * rp fallback parentheses are tagged RP inside the Ruby.
	 */
	public function testRubyParenthesisProducesRpStructElement()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p><ruby><rb>kanji</rb><rp>(</rp><rt>furigana</rt><rp>)</rp></ruby></p>'
		);
		$this->assertStringContainsString('/S /Ruby', $output);
		$this->assertStringContainsString('/S /RP', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A text watermark is marked as a Background artifact in the content stream.
	 */
	public function testWatermarkTextIsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML('<p>Content</p>');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/Artifact <</Type /Background>> BDC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Asserts every BDC and BMC in the output has an EMC.
	 *
	 * @param string $output Raw PDF bytes
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
