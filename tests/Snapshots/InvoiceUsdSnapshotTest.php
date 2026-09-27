<?php

namespace Snapshots;

use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\HtmlInvoiceWriter;
use Mpdf\Invoice\Preset\UnitedStatesPreset;

/**
 * An export to the United States invoiced in dollars: the amounts after a dollar sign with commas between thousands,
 * the prepayment as -$, dates month first, the buyer's state before its ZIP code, and no VAT on the export with the
 * reason beside it
 *
 * @group snapshot
 */
class InvoiceUsdSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-usd';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931-usd.xml';
	}

	/**
	 * @return \Mpdf\Invoice\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new HtmlInvoiceWriter(new Formatter(new UnitedStatesPreset()));
	}

}
