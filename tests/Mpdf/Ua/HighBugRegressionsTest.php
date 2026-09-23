<?php

namespace Mpdf\Ua;

/**
 * Table header ids and the /Headers that point at them, role="doc-title", the decoding of text
 * strings copied from an imported tagged PDF, and links with no href or no content.
 *
 * @group pdfua
 */
class HighBugRegressionsTest extends PdfUaTestCase
{

	/**
	 * A TH's /ID is the same byte string a TD's /Headers uses for it, so the two can be matched
	 * (Matterhorn 09-004/09-005).
	 */
	public function testThIdBytesEqualHeadersReferenceBytes()
	{
		$html = '<table>'
			. '<tr><th id="rate">Rate</th><th id="qty">Qty</th></tr>'
			. '<tr><td headers="rate">5%</td><td headers="qty">12</td></tr>'
			. '</table>';
		$output = $this->getOutput($this->makeMpdf(), $html);

		// mPDF uppercases id="...", so the id is lowercased again to match the headers="..." token
		$this->assertMatchesRegularExpression('@/ID\s*\(rate\)@', $output);
		$this->assertMatchesRegularExpression('@/ID\s*\(qty\)@', $output);

		$this->assertMatchesRegularExpression('@/Headers\s*\[\s*\(rate\)\s*\]@', $output);
		$this->assertMatchesRegularExpression('@/Headers\s*\[\s*\(qty\)\s*\]@', $output);

		// A UTF-16BE /ID could never equal the string in /Headers
		$this->assertStringNotContainsString("\xFE\xFFr\x00a\x00t\x00e", $output);
	}

	/**
	 * An id with characters a PDF name cannot hold is #-escaped the same way in /ID and /Headers.
	 */
	public function testHeaderIdWithIllegalCharsSanitisedConsistently()
	{
		$html = '<table>'
			. '<tr><th id="col(1)">A</th></tr>'
			. '<tr><td headers="col(1)">x</td></tr>'
			. '</table>';
		$output = $this->getOutput($this->makeMpdf(), $html);

		// ( is #28 and ) is #29
		$this->assertStringContainsString('/ID (col#281#29)', $output);
		$this->assertStringContainsString('/Headers [(col#281#29)]', $output);
	}

	/**
	 * Header cells without an id get generated ids that do not repeat across two tables.
	 */
	public function testMultipleTablesProduceUniqueSyntheticThIds()
	{
		$html = ''
			. '<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>'
			. '<table><tr><th>C</th><th>D</th></tr><tr><td>3</td><td>4</td></tr></table>';
		$output = $this->getOutput($this->makeMpdf(), $html);

		preg_match_all('#/ID\s*\(([^)]+)\)#', $output, $matches);
		$ids = $matches[1];
		$this->assertNotEmpty($ids, 'expected at least one /ID emitted');
		$this->assertCount(
			count(array_unique($ids)),
			$ids,
			'synthesised TH /ID values must be unique across tables: ' . implode(',', $ids)
		);
	}

	/**
	 * role="doc-title" is tagged H1, since Title is not a standard structure type.
	 */
	public function testRoleDocTitleDoesNotCrash()
	{
		$html = '<div role="doc-title">My Document Title</div><p>Body.</p>';
		$output = $this->getOutput($this->makeMpdf(), $html);

		$this->assertStringContainsString('/S /H1', $output);

		$this->assertStringNotContainsString('/S /Title', $output);
	}

	/**
	 * An imported UTF-16BE text string is decoded to UTF-8, so it is not encoded a second time on
	 * the way out.
	 */
	public function testFpdiMergerDecodesUtf16BeAltText()
	{
		$decoded = $this->invokeDecode($this->makeUtf16BeHexString('Café résumé'));
		$this->assertSame('Café résumé', $decoded);
	}

