<?php

namespace Mpdf\Invoice\Output;

use Mpdf\Mpdf;
use Mpdf\Pdf\DocumentProfile;
use Mpdf\Strict;

/**
 * HTML written onto the page, as WriteHTML() writes it
 */
class HtmlOutput implements OutputInterface
{

	use Strict;

	/**
	 * @var string
	 */
	private $html;

	/**
	 * @param string $html
	 */
	public function __construct($html)
	{
		$this->html = $html;
	}

	/**
	 * Any document takes HTML
	 *
	 * @param \Mpdf\Pdf\DocumentProfile $document
	 */
	public function check(DocumentProfile $document)
	{
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function apply(Mpdf $mpdf)
	{
		$mpdf->WriteHTML($this->html);
	}

}
