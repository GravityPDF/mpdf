<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\Arrays;

/**
 * The EN 16931 business rules an invoice's content can break, and those XRechnung and France's reform add, checked
 * before it is written so an invalid e-invoice is refused rather than sent. The rules a model cannot break by construction, such as the
 * arithmetic of its totals, are not repeated here.
 */
class Rules
{

	use Strict;

	/**
	 * The categories that need the reason they charge no VAT, and the rule that says so
	 *
	 * @var string[]
	 */
	private static $exempt = ['E' => 'BR-E-10', 'AE' => 'BR-AE-10', 'K' => 'BR-IC-10', 'G' => 'BR-G-10', 'O' => 'BR-O-10'];

	/**
	 * The categories that may give no such reason, and the rule that says so
	 *
	 * @var string[]
	 */
	private static $notExempt = ['S' => 'BR-S-10', 'Z' => 'BR-Z-10', 'L' => 'BR-AF-10', 'M' => 'BR-AG-10'];

	/**
	 * The payment means codes of a credit transfer, a card and a direct debit, which XRechnung keeps apart
	 *
	 * @var string[]
	 */
	private static $paymentGroups = [
		PaymentMeans::CREDIT_TRANSFER => 'transfer',
		PaymentMeans::SEPA_CREDIT_TRANSFER => 'transfer',
		PaymentMeans::BANK_CARD => 'card',
		PaymentMeans::CREDIT_CARD => 'card',
		PaymentMeans::DEBIT_CARD => 'card',
		PaymentMeans::DIRECT_DEBIT => 'debit',
		PaymentMeans::SEPA_DIRECT_DEBIT => 'debit',
	];

	/**
	 * The cadres de facturation France's reform knows
	 *
	 * @var string[]
	 */
	private static $frenchProcesses = ['B1', 'S1', 'M1', 'B2', 'S2', 'M2', 'S3', 'B4', 'S4', 'M4', 'S5', 'S6', 'B7', 'S7', 'B8', 'S8', 'M8', 'B9', 'S9', 'M9'];

	/**
	 * The document types France's reform takes
	 *
	 * @var string[]
	 */
	private static $frenchTypes = ['380', '389', '393', '501', '386', '500', '384', '471', '472', '473', '261', '262', '381', '396', '502', '503'];

	/**
	 * The VAT rates France's reform takes
	 *
	 * @var float[]
	 */
	private static $frenchRates = [0.0, 0.9, 1.05, 1.75, 2.1, 5.5, 7.0, 8.5, 9.2, 9.6, 10.0, 13.0, 19.6, 20.0, 20.6];

	/**
	 * Refuse an invoice that breaks any of the EN 16931 rules, naming each it breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function check(Invoice $invoice)
	{
		self::refuse('EN 16931', self::broken($invoice));
	}

	/**
	 * Refuse an invoice that breaks any of the EN 16931 rules or those XRechnung adds, naming each it breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function checkXRechnung(Invoice $invoice)
	{
		self::refuse('XRechnung', array_merge(self::broken($invoice), self::brokenInXRechnung($invoice)));
	}

	/**
	 * Refuse an invoice that breaks any of the EN 16931 rules or those France's reform adds, naming each it breaks
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function checkFrance(Invoice $invoice)
	{
		self::refuse('France\'s e-invoicing rules', array_merge(self::broken($invoice), self::brokenInFrance($invoice)));
	}

	/**
	 * The rules France's 2026 e-invoicing reform (XP Z12-012) adds that the invoice breaks, each as a message naming it.
	 * A buyer in France is taken to be a business, as a French invoice sent through the reform's platforms is.
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string[]
	 */
	public static function brokenInFrance(Invoice $invoice)
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();
		$broken = [];

		if (!preg_match('~^[A-Za-z0-9+_/-]{1,35}$~', $invoice->getId())) {
			$broken[] = 'BR-FR-01/02: the invoice number is at most 35 letters, digits and + - _ /';
		}

		if (!in_array($invoice->getTypeCode(), self::$frenchTypes, true)) {
			$broken[] = sprintf('BR-FR-04: France does not take document type %s', $invoice->getTypeCode());
		}