	/**
	 * An imported UTF-8 text string loses its byte order mark.
	 */
	public function testFpdiMergerStripsUtf8Bom()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString("\xEF\xBB\xBFhello"));
		$this->assertSame('hello', $decoded);
	}

	/**
	 * An imported ASCII string with no byte order mark, such as a language tag, is kept as it is.
	 */
	public function testFpdiMergerPassesAsciiLangThrough()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString('en-GB'));
		$this->assertSame('en-GB', $decoded);
	}

	/**
	 * An imported UTF-16LE string with a byte order mark, which some older producers write, is decoded.
	 */
	public function testFpdiMergerDecodesUtf16Le()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString("\xFF\xFEA\x00B\x00"));
		$this->assertSame('AB', $decoded);
	}

	/**
	 * A link with no content is removed from the structure tree in auto mode (Matterhorn 02-003).
	 */
	public function testEmptyAnchorPrunedInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p>Before <a href="https://example.com"></a> after.</p>');

		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A link with an href but no content throws in strict mode.
	 */
	public function testEmptyAnchorThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException('\Mpdf\MpdfException');
		$this->expectExceptionMessageMatches('/no accessible content/');
		$this->getOutput($mpdf, '<p>Before <a href="https://example.com"></a> after.</p>');
	}

	/**
	 * A named anchor with no href is a destination, not a link, and opens no Link struct element.
	 */
	public function testNamedAnchorWithoutHrefDoesNotOpenLinkInAuto()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p><a name="section-2">Section 2</a></p>');

		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A named anchor with no href does not throw in strict mode.
	 */
	public function testNamedAnchorWithoutHrefDoesNotThrowInStrict()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p><a name="section-2">Section 2</a></p>');

		$this->assertNotEmpty($output);
		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A named anchor with an empty href, as templates often leave, is treated as a destination and
	 * does not throw in strict mode.
	 */
	public function testNamedAnchorWithEmptyHrefDoesNotThrowInStrict()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput($mpdf, '<p><a name="section-2" href="">Section 2</a></p>');

		$this->assertNotEmpty($output);
		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * An href of only whitespace is not a hyperlink in HTML, so it opens no Link struct element.
	 */
	public function testWhitespaceHrefDoesNotOpenLink()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p><a href="   ">Section</a></p>');

		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A named anchor with a lang is tagged Span, which carries the /Lang (Matterhorn 11-001).
	 */
	public function testNamedAnchorWithLangOpensSpan()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p><a name="section-2" lang="fr">Section 2</a></p>');

		$this->assertStringNotContainsString('/S /Link', $output);
		$this->assertStringContainsString('/S /Span', $output);
		$this->assertStringContainsString("/Lang (\xFE\xFF\x00f\x00r)", $output);
	}

	/**
	 * The Span a named anchor opens for its lang is closed with the anchor, leaving the next
	 * paragraph a sibling of the one it was in.
	 *
	 * veraPDF does not object to a P inside a P, so this reads the structure tree directly.
	 */
	public function testNonHyperlinkAnchorSpanDoesNotLeakIntoFollowingBlocks()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1><p>x <a name="a" lang="fr">y</a> z</p><p>second</p>');

		$topLevelParagraphs = 0;
		foreach ($mpdf->getPdfUaStructureTree()->getRoot()->getChildren() as $child) {
			if ($child->getType() === 'P') {
				$topLevelParagraphs++;
			}
		}

		$this->assertSame(
			2,
			$topLevelParagraphs,
			'both <p> elements must be siblings under Document; a leaked anchor Span nests the second inside the first'
		);
	}

	/**
	 * A link with an href but no content throws in strict mode with the href in the message.
	 */
	public function testEmptyHyperlinkBodyThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();
		$this->expectException('\Mpdf\MpdfException');
		$this->expectExceptionMessageMatches('@example\.com@');
		$this->getOutput($mpdf, '<p>Before <a href="https://example.com"></a> after.</p>');
	}

	/**
	 * A link with an href but no content leaves no Link struct element in auto mode.
	 */
	public function testEmptyHyperlinkBodyPrunedInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p>Before <a href="https://example.com"></a> after.</p>');

		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A link around only a decorative image is either given an /Alt of "Link to {href}" in auto
	 * mode, or removed (Matterhorn 28-002).
	 */
	public function testImageOnlyLinkSynthesisesAltInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="https://example.com/foo"><img src="' . $png . '" alt="" width="20" height="20"></a></p>'
		);

		$linkPresent = strpos($output, '/S /Link') !== false;
		if ($linkPresent) {
			// "Link to" in UTF-16BE after its byte order mark
			$expectedPrefix = "\xFE\xFF\x00L\x00i\x00n\x00k\x00 \x00t\x00o";
			$this->assertStringContainsString(
				$expectedPrefix,
				$output,
				'PDFUAauto must synthesise /Alt on a Link wrapping only decorative content'
			);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * A link around only a decorative image does not throw in strict mode: the link annotation
	 * over the image is its content, and carries its own /Contents.
	 */
	public function testImageOnlyLinkDoesNotThrowInStrictMode()
	{
		$mpdf = $this->makeMpdf();
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="https://example.com/foo"><img src="' . $png . '" alt="" width="20" height="20"></a></p>'
		);
		$this->assertNotEmpty($output);
	}

	/**
	 * PDFDocEncoding 0xB7 decodes to U+00B7, the middle dot (ISO 32000-1 Annex D).
	 */
	public function testFpdiMergerDecodesPdfDocEncodingHighBytes()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString("\xB7"));
		$this->assertSame("\xC2\xB7", $decoded, 'PDFDocEncoding 0xB7 → U+00B7 (middle dot)');
	}

	/**
	 * PDFDocEncoding 0x81 decodes to U+2020, the dagger, where it parts from ISO-8859-1.
	 */
	public function testFpdiMergerDecodesPdfDocEncodingSpecialBytes()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString("\x81"));
		$this->assertSame("\xE2\x80\xA0", $decoded, 'PDFDocEncoding 0x81 → U+2020 (dagger)');
	}

	/**
	 * PDFDocEncoding 0x7F, which is undefined, decodes to U+FFFD.
	 */
	public function testFpdiMergerHandlesUndefinedPdfDocByte()
	{
		$decoded = $this->invokeDecode($this->makeRawHexString("\x7F"));
		$this->assertSame("\xEF\xBF\xBD", $decoded, 'undefined PDFDocEncoding byte → U+FFFD');
	}

	/**
	 * An id that #-escapes to more than a PDF name's 127 bytes is cut to fit, and two different
	 * long ids stay different (ISO 32000-1 §7.3.5).
	 */
	public function testSanitiseIdHandlesOverlongInput()
	{
		$a = str_repeat("\xE2\x98\x85", 100);
		$b = str_repeat("\xE2\x98\x86", 100);

		$sa = \Mpdf\Ua\StructureElement::sanitiseIdForPdf($a);
		$sb = \Mpdf\Ua\StructureElement::sanitiseIdForPdf($b);

		$this->assertLessThanOrEqual(127, strlen($sa), 'sanitised id must be ≤ 127 bytes');
		$this->assertLessThanOrEqual(127, strlen($sb), 'sanitised id must be ≤ 127 bytes');
		$this->assertNotSame($sa, $sb, 'distinct long inputs must produce distinct sanitised ids');
	}

	/**
	 * @param string $utf8
	 * @return \setasign\Fpdi\PdfParser\Type\PdfHexString The text as UTF-16BE with a byte order mark
	 */
	private function makeUtf16BeHexString($utf8)
	{
		$utf16 = "\xFE\xFF" . mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
		return $this->makeRawHexString($utf16);
	}

	/**
	 * @param string $raw
	 * @return \setasign\Fpdi\PdfParser\Type\PdfHexString The bytes as a hex string
	 */
	private function makeRawHexString($raw)
	{
		$node = new \setasign\Fpdi\PdfParser\Type\PdfHexString();
		$node->value = bin2hex($raw);
		return $node;
	}

	/**
	 * Calls the private FpdiStructMerger::decodeImportedTextString().
	 *
	 * @param \setasign\Fpdi\PdfParser\Type\PdfHexString $node
	 * @return string
	 */
	private function invokeDecode($node)
	{
		$mpdf   = $this->makeMpdf();
		$merger = $mpdf->getPdfUaFpdiStructMerger();
		$rm     = new \ReflectionMethod($merger, 'decodeImportedTextString');
		$rm->setAccessible(true);
		return $rm->invoke($merger, $node);
	}
}
