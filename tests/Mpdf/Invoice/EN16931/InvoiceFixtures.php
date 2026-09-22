<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;

/**
 * The invoices behind the fixtures in tests/data/xml/einvoice, each of which Mustang validated for its profile
 */
trait InvoiceFixtures
{

	/**
	 * A French seller billing a German buyer at two VAT rates, less a prepayment
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function invoice()
	{
		$invoice = $this->blankInvoice();
		$invoice->addLine((new LineItem('Consulting', 7.5, 120, 20))->setUnitCode('HUR')->setDescription('September retainer'));
		$invoice->addLine(new LineItem('Book', 3, 12.99, 5.5));
		$invoice->setPrepaidAmount(100);

		return $invoice;
	}

	/**
	 * The same invoice with nothing prepaid, which MINIMUM needs as it cannot say what was
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function minimumInvoice()
	{
		return $this->invoice()->setPrepaidAmount(0);
	}

	/**
	 * The same invoice for services under the reverse charge, so with no VAT and a reason for none
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function reverseChargeInvoice()
	{
		$invoice = $this->blankInvoice();
		$invoice->addLine((new LineItem('Consulting', 7.5, 120, 0, 'AE'))->setUnitCode('HUR'));
		$invoice->setExemptionReason('AE', 'Reverse charge');

		return $invoice;
	}

	/**
	 * The parties, dates and payment details both invoices share, with no lines yet
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function blankInvoice()
	{
		$buyer = (new Party('Buyer GmbH & Co. KG', 'DE'))
			->setAddress('Hauptstraße 1', '10115', 'Berlin', 'Gebäude <B>')
			->setVatId('DE123456789')
			->setEmail('ap@buyer.example');

		$invoice = new Invoice('INV-2026-0001', new \DateTime('2026-09-23'), 'EUR', $this->seller(), $buyer);

		return $invoice->setDeliveryDate(new \DateTime('2026-09-20'))
			->setDueDate(new \DateTime('2026-10-23'))
			->setPaymentTerms('30 days net')
			->setPaymentReference('INV-2026-0001')
			->setPaymentAccount('FR7630006000011234567890189', 'AGRIFRPP', 'Seller SARL')
			->setBuyerReference('BR-42')
			->setOrderReference('PO-1234')
			->addNote('Late payment penalty: 3x the legal interest rate');
	}

	/**
	 * The French seller of every invoice
	 *
	 * @return \Mpdf\Invoice\Party
	 */
	private function seller()
	{
		return (new Party('Seller SARL', 'FR'))
			->setAddress('12 rue de la Paix', '75002', 'Paris')
			->setVatId('FR32123456789')
			->setLegalId('12345678900012', '0002')
			->setEmail('billing@seller.example');
	}

}
