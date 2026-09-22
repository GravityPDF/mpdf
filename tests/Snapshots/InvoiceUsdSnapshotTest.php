<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;

/**
 * An export to the United States invoiced in dollars: the amounts after a dollar sign with commas between thousands,
 * the prepayment as -$, dates month first, the buyer's state before its ZIP code, and no VAT on the export with the
 * reason beside it
 *
 * @group snapshot
 */
class InvoiceUsdSnapshotTest extends InvoiceSnapshot
{

	use InvoiceFixtures;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-usd';
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	protected function getInvoice()
	{
		$buyer = (new Party('Buyer Inc.', 'US'))
			->setAddress('350 Fifth Avenue', '10118', 'New York', 'Suite 4200')
			->setCountrySubdivision('NY')
			->setEmail('ap@buyer.example');

		$invoice = new Invoice('INV-2026-0002', new \DateTime('2026-09-23'), 'USD', $this->seller(), $buyer);
		$invoice->addLine((new LineItem('Consulting', 12.5, 185, 0, 'G'))->setUnitCode('HUR')->setDescription('September retainer'))
			->addLine(new LineItem('Licence', 3, 1249.99, 0, 'G'))
			->setExemptionReason('G', 'Export outside the EU')
			->setDeliveryDate(new \DateTime('2026-09-20'))
			->setDueDate(new \DateTime('2026-10-23'))
			->setPaymentTerms('Net 30')
			->setPaymentAccount('FR7630006000011234567890189', 'AGRIFRPP', 'Seller SARL')
			->setPrepaidAmount(1500)
			->setOrderReference('PO-7781');

		return $invoice;
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new HtmlInvoiceWriter([], Formatter::usd());
	}

}
