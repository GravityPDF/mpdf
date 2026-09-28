<?php

namespace Snapshots;

use Mpdf\Invoice\Formatter;
use Mpdf\Invoice\HtmlInvoiceWriter;
use Mpdf\Invoice\Preset\FrancePreset;

/**
 * A credit note in French: its labels translated, with a space before each colon, a decimal comma, no-break spaces
 * between thousands and before the euro and percent signs, and dates written day first
 *
 * @group snapshot
 */
class InvoiceTranslatedSnapshotTest extends InvoiceSnapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-translated';
	}

	/**
	 * @return string
	 */
	protected function getFixture()
	{
		return 'en16931.xml';
	}

	/**
	 * The invoice fixture typed as a credit note
	 *
	 * @return string
	 */
	protected function getXml()
	{
		return str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>381</ram:TypeCode>', parent::getXml());
	}

	/**
	 * @return \Mpdf\Invoice\HtmlInvoiceWriter
	 */
	protected function getWriter()
	{
		return new HtmlInvoiceWriter(new Formatter(new FrancePreset()), [
			'381' => 'Avoir',
			'issueDate' => 'Date d’émission',
			'deliveryDate' => 'Date de livraison',
			'dueDate' => 'Date d’échéance',
			'buyerReference' => 'Votre référence',
			'orderReference' => 'Commande',
			'seller' => 'De',
			'buyer' => 'À',
			'vatId' => 'N° TVA %s',
			'item' => 'Article',
			'quantity' => 'Quantité',
			'unitPrice' => 'Prix unitaire',
			'vat' => 'TVA',
			'vatGroup' => 'TVA %1$s sur %2$s',
			'amount' => 'Montant',
			'taxBasisTotal' => 'Total HT',
			'grandTotal' => 'Total TTC',
			'prepaid' => 'Acompte versé',
			'due' => 'Net à payer',
			'paymentReference' => 'Référence de paiement : %s',
			'iban' => 'IBAN : %s',
			'bic' => 'BIC : %s',
			'accountName' => 'Titulaire du compte : %s',
		]);
	}

}
