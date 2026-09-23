<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\Rules;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\PdfA3\FacturX;
use Mpdf\Invoice\TradeDocument;
use Mpdf\MpdfException;

/**
 * Writes an invoice as the Cross Industry Invoice of a Factur-X / ZUGFeRD profile
 *
 * MINIMUM carries the parties and totals, BASIC WL adds addresses, the VAT breakdown, allowances, charges and payment
 * details, EN 16931 adds the lines, and XRECHNUNG writes EN 16931 as Germany's XRechnung 3.0 requires. Only EN 16931
 * and XRechnung are full e-invoices: Germany accepts neither MINIMUM nor BASIC WL as one, and France's reform accepts
 * BASIC WL only until September 2027 and MINIMUM never.
 *
 * From BASIC WL up the invoice is first checked against the EN 16931 business rules its content can break, and XRechnung's
 * too for XRECHNUNG, and refused with the rules it breaks.
 *
 * forFrance() adds the checks of France's 2026 reform, whose platforms take EN 16931, and BASIC WL until September
 * 2027.
 *
 * XRechnung is meant to be sent as XML on its own: the German administration does not take it embedded in a PDF,
 * though ZUGFeRD defines an XRECHNUNG profile that embeds it as xrechnung.xml.
 *
 *     $mpdf->WriteInvoice($invoice, [new CiiInvoiceWriter(FacturX::EN16931)]);
 */
class CiiInvoiceWriter extends CiiWriter
{

	/**
	 * The business process XRechnung requires, and names when the buyer asks for none
	 *
	 * @var string
	 */
	private static $peppolBillingProcess = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

	/**
	 * @var bool
	 */
	private $france = false;

	/**
	 * @return string[]
	 */
	protected function getProfiles()
	{
		return [FacturX::MINIMUM, FacturX::BASIC_WL, FacturX::EN16931, FacturX::XRECHNUNG];
	}