		$subjects = [];
		foreach ($invoice->getNotes() as $note) {
			$subjects[] = $note['subjectCode'];
		}
		foreach (['PMT' => 'the fixed recovery fee', 'PMD' => 'the late payment penalties', 'AAB' => 'the early payment discount, or that there is none'] as $code => $mention) {
			$count = count(array_keys($subjects, $code, true));
			if ($count !== 1) {
				$broken[] = sprintf('BR-FR-05/06: the invoice needs one note with subject %s, giving %s; call addNote($text, \'%s\')', $code, $mention, $code);
			}
		}

		if (!in_array($invoice->getBusinessProcess(), self::$frenchProcesses, true)) {
			$broken[] = 'BR-FR-08: the invoice needs its cadre de facturation, B1, S1 or M1 for goods, services or both; call setBusinessProcess()';
		}

		if ($seller->getLegalIdScheme() !== '0002' || !preg_match('/^[0-9]{9}$/', (string) $seller->getLegalId())) {
			$broken[] = 'BR-FR-10: the seller needs its SIREN, 9 digits, as its legal registration; call setLegalId($siren, \'0002\')';
		}

		if ($buyer->getCountryCode() === 'FR') {
			if ($buyer->getLegalIdScheme() !== '0002' || !preg_match('/^[0-9]{9}$/', (string) $buyer->getLegalId())) {
				$broken[] = 'BR-FR-11: a buyer in France needs its SIREN, 9 digits, as its legal registration; call setLegalId($siren, \'0002\')';
			} elseif ($buyer->getElectronicAddressScheme() !== '0225' || strpos((string) $buyer->getElectronicAddress(), $buyer->getLegalId()) !== 0) {
				$broken[] = 'BR-FR-21: a buyer in France is reached at an address starting with its SIREN; call setElectronicAddress($siren, \'0225\')';
			}
		}

		if ($seller->getElectronicAddress() === null || $buyer->getElectronicAddress() === null) {
			$broken[] = 'BR-FR-12/13: the seller and the buyer need an electronic address; call setElectronicAddress()';
		}

		$preceding = $invoice->getPrecedingInvoices();
		$dated = array_filter($preceding, function ($reference) {
			return $reference['issueDate'] !== null;
		});
		if ($invoice->getTypeCode() === Invoice::TYPE_CORRECTED && (count($preceding) !== 1 || !$dated)) {
			$broken[] = 'BR-FR-CO-04: a corrected invoice names the one invoice it corrects, with its date';
		}
		if ($invoice->getTypeCode() === Invoice::TYPE_CREDIT_NOTE && !$dated) {
			$broken[] = 'BR-FR-CO-05: a credit note names the invoice it credits, with its date; call addPrecedingInvoice()';
		}

		if ($invoice->getDueDate() !== null && $invoice->getDueDate() < $invoice->getIssueDate() && $invoice->getTypeCode() !== Invoice::TYPE_PREPAYMENT) {
			$broken[] = 'BR-FR-CO-07: the due date cannot come before the invoice';
		}

		if (in_array($invoice->getBusinessProcess(), ['B2', 'S2', 'M2'], true) && ($invoice->getDuePayableAmount() != 0 || $invoice->getDueDate() === null)) {
			$broken[] = 'BR-FR-CO-09: an invoice already paid (B2, S2, M2) has the whole total prepaid, nothing due, and the date it was paid as its due date';
		}

		if ($invoice->getCurrency() !== 'EUR') {
			$broken[] = 'BR-FR-CO-12: an invoice in another currency than the euro needs its VAT in euros too, which is not supported yet';
		}

		foreach ($invoice->getVatBreakdown() as $group) {
			if (in_array($group['category'], ['L', 'M'], true)) {
				$broken[] = sprintf('BR-FR-15: France does not take VAT category %s', $group['category']);
			}
			if (!in_array((float) $group['rate'], self::$frenchRates, true)) {
				$broken[] = sprintf('BR-FR-16: France does not take a VAT rate of %s%%', $group['rate']);
			}
		}

