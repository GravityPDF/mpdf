<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;

/**
 * A credit note in French: every label replaced, and numbers, amounts and dates written by a subclass the French way
 *
 * @group snapshot
 */
class InvoiceTranslatedSnapshotTest extends InvoiceSnapshot
{

	use InvoiceFixtures;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-translated';
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	protected function getInvoice()
	{
		return $this->invoice()->setTypeCode(Invoice::TYPE_CREDIT_NOTE);
	}

	/**
	 * @return \Mpdf\Invoice\EN16931\Writer\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new FrenchHtmlInvoiceWriter([
			Invoice::TYPE_CREDIT_NOTE => 'Avoir',
			'issueDate' => 'Date d’émission',
			'deliveryDate' => 'Date de livraison',
			'dueDate' => 'Date d’échéance',
			'buyerReference' => 'Votre référence',
			'orderReference' => 'Commande',
			'seller' => 'De',
			'buyer' => 'À',
			'vatId' => 'N° TVA',
			'item' => 'Article',
			'quantity' => 'Quantité',
			'unitPrice' => 'Prix unitaire',
			'vat' => 'TVA',
			'vatGroup' => 'TVA %1$s sur %2$s',
			'amount' => 'Montant',
			'lineTotal' => 'Total HT',
			'grandTotal' => 'Total TTC',
			'prepaid' => 'Acompte versé',
			'due' => 'Net à payer',
			'paymentReference' => 'Référence de paiement',
			'iban' => 'IBAN',
			'bic' => 'BIC',
			'accountName' => 'Titulaire du compte',
		]);
	}

}
