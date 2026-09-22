<?php

namespace Mpdf\Ua;

/**
 * What a document that breaks a PDF/UA-1 rule does: with PDFUAauto it is corrected and a warning
 * recorded, without it an exception is thrown, as the author's intent cannot be guessed
 *
 * @group pdfua
 */
class ValidationTest extends PdfUaTestCase
{

	/**
	 * With PDFUAauto, an image without alt is treated as decorative, with a warning.
	 */
	public function testImageMissingAltAddsWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$this->getOutput($mpdf, '<p>Before <img src="' . $png . '" width="20" height="20"> after.</p>');

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertNotEmpty($warnings, 'A warning must be recorded when alt is missing in PDFUAauto mode');

		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'missing alt') !== false || stripos($w, 'decorative') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Warning text must reference the missing alt / decorative treatment');
	}

	/**
	 * Without PDFUAauto, an image without alt throws.
	 */
	public function testImageMissingAltThrowsWhenStrict()
	{
		$mpdf = $this->makeMpdf();
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/missing the alt attribute/');
		$this->getOutput($mpdf, '<p>Before <img src="' . $png . '" width="20" height="20"> after.</p>');
	}

	/**
	 * An empty alt marks an image decorative, without an exception or a warning.
	 */
	public function testImageEmptyAltAcceptedAsDecorative()
	{
		$mpdf = $this->makeMpdf();
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$out = $this->getOutput($mpdf, '<p>Before <img src="' . $png . '" alt="" width="20" height="20"> after.</p>');
		$this->assertNotEmpty($out, 'Output must be produced when alt is explicitly empty');

		foreach ($mpdf->getPdfUaWarnings() as $w) {
			$this->assertStringNotContainsString(
				'missing alt',
				strtolower($w),
				'No missing-alt warning when alt="" is explicit'
			);
		}
	}

	/**
	 * With PDFUAauto, document JavaScript is dropped with a warning.
	 */
	public function testJavaScriptEmbedAddsWarning()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetJS('app.alert("hi");');
		$this->getOutput($mpdf, '<h1>Hello</h1>');

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'javascript') !== false || stripos($w, 'SetJS') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'SetJS in PDFUAauto mode must record a warning');
	}

	/**
	 * Without PDFUAauto, SetJS() throws.
	 */
	public function testJavaScriptEmbedThrowsWhenStrict()
	{
		$mpdf = $this->makeMpdf();

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/SetJS|JavaScript/i');
		$mpdf->SetJS('app.alert("hi");');
	}

	/**
	 * Without PDFUAauto, OverWrite() throws before reading the file, as replacing bytes in a
	 * finished PDF would break the offsets its structure tree relies on.
	 */
	public function testOverWriteThrowsInPdfuaMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$mpdf->WriteHTML('<h1>Hello</h1>');

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/OverWrite/');
		$mpdf->OverWrite('/tmp/does_not_matter.pdf', 'foo', 'bar', 'S', 'out');
	}

	/**
	 * With PDFUAauto, a heading that skips a level is warned about.
	 */
	public function testHeadingLevelSkipAddsWarningInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$this->getOutput($mpdf, '<h1>A</h1><h3>B</h3>');

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'heading sequence') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Skipping H2 must record a heading-sequence warning');
	}

	/**
	 * Without PDFUAauto, a heading that skips a level throws.
	 */
	public function testHeadingLevelSkipThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/heading sequence/');
		$this->getOutput($mpdf, '<h1>A</h1><h3>B</h3>');
	}

	/**
	 * Without PDFUAauto, a document with no language throws.
	 */
	public function testMissingLangThrowsInStrictMode()
	{
		// makeMpdf() would set a language
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'title' => 'Test']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<h1>Hello</h1>');

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/Lang/');
		$mpdf->Output('', 'S');
	}

	/**
	 * With PDFUAauto, a document with no language is given en-US, with a warning.
	 */
	public function testMissingLangFallsBackInAutoMode()
	{
		$mpdf = new \Mpdf\Mpdf(['PDFUA' => true, 'PDFUAauto' => true, 'title' => 'Test']);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<h1>Hello</h1>');
		$out = $mpdf->Output('', 'S');

		$this->assertStringContainsString('/Lang (en-US)', $out, 'PDFUAauto must fall back to en-US');

		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (stripos($w, 'lang') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'A /Lang fallback warning must be recorded');
	}

	/**
	 * With PDFUAauto, protection that leaves out 'extract' still keeps bit 10 of /P, so assistive
	 * technology can read the content.
	 */
	public function testEncryptionExtractBitPreservedInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection(['copy', 'print']);
		$out = $this->getOutput($mpdf, '<h1>Hello</h1>');

		$this->assertNotEmpty($out, 'Output must still be produced when permissions need correction');
		// The other /P keys are the parents of structure elements
		$found = preg_match('/\/Filter \/Standard.*?\/P (-?\d+)/s', $out, $m);
		$this->assertSame(1, $found, 'Encryption dict with /P must be present in the PDF');
		$p = (int) $m[1];
		$this->assertNotSame(0, $p & (1 << 9), '/P field must keep bit 10 set after PDFUAauto correction');
	}

	/**
	 * Without PDFUAauto, protection that leaves out 'extract' throws rather than being changed.
	 */
	public function testEncryptionExtractBitMissingThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/extract/i');
		$mpdf->SetProtection(['copy', 'print']);
	}

	/**
	 * With PDFUAauto, every example fixture renders without an exception.
	 *
	 * Whether they conform is left to VeraPdfConformanceTest.
	 */
	public function testAllExamplesRenderWithoutExceptions()
	{
		$fixtureDir = __DIR__ . '/../../data/html/pdfua-examples';
		$fixtures = glob($fixtureDir . '/example*.html');
		$this->assertNotEmpty($fixtures, 'Fixture directory must contain example HTML files');

		foreach ($fixtures as $fixture) {
			$name = basename($fixture, '.html');
			try {
				$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
				// Inactive form fields are drawn in a core font, which PDF/UA forbids
				if ($name === 'example09_forms') {
					$mpdf->useActiveForms = true;
				}
				$mpdf->WriteHTML(file_get_contents($fixture));
				$bytes = $mpdf->Output(null, 'S');
				$this->assertNotEmpty($bytes, $name . ' produced empty output');
			} catch (\Throwable $e) {
				$this->fail('Example "' . $name . '" threw ' . get_class($e) . ': ' . $e->getMessage());
			}
		}
	}

	/**
	 * Every Type0 and TrueType font dictionary refers to a ToUnicode CMap (Matterhorn 09-006).
	 */
	public function testFontSubsetToUnicodeCoverage()
	{
		$mpdf = $this->makeMpdf();
		$out = $this->getOutput($mpdf, '<h1>Hello</h1><p>Some text content.</p>');

		// A CIDFontType2 shares the CMap of the Type0 font it descends from
		preg_match_all(
			'#<<[^<>]*?/Type\s*/Font\s*[^<>]*?/Subtype\s*/(?:Type0|TrueType)[^<>]*?>>#s',
			$out,
			$m
		);
		$this->assertNotEmpty($m[0], 'PDFUA output must contain at least one embedded font dict');
		foreach ($m[0] as $i => $dict) {
			$this->assertStringContainsString(
				'/ToUnicode',
				$dict,
				'Font dict #' . $i . ' must reference /ToUnicode CMap (Matterhorn 09-006)'
			);
		}
	}

	/**
	 * With PDFUAauto, a document breaking three rules renders and records a warning for each.
	 */
	public function testMultipleViolationsAccumulateWarnings()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$mpdf->SetJS('app.alert("hi");');
		$bytes = $this->getOutput(
			$mpdf,
			'<h1>One</h1><h3>Three</h3>'
			. '<p>Image: <img src="' . $png . '" width="20" height="20"></p>'
		);

		$this->assertNotEmpty($bytes);

		$warnings = $mpdf->getPdfUaWarnings();
		$this->assertGreaterThanOrEqual(
			3,
			count($warnings),
			'Each of the three violations should add at least one warning; got: ' . print_r($warnings, true)
		);
	}
}
