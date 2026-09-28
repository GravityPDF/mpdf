<?php

namespace Mpdf\Invoice;

/**
 * Reads what a printed invoice shows from a UBL 2.1 Invoice or CreditNote, as EN 16931 binds them and Peppol BIS
 * Billing and XRechnung use them, as AbstractInvoiceReader describes
 *
 * UBL has no structured payment terms in EN 16931, so its only discounts are XRechnung's Skonto lines, and it has no
 * penalties.
 *
 * @see https://docs.peppol.eu/poacc/billing/3.0/ Peppol BIS Billing, whose UBL this reads
 */
class UblInvoiceReader extends AbstractInvoiceReader
{

	const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

	const NS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

	const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

	const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

	/**
	 * The UNTDID 2475 code for when VAT is due, by the UNTDID 2005 code UBL gives it as
	 *
	 * @var string[]
	 */
	private static $vatDueDateCodes = ['3' => '5', '35' => '29', '432' => '72'];

	/**
	 * The invoice a parsed document states
	 *
	 * @param \DOMDocument $document
	 *
	 * @return mixed[] As AbstractInvoiceReader describes
	 *
	 * @throws \Mpdf\MpdfException When the document is not a UBL Invoice or CreditNote
	 */
	protected function readDocument(\DOMDocument $document)
	{
		$root = $this->root($document, 'UBL invoice XML', ['{' . self::NS_INVOICE . '}Invoice', '{' . self::NS_CREDIT_NOTE . '}CreditNote'], [
			'cac' => self::NS_CAC,
			'cbc' => self::NS_CBC,
		]);

		$currency = $this->text('cbc:DocumentCurrencyCode', $root);
		$delivery = $this->node('cac:Delivery', $root);
		$dueDate = $this->date('cbc:DueDate', $root);
		list($terms, $discounts) = $this->paymentTerms($this->texts('cac:PaymentTerms/cbc:Note', $root));

		return [
			'id' => $this->text('cbc:ID', $root),
			'typeCode' => $this->text('cbc:InvoiceTypeCode | cbc:CreditNoteTypeCode', $root),
			'issueDate' => $this->date('cbc:IssueDate', $root),
			'currency' => $currency,
			'notes' => $this->notes($root),
			'buyerReference' => $this->text('cbc:BuyerReference', $root),
			'orderReference' => $this->text('cac:OrderReference/cbc:ID', $root),
			'deliveryDate' => $this->date('cbc:ActualDeliveryDate', $delivery),
			// A CreditNote has no DueDate, and gives its due date with the payment means
			'dueDate' => $dueDate !== null ? $dueDate : $this->date('cac:PaymentMeans/cbc:PaymentDueDate', $root),
			'precedingInvoices' => $this->precedingInvoices($root),
			'seller' => $this->party($this->node('cac:AccountingSupplierParty/cac:Party', $root)),
			'buyer' => $this->party($this->node('cac:AccountingCustomerParty/cac:Party', $root)),
			'deliverTo' => $this->deliverTo($delivery),
			'lines' => $this->lines($root),
			'allowanceCharges' => $this->allowanceCharges($root),
			'vatBreakdown' => $this->vatBreakdown($root),
			'totals' => $this->totals($root, $currency),
			'paymentTerms' => $terms,
			'paymentDiscounts' => $discounts,
			'paymentPenalties' => [],
			'paymentReference' => $this->text('cac:PaymentMeans/cbc:PaymentID', $root),
			'paymentMeans' => $this->paymentMeans($root),
		];
	}

	/**
	 * The invoice's notes, without the #subject code# EN 16931 puts ahead of a note's text in UBL
	 *
	 * @param \DOMElement $root
	 *
	 * @return string[]
	 */
	private function notes(\DOMElement $root)
	{
		return array_values(array_filter(array_map(function ($note) {
			return trim(preg_replace('/^#[A-Z]{3}#/', '', $note));
		}, $this->texts('cbc:Note', $root)), 'strlen'));
	}

	/**
	 * A party's name, address, identifiers and contact, or null when the XML has none
	 *
	 * @param \DOMNode|null $party
	 *
	 * @return mixed[]|null
	 */
	private function party($party)
	{
		if ($party === null) {
			return null;
		}

		$name = $this->text('cac:PartyLegalEntity/cbc:RegistrationName', $party);
		$contact = $this->node('cac:Contact', $party);

		return [
			// The legal name, or the trading name where the XML gives only that
			'name' => $name !== null ? $name : $this->text('cac:PartyName/cbc:Name', $party),
			'address' => $this->address($this->node('cac:PostalAddress', $party)),
			'vatId' => $this->text('cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID', $party),
			'taxNumber' => $this->text('cac:PartyTaxScheme[not(cac:TaxScheme/cbc:ID="VAT")]/cbc:CompanyID', $party),
			'contact' => [
				'name' => $this->text('cbc:Name', $contact),
				'phone' => $this->text('cbc:Telephone', $contact),
				'email' => $this->text('cbc:ElectronicMail', $contact),
			],
			'electronicAddress' => $this->text('cbc:EndpointID', $party),
			'electronicAddressScheme' => $this->text('cbc:EndpointID/@schemeID', $party),
		];
	}

