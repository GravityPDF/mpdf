<?php

namespace Mpdf\Import;

/**
 * mPDF's parser for importing a PDF, reading cross-reference streams and object streams (PDF 1.5) as well as
 * classic cross-reference tables
 *
 * Part of mPDF, written from the PDF specification (ISO 32000-1, 7.5.7 and 7.5.8).
 */
class PdfParser extends \setasign\Fpdi\PdfParser\PdfParser
{

	/**
	 * @return \Mpdf\Import\ObjectStreamCrossReference
	 *
	 * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
	 * @throws \setasign\Fpdi\PdfParser\PdfParserException
	 */
	public function getCrossReference()
	{
		if ($this->xref === null) {
			$this->xref = new ObjectStreamCrossReference($this, $this->resolveFileHeader());
		}

		return $this->xref;
	}

}
