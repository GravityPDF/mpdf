<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter;
use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;

/**
 * The invoices behind the fixtures in tests/data/invoice, whose XML Mustang validated for each profile, and the HTML
 * writer they are printed with
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
	 * The blank invoice with one line of services under the reverse charge, so with no VAT and a reason for none
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
	 * An online order paid by card: a discounted line, an order-wide percentage discount and shipping, each bearing the
	 * VAT of the lines, a note with its subject, the seller's contact and tax number, and its business process
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function shopInvoice()
	{
		$seller = $this->seller()->setTaxNumber('201/113/40209')->setContact('Accounts', '+33 1 23 45 67 89', 'accounts@seller.example');
		$invoice = new Invoice('INV-2026-0100', new \DateTime('2026-09-23'), 'EUR', $seller, $this->buyer());

		$invoice->addLine((new LineItem('Desk', 2, 250, 20))->addAllowanceCharge(AllowanceCharge::allowance(50, 'Display model')->setReasonCode('95')))
			->addLine(new LineItem('Lamp', 4, 35.5, 20))
			->addAllowanceCharge(AllowanceCharge::percentAllowance(10, 592, 'Loyalty discount')->setVat(20))
			->addAllowanceCharge(AllowanceCharge::charge(24.9, 'Shipping')->setReasonCode('FC')->setVat(20))
			->setBusinessProcess('B1')
			->setDeliveryDate(new \DateTime('2026-09-20'))
			->addNote('Late payment penalty: 3x the legal interest rate', 'PMD')
			->addPaymentMeans(PaymentMeans::card('4242', 'Buyer GmbH'));

		return $invoice->setPrepaidAmount($invoice->getGrandTotal());
	}

	/**
	 * A credit note for part of the first invoice, naming it and refunded by transfer
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function creditNote()
	{
		$invoice = new Invoice('CN-2026-0007', new \DateTime('2026-10-02'), 'EUR', $this->seller(), $this->buyer());

		return $invoice->setTypeCode(Invoice::TYPE_CREDIT_NOTE)
			->addPrecedingInvoice('INV-2026-0001', new \DateTime('2026-09-23'))
			->addLine((new LineItem('Consulting', 1.5, 120, 20))->setUnitCode('HUR'))
			->setDeliveryDate(new \DateTime('2026-09-20'))
			->setPaymentTerms('Refunded within 14 days')
			->addPaymentMeans(PaymentMeans::sepaCreditTransfer('DE02120300000000202051'));
	}

	/**
	 * Goods sent to the buyer's warehouse in another member state, VAT free as an intra-community supply and paid by
	 * SEPA direct debit, with the buyer's Leitweg-ID as its electronic address
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function intraCommunityInvoice()
	{
		$buyer = $this->buyer()->setElectronicAddress('04011000-12345-34', '0204');
		$warehouse = (new Party('Buyer GmbH Lager', 'DE'))->setAddress('Industriestraße 5', '20457', 'Hamburg');
		$invoice = new Invoice('INV-2026-0101', new \DateTime('2026-09-23'), 'EUR', $this->seller(), $buyer);

		return $invoice->addLine((new LineItem('Pallet of paper', 10, 189, 0, 'K'))->setUnitCode('XPX'))
			->setExemptionReason('K', 'Intra-community supply')
			->setDeliverTo($warehouse)
			->setDeliveryDate(new \DateTime('2026-09-21'))
			->setDueDate(new \DateTime('2026-10-23'))
			->addPaymentMeans(PaymentMeans::sepaDirectDebit('MANDATE-42', 'DE02120300000000202051', 'FR98ZZZ999999'));
	}

	/**
	 * The first invoice as XRechnung needs it: the seller's contact, the buyer's Leitweg-ID as its reference and its
	 * electronic address, and a discount for early payment in the Skonto form
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function xrechnungInvoice()
	{
		$invoice = $this->invoice()
			->setBuyerReference('04011000-12345-34')
			->setPaymentTerms("30 days net\n#SKONTO#TAGE=14#PROZENT=2.00#\n");
		$invoice->getSeller()->setContact('Accounts', '+33 1 23 45 67 89', 'accounts@seller.example');
		$invoice->getBuyer()->setElectronicAddress('04011000-12345-34', '0204');

		return $invoice;
	}

	/**
	 * A French services invoice as the 2026 reform needs it: both parties identified by SIREN and reached at addresses
	 * starting with it, its cadre de facturation, VAT on debits, and the three mandatory mentions as notes
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function frenchInvoice()
	{
		$seller = (new Party('Vendeur SARL', 'FR'))
			->setAddress('12 rue de la Paix', '75002', 'Paris')
			->setVatId('FR32123456789')
			->setLegalId('123456789', '0002')
			->setElectronicAddress('123456789', '0225');
		$buyer = (new Party('Acheteur SAS', 'FR'))
			->setAddress('8 quai de Saône', '69002', 'Lyon')
			->setVatId('FR44987654321')
			->setLegalId('987654321', '0002')
			->setElectronicAddress('987654321_FACTURES', '0225');

		$invoice = new Invoice('FA-2026-0042', new \DateTime('2026-09-23'), 'EUR', $seller, $buyer);

		return $invoice->addLine((new LineItem('Conseil', 7.5, 120, 20))->setUnitCode('HUR'))
			->setBusinessProcess('S1')
			->setVatOnDebits()
			->setDeliveryDate(new \DateTime('2026-09-20'))
			->setDueDate(new \DateTime('2026-10-23'))
			->addPaymentMeans(PaymentMeans::sepaCreditTransfer('FR7630006000011234567890189', 'AGRIFRPP'))
			->addNote('Indemnité forfaitaire pour frais de recouvrement : 40 €', 'PMT')
			->addNote('Pénalités de retard : trois fois le taux d’intérêt légal', 'PMD')
			->addNote('Pas d’escompte pour paiement anticipé', 'AAB');
	}

	/**
	 * The German buyer every invoice here is to
	 *
	 * @return \Mpdf\Invoice\Party
	 */
	private function buyer()
	{
		return (new Party('Buyer GmbH & Co. KG', 'DE'))
			->setAddress('Hauptstraße 1', '10115', 'Berlin', 'Gebäude <B>')
			->setVatId('DE123456789')
			->setElectronicAddress('ap@buyer.example');
	}

	/**
	 * The parties, dates and payment details every invoice here shares, with no lines yet
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function blankInvoice()
	{
		$invoice = new Invoice('INV-2026-0001', new \DateTime('2026-09-23'), 'EUR', $this->seller(), $this->buyer());

		return $invoice->setDeliveryDate(new \DateTime('2026-09-20'))
			->setDueDate(new \DateTime('2026-10-23'))
			->setPaymentTerms('30 days net')
			->setPaymentReference('INV-2026-0001')
			->addPaymentMeans(PaymentMeans::sepaCreditTransfer('FR7630006000011234567890189', 'AGRIFRPP', 'Seller SARL'))
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
			->setElectronicAddress('billing@seller.example');
	}

	/**
	 * The HTML writer in the British convention, with any labels given
	 *
	 * @param string[] $labels
	 *
	 * @return \Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter
	 */
	private function htmlWriter(array $labels = [])
	{
		return new HtmlInvoiceWriter(new Formatter(new UnitedKingdomPreset()), $labels);
	}

}
