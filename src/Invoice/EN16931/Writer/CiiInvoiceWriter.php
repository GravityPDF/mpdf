<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\EN16931\Cius\CiusInterface;
use Mpdf\Invoice\EN16931\Cius\EN16931;
use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\Rules;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\Output\EmbeddedInvoiceOutput;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\VatCategory;
use Mpdf\MpdfException;

/**
 * Writes an invoice as the Cross Industry Invoice of a Factur-X / ZUGFeRD profile, and embeds it as Factur-X
 *
 * MINIMUM carries the parties and totals, BASIC WL adds addresses, the VAT breakdown, allowances, charges and payment
 * details, and EN 16931 adds the lines. Which profile a country takes, if any, is for the caller to check; the writer
 * writes the one it is given.
 *
 * From BASIC WL up the invoice is first checked against some of the EN 16931 business rules its content can break, and
 * refused with the rules it breaks. withCius() applies a country's or network's specification on top, such as
 * XRechnung or France's reform, adding its rules and whatever it writes differently; the writer names no country
 * itself. The checks catch common mistakes early; passing them does not make the XML valid or the invoice compliant,
 * so validate the XML before sending it.
 *
 *     DocumentComposer::compose($mpdf, $invoice, [new CiiInvoiceWriter(FacturX::EN16931)]);
 *     $xrechnung = (new CiiInvoiceWriter(FacturX::EN16931))->withCius(new XRechnung())->write($invoice);
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/ Factur-X, the specification shared with ZUGFeRD
 */
class CiiInvoiceWriter extends CiiWriter
{

	/**
	 * The guideline ID each profile's invoice names in ExchangedDocumentContext, the IDs FacturX reads the level from
	 *
	 * @var string[]
	 */
	private static $guidelines = [
		FacturX::MINIMUM => FacturX::GUIDELINE_MINIMUM,
		FacturX::BASIC_WL => FacturX::GUIDELINE_BASIC_WL,
		FacturX::EN16931 => FacturX::GUIDELINE_EN16931,
	];

	/**
	 * The specification applied on top of EN 16931
	 *
	 * @var \Mpdf\Invoice\EN16931\Cius\CiusInterface
	 */
	private $cius;

	/**
	 * @param string $profile One of MINIMUM, BASIC WL and EN 16931, e.g. FacturX::EN16931
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($profile)
	{
		parent::__construct($profile);
		$this->cius = new EN16931();
	}

	/**
	 * @return string[]
	 */
	protected function getProfiles()
	{
		return array_keys(self::$guidelines);
	}

	/**
	 * Apply a country's or network's specification on top of EN 16931, in place of any applied before
	 *
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius
	 *
	 * @return $this
	 *
	 * @throws \Mpdf\MpdfException When the specification does not take the writer's profile
	 */
	public function withCius(CiusInterface $cius)
	{
		$profiles = $cius->getProfiles();
		if ($profiles !== null && !in_array($this->getProfile(), $profiles, true)) {
			throw new MpdfException(sprintf('%s does not take the %s profile; use %s', $cius->getName(), $this->getProfile(), implode(' or ', $profiles)));
		}

		$this->cius = $cius;

		return $this;
	}

	/**
	 * The invoice written as Factur-X / ZUGFeRD, for DocumentComposer to embed
	 *
	 * FacturX reads the level from the guideline the invoice names, which a specification may change, e.g. XRechnung's.
	 *
	 * @param \Mpdf\Invoice\TradeDocument $document
	 *
	 * @return \Mpdf\Invoice\Output\EmbeddedInvoiceOutput
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function output(TradeDocument $document)
	{
		return new EmbeddedInvoiceOutput(new FacturX($this->write($document)));
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
			throw new MpdfException('BR-16: an invoice needs at least one line');
		}

		if (!$this->includes(FacturX::BASIC_WL) && $document->getPrepaidAmount() != 0) {
			throw new MpdfException('MINIMUM cannot carry a prepaid amount, so its amount due would not add up; use BASIC WL or above');
		}

		if ($this->includes(FacturX::BASIC_WL)) {
			Rules::check($document, $this->cius);
		}

		$root = $this->createRoot('rsm:CrossIndustryInvoice', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');

		$context = $this->append($root, 'rsm:ExchangedDocumentContext');
		$process = $this->cius->getBusinessProcess($document);
		if ($process !== null) {
			$this->append($this->append($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter'), 'ram:ID', $process);
		}
		$this->append($this->append($context, 'ram:GuidelineSpecifiedDocumentContextParameter'), 'ram:ID', $this->getGuideline());

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

		$seller = $this->appendParty($agreement, 'ram:SellerTradeParty', $invoice->getSeller());
		$this->appendLocation($seller, $invoice->getSeller());

		$buyer = $this->appendParty($agreement, 'ram:BuyerTradeParty', $invoice->getBuyer());
		if ($this->includes(FacturX::BASIC_WL)) {
			$this->appendLocation($buyer, $invoice->getBuyer());
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
	 */
	private function appendLocation(\DOMElement $element, Party $party)
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

		$this->appendAddress($element, $party);