	/**
	 * Where the goods go, as a party with a name and an address only, or null when the XML gives neither
	 *
	 * @param \DOMNode|null $delivery
	 *
	 * @return mixed[]|null
	 */
	private function deliverTo($delivery)
	{
		$name = $this->text('cac:DeliveryParty/cac:PartyName/cbc:Name', $delivery);
		$address = $this->node('cac:DeliveryLocation/cac:Address', $delivery);
		if ($name === null && $address === null) {
			return null;
		}

		return [
			'name' => $name,
			'address' => $this->address($address),
			'vatId' => null,
			'taxNumber' => null,
			'contact' => ['name' => null, 'phone' => null, 'email' => null],
			'electronicAddress' => null,
			'electronicAddressScheme' => null,
		];
	}

	/**
	 * An address as Formatter::address() takes it
	 *
	 * @param \DOMNode|null $address
	 *
	 * @return mixed[]
	 */
	private function address($address)
	{
		return [
			'street' => $this->text('cbc:StreetName', $address),
			'additional' => $this->joined([$this->text('cbc:AdditionalStreetName', $address), $this->text('cac:AddressLine/cbc:Line', $address)]),
			'postcode' => $this->text('cbc:PostalZone', $address),
			'city' => $this->text('cbc:CityName', $address),
			'subdivision' => $this->text('cbc:CountrySubentity', $address),
			'country' => $this->text('cac:Country/cbc:IdentificationCode', $address),
		];
	}

	/**
	 * The invoice's lines, in the order the XML gives them
	 *
	 * @param \DOMElement $root
	 *
	 * @return mixed[]
	 */
	private function lines(\DOMElement $root)
	{
		$lines = [];
		foreach ($this->nodes('cac:InvoiceLine | cac:CreditNoteLine', $root) as $line) {
			$quantity = $this->node('cbc:InvoicedQuantity | cbc:CreditedQuantity', $line);
			$price = $this->node('cac:Price', $line);
			$tax = $this->node('cac:Item/cac:ClassifiedTaxCategory', $line);

			$lines[] = [
				'name' => $this->text('cac:Item/cbc:Name', $line),
				'description' => $this->text('cac:Item/cbc:Description', $line),
				'quantity' => $this->amount('.', $quantity),
				'unitCode' => $this->text('@unitCode', $quantity),
				'unitPrice' => $this->amount('cbc:PriceAmount', $price),
				'basisQuantity' => $this->amount('cbc:BaseQuantity', $price),
				'basisQuantityUnit' => $this->text('cbc:BaseQuantity/@unitCode', $price),
				'vatCategory' => $this->text('cbc:ID', $tax),
				'vatRate' => $this->amount('cbc:Percent', $tax),
				'netAmount' => $this->amount('cbc:LineExtensionAmount', $line),
				'allowanceCharges' => $this->allowanceCharges($line),
			];
		}

		return $lines;
	}

	/**
	 * The allowances and charges on the invoice or a line
	 *
	 * @param \DOMNode $context
	 *
	 * @return mixed[]
	 */
	private function allowanceCharges(\DOMNode $context)
	{
		$allowanceCharges = [];
		foreach ($this->nodes('cac:AllowanceCharge', $context) as $allowanceCharge) {
			$allowanceCharges[] = [
				'charge' => $this->text('cbc:ChargeIndicator', $allowanceCharge) === 'true',
				'amount' => $this->amount('cbc:Amount', $allowanceCharge),
				'reason' => $this->text('cbc:AllowanceChargeReason', $allowanceCharge),
				'reasonCode' => $this->text('cbc:AllowanceChargeReasonCode', $allowanceCharge),
			];
		}

		return $allowanceCharges;
	}

	/**
	 * The VAT of each category and rate, each with the one code the invoice gives for when VAT is due
	 *
	 * @param \DOMElement $root
	 *
	 * @return mixed[]
	 */
	private function vatBreakdown(\DOMElement $root)
	{
		$dueDateCode = $this->text('cac:InvoicePeriod/cbc:DescriptionCode', $root);
		$dueDateCode = $dueDateCode !== null && isset(self::$vatDueDateCodes[$dueDateCode]) ? self::$vatDueDateCodes[$dueDateCode] : null;

		$breakdown = [];
		foreach ($this->nodes('cac:TaxTotal/cac:TaxSubtotal', $root) as $subtotal) {
			$category = $this->node('cac:TaxCategory', $subtotal);
			$breakdown[] = [
				'category' => $this->text('cbc:ID', $category),
				'rate' => $this->amount('cbc:Percent', $category),
				'basis' => $this->amount('cbc:TaxableAmount', $subtotal),
				'amount' => $this->amount('cbc:TaxAmount', $subtotal),
				'exemptionReason' => $this->text('cbc:TaxExemptionReason', $category),
				'dueDateCode' => $dueDateCode,
			];
		}

		return $breakdown;
	}

