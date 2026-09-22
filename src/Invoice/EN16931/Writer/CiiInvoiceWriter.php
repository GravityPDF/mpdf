<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PdfA3\FacturX;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\TradeDocument;
use Mpdf\MpdfException;

/**
 * Writes an invoice as the Cross Industry Invoice of a Factur-X / ZUGFeRD profile
 *
 * MINIMUM carries the parties and totals, BASIC WL adds addresses, the VAT breakdown and payment details,
 * and EN 16931 adds the lines.
 *
 *     $mpdf->WriteInvoice($invoice, [new CiiInvoiceWriter(FacturX::EN16931)]);
 */
class CiiInvoiceWriter extends CiiWriter
{

	/**
	 * @return string[]
	 */
	protected function getProfiles()
	{
		return [FacturX::MINIMUM, FacturX::BASIC_WL, FacturX::EN16931];
	}

	/**
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function write(TradeDocument $document)
	{
		if (!$document instanceof Invoice) {
			throw new MpdfException(sprintf('%s writes invoices, not %s', __CLASS__, get_class($document)));
		}

		if (!$document->getLines()) {
			throw new MpdfException('An invoice needs at least one line');
		}

		if (!$this->includes(FacturX::BASIC_WL) && $document->getPrepaidAmount() != 0) {
			throw new MpdfException('MINIMUM cannot carry a prepaid amount, so its amount due would not add up; use BASIC WL or above');
		}

		$root = $this->createRoot('rsm:CrossIndustryInvoice', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');

		$context = $this->append($root, 'rsm:ExchangedDocumentContext');
		$guideline = $this->append($context, 'ram:GuidelineSpecifiedDocumentContextParameter');
		$this->append($guideline, 'ram:ID', FacturX::getGuideline($this->getProfile()));

		$exchanged = $this->append($root, 'rsm:ExchangedDocument');
		$this->append($exchanged, 'ram:ID', $document->getId());
		$this->append($exchanged, 'ram:TypeCode', $document->getTypeCode());
		$this->appendDate($exchanged, 'ram:IssueDateTime', $document->getIssueDate());
		if ($this->includes(FacturX::BASIC_WL)) {
			foreach ($document->getNotes() as $note) {
				$this->append($this->append($exchanged, 'ram:IncludedNote'), 'ram:Content', $note);
			}
		}

		$transaction = $this->append($root, 'rsm:SupplyChainTradeTransaction');
		if ($this->includes(FacturX::EN16931)) {
			$this->appendLines($transaction, $document);
		}
		$this->appendAgreement($transaction, $document);
		$this->appendDelivery($transaction, $document);
		$this->appendSettlement($transaction, $document);

		return $root->ownerDocument->saveXML();
	}

	/**
	 * @param \DOMElement $transaction
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendLines(\DOMElement $transaction, Invoice $invoice)
	{
		foreach ($invoice->getLines() as $i => $line) {
			$item = $this->append($transaction, 'ram:IncludedSupplyChainTradeLineItem');
			$this->append($this->append($item, 'ram:AssociatedDocumentLineDocument'), 'ram:LineID', $i + 1);

			$product = $this->append($item, 'ram:SpecifiedTradeProduct');
			$this->append($product, 'ram:Name', $line->getName());
			$this->appendIfSet($product, 'ram:Description', $line->getDescription());

			$price = $this->append($this->append($item, 'ram:SpecifiedLineTradeAgreement'), 'ram:NetPriceProductTradePrice');
			$this->append($price, 'ram:ChargeAmount', $this->decimal($line->getUnitPrice()));

			$delivery = $this->append($item, 'ram:SpecifiedLineTradeDelivery');
			$this->append($delivery, 'ram:BilledQuantity', $this->decimal($line->getQuantity()), ['unitCode' => $line->getUnitCode()]);

			$settlement = $this->append($item, 'ram:SpecifiedLineTradeSettlement');
			$tax = $this->append($settlement, 'ram:ApplicableTradeTax');
			$this->append($tax, 'ram:TypeCode', 'VAT');
			$this->appendCategory($tax, $line->getVatCategory(), $line->getVatRate());
			$summation = $this->append($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
			$this->append($summation, 'ram:LineTotalAmount', $this->amount($line->getNetAmount()));
		}
	}

	/**
	 * @param \DOMElement $transaction
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendAgreement(\DOMElement $transaction, Invoice $invoice)
	{
		$agreement = $this->append($transaction, 'ram:ApplicableHeaderTradeAgreement');
		$this->appendIfSet($agreement, 'ram:BuyerReference', $invoice->getBuyerReference());

		$detailed = $this->includes(FacturX::BASIC_WL);

		$seller = $this->appendParty($agreement, 'ram:SellerTradeParty', $invoice->getSeller());
		$this->appendLocation($seller, $invoice->getSeller(), $detailed);

		$buyer = $this->appendParty($agreement, 'ram:BuyerTradeParty', $invoice->getBuyer());
		if ($detailed) {
			$this->appendLocation($buyer, $invoice->getBuyer(), true);
		}

		if ($invoice->getOrderReference() !== null) {
			$this->append($this->append($agreement, 'ram:BuyerOrderReferencedDocument'), 'ram:IssuerAssignedID', $invoice->getOrderReference());
		}
	}

	/**
	 * A seller or buyer with only its name and legal registration, which is all MINIMUM carries for the buyer
	 *
	 * @param \DOMElement $agreement
	 * @param string $name
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return \DOMElement
	 */
	private function appendParty(\DOMElement $agreement, $name, Party $party)
	{
		$element = $this->append($agreement, $name);
		$this->append($element, 'ram:Name', $party->getName());

		if ($party->getLegalId() !== null) {
			$organization = $this->append($element, 'ram:SpecifiedLegalOrganization');
			$this->append($organization, 'ram:ID', $party->getLegalId(), $party->getLegalIdScheme() !== null ? ['schemeID' => $party->getLegalIdScheme()] : []);
		}

		return $element;
	}

