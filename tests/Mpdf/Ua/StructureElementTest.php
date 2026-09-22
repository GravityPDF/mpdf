<?php

namespace Mpdf\Ua;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * How StructureElement::sanitiseIdForPdf() turns an id into the PDF name used by /ID and /Headers
 *
 * @group pdfua
 */
class StructureElementTest extends TestCase
{

	/**
	 * A name is safe bytes and #xx escapes only; a '#' without two hex digits after it is malformed
	 * (ISO 32000-1 §7.3.5).
	 *
	 * @param string $name
	 */
	private function assertValidPdfName($name)
	{
		$this->assertMatchesRegex(
			'/^(?:[a-z0-9_.\-]|#[0-9A-F]{2})*$/',
			$name,
			'sanitised id is not a valid PDF name production: ' . $name
		);
	}

	/**
	 * assertMatchesRegularExpression() where PHPUnit has it, assertRegExp() where it does not.
	 *
	 * @param string $pattern
	 * @param string $value
	 * @param string $message
	 */
	private function assertMatchesRegex($pattern, $value, $message = '')
	{
		if (method_exists($this, 'assertMatchesRegularExpression')) {
			$this->assertMatchesRegularExpression($pattern, $value, $message);
		} else {
			$this->assertRegExp($pattern, $value, $message);
		}
	}

	/**
	 * An id under the length cap is only lowercased.
	 */
	public function testShortIdUnchanged()
	{
		$this->assertSame('header-1', StructureElement::sanitiseIdForPdf('Header-1'));
	}

	/**
	 * An overlong id whose cut would fall inside an #xx escape is cut before that escape instead.
	 */
	public function testLongNonAsciiIdEndsOnCompleteToken()
	{
		$id  = str_repeat('a', 107) . str_repeat("\xC3\xA9", 8); // "é" in UTF-8
		$out = StructureElement::sanitiseIdForPdf($id);

		$this->assertValidPdfName($out);
		$this->assertStringEndsWith('#2D' . substr(sha1($id), 0, 16), $out);
	}

	/**
	 * Wherever the cut falls against the escapes, the name stays valid and within 127 bytes.
	 */
	public function testTruncationNeverSplitsEscapeAtAnyAlignment()
	{
		for ($ascii = 100; $ascii <= 115; $ascii++) {
			$id  = str_repeat('a', $ascii) . str_repeat("\xC3\xA9", 12);
			$out = StructureElement::sanitiseIdForPdf($id);
			$this->assertValidPdfName($out);
			$this->assertLessThanOrEqual(127, strlen($out), "id length $ascii overflowed the cap");
		}
	}

	/**
	 * An overlong id made only of escaped bytes is still cut on a whole escape.
	 */
	public function testAllNonAsciiIdIsValidName()
	{
		$id  = str_repeat("\xE2\x9C\x93", 60); // U+2713 CHECK MARK ×60
		$out = StructureElement::sanitiseIdForPdf($id);

		$this->assertValidPdfName($out);
		$this->assertLessThanOrEqual(127, strlen($out));
	}

	/**
	 * Overlong ids that differ only after the cut stay distinct through their hash suffix.
	 */
	public function testOverlongInputsWithSharedPrefixStayDistinct()
	{
		$base = str_repeat('a', 130);
		$a    = StructureElement::sanitiseIdForPdf($base . 'x');
		$b    = StructureElement::sanitiseIdForPdf($base . 'y');

		$this->assertNotSame($a, $b);
		$this->assertValidPdfName($a);
		$this->assertValidPdfName($b);
	}
}
