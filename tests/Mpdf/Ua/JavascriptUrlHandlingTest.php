<?php

namespace Mpdf\Ua;

/**
 * Links to a javascript: or vbscript: URL in a PDF/UA document.
 *
 * Strict mode throws. PDFUAauto mode drops the link but keeps its text, keeping an aria-label
 * or lang on a Span, and warns once per link. MetadataWriter also drops such a URI on a link
 * added through Link() directly.
 *
 * @group pdfua
 */
class JavascriptUrlHandlingTest extends PdfUaTestCase
{

	/**
	 * In strict mode a javascript: link throws, naming the URL.
	 *
	 * @return void
	 */
	public function testJavascriptUrlThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();
		try {
			$this->getOutput($mpdf, '<p><a href="javascript:alert(1)">x</a></p>');
			$this->fail('Expected MpdfException for javascript: href in strict mode.');
		} catch (\Mpdf\MpdfException $e) {
			$msg = $e->getMessage();
			$this->assertStringContainsString('17-001', $msg);
			$this->assertStringContainsString('28-002', $msg);
			$this->assertStringContainsString('javascript:alert(1)', $msg);
		}
	}

	/**
	 * In strict mode a javascript: link throws whatever the case of the scheme.
	 *
	 * @return void
	 */
	public function testCaseInsensitiveJavascriptDetectionStrict()
	{
		foreach (['JAVASCRIPT:foo()', 'JavaScript:bar()', 'jAvAsCrIpT:baz()'] as $href) {
			$mpdf = $this->makeMpdf();
			try {
				$this->getOutput($mpdf, '<p><a href="' . $href . '">x</a></p>');
				$this->fail('Expected MpdfException for ' . $href);
			} catch (\Mpdf\MpdfException $e) {
				$this->assertStringContainsString('17-001', $e->getMessage());
			}
		}
	}

	/**
	 * UaPolicy blocks a javascript: URL with whitespace in front of it.
	 *
	 * From HTML, GetFullPath() may already have joined such an href onto the base path; a call
	 * to Link() hands it over as written.
	 *
	 * @return void
	 */
	public function testWhitespacePrefixedJavascriptCaughtByPolicy()
	{
		$this->assertTrue(\Mpdf\Ua\UaPolicy::isPolicyBlockedHref(' javascript:foo()'));
		$this->assertTrue(\Mpdf\Ua\UaPolicy::isPolicyBlockedHref("\tjavascript:foo()"));
		$this->assertTrue(\Mpdf\Ua\UaPolicy::isPolicyBlockedHref("\rjavascript:foo()"));
		$this->assertTrue(\Mpdf\Ua\UaPolicy::isPolicyBlockedHref("\njavascript:foo()"));
		$this->assertTrue(\Mpdf\Ua\UaPolicy::isPolicyBlockedHref('  javascript:foo()'));
	}

	/**
	 * UaPolicy blocks script schemes in any case and spacing, and lets every other kind of href through.
	 *
	 * @return void
	 */
	public function testPolicyRegexCoversCaseAndWhitespaceVariants()
	{
		$blocked = [
			'javascript:alert(1)',
			'JAVASCRIPT:foo()',
			'JavaScript:bar()',
			'jAvAsCrIpT:baz()',
			'vbscript:msgbox(1)',
			'VBSCRIPT:foo()',
			' javascript:foo()',
			"\tjavascript:foo()",
			'javascript :foo()',
		];
		foreach ($blocked as $href) {
			$this->assertTrue(
				\Mpdf\Ua\UaPolicy::isPolicyBlockedHref($href),
				'Expected policy block for: ' . $href
			);
		}

		$allowed = [
			null, '', '#', '#fragment',
			'http://example.com',
			'https://example.com',
			'mailto:foo@example.com',
			'tel:+1234567890',
			'data:text/plain;base64,AAAA',
			'ftp://example.com',
			'file:///etc/hosts',
			'/local/path',
			'./relative.html',
			'page.html',
		];
		foreach ($allowed as $href) {
			$this->assertFalse(
				\Mpdf\Ua\UaPolicy::isPolicyBlockedHref($href),
				'Expected policy pass for: ' . var_export($href, true)
			);
		}
	}

	/**
	 * In strict mode a vbscript: link throws, naming the URL.
	 *
	 * @return void
	 */
	public function testVbscriptUrlAlsoBlockedStrict()
	{
		$mpdf = $this->makeMpdf();
		try {
			$this->getOutput($mpdf, '<p><a href="vbscript:msgbox(1)">x</a></p>');
			$this->fail('Expected MpdfException for vbscript: href in strict mode.');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertStringContainsString('vbscript:msgbox(1)', $e->getMessage());
		}
	}

	/**
	 * In PDFUAauto mode a javascript: link leaves no URL, URI action or Link element in the document.
	 *
	 * @return void
	 */
	public function testJavascriptUrlStrippedInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p><a href="javascript:alert(1)">click here</a></p>');

		$this->assertStringNotContainsString('javascript:alert(1)', $output);
		$this->assertStringNotContainsString('javascript:', $output);
		$this->assertStringNotContainsString('/S /URI', $output);
		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A dropped javascript: link is warned about once.
	 *
	 * @return void
	 */
	public function testJavascriptUrlEmitsExactlyOneWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$this->getOutput($mpdf, '<p><a href="javascript:alert(1)">x</a></p>');
		$warnings = $mpdf->getPdfUaWarnings();

		$matching = [];
		foreach ($warnings as $w) {
			if (stripos($w, 'javascript:alert(1)') !== false) {
				$matching[] = $w;
			}
		}
		$this->assertCount(1, $matching, 'Expected exactly one warning citing the offending href.');
		$this->assertStringContainsString('stripped', $matching[0]);
	}

	/**
	 * In PDFUAauto mode a javascript: link is dropped whatever the case of the scheme.
	 *
	 * @return void
	 */
	public function testCaseInsensitiveJavascriptDetectionAuto()
	{
		foreach (['JAVASCRIPT:foo()', 'JavaScript:bar()', 'jAvAsCrIpT:baz()'] as $href) {
			$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
			$output = $this->getOutput($mpdf, '<p><a href="' . $href . '">x</a></p>');
			$this->assertStringNotContainsString($href, $output, 'Mixed-case ' . $href . ' must not appear in PDF');
			$this->assertStringNotContainsString('/S /URI', $output);
		}
	}

	/**
	 * In PDFUAauto mode a javascript: link with whitespace in front of it is dropped.
	 *
	 * @return void
	 */
	public function testWhitespacePrefixedJavascriptDetectionAuto()
	{
		// With no HTTP_HOST there is no base path for GetFullPath() to join the href onto
		$savedHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		unset($_SERVER['HTTP_HOST']);
		try {
			$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
			$output = $this->getOutput($mpdf, '<p><a href=" javascript:foo()">x</a></p>');
			$this->assertStringNotContainsString('javascript:foo()', $output);
			$this->assertStringNotContainsString('/S /URI', $output);
		} finally {
			if ($savedHost !== null) {
				$_SERVER['HTTP_HOST'] = $savedHost;
			}
		}
	}

	/**
	 * In PDFUAauto mode a vbscript: link is dropped.
	 *
	 * @return void
	 */
	public function testVbscriptUrlAlsoBlockedAuto()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p><a href="vbscript:msgbox(1)">x</a></p>');
		$this->assertStringNotContainsString('vbscript:msgbox(1)', $output);
		$this->assertStringNotContainsString('vbscript:', $output);
		$this->assertStringNotContainsString('/S /URI', $output);
	}

	/**
	 * A dropped javascript: link whose text is split by inline tags is still warned about once.
	 *
	 * @return void
	 */
	public function testInlineSegmentedAnchorEmitsOneWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$html = '<p><a href="javascript:foo()"><b>bold</b> plain <i>italic</i></a></p>';
		$output = $this->getOutput($mpdf, $html);

		$warnings = $mpdf->getPdfUaWarnings();
		$matching = 0;
		foreach ($warnings as $w) {
			if (stripos($w, 'javascript:foo()') !== false) {
				$matching++;
			}
		}
		$this->assertSame(1, $matching, 'Expected exactly one warning for one stripped anchor.');

		$this->assertStringNotContainsString('javascript:foo()', $output);
		$this->assertStringNotContainsString('/S /URI', $output);
		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * The aria-label of a dropped link is kept as the /Alt of a Span.
	 *
	 * @return void
	 */
	public function testStrippedAnchorPreservesAriaLabel()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="javascript:foo()" aria-label="Run">x</a></p>'
		);

		$this->assertStringNotContainsString('/S /Link', $output);
		$this->assertStringContainsString('/S /Span', $output);
		// "Run" in UTF-16BE
		$this->assertStringContainsString("\xFE\xFF\x00R\x00u\x00n", $output);
	}

	/**
	 * The lang of a dropped link is kept on a Span.
	 *
	 * @return void
	 */
	public function testStrippedAnchorPreservesLang()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="javascript:foo()" lang="fr">bonjour</a></p>'
		);

		$this->assertStringNotContainsString('/S /Link', $output);
		$this->assertStringContainsString('/S /Span', $output);
		$utf16BeFr = "\xFE\xFF\x00f\x00r";
		$this->assertStringContainsString($utf16BeFr, $output);
	}

	/**
	 * A dropped link with no aria-label or lang leaves its text in the paragraph, with no Span.
	 *
	 * @return void
	 */
	public function testStrippedAnchorWithoutAriaProducesNoExtraSpan()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput(
			$mpdf,
			'<p>Before <a href="javascript:foo()">plain</a> after.</p>'
		);

		$this->assertStringNotContainsString('/S /Link', $output);
		$this->assertStringNotContainsString('/S /Span', $output);
	}

	/**
	 * A mailto: link is written as a Link with a URI action.
	 *
	 * @return void
	 */
	public function testMailtoUrlNotBlocked()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="mailto:foo@example.com">contact</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
		$this->assertStringContainsString('/S /URI', $output);
		$this->assertStringContainsString('mailto:foo@example.com', $output);
	}

	/**
	 * A tel: link is kept as a Link, without a warning.
	 *
	 * @return void
	 */
	public function testTelUrlNotBlocked()
	{
		// An href without a dot is taken for a named anchor, so this is an internal link, not a URI action
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="tel:+1234567890">call</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			$this->assertStringNotContainsString('tel:', $w);
		}
	}

	/**
	 * An https: link is written as a Link with a URI action.
	 *
	 * @return void
	 */
	public function testHttpsUrlUnchanged()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="https://example.com">ok</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
		$this->assertStringContainsString('/S /URI', $output);
		$this->assertStringContainsString('https://example.com', $output);
	}

	/**
	 * A data: link is kept as a Link in strict mode, without a warning.
	 *
	 * @return void
	 */
	public function testDataUriNotBlocked()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="data:text/plain;base64,SGVsbG8=">embedded</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			$this->assertStringNotContainsString('data:', $w);
		}
	}

	/**
	 * A link to a fragment is kept as a Link.
	 *
	 * @return void
	 */
	public function testInternalAnchorNotBlocked()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p><a href="#section">jump</a></p>'
		);
		$this->assertStringContainsString('/S /Link', $output);
	}

	/**
	 * An a with a name and no href is a target, not a link, and makes no Link element.
	 *
	 * @return void
	 */
	public function testAnchorWithoutHrefStillFollowsExistingPath()
	{
		$mpdf = $this->makeMpdf();
		$output = $this->getOutput(
			$mpdf,
			'<p>Before <a name="anchor">marker</a> after.</p>'
		);
		$this->assertStringNotContainsString('/S /Link', $output);
	}

	/**
	 * A javascript: URL given to Link() directly, never seen by Tag\A, is dropped by MetadataWriter with a warning.
	 *
	 * @return void
	 */
	public function testThirdPartyJavascriptUriCaughtByAnnotationGuard()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->WriteHTML('<p>preface</p>');
		$mpdf->Link(10, 10, 100, 20, 'javascript:thirdParty(1)');
		$output = $mpdf->Output(null, 'S');

		$this->assertStringNotContainsString('javascript:thirdParty(1)', $output);
		$this->assertStringNotContainsString('/S /URI', $output);
		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'javascript:thirdParty(1)') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'MetadataWriter guard must record a warning for direct Link() injection.');
	}

	/**
	 * Without PDFUA a javascript: link neither throws nor warns.
	 *
	 * @return void
	 */
	public function testJavascriptUrlUnchangedWhenPdfuaOff()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p><a href="javascript:alert(1)">x</a></p>');
		$output = $mpdf->Output(null, 'S');

		$this->assertNotEmpty($output);
		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertSame([], array_filter($warnings, function ($w) {
			return stripos($w, 'javascript') !== false;
		}));
	}
}