	/**
	 * A party's address and VAT ID: only the country below BASIC WL, the full postal and email address from BASIC WL up
	 *
	 * @param \DOMElement $element
	 * @param \Mpdf\Invoice\Party $party
	 * @param bool $detailed
	 */
	private function appendLocation(\DOMElement $element, Party $party, $detailed)
	{
		$address = $this->append($element, 'ram:PostalTradeAddress');
		if ($detailed) {
			$this->appendIfSet($address, 'ram:PostcodeCode', $party->getPostcode());
			$this->appendIfSet($address, 'ram:LineOne', $party->getStreet());
			$this->appendIfSet($address, 'ram:LineTwo', $party->getAdditionalStreet());
			$this->appendIfSet($address, 'ram:CityName', $party->getCity());
		}
		$this->append($address, 'ram:CountryID', $party->getCountryCode());
		if ($detailed) {
			$this->appendIfSet($address, 'ram:CountrySubDivisionName', $party->getCountrySubdivision());
		}

		if ($detailed && $party->getEmail() !== null) {
			$communication = $this->append($element, 'ram:URIUniversalCommunication');
			$this->append($communication, 'ram:URIID', $party->getEmail(), ['schemeID' => 'EM']);
		}

		if ($party->getVatId() !== null) {
			$registration = $this->append($element, 'ram:SpecifiedTaxRegistration');
			$this->append($registration, 'ram:ID', $party->getVatId(), ['schemeID' => 'VA']);
		}
	}

