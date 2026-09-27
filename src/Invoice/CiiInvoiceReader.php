<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * Reads what a printed invoice shows from Cross Industry Invoice (CII) XML, the syntax Factur-X, ZUGFeRD and XRechnung
 * CII share
 *
 * It reads what the XML states, totals included, and works nothing out: the XML is the invoice, and the page shows it.
 * It neither validates the XML nor checks that its totals add up. Anything the XML leaves out, as MINIMUM leaves out
 * the lines, is null or an empty list.
 *
 * read() gives an array:
 * - id, typeCode (UNTDID 1001), currency, buyerReference, orderReference, paymentReference: strings or null
 * - issueDate, deliveryDate, dueDate: a DateTime, the text of a date in a format other than 102, or null
 * - notes: string[]
 * - precedingInvoices: [id, issueDate][]
 * - seller, buyer: a party; deliverTo: a party or null. A party is name, address (as Formatter::address() takes it),
 *   vatId, taxNumber, contact (name, phone, email), electronicAddress and electronicAddressScheme
 * - lines: name, description, quantity, unitCode, unitPrice, basisQuantity, vatCategory, vatRate, netAmount and
 *   allowanceCharges
 * - allowanceCharges, on the invoice or a line: charge (bool), amount, reason and reasonCode
 * - vatBreakdown: category, rate, basis, amount, exemptionReason and dueDateCode (UNTDID 2475)
 * - totals: lineTotal, chargeTotal, allowanceTotal, taxBasisTotal, taxTotal, roundingAmount, grandTotal, prepaidAmount
 *   and duePayableAmount, each a float or null
 * - paymentTerms: string or null
 * - paymentMeans: typeCode, information, account, iban (bool), bic, accountName, debitedAccount, mandate, creditorId,
 *   card and cardholder
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/ Factur-X, whose CII this reads
 */
class CiiInvoiceReader
{

	use Strict;

	const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

	const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

	const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

	const NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

	/**
	 * @var \DOMXPath
	 */
	private $xpath;

	/**
	 * The invoice the XML states
	 *
	 * @param string $xml
	 *
	 * @return mixed[] As the class describes
	 *
	 * @throws \Mpdf\MpdfException When the XML does not parse, has a document type declaration, or is not a Cross
	 *                             Industry Invoice
	 */
	public function read($xml)
	{
		$root = $this->load($xml);

		$agreement = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement', $root);
		$delivery = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery', $root);
		$settlement = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement', $root);
		$currency = $this->text('ram:InvoiceCurrencyCode', $settlement);

		$terms = [];
		$dueDate = null;
		$mandate = null;
		foreach ($this->nodes('ram:SpecifiedTradePaymentTerms', $settlement) as $term) {
			$terms[] = $this->text('ram:Description', $term);
			$dueDate = $dueDate !== null ? $dueDate : $this->date('ram:DueDateDateTime', $term);
			$mandate = $mandate !== null ? $mandate : $this->text('ram:DirectDebitMandateID', $term);
		}
		$terms = array_values(array_filter($terms, [$this, 'isFilled']));

		return [
			'id' => $this->text('rsm:ExchangedDocument/ram:ID', $root),
			'typeCode' => $this->text('rsm:ExchangedDocument/ram:TypeCode', $root),
			'issueDate' => $this->date('rsm:ExchangedDocument/ram:IssueDateTime', $root),
			'currency' => $currency,
			'notes' => $this->texts('rsm:ExchangedDocument/ram:IncludedNote/ram:Content', $root),
			'buyerReference' => $this->text('ram:BuyerReference', $agreement),
			'orderReference' => $this->text('ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID', $agreement),
			'deliveryDate' => $this->date('ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime', $delivery),
			'dueDate' => $dueDate,
			'precedingInvoices' => $this->precedingInvoices($settlement),
			'seller' => $this->party($this->node('ram:SellerTradeParty', $agreement)),
			'buyer' => $this->party($this->node('ram:BuyerTradeParty', $agreement)),
			'deliverTo' => $this->party($this->node('ram:ShipToTradeParty', $delivery)),
			'lines' => $this->lines($root),
			'allowanceCharges' => $this->allowanceCharges($settlement),
			'vatBreakdown' => $this->vatBreakdown($settlement),
			'totals' => $this->totals($this->node('ram:SpecifiedTradeSettlementHeaderMonetarySummation', $settlement), $currency),
			'paymentTerms' => $terms ? implode("\n", $terms) : null,
			'paymentReference' => $this->text('ram:PaymentReference', $settlement),
			'paymentMeans' => $this->paymentMeans($settlement, $mandate),
		];
	}

