<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * The data every trade document holds (number, date, currency, parties, lines, allowances and charges) and its totals
 *
 * The totals are worked out here rather than by a writer, so a document printed from them and the XML embedded
 * alongside it cannot disagree.
 */
abstract class TradeDocument
{

	use Strict;

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var \DateTimeInterface
	 */
	private $issueDate;

	/**
	 * @var string
	 */
	private $currency;

	/**
	 * @var \Mpdf\Invoice\Party
	 */
	private $seller;

	/**
	 * @var \Mpdf\Invoice\Party
	 */
	private $buyer;

	/**
	 * @var \Mpdf\Invoice\LineItem[]
	 */
	private $lines = [];

	/**
	 * @var array[] Each with content and subjectCode
	 */
	private $notes = [];

	/**
	 * @var \Mpdf\Invoice\AllowanceCharge[]
	 */
	private $allowanceCharges = [];

	/**
	 * @var string|null
	 */
	private $buyerReference;

	/**
	 * @var string|null
	 */
	private $orderReference;

	/**
	 * @param string $id The document number
	 * @param \DateTimeInterface $issueDate
	 * @param string $currency ISO 4217, e.g. EUR
	 * @param \Mpdf\Invoice\Party $seller
	 * @param \Mpdf\Invoice\Party $buyer
	 */
	public function __construct($id, \DateTimeInterface $issueDate, $currency, Party $seller, Party $buyer)
	{
		$this->id = $id;
		$this->issueDate = $issueDate;
		$this->currency = strtoupper($currency);
		$this->seller = $seller;
		$this->buyer = $buyer;
	}

	/**
	 * The UNTDID 1001 code of the kind of document, e.g. 380 for a commercial invoice
	 *
	 * @return string
	 */
	abstract public function getTypeCode();

	/**
	 * @param \Mpdf\Invoice\LineItem $line
	 *
	 * @return $this
	 */
	public function addLine(LineItem $line)
	{
		$this->lines[] = $line;

		return $this;
	}

	/**
	 * @param string $note Free text for the buyer, e.g. the seller's registered capital or a late payment penalty
	 * @param string|null $subjectCode The UNTDID 4451 subject of the note, e.g. PMT, PMD or AAB, which France's reform
	 *                                 uses for its mandatory mentions
	 *
	 * @return $this
	 */
	public function addNote($note, $subjectCode = null)
	{
		$this->notes[] = ['content' => $note, 'subjectCode' => $subjectCode];

		return $this;
	}

	/**
	 * A discount or credit taken off, or a charge such as shipping added to, the document as a whole
	 *
	 * @param \Mpdf\Invoice\AllowanceCharge $allowanceCharge With the VAT it bears set, which it changes the base of
	 *
	 * @return $this
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function addAllowanceCharge(AllowanceCharge $allowanceCharge)
	{
		if ($allowanceCharge->getVatCategory() === null) {
			throw new MpdfException('An allowance or charge on a whole document needs the VAT it bears; call setVat() on it');
		}

		$this->allowanceCharges[] = $allowanceCharge;

		return $this;
	}

	/**
	 * @param string $reference The reference the buyer asked to be quoted, e.g. a Leitweg-ID for XRechnung
	 *
	 * @return $this
	 */
	public function setBuyerReference($reference)
	{
		$this->buyerReference = $reference;

		return $this;
	}

