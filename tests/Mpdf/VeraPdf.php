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
	 * @var bool|null whether veraPDF runs, asked once as each start is a JVM's
	 */
	private static $veraPdfRuns;

	/**
	 * Skips the test unless veraPDF can be run, to call before the test spends time building a document
	 */
	private function skipWithoutVeraPdf()
	{
		if (self::$veraPdfRuns === null) {
			exec('verapdf --version 2>&1', $version, $status);
			self::$veraPdfRuns = $status === 0;
		}

		if (!self::$veraPdfRuns) {
			$this->markTestSkipped('veraPDF is not on the PATH');
		}
	}

	/**
	 * Asserts veraPDF finds the document conforms to a flavour, as its --flavour option names it: '2b' for
	 * PDF/A-2b, 'ua1' for PDF/UA-1 and so on
	 *
	 * @param string $file
	 * @param string $flavour
	 */
	private function assertConforms($file, $flavour)
	{
		$this->skipWithoutVeraPdf();

		exec('verapdf --flavour ' . escapeshellarg($flavour) . ' --format xml ' . escapeshellarg($file) . ' 2>&1', $report);
		$report = implode("\n", $report);

		preg_match_all('/<rule [^>]*clause="([^"]+)" testNumber="(\d+)" status="failed"/', $report, $failed, PREG_SET_ORDER);
		$this->assertSame([], array_map(function ($rule) {
			return $rule[1] . '-' . $rule[2];
		}, $failed), sprintf('veraPDF found the document does not conform to %s', $flavour));
		$this->assertStringContainsString('isCompliant="true"', $report);
	}

}