		if ($this->includes(FacturX::BASIC_WL) && $party->getElectronicAddress() !== null) {
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
	 * A party's postal address: only the country below BASIC WL
	 *
	 * @param \DOMElement $element
	 * @param \Mpdf\Invoice\Party $party
	 */
	private function appendAddress(\DOMElement $element, Party $party)
	{
		$detailed = $this->includes(FacturX::BASIC_WL);
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
			$this->appendAddress($shipTo, $invoice->getDeliverTo());
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
		$totals = $invoice->getTotals();

		if ($detailed) {
			if ($directDebit !== null) {
				$this->appendIfSet($settlement, 'ram:CreditorReferenceID', $directDebit->getCreditorId());
			}
			$this->appendIfSet($settlement, 'ram:PaymentReference', $invoice->getPaymentReference());
		}
		$this->append($settlement, 'ram:InvoiceCurrencyCode', $currency);

		if ($detailed) {
			foreach ($invoice->getPaymentMeans() as $means) {
				$this->appendPaymentMeans($settlement, $means);
			}

			foreach ($totals->getVatBreakdown() as $group) {
				$tax = $this->append($settlement, 'ram:ApplicableTradeTax');
				$this->append($tax, 'ram:CalculatedAmount', $this->amount($group['amount']));
				$this->append($tax, 'ram:TypeCode', 'VAT');
				$this->appendIfSet($tax, 'ram:ExemptionReason', $invoice->getExemptionReason($group['category']));
				$this->append($tax, 'ram:BasisAmount', $this->amount($group['basis']));
				$this->append($tax, 'ram:CategoryCode', $group['category']);
				$this->appendIfSet($tax, 'ram:DueDateTypeCode', $invoice->getVatDueDateCode());
				if ($this->cius->givesRate($group['category'])) {
					$this->append($tax, 'ram:RateApplicablePercent', $this->decimal($group['rate']));
				}
			}

			foreach ($invoice->getAllowanceCharges() as $allowanceCharge) {
				$this->appendAllowanceCharge($settlement, $allowanceCharge);
			}

			$this->appendPaymentTerms($settlement, $invoice, $directDebit === null ? null : $directDebit->getMandateReference());
		}

		$summation = $this->append($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
		if ($detailed) {
			$this->append($summation, 'ram:LineTotalAmount', $this->amount($totals->getLineTotal()));
			if ($invoice->getAllowanceCharges()) {
				$this->append($summation, 'ram:ChargeTotalAmount', $this->amount($totals->getChargeTotal()));
				$this->append($summation, 'ram:AllowanceTotalAmount', $this->amount($totals->getAllowanceTotal()));
			}
		}
		$this->append($summation, 'ram:TaxBasisTotalAmount', $this->amount($totals->getTaxBasisTotal()));
		$this->append($summation, 'ram:TaxTotalAmount', $this->amount($totals->getTaxTotal()), ['currencyID' => $currency]);
		$this->append($summation, 'ram:GrandTotalAmount', $this->amount($totals->getGrandTotal()));
		if ($detailed && $invoice->getPrepaidAmount() != 0) {
			$this->append($summation, 'ram:TotalPrepaidAmount', $this->amount($invoice->getPrepaidAmount()));
		}
		$this->append($summation, 'ram:DuePayableAmount', $this->amount($totals->getDuePayableAmount()));

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
	 * @param string|null $mandate The direct debit's mandate reference (BT-89)
	 */
	private function appendPaymentTerms(\DOMElement $settlement, Invoice $invoice, $mandate)
	{
		if ($invoice->getPaymentTerms() === null && $invoice->getDueDate() === null && $mandate === null) {
			return;
		}

		$terms = $this->append($settlement, 'ram:SpecifiedTradePaymentTerms');
		$this->appendIfSet($terms, 'ram:Description', $invoice->getPaymentTerms());
		if ($invoice->getDueDate() !== null) {
			$this->appendDate($terms, 'ram:DueDateDateTime', $invoice->getDueDate());
		}
		$this->appendIfSet($terms, 'ram:DirectDebitMandateID', $mandate);
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
	 * A line's or an allowance's VAT category, and its rate unless it is O (not subject to VAT), which has none
	 *
	 * EN 16931 forbids the rate of O on a line (BR-O-05), an allowance (BR-O-06) and a charge (BR-O-07), and no CIUS
	 * may ask for it: XRechnung's BR-DE-14 wants a rate in the VAT breakdown alone, which CiusInterface::givesRate()
	 * decides. This is not a CIUS hook for that reason.
	 *
	 * @see https://github.com/ConnectingEurope/eInvoicing-EN16931 The EN 16931 validation artefacts
	 *
	 * @param \DOMElement $tax
	 * @param string $category
	 * @param float $rate
	 */
	private function appendCategory(\DOMElement $tax, $category, $rate)
	{
		$this->append($tax, 'ram:CategoryCode', $category);
		if (VatCategory::hasRate($category)) {
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
	 * The guideline ID the invoice names in ExchangedDocumentContext: the specification's, or else the profile's
	 *
	 * @return string
	 */
	private function getGuideline()
	{
		$guideline = $this->cius->getGuideline();

		return $guideline === null ? self::$guidelines[$this->getProfile()] : $guideline;
	}

	/**
	 * The direct debit the invoice is paid by, whose mandate and creditor identifier (BT-89, BT-90, both optional) CII
	 * writes apart from it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return \Mpdf\Invoice\PaymentMeans|null
	 */
	private function findDirectDebit(Invoice $invoice)
	{
		foreach ($invoice->getPaymentMeans() as $means) {
			if ($means->isDirectDebit()) {
				return $means;
			}
		}

		return null;
	}

}
