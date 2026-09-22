<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;
use Mpdf\Ua\Import\FpdiStructMerger;

/**
 * A string read from an imported PDF is dropped when half or more of its characters
 * are replacement or control characters, the shape of ciphertext decoded as text.
 *
 * FPDI refuses encrypted sources today, so this guards against a release that does not.
 *
 * @group pdfua
 * @group security
 */
class SanityGauntletThresholdTest extends PdfUaTestCase
{

	/** @var FpdiStructMerger */
	private $merger;

	/** @var \ReflectionMethod */
	private $gauntlet;

	/**
	 * Opens the merger's private string check for the tests to call.
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->merger = $this->makeMpdf()->getPdfUaFpdiStructMerger();
		$this->gauntlet = new \ReflectionMethod(FpdiStructMerger::class, 'stringPassesSanityGauntlet');
		$this->gauntlet->setAccessible(true);
	}

	/**
	 * @param string $decoded A UTF-8 string as read from an imported PDF
	 *
	 * @return bool Whether the merger would keep it
	 */
	private function passes($decoded)
	{
		return $this->gauntlet->invoke($this->merger, $decoded);
	}

	/**
	 * A string that is exactly half U+FFFD is dropped.
	 */
	public function testExactlyHalfSuspiciousIsRejected()
	{
		$fiftyFifty = str_repeat('A', 50) . str_repeat("\xEF\xBF\xBD", 50);
		$this->assertFalse(
			$this->passes($fiftyFifty),
			'a string that is exactly 50% suspicious codepoints must fail the gauntlet'
		);
	}

	/**
	 * A predominantly legible string (10% suspicious) still passes.
	 */
	public function testMajorityLegibleStringPasses()
	{
		$mostlyLegible = str_repeat('A', 90) . str_repeat("\xEF\xBF\xBD", 10);
		$this->assertTrue(
			$this->passes($mostlyLegible),
			'a predominantly legible string must pass the gauntlet'
		);
	}

	/**
	 * Ordinary alternative-description text passes unchanged.
	 */
	public function testCleanTextPasses()
	{
		$this->assertTrue(
			$this->passes('A perfectly ordinary alternative description.'),
			'clean legible text must pass the gauntlet'
		);
	}
}
