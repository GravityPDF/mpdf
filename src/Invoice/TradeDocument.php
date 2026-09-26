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
	 * What was paid in advance, taken off the amount due; none unless the kind of document takes it
	 *
	 * @return float
	 */
	public function getPrepaidAmount()
	{
		return 0.0;
	}

	/**
	 * The document's totals as it stands, worked out afresh on each call; take them once when several are needed
	 *
	 * @return \Mpdf\Invoice\Totals
	 */
	public function getTotals()
	{
		return new Totals($this->lines, $this->allowanceCharges, $this->getPrepaidAmount());
	}

}
