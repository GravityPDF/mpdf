<?php

namespace Mpdf;

/**
 * Validates a document with veraPDF, the reference validator for PDF/A, PDF/UA and WTPDF
 *
 * A test using it is skipped where `verapdf` is not on the PATH. Tests that use it belong in the `conformance` group,
 * which the Conformance workflow runs with veraPDF installed.
 */
trait VeraPdf
{

	/**
	 * Asserts veraPDF finds the document conforms to a flavour, as its --flavour option names it: '2b' for
	 * PDF/A-2b, 'ua1' for PDF/UA-1 and so on
	 *
	 * @param string $file
	 * @param string $flavour
	 */
	private function assertConforms($file, $flavour)
	{
		exec('verapdf --version 2>&1', $version, $status);
		if ($status !== 0) {
			$this->markTestSkipped('veraPDF is not on the PATH');
		}

		exec('verapdf --flavour ' . escapeshellarg($flavour) . ' --format xml ' . escapeshellarg($file) . ' 2>&1', $report);
		$report = implode("\n", $report);

		preg_match_all('/<rule [^>]*clause="([^"]+)" testNumber="(\d+)" status="failed"/', $report, $failed, PREG_SET_ORDER);
		$this->assertSame([], array_map(function ($rule) {
			return $rule[1] . '-' . $rule[2];
		}, $failed), sprintf('veraPDF found the document does not conform to %s', $flavour));
		$this->assertStringContainsString('isCompliant="true"', $report);
	}

}