	/**
	 * @param string $reference The number of the buyer's purchase order
	 *
	 * @return $this
	 */
	public function setOrderReference($reference)
	{
		$this->orderReference = $reference;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return \DateTimeInterface
	 */
	public function getIssueDate()
	{
		return $this->issueDate;
	}

	/**
	 * @return string
	 */
	public function getCurrency()
	{
		return $this->currency;
	}

	/**
	 * @return \Mpdf\Invoice\Party
	 */
	public function getSeller()
	{
		return $this->seller;
	}

	/**
	 * @return \Mpdf\Invoice\Party
	 */
	public function getBuyer()
	{
		return $this->buyer;
	}

	/**
	 * @return \Mpdf\Invoice\LineItem[]
	 */
	public function getLines()
	{
		return $this->lines;
	}

	/**
	 * @return array[] Each with content and subjectCode
	 */
	public function getNotes()
	{
		return $this->notes;
	}

	/**
	 * @return string|null
	 */
	public function getBuyerReference()
	{
		return $this->buyerReference;
	}

	/**
	 * @return string|null
	 */
	public function getOrderReference()
	{
		return $this->orderReference;
	}

	/**
	 * @return \Mpdf\Invoice\AllowanceCharge[]
	 */
	public function getAllowanceCharges()
	{
		return $this->allowanceCharges;
	}

	/**
	 * The sum of the lines' net amounts
	 *
	 * @return float
	 */
	public function getLineTotal()
	{
		$total = 0;
		foreach ($this->lines as $line) {
			$total += $line->getNetAmount();
		}

		return round($total, 2);
	}

	/**
	 * The sum of the document's allowances, before VAT
	 *
	 * @return float
	 */
	public function getAllowanceTotal()
	{
		return $this->sumAllowanceCharges(false);
	}

	/**
	 * The sum of the document's charges, before VAT
	 *
	 * @return float
	 */
	public function getChargeTotal()
	{
		return $this->sumAllowanceCharges(true);
	}

	/**
	 * The total VAT is charged on: the lines, less the document's allowances, plus its charges
	 *
	 * @return float
	 */
	public function getTaxBasisTotal()
	{
		return round($this->getLineTotal() - $this->getAllowanceTotal() + $this->getChargeTotal(), 2);
	}

	/**
	 * The lines, allowances and charges grouped by VAT category and rate, with the VAT each group owes
	 *
	 * @return array[] Each with category, rate, basis and amount
	 */
	public function getVatBreakdown()
	{
		$groups = [];
		foreach ($this->lines as $line) {
			$this->addToGroup($groups, $line->getVatCategory(), $line->getVatRate(), $line->getNetAmount());
		}
		foreach ($this->allowanceCharges as $allowanceCharge) {
			$this->addToGroup($groups, $allowanceCharge->getVatCategory(), $allowanceCharge->getVatRate(), $allowanceCharge->getSignedAmount());
		}

		foreach ($groups as $key => $group) {
			$groups[$key]['basis'] = round($group['basis'], 2);
			$groups[$key]['amount'] = round($groups[$key]['basis'] * $group['rate'] / 100, 2);
		}

		return array_values($groups);
	}

	/**
	 * The VAT owed across every category
	 *
	 * @return float
	 */
	public function getTaxTotal()
	{
		$total = 0;
		foreach ($this->getVatBreakdown() as $group) {
			$total += $group['amount'];
		}

		return round($total, 2);
	}

	/**
	 * The total with VAT
	 *
	 * @return float
	 */
	public function getGrandTotal()
	{
		return round($this->getTaxBasisTotal() + $this->getTaxTotal(), 2);
	}

	/**
	 * @param bool $charges
	 *
	 * @return float
	 */
	private function sumAllowanceCharges($charges)
	{
		$total = 0;
		foreach ($this->allowanceCharges as $allowanceCharge) {
			if ($allowanceCharge->isCharge() === $charges) {
				$total += $allowanceCharge->getAmount();
			}
		}

		return round($total, 2);
	}

	/**
	 * Add an amount to the VAT breakdown group of its category and rate
	 *
	 * @param array[] $groups
	 * @param string $category
	 * @param float $rate
	 * @param float $amount
	 */
	private function addToGroup(array &$groups, $category, $rate, $amount)
	{
		$key = $category . ':' . $rate;
		if (!isset($groups[$key])) {
			$groups[$key] = ['category' => $category, 'rate' => $rate, 'basis' => 0];
		}
		$groups[$key]['basis'] += $amount;
	}

}