	/**
	 * @param \DOMElement $transaction
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendDelivery(\DOMElement $transaction, Invoice $invoice)
	{
		$delivery = $this->append($transaction, 'ram:ApplicableHeaderTradeDelivery');
		if ($this->includes(FacturX::BASIC_WL) && $invoice->getDeliveryDate() !== null) {
			$event = $this->append($delivery, 'ram:ActualDeliverySupplyChainEvent');
			$this->appendDate($event, 'ram:OccurrenceDateTime', $invoice->getDeliveryDate());
		}
	}

	/**
	 * @param \DOMElement $transaction
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendSettlement(\DOMElement $transaction, Invoice $invoice)
	{
		$settlement = $this->append($transaction, 'ram:ApplicableHeaderTradeSettlement');
		$currency = $invoice->getCurrency();
		$detailed = $this->includes(FacturX::BASIC_WL);

		if ($detailed) {
			$this->appendIfSet($settlement, 'ram:PaymentReference', $invoice->getPaymentReference());
		}
		$this->append($settlement, 'ram:InvoiceCurrencyCode', $currency);

		if ($detailed) {
			$this->appendPaymentMeans($settlement, $invoice);

			foreach ($invoice->getVatBreakdown() as $group) {
				$tax = $this->append($settlement, 'ram:ApplicableTradeTax');
				$this->append($tax, 'ram:CalculatedAmount', $this->amount($group['amount']));
				$this->append($tax, 'ram:TypeCode', 'VAT');
				$this->appendIfSet($tax, 'ram:ExemptionReason', $invoice->getExemptionReason($group['category']));
				$this->append($tax, 'ram:BasisAmount', $this->amount($group['basis']));
				$this->appendCategory($tax, $group['category'], $group['rate']);
			}

			if ($invoice->getPaymentTerms() !== null || $invoice->getDueDate() !== null) {
				$terms = $this->append($settlement, 'ram:SpecifiedTradePaymentTerms');
				$this->appendIfSet($terms, 'ram:Description', $invoice->getPaymentTerms());
				if ($invoice->getDueDate() !== null) {
					$this->appendDate($terms, 'ram:DueDateDateTime', $invoice->getDueDate());
				}
			}
		}

		$summation = $this->append($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
		if ($detailed) {
			$this->append($summation, 'ram:LineTotalAmount', $this->amount($invoice->getLineTotal()));
		}
		$this->append($summation, 'ram:TaxBasisTotalAmount', $this->amount($invoice->getLineTotal()));
		$this->append($summation, 'ram:TaxTotalAmount', $this->amount($invoice->getTaxTotal()), ['currencyID' => $currency]);
		$this->append($summation, 'ram:GrandTotalAmount', $this->amount($invoice->getGrandTotal()));
		if ($detailed && $invoice->getPrepaidAmount() != 0) {
			$this->append($summation, 'ram:TotalPrepaidAmount', $this->amount($invoice->getPrepaidAmount()));
		}
		$this->append($summation, 'ram:DuePayableAmount', $this->amount($invoice->getDuePayableAmount()));
	}

	/**
	 * The VAT category and its rate. Category O (not subject to VAT) has no rate in EN 16931
	 *
	 * @param \DOMElement $tax
	 * @param string $category
	 * @param float $rate
	 */
	private function appendCategory(\DOMElement $tax, $category, $rate)
	{
		$this->append($tax, 'ram:CategoryCode', $category);
		if ($category !== LineItem::NOT_SUBJECT_TO_VAT) {
			$this->append($tax, 'ram:RateApplicablePercent', $this->decimal($rate));
		}
	}

	/**
	 * A credit transfer to the seller's account: SEPA (58) for euros, any other (30) otherwise. BASIC WL carries only the IBAN.
	 *
	 * @param \DOMElement $settlement
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendPaymentMeans(\DOMElement $settlement, Invoice $invoice)
	{
		if ($invoice->getIban() === null) {
			return;
		}

		$means = $this->append($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
		$this->append($means, 'ram:TypeCode', $invoice->getCurrency() === 'EUR' ? '58' : '30');

		$account = $this->append($means, 'ram:PayeePartyCreditorFinancialAccount');
		$this->append($account, 'ram:IBANID', $invoice->getIban());

		if (!$this->includes(FacturX::EN16931)) {
			return;
		}

		$this->appendIfSet($account, 'ram:AccountName', $invoice->getAccountName());
		if ($invoice->getBic() !== null) {
			$this->append($this->append($means, 'ram:PayeeSpecifiedCreditorFinancialInstitution'), 'ram:BICID', $invoice->getBic());
		}
	}

}
