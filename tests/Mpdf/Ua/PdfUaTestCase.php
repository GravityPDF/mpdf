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

	/**
	 * The /ActualText of every /Span wrapper in the content streams, in hex.
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	protected function actualTexts($pdf)
	{
		preg_match_all('/\/Span <<\/ActualText <(FEFF[0-9A-F]*)>>>/', $pdf, $m);

		return $m[1];
	}

	/**
	 * The /ActualText hex that stands for a string: UTF-16BE behind a byte order mark.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected function actualTextOf($text)
	{
		return 'FEFF' . strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')));
	}
}
