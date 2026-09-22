<?php

namespace Mpdf\Ua;

/**
 * Builds PDF/UA documents with embedded fonts and uncompressed streams, so tests can match
 * the raw operators. BaseMpdfTest is not extended because it uses core fonts, which PDF/UA
 * forbids.
 */
abstract class PdfUaTestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A PDF/UA document with a title and a language, both of which PDF/UA requires.
	 *
	 * @param array $config Config merged over those defaults
	 *
	 * @return \Mpdf\Mpdf
	 */
	protected function makeMpdf($config = [])
	{
		$defaults = ['PDFUA' => true, 'title' => 'Test Document', 'mode' => 'en-GB'];
		$mpdf = new \Mpdf\Mpdf(array_merge($defaults, $config));
		$mpdf->compress = false;
		return $mpdf;
	}

	/**
	 * Write HTML into the document and return the raw PDF bytes.
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param string     $html
	 *
	 * @return string
	 */
	protected function getOutput(\Mpdf\Mpdf $mpdf, $html)
	{
		$mpdf->WriteHTML($html);
		return $mpdf->Output(null, 'S');
	}
}
