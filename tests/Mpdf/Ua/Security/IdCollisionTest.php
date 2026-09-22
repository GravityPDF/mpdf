<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;
use Mpdf\Ua\StructureElement;

/**
 * An id too long for a PDF name is shortened with a 64-bit hash suffix, wide
 * enough that distinct long ids sharing a prefix do not end up the same.
 *
 * @group pdfua
 * @group security
 */
class IdCollisionTest extends PdfUaTestCase
{

	/**
	 * Sixty thousand distinct overlong ids sharing a prefix all shorten to distinct names.
	 */
	public function testWidenedSuffixAvoidsBirthdayCollisionsAt60k()
	{
		$prefix = str_repeat('a', 117);
		$seen   = [];
		$collisions = 0;
		$N = 60000;

		for ($i = 0; $i < $N; $i++) {
			$id = $prefix . sprintf('-%012d-%s', $i, bin2hex(random_bytes(6)));
			$san = StructureElement::sanitiseIdForPdf($id);
			if (isset($seen[$san])) {
				$collisions++;
			}
			$seen[$san] = true;
		}

		$this->assertSame(
			0,
			$collisions,
			'Expected zero collisions in ' . $N . ' overlong IDs after the M-2 widening.'
		);
	}

	/**
	 * A long id is shortened to fit the 127-byte limit on a PDF name.
	 */
	public function testSanitisedIdFitsPdfNameLengthLimit()
	{
		$long = str_repeat('xyzABC123', 200);
		$out = StructureElement::sanitiseIdForPdf($long);
		$this->assertLessThanOrEqual(127, strlen($out));
	}

	/**
	 * Two long ids differing only after the cut-off shorten to different names.
	 */
	public function testTwoPrefixSharingOverlongIdsRemainDistinct()
	{
		$prefix = str_repeat('z', 200);
		$a = StructureElement::sanitiseIdForPdf($prefix . '-distinct-tail-A');
		$b = StructureElement::sanitiseIdForPdf($prefix . '-distinct-tail-B');
		$this->assertNotSame($a, $b);
	}

	/**
	 * Ids are lower-cased, so a TH id and a TD headers entry differing only in case still match.
	 */
	public function testCaseFoldingStillNormalises()
	{
		$this->assertSame(
			StructureElement::sanitiseIdForPdf('My-ID'),
			StructureElement::sanitiseIdForPdf('my-id')
		);
	}
}
