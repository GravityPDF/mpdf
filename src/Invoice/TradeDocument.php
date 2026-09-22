<?php

namespace Mpdf\Invoice;

use Mpdf\Strict;

/**
 * The data every trade document holds (number, date, currency, parties and lines) and the totals of its lines
 *
 * The totals are worked out here rather than by a generator, so a document printed from them and the XML embedded
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
	 * @var string[]
	 */
	private $notes = [];

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
	 *
	 * @return $this
	 */
	public function addNote($note)
	{
		$this->notes[] = $note;

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
	 * @return string[]
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
	 * The lines grouped by VAT category and rate, with the VAT each group owes
	 *
	 * @return array[] Each with category, rate, basis and amount
	 */
	public function getVatBreakdown()
	{
		$groups = [];
		foreach ($this->lines as $line) {
			$key = $line->getVatCategory() . ':' . $line->getVatRate();
			if (!isset($groups[$key])) {
				$groups[$key] = [
					'category' => $line->getVatCategory(),
					'rate' => $line->getVatRate(),
					'basis' => 0,
				];
			}
			$groups[$key]['basis'] += $line->getNetAmount();
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
	 * The line total with VAT
	 *
	 * @return float
	 */
	public function getGrandTotal()
	{
		return round($this->getLineTotal() + $this->getTaxTotal(), 2);
	}

}