	/**
	 * Parse the XML without reaching the network or taking a DTD, and check it is a Cross Industry Invoice
	 *
	 * @param string $xml
	 *
	 * @return \DOMElement
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function load($xml)
	{
		if (!is_string($xml) || trim($xml) === '') {
			throw new MpdfException('The invoice XML must be a non-empty string.');
		}

		$document = new \DOMDocument();
		$useErrors = libxml_use_internal_errors(true);
		$loaded = $document->loadXML($xml, LIBXML_NONET);
		$error = libxml_get_last_error();
		libxml_clear_errors();
		libxml_use_internal_errors($useErrors);

		if (!$loaded) {
			throw new MpdfException(sprintf('The invoice XML does not parse: %s', $error ? trim($error->message) : 'unknown error'));
		}

		// Cross Industry Invoice XML has no document type declaration, and one could only bring in entities
		if ($document->doctype !== null) {
			throw new MpdfException('The invoice XML has a document type declaration, which Cross Industry Invoice XML never has.');
		}

		$root = $document->documentElement;
		if ($root->namespaceURI !== self::NS_RSM || $root->localName !== 'CrossIndustryInvoice') {
			throw new MpdfException(sprintf('%s reads Cross Industry Invoice XML, not a {%s}%s document.', __CLASS__, $root->namespaceURI, $root->localName));
		}

		$this->xpath = new \DOMXPath($document);
		$this->xpath->registerNamespace('rsm', self::NS_RSM);
		$this->xpath->registerNamespace('ram', self::NS_RAM);
		$this->xpath->registerNamespace('udt', self::NS_UDT);
		$this->xpath->registerNamespace('qdt', self::NS_QDT);

		return $root;
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

		$address = $this->node('ram:PostalTradeAddress', $party);
		$contact = $this->node('ram:DefinedTradeContact', $party);
		$electronicAddress = $this->node('ram:URIUniversalCommunication/ram:URIID', $party);

		return [
			'name' => $this->text('ram:Name', $party),
			'address' => [
				'street' => $this->text('ram:LineOne', $address),
				'additional' => $this->joined([$this->text('ram:LineTwo', $address), $this->text('ram:LineThree', $address)]),
				'postcode' => $this->text('ram:PostcodeCode', $address),
				'city' => $this->text('ram:CityName', $address),
				'subdivision' => $this->text('ram:CountrySubDivisionName', $address),
				'country' => $this->text('ram:CountryID', $address),
			],
			'vatId' => $this->text('ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]', $party),
			'taxNumber' => $this->text('ram:SpecifiedTaxRegistration/ram:ID[@schemeID="FC"]', $party),
			'contact' => [
				'name' => $this->text('ram:PersonName', $contact),
				'phone' => $this->text('ram:TelephoneUniversalCommunication/ram:CompleteNumber', $contact),
				'email' => $this->text('ram:EmailURIUniversalCommunication/ram:URIID', $contact),
			],
			'electronicAddress' => $electronicAddress !== null ? trim($electronicAddress->textContent) : null,
			'electronicAddressScheme' => $electronicAddress instanceof \DOMElement && $electronicAddress->hasAttribute('schemeID') ? $electronicAddress->getAttribute('schemeID') : null,
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
		foreach ($this->nodes('rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem', $root) as $item) {
			$price = $this->node('ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice', $item);
			$quantity = $this->node('ram:SpecifiedLineTradeDelivery/ram:BilledQuantity', $item);
			$settlement = $this->node('ram:SpecifiedLineTradeSettlement', $item);

			$lines[] = [
				'name' => $this->text('ram:SpecifiedTradeProduct/ram:Name', $item),
				'description' => $this->text('ram:SpecifiedTradeProduct/ram:Description', $item),
				'quantity' => $this->amount('.', $quantity),
				'unitCode' => $quantity instanceof \DOMElement && $quantity->hasAttribute('unitCode') ? $quantity->getAttribute('unitCode') : null,
				'unitPrice' => $this->amount('ram:ChargeAmount', $price),
				'basisQuantity' => $this->amount('ram:BasisQuantity', $price),
				'vatCategory' => $this->text('ram:ApplicableTradeTax/ram:CategoryCode', $settlement),
				'vatRate' => $this->amount('ram:ApplicableTradeTax/ram:RateApplicablePercent', $settlement),
				'netAmount' => $this->amount('ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount', $settlement),
				'allowanceCharges' => $this->allowanceCharges($settlement),
			];
		}

		return $lines;
	}

	/**
	 * The allowances and charges on the invoice or a line
	 *
	 * @param \DOMNode|null $settlement
	 *
	 * @return mixed[]
	 */
	private function allowanceCharges($settlement)
	{
		$allowanceCharges = [];
		foreach ($this->nodes('ram:SpecifiedTradeAllowanceCharge', $settlement) as $allowanceCharge) {
			$allowanceCharges[] = [
				'charge' => $this->text('ram:ChargeIndicator/udt:Indicator', $allowanceCharge) === 'true',
				'amount' => $this->amount('ram:ActualAmount', $allowanceCharge),
				'reason' => $this->text('ram:Reason', $allowanceCharge),
				'reasonCode' => $this->text('ram:ReasonCode', $allowanceCharge),
			];
		}

		return $allowanceCharges;
	}