	/**
	 * Check invoices against the rules France's 2026 e-invoicing reform adds too, as its platforms will
	 *
	 * @return $this
	 *
	 * @throws \Mpdf\MpdfException When the profile is one France does not take
	 */
	public function forFrance()
	{
		if (!$this->includes(FacturX::BASIC_WL) || $this->includes(FacturX::XRECHNUNG)) {
			throw new MpdfException(sprintf('France\'s reform does not take %s; use EN 16931, or BASIC WL until September 2027', $this->getProfile()));
		}

		$this->france = true;

		return $this;
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

		if ($this->france) {
			Rules::checkFrance($document);
		} elseif ($this->includes(FacturX::XRECHNUNG)) {
			Rules::checkXRechnung($document);
		} elseif ($this->includes(FacturX::BASIC_WL)) {
			Rules::check($document);
		}

		$root = $this->createRoot('rsm:CrossIndustryInvoice', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');

		$context = $this->append($root, 'rsm:ExchangedDocumentContext');
		$process = $document->getBusinessProcess();
		if ($process === null && $this->includes(FacturX::XRECHNUNG)) {
			$process = self::$peppolBillingProcess;
		}
		if ($process !== null) {
			$this->append($this->append($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter'), 'ram:ID', $process);
		}
		$this->append($this->append($context, 'ram:GuidelineSpecifiedDocumentContextParameter'), 'ram:ID', FacturX::getGuideline($this->getProfile()));

		$exchanged = $this->append($root, 'rsm:ExchangedDocument');
		$this->append($exchanged, 'ram:ID', $document->getId());
		$this->append($exchanged, 'ram:TypeCode', $document->getTypeCode());
		$this->appendDate($exchanged, 'ram:IssueDateTime', $document->getIssueDate());
		if ($this->includes(FacturX::BASIC_WL)) {
			foreach ($document->getNotes() as $note) {
				$included = $this->append($exchanged, 'ram:IncludedNote');
				$this->append($included, 'ram:Content', $note['content']);
				$this->appendIfSet($included, 'ram:SubjectCode', $note['subjectCode']);
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
			foreach ($line->getAllowanceCharges() as $allowanceCharge) {
				$this->appendAllowanceCharge($settlement, $allowanceCharge);
			}
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
	 * @param \DOMElement $parent
	 * @param string $name
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return \DOMElement
	 */
	private function appendParty(\DOMElement $parent, $name, Party $party)
	{
		$element = $this->append($parent, $name);
		$this->append($element, 'ram:Name', $party->getName());

		if ($party->getLegalId() !== null) {
			$organization = $this->append($element, 'ram:SpecifiedLegalOrganization');
			$this->append($organization, 'ram:ID', $party->getLegalId(), $party->getLegalIdScheme() !== null ? ['schemeID' => $party->getLegalIdScheme()] : []);
		}

		return $element;
	}

	/**
	 * A party's contact, address and tax registrations: only the country below BASIC WL, the full postal and
	 * electronic address from BASIC WL up, and the contact from EN 16931 up
	 *
	 * @param \DOMElement $element
	 * @param \Mpdf\Invoice\Party $party
	 * @param bool $detailed
	 */
	private function appendLocation(\DOMElement $element, Party $party, $detailed)
	{
		if ($this->includes(FacturX::EN16931) && $party->getContactName() !== null) {
			$contact = $this->append($element, 'ram:DefinedTradeContact');
			$this->append($contact, 'ram:PersonName', $party->getContactName());
			if ($party->getContactPhone() !== null) {
				$this->append($this->append($contact, 'ram:TelephoneUniversalCommunication'), 'ram:CompleteNumber', $party->getContactPhone());
			}
			if ($party->getContactEmail() !== null) {
				$this->append($this->append($contact, 'ram:EmailURIUniversalCommunication'), 'ram:URIID', $party->getContactEmail());
			}
		}

		$this->appendAddress($element, $party, $detailed);

		if ($detailed && $party->getElectronicAddress() !== null) {
			$communication = $this->append($element, 'ram:URIUniversalCommunication');
			$this->append($communication, 'ram:URIID', $party->getElectronicAddress(), ['schemeID' => $party->getElectronicAddressScheme()]);
		}

		if ($party->getTaxNumber() !== null) {
			$this->append($this->append($element, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $party->getTaxNumber(), ['schemeID' => 'FC']);
		}

		if ($party->getVatId() !== null) {
			$this->append($this->append($element, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $party->getVatId(), ['schemeID' => 'VA']);
		}
	}

	/**
	 * @param \DOMElement $element
	 * @param \Mpdf\Invoice\Party $party
	 * @param bool $detailed Whether to write the street, city and state, or only the country
	 */
	private function appendAddress(\DOMElement $element, Party $party, $detailed)
	{
		$address = $this->append($element, 'ram:PostalTradeAddress');
		if ($detailed) {
			$this->appendIfSet($address, 'ram:PostcodeCode', $party->getPostcode());
			$this->appendIfSet($address, 'ram:LineOne', $party->getStreet());
			$this->appendIfSet($address, 'ram:LineTwo', $party->getAdditionalStreet());
			$this->appendIfSet($address, 'ram:CityName', $party->getCity());
		}
		$this->append($address, 'ram:CountryID', $party->getCountryCode());
		// The schema puts the state after the country, so it cannot join the lines above
		if ($detailed) {
			$this->appendIfSet($address, 'ram:CountrySubDivisionName', $party->getCountrySubdivision());
		}
	}

	/**
	 * @param \DOMElement $transaction
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 */
	private function appendDelivery(\DOMElement $transaction, Invoice $invoice)
	{
		$delivery = $this->append($transaction, 'ram:ApplicableHeaderTradeDelivery');
		if (!$this->includes(FacturX::BASIC_WL)) {
			return;
		}

		if ($invoice->getDeliverTo() !== null) {
			$shipTo = $this->append($delivery, 'ram:ShipToTradeParty');
			$this->append($shipTo, 'ram:Name', $invoice->getDeliverTo()->getName());
			$this->appendAddress($shipTo, $invoice->getDeliverTo(), true);
		}

		if ($invoice->getDeliveryDate() !== null) {
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
		$directDebit = $this->findDirectDebit($invoice);

		if ($detailed) {
			if ($directDebit !== null) {
				$this->append($settlement, 'ram:CreditorReferenceID', $directDebit->getCreditorId());
			}
			$this->appendIfSet($settlement, 'ram:PaymentReference', $invoice->getPaymentReference());
		}
		$this->append($settlement, 'ram:InvoiceCurrencyCode', $currency);

		if ($detailed) {
			foreach ($invoice->getPaymentMeans() as $means) {
				$this->appendPaymentMeans($settlement, $means);
			}

			foreach ($invoice->getVatBreakdown() as $group) {
				$tax = $this->append($settlement, 'ram:ApplicableTradeTax');
				$this->append($tax, 'ram:CalculatedAmount', $this->amount($group['amount']));
				$this->append($tax, 'ram:TypeCode', 'VAT');
				$this->appendIfSet($tax, 'ram:ExemptionReason', $invoice->getExemptionReason($group['category']));
				$this->append($tax, 'ram:BasisAmount', $this->amount($group['basis']));
				$this->appendCategory($tax, $group['category'], $group['rate'], $this->includes(FacturX::XRECHNUNG), $invoice->isVatOnDebits());
			}

			foreach ($invoice->getAllowanceCharges() as $allowanceCharge) {
				$this->appendAllowanceCharge($settlement, $allowanceCharge);
			}

			$this->appendPaymentTerms($settlement, $invoice, $directDebit);
		}

		$summation = $this->append($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
		if ($detailed) {
			$this->append($summation, 'ram:LineTotalAmount', $this->amount($invoice->getLineTotal()));
			if ($invoice->getAllowanceCharges()) {
				$this->append($summation, 'ram:ChargeTotalAmount', $this->amount($invoice->getChargeTotal()));
				$this->append($summation, 'ram:AllowanceTotalAmount', $this->amount($invoice->getAllowanceTotal()));
			}
		}
		$this->append($summation, 'ram:TaxBasisTotalAmount', $this->amount($invoice->getTaxBasisTotal()));
		$this->append($summation, 'ram:TaxTotalAmount', $this->amount($invoice->getTaxTotal()), ['currencyID' => $currency]);
		$this->append($summation, 'ram:GrandTotalAmount', $this->amount($invoice->getGrandTotal()));
		if ($detailed && $invoice->getPrepaidAmount() != 0) {
			$this->append($summation, 'ram:TotalPrepaidAmount', $this->amount($invoice->getPrepaidAmount()));
		}
		$this->append($summation, 'ram:DuePayableAmount', $this->amount($invoice->getDuePayableAmount()));

		if ($detailed) {
			foreach ($invoice->getPrecedingInvoices() as $preceding) {
				$reference = $this->append($settlement, 'ram:InvoiceReferencedDocument');
				$this->append($reference, 'ram:IssuerAssignedID', $preceding['id']);
				if ($preceding['issueDate'] !== null) {
					$this->appendDate($reference, 'ram:FormattedIssueDateTime', $preceding['issueDate'], 'qdt');
				}
			}
		}
	}

	/**
	 * The terms, due date and direct debit mandate, when there are any
	 *
	 * @param \DOMElement $settlement
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\PaymentMeans|null $directDebit
	 */
	private function appendPaymentTerms(\DOMElement $settlement, Invoice $invoice, $directDebit)
	{
		if ($invoice->getPaymentTerms() === null && $invoice->getDueDate() === null && $directDebit === null) {
			return;
		}

		$terms = $this->append($settlement, 'ram:SpecifiedTradePaymentTerms');
		$this->appendIfSet($terms, 'ram:Description', $invoice->getPaymentTerms());
		if ($invoice->getDueDate() !== null) {
			$this->appendDate($terms, 'ram:DueDateDateTime', $invoice->getDueDate());
		}
		if ($directDebit !== null) {
			$this->append($terms, 'ram:DirectDebitMandateID', $directDebit->getMandateReference());
		}
	}

	/**
	 * An allowance or charge: with the VAT it bears on the invoice as a whole, without it on a line, which takes the line's
	 *
	 * @param \DOMElement $parent
	 * @param \Mpdf\Invoice\AllowanceCharge $allowanceCharge
	 */
	private function appendAllowanceCharge(\DOMElement $parent, AllowanceCharge $allowanceCharge)
	{
		$element = $this->append($parent, 'ram:SpecifiedTradeAllowanceCharge');
		$this->append($this->append($element, 'ram:ChargeIndicator'), 'udt:Indicator', $allowanceCharge->isCharge() ? 'true' : 'false');
		if ($allowanceCharge->getPercent() !== null) {
			$this->append($element, 'ram:CalculationPercent', $this->decimal($allowanceCharge->getPercent()));
			$this->append($element, 'ram:BasisAmount', $this->amount($allowanceCharge->getBasis()));
		}
		$this->append($element, 'ram:ActualAmount', $this->amount($allowanceCharge->getAmount()));
		$this->appendIfSet($element, 'ram:ReasonCode', $allowanceCharge->getReasonCode());
		$this->appendIfSet($element, 'ram:Reason', $allowanceCharge->getReason());

		if ($allowanceCharge->getVatCategory() !== null) {
			$tax = $this->append($element, 'ram:CategoryTradeTax');
			$this->append($tax, 'ram:TypeCode', 'VAT');
			$this->appendCategory($tax, $allowanceCharge->getVatCategory(), $allowanceCharge->getVatRate());
		}
	}

	/**
	 * The VAT category and its rate. Category O (not subject to VAT) has no rate in EN 16931, but XRechnung wants one,
	 * 0, in the VAT breakdown
	 *
	 * @param \DOMElement $tax
	 * @param string $category
	 * @param float $rate
	 * @param bool $alwaysRate
	 * @param bool $onDebits Whether the VAT falls due when invoiced, which the breakdown gives as due date type 5
	 */
	private function appendCategory(\DOMElement $tax, $category, $rate, $alwaysRate = false, $onDebits = false)
	{
		$this->append($tax, 'ram:CategoryCode', $category);
		if ($onDebits) {
			$this->append($tax, 'ram:DueDateTypeCode', '5');
		}
		if ($alwaysRate || $category !== LineItem::NOT_SUBJECT_TO_VAT) {
			$this->append($tax, 'ram:RateApplicablePercent', $this->decimal($rate));
		}
	}

	/**
	 * A way to pay. BASIC WL carries the accounts alone; EN 16931 adds the card, the account name, the BIC and the
	 * means in words.
	 *
	 * @param \DOMElement $settlement
	 * @param \Mpdf\Invoice\PaymentMeans $means
	 */
	private function appendPaymentMeans(\DOMElement $settlement, PaymentMeans $means)
	{
		$full = $this->includes(FacturX::EN16931);

		$element = $this->append($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
		$this->append($element, 'ram:TypeCode', $means->getTypeCode());

		if ($full) {
			$this->appendIfSet($element, 'ram:Information', $means->getInformation());
			if ($means->getCardNumber() !== null) {
				$card = $this->append($element, 'ram:ApplicableTradeSettlementFinancialCard');
				$this->append($card, 'ram:ID', $means->getCardNumber());
				$this->appendIfSet($card, 'ram:CardholderName', $means->getCardholderName());
			}
		}

		if ($means->getDebitedAccount() !== null) {
			$this->append($this->append($element, 'ram:PayerPartyDebtorFinancialAccount'), 'ram:IBANID', $means->getDebitedAccount());
		}

		if ($means->getAccount() !== null) {
			$account = $this->append($element, 'ram:PayeePartyCreditorFinancialAccount');
			if ($means->isIban()) {
				$this->append($account, 'ram:IBANID', $means->getAccount());
			}
			if ($full) {
				$this->appendIfSet($account, 'ram:AccountName', $means->getAccountName());
			}
			if (!$means->isIban()) {
				$this->append($account, 'ram:ProprietaryID', $means->getAccount());
			}

			if ($full && $means->getBic() !== null) {
				$this->append($this->append($element, 'ram:PayeeSpecifiedCreditorFinancialInstitution'), 'ram:BICID', $means->getBic());
			}
		}
	}

	/**
	 * The SEPA direct debit the invoice is paid by, whose mandate and creditor identifier CII writes apart from it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return \Mpdf\Invoice\PaymentMeans|null
	 */
	private function findDirectDebit(Invoice $invoice)
	{
		foreach ($invoice->getPaymentMeans() as $means) {
			if ($means->getMandateReference() !== null) {
				return $means;
			}
		}

		return null;
	}

}
