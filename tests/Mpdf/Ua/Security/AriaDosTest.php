<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;
use Mpdf\Ua\AriaIdResolver;

/**
 * An id list in aria-labelledby is capped in bytes and in tokens, so a huge attribute
 * cannot run the document out of memory.
 *
 * @group pdfua
 * @group security
 */
class AriaDosTest extends PdfUaTestCase
{

	/**
	 * An aria-labelledby longer than the byte cap is refused with a warning and without a memory spike.
	 */
	public function testOversizedAriaLabelledbyIsRejected()
	{
		$payload = str_repeat('a ', AriaIdResolver::MAX_ARIA_IDS_LENGTH);
		$mpdf = $this->makeMpdf();

		$startMem = memory_get_usage(true);
		$this->getOutput($mpdf, '<p aria-labelledby="' . $payload . '">x</p>');
		$peak = memory_get_peak_usage(true);

		$this->assertLessThan(
			64 * 1024 * 1024,
			$peak - $startMem,
			'Bounded aria-labelledby parsing must not amplify memory.'
		);

		$found = false;
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			if (stripos($w, 'M-1') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected an M-1 truncation/rejection warning.');
	}

	/**
	 * An aria-labelledby under the byte cap but over the token cap is truncated with a warning.
	 */
	public function testTooManyTokensAreTruncated()
	{
		$ids = [];
		$count = AriaIdResolver::MAX_ARIA_IDS_TOKENS + 50;
		for ($i = 0; $i < $count; $i++) {
			$ids[] = 'i' . $i;
		}
		$value = implode(' ', $ids);
		// None of the ids resolve, which strict mode throws on
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$this->getOutput($mpdf, '<p aria-labelledby="' . $value . '">x</p>');

		$found = false;
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			if (stripos($w, 'truncated') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected a token-cap truncation warning.');
	}

	/**
	 * A short aria-labelledby raises no truncation warning.
	 */
	public function testNormalAriaLabelledbyIsUnaffected()
	{
		// The inline spans register no structure id, so the reference is unresolved, which strict mode throws on
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$output = $this->getOutput($mpdf, '<p aria-labelledby="a b">x</p><span id="a">A</span><span id="b">B</span>');

		$truncationWarning = false;
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			if (stripos($w, 'M-1') !== false || stripos($w, 'truncated') !== false) {
				$truncationWarning = true;
				break;
			}
		}
		$this->assertFalse($truncationWarning, 'Normal aria-labelledby must not trigger the M-1 cap.');
	}
}
