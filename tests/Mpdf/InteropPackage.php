<?php

namespace Mpdf;

/**
 * Needs a package that writes invoice XML, one not in require-dev because it needs a newer PHP than mPDF supports
 *
 * A test using it is skipped where the package is not installed. The einvoice-interop workflow names the packages it
 * installs in EINVOICE_INTEROP, and there a missing one fails the test instead.
 */
trait InteropPackage
{

	/**
	 * Skips the test unless the package is installed, or fails it where EINVOICE_INTEROP names the package
	 *
	 * @param string $class A class the package declares
	 * @param string $package
	 */
	private function requirePackage($class, $package)
	{
		if (class_exists($class)) {
			return;
		}

		if (in_array($package, explode(' ', (string) getenv('EINVOICE_INTEROP')), true)) {
			$this->fail(sprintf('%s is not installed, but the einvoice-interop workflow installs it', $package));
		}

		$this->markTestSkipped(sprintf('%s is not installed; run composer require --dev %s', $package, $package));
	}

}