	/**
	 * The VAT of each category and rate
	 *
	 * @param \DOMNode|null $settlement
	 *
	 * @return mixed[]
	 */
	private function vatBreakdown($settlement)
	{
		$breakdown = [];
		foreach ($this->nodes('ram:ApplicableTradeTax', $settlement) as $tax) {
			$breakdown[] = [
				'category' => $this->text('ram:CategoryCode', $tax),
				'rate' => $this->amount('ram:RateApplicablePercent', $tax),
				'basis' => $this->amount('ram:BasisAmount', $tax),
				'amount' => $this->amount('ram:CalculatedAmount', $tax),
				'exemptionReason' => $this->text('ram:ExemptionReason', $tax),
				'dueDateCode' => $this->text('ram:DueDateTypeCode', $tax),
			];
		}

		return $breakdown;
	}

	/**
	 * The invoice's totals, with its VAT in the invoice's own currency where the XML also gives it in another
	 *
	 * @param \DOMNode|null $summation
	 * @param string|null $currency
	 *
	 * @return mixed[]
	 */
	private function totals($summation, $currency)
	{
		$taxTotal = preg_match('/^[A-Z]{3}$/', (string) $currency) ? $this->amount('ram:TaxTotalAmount[@currencyID="' . $currency . '"]', $summation) : null;

		return [
			'lineTotal' => $this->amount('ram:LineTotalAmount', $summation),
			'chargeTotal' => $this->amount('ram:ChargeTotalAmount', $summation),
			'allowanceTotal' => $this->amount('ram:AllowanceTotalAmount', $summation),
			'taxBasisTotal' => $this->amount('ram:TaxBasisTotalAmount', $summation),
			'taxTotal' => $taxTotal !== null ? $taxTotal : $this->amount('ram:TaxTotalAmount', $summation),
			'roundingAmount' => $this->amount('ram:RoundingAmount', $summation),
			'grandTotal' => $this->amount('ram:GrandTotalAmount', $summation),
			'prepaidAmount' => $this->amount('ram:TotalPrepaidAmount', $summation),
			'duePayableAmount' => $this->amount('ram:DuePayableAmount', $summation),
		];
	}

	/**
	 * The invoices this one corrects or follows
	 *
	 * @param \DOMNode|null $settlement
	 *
	 * @return mixed[]
	 */
	private function precedingInvoices($settlement)
	{
		$invoices = [];
		foreach ($this->nodes('ram:InvoiceReferencedDocument', $settlement) as $invoice) {
			$invoices[] = [
				'id' => $this->text('ram:IssuerAssignedID', $invoice),
				'issueDate' => $this->date('ram:FormattedIssueDateTime', $invoice),
			];
		}

		return $invoices;
	}

