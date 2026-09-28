<?php

namespace Mpdf\Invoice;

/**
 * Reads what a printed invoice shows from Cross Industry Invoice (CII) XML, the syntax Factur-X, ZUGFeRD and XRechnung
 * CII share, into the array AbstractInvoiceReader describes
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/ Factur-X, whose CII this reads
 */
class CiiInvoiceReader extends AbstractInvoiceReader
{

	const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

	const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

	const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

	/**
	 * The invoice a parsed document states
	 *
	 * @param \DOMDocument $document
	 *
	 * @return mixed[] As AbstractInvoiceReader describes
	 *
	 * @throws \Mpdf\MpdfException When the document is not a Cross Industry Invoice
	 */
	protected function readDocument(\DOMDocument $document)
	{
		$root = $this->root($document, 'Cross Industry Invoice XML', ['{' . self::NS_RSM . '}CrossIndustryInvoice'], [
			'rsm' => self::NS_RSM,
			'ram' => self::NS_RAM,
			'udt' => self::NS_UDT,
		]);

		$agreement = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement', $root);
		$delivery = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery', $root);
		$settlement = $this->node('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement', $root);

		list($terms, $discounts) = $this->paymentTerms($this->texts('ram:SpecifiedTradePaymentTerms/ram:Description', $settlement));
		foreach ($this->nodes('ram:SpecifiedTradePaymentTerms/ram:ApplicableTradePaymentDiscountTerms', $settlement) as $discount) {
			$discounts[] = $this->paymentAdjustment($discount, 'ram:ActualDiscountAmount');
		}
		$penalties = [];
		foreach ($this->nodes('ram:SpecifiedTradePaymentTerms/ram:ApplicableTradePaymentPenaltyTerms', $settlement) as $penalty) {
			$penalties[] = $this->paymentAdjustment($penalty, 'ram:ActualPenaltyAmount');
		}

		return [
			'id' => $this->text('rsm:ExchangedDocument/ram:ID', $root),
			'typeCode' => $this->text('rsm:ExchangedDocument/ram:TypeCode', $root),
			'issueDate' => $this->date('rsm:ExchangedDocument/ram:IssueDateTime', $root),
			'currency' => $this->text('ram:InvoiceCurrencyCode', $settlement),
			'notes' => $this->texts('rsm:ExchangedDocument/ram:IncludedNote/ram:Content', $root),
			'buyerReference' => $this->text('ram:BuyerReference', $agreement),
			'orderReference' => $this->text('ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID', $agreement),
			'deliveryDate' => $this->date('ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime', $delivery),
			'dueDate' => $this->date('ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime', $settlement),
			'precedingInvoices' => $this->precedingInvoices($settlement),
			'seller' => $this->party($this->node('ram:SellerTradeParty', $agreement)),
			'buyer' => $this->party($this->node('ram:BuyerTradeParty', $agreement)),
			'deliverTo' => $this->party($this->node('ram:ShipToTradeParty', $delivery)),
			'lines' => $this->lines($root),
			'allowanceCharges' => $this->allowanceCharges($settlement),
			'vatBreakdown' => $this->vatBreakdown($settlement),
			'totals' => $this->totals($this->node('ram:SpecifiedTradeSettlementHeaderMonetarySummation', $settlement)),
			'paymentTerms' => $terms,
			'paymentDiscounts' => $discounts,
			'paymentPenalties' => $penalties,
			'paymentReference' => $this->text('ram:PaymentReference', $settlement),
			'paymentMeans' => $this->paymentMeans($settlement),
		];
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
			'electronicAddress' => $this->text('ram:URIUniversalCommunication/ram:URIID', $party),
			'electronicAddressScheme' => $this->text('ram:URIUniversalCommunication/ram:URIID/@schemeID', $party),
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
				'unitCode' => $this->text('@unitCode', $quantity),
				'unitPrice' => $this->amount('ram:ChargeAmount', $price),
				'basisQuantity' => $this->amount('ram:BasisQuantity', $price),
				'basisQuantityUnit' => $this->text('ram:BasisQuantity/@unitCode', $price),
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
	 *
	 * @return mixed[]
	 */
	private function totals($summation)
	{
		$taxTotal = $this->amount('ram:TaxTotalAmount[@currencyID = ../../ram:InvoiceCurrencyCode]', $summation);

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
	 *
	 * @return mixed[]
	 */
	private function paymentMeans($settlement)
	{
		$mandate = $this->text('ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID', $settlement);
		$creditorId = $this->text('ram:CreditorReferenceID', $settlement);

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
				'creditorId' => $debitedAccount !== null ? $creditorId : null,
				'card' => $this->text('ram:ApplicableTradeSettlementFinancialCard/ram:ID', $paymentMeans),
				'cardholder' => $this->text('ram:ApplicableTradeSettlementFinancialCard/ram:CardholderName', $paymentMeans),
			];
		}

		return $means;
	}

	/**
	 * An early payment discount or late payment penalty, as EXTENDED gives them in the payment terms
	 *
	 * @param \DOMNode $terms
	 * @param string $amountPath To the amount of the discount or penalty itself
	 *
	 * @return mixed[]
	 */
	private function paymentAdjustment(\DOMNode $terms, $amountPath)
	{
		return [
			'percent' => $this->amount('ram:CalculationPercent', $terms),
			'amount' => $this->amount($amountPath, $terms),
			'basisAmount' => $this->amount('ram:BasisAmount', $terms),
			'period' => $this->amount('ram:BasisPeriodMeasure', $terms),
			'periodUnit' => $this->text('ram:BasisPeriodMeasure/@unitCode', $terms),
			'basisDate' => $this->date('ram:BasisDateTime', $terms),
		];
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

}
