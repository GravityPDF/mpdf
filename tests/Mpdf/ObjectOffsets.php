<?php

namespace Mpdf;

use Mpdf\Import\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Checks a written document's cross-reference against where its objects are
 */
trait ObjectOffsets
{

	/**
	 * Every object the cross-reference places in the file is at the offset it gives
	 *
	 * @param string $pdf
	 *
	 * @return int How many objects were checked
	 */
	private function assertEachOffsetLandsOnItsObject($pdf)
	{
		$crossReference = (new PdfParser(StreamReader::createByString($pdf)))->getCrossReference();
		$checked = 0;
		for ($number = 1; $number < $crossReference->getSize(); $number++) {
			$offset = $crossReference->getOffsetFor($number);
			if (is_int($offset)) {
				$this->assertSame($number . ' 0 obj', substr($pdf, $offset, strlen($number . ' 0 obj')), 'Object ' . $number);
				$checked++;
			}
		}

		return $checked;
	}

}