	/**
	 * Each way to pay; a direct debit takes its mandate from the payment terms and its creditor from the settlement
	 *
	 * @param \DOMNode|null $settlement
	 * @param string|null $mandate
	 *
	 * @return mixed[]
	 */
	private function paymentMeans($settlement, $mandate)
	{
		$means = [];
		foreach ($this->nodes('ram:SpecifiedTradeSettlementPaymentMeans', $settlement) as $paymentMeans) {
			$payee = $this->node('ram:PayeePartyCreditorFinancialAccount', $paymentMeans);
			$iban = $this->text('ram:IBANID', $payee);
			$debitedAccount = $this->text('ram:PayerPartyDebtorFinancialAccount/ram:IBANID', $paymentMeans);

			$means[] = [
				'typeCode' => $this->text('ram:TypeCode', $paymentMeans),
				'information' => $this->text('ram:Information', $paymentMeans),
				'account' => $iban !== null ? $iban : $this->text('ram:ProprietaryID', $payee),
				'iban' => $iban !== null,
				'bic' => $this->text('ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID', $paymentMeans),
				'accountName' => $this->text('ram:AccountName', $payee),
				'debitedAccount' => $debitedAccount,
				'mandate' => $debitedAccount !== null ? $mandate : null,
				'creditorId' => $debitedAccount !== null ? $this->text('ram:CreditorReferenceID', $settlement) : null,
				'card' => $this->text('ram:ApplicableTradeSettlementFinancialCard/ram:ID', $paymentMeans),
				'cardholder' => $this->text('ram:ApplicableTradeSettlementFinancialCard/ram:CardholderName', $paymentMeans),
			];
		}

		return $means;
	}

	/**
	 * A date in format 102 (CCYYMMDD) as a DateTime, a date in any other format as its text, or null when there is none
	 *
	 * @param string $path To the element holding the date string
	 * @param \DOMNode|null $context
	 *
	 * @return \DateTime|string|null
	 */
	private function date($path, $context)
	{
		$date = $this->node($path . '/*[local-name()="DateTimeString"]', $context);
		if ($date === null) {
			return null;
		}

		$text = trim($date->textContent);
		if ($date instanceof \DOMElement && $date->getAttribute('format') === '102' && preg_match('/^\d{8}$/', $text)) {
			$parsed = \DateTime::createFromFormat('!Ymd', $text);
			if ($parsed !== false) {
				return $parsed;
			}
		}

		return $text;
	}

	/**
	 * A number, or null when there is none
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return float|null
	 */
	private function amount($path, $context)
	{
		$text = $this->text($path, $context);

		return $text !== null && is_numeric($text) ? (float) $text : null;
	}

	/**
	 * The trimmed text of the first node the path finds, or null when it finds none or only whitespace
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return string|null
	 */
	private function text($path, $context)
	{
		$node = $this->node($path, $context);
		$text = $node !== null ? trim($node->textContent) : '';

		return $text !== '' ? $text : null;
	}

	/**
	 * The trimmed text of every node the path finds, leaving out those with none
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return string[]
	 */
	private function texts($path, $context)
	{
		$texts = [];
		foreach ($this->nodes($path, $context) as $node) {
			$text = trim($node->textContent);
			if ($text !== '') {
				$texts[] = $text;
			}
		}

		return $texts;
	}

	/**
	 * Lines of text joined by a comma, leaving out those that are not set, or null when none are
	 *
	 * @param mixed[] $parts
	 *
	 * @return string|null
	 */
	private function joined(array $parts)
	{
		$parts = array_filter($parts, [$this, 'isFilled']);

		return $parts ? implode(', ', $parts) : null;
	}

	/**
	 * Whether a text is there
	 *
	 * @param string|null $text
	 *
	 * @return bool
	 */
	private function isFilled($text)
	{
		return $text !== null && $text !== '';
	}

	/**
	 * The first node the path finds, or null
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return \DOMNode|null
	 */
	private function node($path, $context)
	{
		$nodes = $this->nodes($path, $context);

		return $nodes ? $nodes[0] : null;
	}

	/**
	 * Every node the path finds, none when there is no context to search in
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return \DOMNode[]
	 */
	private function nodes($path, $context)
	{
		if ($context === null) {
			return [];
		}

		$nodes = $this->xpath->query($path, $context);

		return $nodes !== false ? iterator_to_array($nodes) : [];
	}

}
