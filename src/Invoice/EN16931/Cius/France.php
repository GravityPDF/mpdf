<?php

namespace Mpdf\Invoice\EN16931\Cius;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\Party;
use Mpdf\Invoice\Totals;
use Mpdf\Invoice\VatCategory;

/**
 * Some of the rules France's 2026 e-invoicing reform (XP Z12-012) adds, as its platforms check them
 *
 * The invoice is written as EN 16931 writes it; only the rules are added. A buyer in France is taken to be a business,
 * as a French invoice sent through the reform's platforms is.
 *
 * @see https://www.impots.gouv.fr/professionnel/je-passe-la-facturation-electronique
 */
class France extends EN16931
{

	/**
	 * The cadres de facturation France's reform knows
	 *
	 * @var string[]
	 */
	private static $processes = ['B1', 'S1', 'M1', 'B2', 'S2', 'M2', 'S3', 'B4', 'S4', 'M4', 'S5', 'S6', 'B7', 'S7', 'B8', 'S8', 'M8', 'B9', 'S9', 'M9'];

	/**
	 * The document types France's reform takes
	 *
	 * @var string[]
	 */
	private static $types = ['380', '389', '393', '501', '386', '500', '384', '471', '472', '473', '261', '262', '381', '396', '502', '503'];

	/**
	 * The VAT rates France's reform takes
	 *
	 * @var float[]
	 */
	private static $rates = [0.0, 0.9, 1.05, 1.75, 2.1, 5.5, 7.0, 8.5, 9.2, 9.6, 10.0, 13.0, 19.6, 20.0, 20.6];

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'France\'s e-invoicing reform';
	}

	/**
	 * France's reform takes BASIC WL until September 2027, EN 16931 after, and MINIMUM never
	 *
	 * @return string[]
	 */
	public function getProfiles()
	{
		return [FacturX::BASIC_WL, FacturX::EN16931];
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals The invoice's totals, worked out once for every rule
	 *
	 * @return string[]
	 */
	public function broken(Invoice $invoice, Totals $totals)
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();
		$broken = [];

		if (!preg_match('~^[A-Za-z0-9+_/-]{1,35}$~', $invoice->getId())) {
			$broken[] = 'BR-FR-01/02: the invoice number is at most 35 letters, digits and + - _ /';
		}

		if (!in_array($invoice->getTypeCode(), self::$types, true)) {
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

		if (!in_array($invoice->getBusinessProcess(), self::$processes, true)) {
			$broken[] = 'BR-FR-08: the invoice needs its cadre de facturation, B1, S1 or M1 for goods, services or both; call setBusinessProcess()';
		}

		if (!$this->hasSiren($seller)) {
			$broken[] = 'BR-FR-10: the seller needs its SIREN, 9 digits, as its legal registration; call setLegalId($siren, \'0002\')';
		}

		if ($buyer->getCountryCode() === 'FR') {
			if (!$this->hasSiren($buyer)) {
				$broken[] = 'BR-FR-11: a buyer in France needs its SIREN, 9 digits, as its legal registration; call setLegalId($siren, \'0002\')';
			} elseif ($buyer->getElectronicAddressScheme() !== '0225' || strpos((string) $buyer->getElectronicAddress(), $buyer->getLegalId()) !== 0) {
				$broken[] = 'BR-FR-21: a buyer in France is reached at an address starting with its SIREN; call setElectronicAddress($siren, \'0225\')';
			}
		}

		if (!$this->bothReachable($invoice)) {
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

		if (in_array($invoice->getBusinessProcess(), ['B2', 'S2', 'M2'], true) && ($totals->getDuePayableAmount() != 0 || $invoice->getDueDate() === null)) {
			$broken[] = 'BR-FR-CO-09: an invoice already paid (B2, S2, M2) has the whole total prepaid, nothing due, and the date it was paid as its due date';
		}

		if ($invoice->getCurrency() !== 'EUR') {
			$broken[] = 'BR-FR-CO-12: an invoice in another currency than the euro needs its VAT in euros too, which is not supported yet';
		}

		foreach ($totals->getVatBreakdown() as $group) {
			if (in_array($group['category'], [VatCategory::CANARY_ISLANDS, VatCategory::CEUTA_MELILLA], true)) {
				$broken[] = sprintf('BR-FR-15: France does not take VAT category %s', $group['category']);
			}
			if (!in_array((float) $group['rate'], self::$rates, true)) {
				$broken[] = sprintf('BR-FR-16: France does not take a VAT rate of %s%%', $group['rate']);
			}
		}

		return $broken;
	}

	/**
	 * Whether the party is registered by its SIREN, France's 9-digit company number
	 *
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return bool
	 */
	private function hasSiren(Party $party)
	{
		return $party->getLegalIdScheme() === '0002' && preg_match('/^[0-9]{9}$/', (string) $party->getLegalId()) === 1;
	}

}