		return $broken;
	}

	/**
	 * The rules XRechnung 3.0 adds that the invoice breaks, each as a message naming it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string[]
	 */
	public static function brokenInXRechnung(Invoice $invoice)
	{
		$seller = $invoice->getSeller();
		$broken = [];

		if (!$invoice->getPaymentMeans()) {
			$broken[] = 'BR-DE-1: the invoice needs a way to pay; call addPaymentMeans()';
		}

		if ($seller->getContactName() === null || $seller->getContactPhone() === null || $seller->getContactEmail() === null) {
			$broken[] = 'BR-DE-2: the seller needs a contact with a name, phone number and email address; call setContact()';
		}

		$parties = ['seller' => ['BR-DE-3/4', $seller], 'buyer' => ['BR-DE-8/9', $invoice->getBuyer()]];
		if ($invoice->getDeliverTo() !== null) {
			$parties['delivery address'] = ['BR-DE-10/11', $invoice->getDeliverTo()];
		}
		foreach ($parties as $name => $party) {
			if ($party[1]->getCity() === null || $party[1]->getPostcode() === null) {
				$broken[] = sprintf('%s: the %s needs a city and postcode', $party[0], $name);
			}
		}

		if ($invoice->getBuyerReference() === null) {
			$broken[] = 'BR-DE-15: the invoice needs the buyer\'s reference, a Leitweg-ID for a public buyer; call setBuyerReference()';
		}

		if ($seller->getElectronicAddress() === null || $invoice->getBuyer()->getElectronicAddress() === null) {
			$broken[] = 'PEPPOL-EN16931-R010/R020: the seller and the buyer need an electronic address; call setElectronicAddress()';
		}

		$groups = [];
		foreach ($invoice->getPaymentMeans() as $means) {
			$group = Arrays::get(self::$paymentGroups, $means->getTypeCode(), 'other');
			$groups[$group] = true;
			if ($group === 'debit' && $means->getMandateReference() === null) {
				$broken[] = 'PEPPOL-EN16931-R061: a direct debit needs its mandate; use PaymentMeans::sepaDirectDebit()';
			}
		}
		if (isset($groups['debit']) && (isset($groups['transfer']) || isset($groups['card']))) {
			$broken[] = 'BR-DE-23/24: a direct debit cannot be offered beside a transfer or a card';
		}

		foreach (explode("\n", (string) $invoice->getPaymentTerms()) as $line) {
			if (strpos($line, '#') === 0 && !preg_match('/^#SKONTO#TAGE=[0-9]+#PROZENT=[0-9]+\.[0-9]{2}(#BASISBETRAG=-?[0-9]+\.[0-9]{2})?#$/', $line)) {
				$broken[] = 'BR-DE-18: a line of the payment terms starting # must read #SKONTO#TAGE=14#PROZENT=2.00#';
			}
		}

		return $broken;
	}

	/**
	 * @param string $standard
	 * @param string[] $broken
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private static function refuse($standard, array $broken)
	{
		if ($broken) {
			throw new MpdfException(sprintf("The invoice breaks %s:\n- %s", $standard, implode("\n- ", $broken)));
		}
	}

	/**
	 * The rules the invoice breaks, each as a message naming it
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string[]
	 */
	public static function broken(Invoice $invoice)
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();
		$sellerTaxed = $seller->getVatId() !== null || $seller->getTaxNumber() !== null;
		$broken = [];

		if ($seller->getVatId() === null && $seller->getLegalId() === null) {
			$broken[] = 'BR-CO-26: the seller needs a VAT identifier or legal registration, so the buyer can identify it';
		}

		$categories = self::categories($invoice);

		foreach (['S', 'Z', 'E', 'L', 'M'] as $category) {
			if (isset($categories[$category]) && !$sellerTaxed) {
				$broken[] = sprintf('BR-%s-2: VAT category %s needs the seller\'s VAT identifier or tax number', self::ruleCode($category), $category);
			}
		}

		if (isset($categories['AE'])) {
			if (!$sellerTaxed) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the seller\'s VAT identifier or tax number';
			}
			if ($buyer->getVatId() === null && $buyer->getLegalId() === null) {
				$broken[] = 'BR-AE-2: a reverse charge (AE) needs the buyer\'s VAT identifier or legal registration';
			}
		}

		if (isset($categories['K'])) {
			if ($seller->getVatId() === null || $buyer->getVatId() === null) {
				$broken[] = 'BR-IC-2: an intra-community supply (K) needs the VAT identifiers of the seller and the buyer';
			}
			if ($invoice->getDeliveryDate() === null) {
				$broken[] = 'BR-IC-11: an intra-community supply (K) needs its delivery date; call setDeliveryDate()';
			}
			if ($invoice->getDeliverTo() === null) {
				$broken[] = 'BR-IC-12: an intra-community supply (K) needs the country it was delivered to; call setDeliverTo()';
			}
		}

		if (isset($categories['G']) && $seller->getVatId() === null) {
			$broken[] = 'BR-G-2: an export (G) needs the seller\'s VAT identifier';
		}

		if (isset($categories[LineItem::NOT_SUBJECT_TO_VAT])) {
			if (count($categories) > 1) {
				$broken[] = 'BR-O-11: an invoice not subject to VAT (O) can have no other VAT category';
			}
			if ($seller->getVatId() !== null || $buyer->getVatId() !== null) {
				$broken[] = 'BR-O-2: an invoice not subject to VAT (O) can name no VAT identifier for the seller or the buyer';
			}
		}

		foreach (self::$exempt as $category => $rule) {
			if (isset($categories[$category]) && $invoice->getExemptionReason($category) === null) {
				$broken[] = sprintf('%s: VAT category %s needs the reason it charges no VAT; call setExemptionReason(\'%s\', ...)', $rule, $category, $category);
			}
		}

		foreach (self::$notExempt as $category => $rule) {
			if (isset($categories[$category]) && $invoice->getExemptionReason($category) !== null) {
				$broken[] = sprintf('%s: VAT category %s charges VAT, so can give no reason it is exempt', $rule, $category);
			}
		}

		if ($invoice->getDuePayableAmount() > 0 && $invoice->getDueDate() === null && $invoice->getPaymentTerms() === null) {
			$broken[] = 'BR-CO-25: an amount due needs a due date or payment terms';
		}

		$broken = array_merge($broken, self::unexplained($invoice->getAllowanceCharges(), 'BR-33', 'BR-38', 'on the invoice'));
		foreach ($invoice->getLines() as $line) {
			$broken = array_merge($broken, self::unexplained($line->getAllowanceCharges(), 'BR-42', 'BR-44', 'on "' . $line->getName() . '"'));
		}

		return $broken;
	}

	/**
	 * The VAT categories the invoice's lines, allowances and charges use
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return bool[] By category
	 */
	private static function categories(Invoice $invoice)
	{
		$categories = [];
		foreach ($invoice->getVatBreakdown() as $group) {
			$categories[$group['category']] = true;
		}

		return $categories;
	}

	/**
	 * The rules broken by allowances or charges that give neither a reason nor a reason code
	 *
	 * @param \Mpdf\Invoice\AllowanceCharge[] $allowanceCharges
	 * @param string $allowanceRule
	 * @param string $chargeRule
	 * @param string $where
	 *
	 * @return string[]
	 */
	private static function unexplained(array $allowanceCharges, $allowanceRule, $chargeRule, $where)
	{
		$broken = [];
		foreach ($allowanceCharges as $allowanceCharge) {
			if ($allowanceCharge->getReason() === null && $allowanceCharge->getReasonCode() === null) {
				$charge = $allowanceCharge->isCharge();
				$broken[] = sprintf('%s: a %s %s needs a reason or a reason code', $charge ? $chargeRule : $allowanceRule, $charge ? 'charge' : 'allowance', $where);
			}
		}

		return array_unique($broken);
	}

	/**
	 * The name EN 16931 gives a category's rules: AF and AG for L and M, the category itself otherwise
	 *
	 * @param string $category
	 *
	 * @return string
	 */
	private static function ruleCode($category)
	{
		return Arrays::get(['L' => 'AF', 'M' => 'AG'], $category, $category);
	}

}
