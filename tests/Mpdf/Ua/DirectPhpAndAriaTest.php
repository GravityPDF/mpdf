<?php

namespace Mpdf\Ua;

/**
 * Tagging of content drawn through the PHP API (AutosizeText, layers, Link), ARIA naming and lang
 * attributes, and the permission and XMP rules that apply when a PDF/UA document is encrypted.
 *
 * @group pdfua
 */
class DirectPhpAndAriaTest extends PdfUaTestCase
{

	/**
	 * AutosizeText() tags its text as a Span.
	 */
	public function testAutosizeTextProducesSpanStruct()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$mpdf->AutosizeText('Hello', 100, 'DejaVuSansCondensed', '');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /Span', $output);
	}

	/**
	 * The Span AutosizeText() opens in the content stream is closed again.
	 */
	public function testAutosizeTextSpanContainsRenderedText()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$mpdf->AutosizeText('Hello', 100, 'DejaVuSansCondensed', '');
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/Span <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Marked content stays balanced when a layer's own BDC/EMC is interleaved with it.
	 */
	public function testBdcEmcBalanceWithOcgLayers()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$mpdf->BeginLayer('Background');
		$mpdf->WriteHTML('<p>Layer content</p>');
		$mpdf->EndLayer();
		$mpdf->WriteHTML('<p>Normal content</p>');
		$output = $mpdf->Output(null, 'S');
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A paragraph inside a layer is still tagged P.
	 */
	public function testLayerContainingParagraphPreservesStructure()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddPage();
		$mpdf->BeginLayer('Foreground');
		$mpdf->WriteHTML('<p>Paragraph in layer</p>');
		$mpdf->EndLayer();
		$output = $mpdf->Output(null, 'S');
		$this->assertStringContainsString('/S /P', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An element with aria-hidden="true" is drawn as an artifact, with no struct element.
	 */
	public function testAriaHiddenElementBecomesArtifact()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p aria-hidden="true">Hidden text</p>');
		$this->assertStringContainsString('/Artifact BMC', $output);
		$this->assertStringNotContainsString('/P <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * aria-labelledby naming an id in the document resolves without a warning.
	 */
	public function testAriaLabelledbyResolvesToAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="caption">Caption text</p>'
			. '<img src="' . __DIR__ . '/../../data/img/issue1609.png" '
			. 'alt="placeholder" aria-labelledby="caption">';
		$this->getOutput($mpdf, $html);
		$warnings = $mpdf->getPdfUaWarnings();
		$combined = implode(' ', $warnings);
		$this->assertStringNotContainsString('Unresolved ARIA reference', $combined);
	}

	/**
	 * aria-describedby naming an id in the document resolves without a warning.
	 */
	public function testAriaDescribedbyResolvesToE()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p id="desc">Description text</p>'
			. '<p aria-describedby="desc">Described paragraph</p>';
		$this->getOutput($mpdf, $html);
		$warnings = $mpdf->getPdfUaWarnings();
		$combined = implode(' ', $warnings);
		$this->assertStringNotContainsString('Unresolved ARIA reference', $combined);
	}

	/**
	 * aria-labelledby naming an id the document lacks is reported, with the id.
	 */
	public function testUnresolvedAriaReferenceWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p aria-labelledby="does-not-exist">Paragraph</p>';
		$this->getOutput($mpdf, $html);
		$warnings = $mpdf->getPdfUaWarnings();
		$combined = implode(' ', $warnings);
		$this->assertStringContainsString('Unresolved ARIA reference', $combined);
		$this->assertStringContainsString('does-not-exist', $combined);
	}

	/**
	 * An image with no alt but an aria-labelledby is a Figure whose /Alt is the referenced text,
	 * and strict mode does not throw for it.
	 */
	public function testImageNoAltWithAriaLabelledbyResolvesToFigureAlt()
	{
		$mpdf = $this->makeMpdf();
		$html = '<p id="figcap">Q3 revenue chart</p>'
			. '<img src="' . __DIR__ . '/../../data/img/issue1609.png" '
			. 'aria-labelledby="figcap">';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /Figure', $output);
		$utf16BeAlt = "\xfe\xff" . mb_convert_encoding('Q3 revenue chart', 'UTF-16BE', 'UTF-8');
		$this->assertStringContainsString($utf16BeAlt, $output);
		$combined = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringNotContainsString('Unresolved ARIA reference', $combined);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An image with no alt but an aria-label is a Figure named by that label.
	 */
	public function testImageNoAltWithAriaLabelResolvesToFigureAlt()
	{
		$mpdf = $this->makeMpdf();
		$html = '<img src="' . __DIR__ . '/../../data/img/issue1609.png" '
			. 'aria-label="Sales dashboard">';
		$output = $this->getOutput($mpdf, $html);

		$this->assertStringContainsString('/S /Figure', $output);
		$utf16BeAlt = "\xfe\xff" . mb_convert_encoding('Sales dashboard', 'UTF-16BE', 'UTF-8');
		$this->assertStringContainsString($utf16BeAlt, $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * An image with no alt and nothing else to name it still throws in strict mode (Matterhorn 13-004).
	 */
	public function testImageNoAltNoNameStillThrowsInStrict()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException(\Mpdf\MpdfException::class);
		$this->getOutput(
			$mpdf,
			'<img src="' . __DIR__ . '/../../data/img/issue1609.png">'
		);
	}

	/**
	 * An image with no alt that role="presentation", role="none" or aria-hidden="true" marks as
	 * decorative is an artifact, and strict mode does not throw for it.
	 *
	 * @dataProvider decorativeImageAttributeProvider
	 *
	 * @param string $attribute
	 */
	public function testImageMarkedDecorativeWithoutAltIsArtifact($attribute)
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>Text</p><img src="' . __DIR__ . '/../../data/img/issue1609.png" ' . $attribute . '>'
		);

		$this->assertStringNotContainsString('/S /Figure', $output);
		$this->assertStringContainsString('/Artifact BMC', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * The attributes that mark an image decorative.
	 *
	 * @return array<string, string[]>
	 */
	public function decorativeImageAttributeProvider()
	{
		return [
			'presentation' => ['role="presentation"'],
			'none'         => ['role="none"'],
			'aria-hidden'  => ['aria-hidden="true"'],
		];
	}

	/**
	 * A lang attribute on a block sets /Lang on its struct element.
	 */
	public function testLangAttributePropagatesToStructElement()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p lang="fr">Bonjour le monde</p>');
		// The catalog's /Lang is en-GB, so "fr" can only be the paragraph's own /Lang
		$utf16BeFr = "\xfe\xff\x00f\x00r";
		$this->assertStringContainsString($utf16BeFr, $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * SetProtection() without 'extract' throws in strict mode, since assistive technology needs
	 * that permission (Matterhorn 07-001).
	 */
	public function testProtectionWithoutAccessibilityBitThrowsInStrict()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/extract.*PDF\/UA|PDF\/UA.*extract|Matterhorn 07-001/');
		$mpdf->SetProtection([], '', 'owner');
	}

	/**
	 * SetProtection() without 'extract' adds it back in auto mode and warns.
	 */
	public function testProtectionWithoutAccessibilityBitWarnsInAuto()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection([], '', 'owner');
		$output = $mpdf->Output(null, 'S');
		$warnings = $mpdf->getPdfUaWarnings();
		$combined = implode(' ', $warnings);
		$this->assertStringContainsString('extract', $combined);
		$this->assertNotEmpty($output);
	}

	/**
	 * In an encrypted document the XMP stream goes through the Identity crypt filter, so the
	 * pdfuaid metadata is readable without the key (ISO 32000-1 §14.3.2).
	 */
	public function testEncryptedXmpStreamUsesIdentityCryptFilter()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection(['extract'], '', 'owner');
		$output = $this->getOutput($mpdf, '<p>Encrypted PDF/UA document</p>');
		$this->assertStringContainsString('/Filter[/Crypt]', $output);
		$this->assertStringContainsString('/Name/Identity', $output);
		$this->assertStringContainsString('pdfuaid:part', $output);
	}

	/**
	 * A link made with Mpdf::Link(), outside any a tag, gets its own Link struct element joined to
	 * the annotation by an OBJR and /StructParent.
	 */
	public function testDirectLinkApiProducesTaggedLinkStruct()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Doc</h1><p>Body paragraph text.</p>');
		$mpdf->Link(20, 40, 60, 8, 'https://example.com/direct-api');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringContainsString('/S /Link', $output);
		$this->assertStringContainsString('/Type /OBJR', $output);
		$this->assertStringContainsString('/StructParent', $output);
	}

	/**
	 * An a tag link and a Mpdf::Link() link on one page give one Link struct element each.
	 */
	public function testDirectLinkAndAnchorDoNotDoubleTag()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Doc</h1><p>Visit <a href="https://anchor.test">the anchor</a> now.</p>');
		$mpdf->Link(20, 60, 60, 8, 'https://direct.test');
		$output = $mpdf->Output(null, 'S');

		$this->assertSame(2, substr_count($output, '/S /Link'));
	}

	/**
	 * A link in a running header is dropped with a warning, keeping its text as artifact content.
	 *
	 * Artifacts are outside the structure tree (ISO 32000-1 §14.8.2.2) but every Link annotation
	 * must sit in a Link struct element (ISO 14289-1 §7.18.5), so the annotation cannot be kept; nor
	 * may its OBJR be hung off the Document root.
	 */
	public function testHeaderLinkIsArtifactNotOnDocumentRoot()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<div>Header with <a href="https://example.com">a link</a></div>');
		$output = $this->getOutput($mpdf, '<h1>Doc</h1><p>Body paragraph with no links.</p>');

		$this->assertStringNotContainsString('/Subtype /Link', $output);
		$this->assertStringNotContainsString('/Type /OBJR', $output);
		$this->assertStringNotContainsString('/S /Link', $output);
		$combined = implode(' ', $mpdf->getPdfUaWarnings());
		$this->assertStringContainsString('running header/footer', $combined);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * A body link is tagged as usual when a header link in the same document is dropped.
	 */
	public function testBodyLinkSelfTagsWhileHeaderLinkIsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<div>Header <a href="https://header.test">header link</a></div>');
		$output = $this->getOutput(
			$mpdf,
			'<h1>Doc</h1><p>Body with <a href="https://body.test">a body link</a> here.</p>'
		);

		$this->assertSame(1, substr_count($output, '/Subtype /Link'));
		$this->assertSame(1, substr_count($output, '/S /Link'));
		$this->assertSame(1, substr_count($output, '/Type /OBJR'));
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Asserts every tagging BDC/BMC in the output has an EMC.
	 *
	 * Only artifact and MCID operators count as openings; a layer's /OC BDC is not tagging.
	 *
	 * @param string $output Raw PDF bytes
	 */
	private function assertBdcEmcBalanced($output)
	{
		preg_match_all('|/Artifact <</Type /Pagination[^>]*>> BDC|', $output, $paginationBdc);
		preg_match_all('|/Artifact BMC\b|', $output, $artifactBmc);
		preg_match_all('|/\w+ <</MCID \d+>> BDC\b|', $output, $structBdc);
		preg_match_all('/\bEMC\b/', $output, $emcMatches);

		$opens  = count($paginationBdc[0]) + count($artifactBmc[0]) + count($structBdc[0]);
		$closes = count($emcMatches[0]);

		$this->assertSame(
			$opens,
			$closes,
			sprintf(
				'BDC+BMC count (%d) must equal EMC count (%d). Pagination BDC: %d, Artifact BMC: %d, Struct BDC: %d, EMC: %d',
				$opens,
				$closes,
				count($paginationBdc[0]),
				count($artifactBmc[0]),
				count($structBdc[0]),
				$closes
			)
		);
	}
}