	/**
	 * The invoice's totals, with its VAT in the invoice's own currency where the XML also gives it in another
	 *
	 * @param \DOMElement $root
	 * @param string|null $currency
	 *
	 * @return mixed[]
	 */
	private function totals(\DOMElement $root, $currency)
	{
		$summation = $this->node('cac:LegalMonetaryTotal', $root);
		$taxTotal = preg_match('/^[A-Z]{3}$/', (string) $currency) ? $this->amount('cac:TaxTotal/cbc:TaxAmount[@currencyID="' . $currency . '"]', $root) : null;

		return [
			'lineTotal' => $this->amount('cbc:LineExtensionAmount', $summation),
			'chargeTotal' => $this->amount('cbc:ChargeTotalAmount', $summation),
			'allowanceTotal' => $this->amount('cbc:AllowanceTotalAmount', $summation),
			'taxBasisTotal' => $this->amount('cbc:TaxExclusiveAmount', $summation),
			'taxTotal' => $taxTotal !== null ? $taxTotal : $this->amount('cac:TaxTotal/cbc:TaxAmount', $root),
			'roundingAmount' => $this->amount('cbc:PayableRoundingAmount', $summation),
			'grandTotal' => $this->amount('cbc:TaxInclusiveAmount', $summation),
			'prepaidAmount' => $this->amount('cbc:PrepaidAmount', $summation),
			'duePayableAmount' => $this->amount('cbc:PayableAmount', $summation),
		];
	}

	/**
	 * The invoices this one corrects or follows
	 *
	 * @param \DOMElement $root
	 *
	 * @return mixed[]
	 */
	private function precedingInvoices(\DOMElement $root)
	{
		$invoices = [];
		foreach ($this->nodes('cac:BillingReference/cac:InvoiceDocumentReference', $root) as $invoice) {
			$invoices[] = [
				'id' => $this->text('cbc:ID', $invoice),
				'issueDate' => $this->date('cbc:IssueDate', $invoice),
			];
		}

		return $invoices;
	}

	/**
	 * Each way to pay; a direct debit takes its creditor from the payee or, failing that, the seller
	 *
	 * @param \DOMElement $root
	 *
	 * @return mixed[]
	 */
	private function paymentMeans(\DOMElement $root)
	{
		$creditorId = $this->text('cac:PayeeParty/cac:PartyIdentification/cbc:ID[@schemeID="SEPA"]', $root);
		if ($creditorId === null) {
			$creditorId = $this->text('cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID="SEPA"]', $root);
		}

		$means = [];
		foreach ($this->nodes('cac:PaymentMeans', $root) as $paymentMeans) {
			$payee = $this->node('cac:PayeeFinancialAccount', $paymentMeans);
			$account = $this->text('cbc:ID', $payee);
			$debitedAccount = $this->text('cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID', $paymentMeans);

			$means[] = [
				'typeCode' => $this->text('cbc:PaymentMeansCode', $paymentMeans),
				'information' => $this->text('cbc:PaymentMeansCode/@name', $paymentMeans),
				'account' => $account,
				// UBL does not say whether an account is an IBAN, so one is taken for it when it has an IBAN's form
				'iban' => $account !== null && preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', str_replace(' ', '', $account)) === 1,
				'bic' => $this->text('cac:FinancialInstitutionBranch/cbc:ID', $payee),
				'accountName' => $this->text('cbc:Name', $payee),
				'debitedAccount' => $debitedAccount,
				'mandate' => $debitedAccount !== null ? $this->text('cac:PaymentMandate/cbc:ID', $paymentMeans) : null,
				'creditorId' => $debitedAccount !== null ? $creditorId : null,
				'card' => $this->text('cac:CardAccount/cbc:PrimaryAccountNumberID', $paymentMeans),
				'cardholder' => $this->text('cac:CardAccount/cbc:HolderName', $paymentMeans),
			];
		}

		return $means;
	}

	/**
	 * A date written YYYY-MM-DD as a DateTime, leaving out any time zone after it, a date written any other way as its
	 * text, or null when there is none
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return \DateTime|string|null
	 */
	private function date($path, $context)
	{
		$text = $this->text($path, $context);
		if ($text !== null && preg_match('/^(\d{4}-\d{2}-\d{2})(?:Z|[+-]\d{2}:\d{2})?$/', $text, $match)) {
			$parsed = \DateTime::createFromFormat('!Y-m-d', $match[1]);
			if ($parsed !== false) {
				return $parsed;
			}
		}

		return $text;
	}

}
