<?php

namespace Snapshots;

use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\HtmlInvoiceWriter;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;

/**
 * Invoice XML from tests/data/invoice printed by HtmlInvoiceWriter, in the British convention unless a snapshot says
 * otherwise
 *
 * @group snapshot
 */
abstract class InvoiceSnapshot extends Snapshot
{

	/**
	 * The file in tests/data/invoice holding the XML to print
	 *
	 * @return string
	 */
	abstract protected function getFixture();

	/**
	 * The XML to print
	 *
	 * @return string
	 */
	protected function getXml()
	{
		return file_get_contents(__DIR__ . '/../data/invoice/' . $this->getFixture());
	}

	/**
	 * The writer to print it with
	 *
	 * @return \Mpdf\Invoice\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new HtmlInvoiceWriter(new Formatter(new UnitedKingdomPreset()));
	}

	/**
	 * Print the invoice
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($this->getWriter()->write($this->getXml()));
	}

}
