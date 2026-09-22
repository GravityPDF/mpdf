<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\TradeDocument;

/**
 * An EN 16931 invoice or credit note, and how it is to be paid
 */
class Invoice extends TradeDocument
{

	const TYPE_INVOICE = '380';

	const TYPE_CREDIT_NOTE = '381';

	const TYPE_CORRECTED = '384';

	const TYPE_PREPAYMENT = '386';

	const TYPE_SELF_BILLED = '389';

	/**
	 * @var string
	 */
	private $typeCode = self::TYPE_INVOICE;

	/**
	 * @var \DateTimeInterface|null
	 */
	private $deliveryDate;

	/**
	 * @var \DateTimeInterface|null
	 */
	private $dueDate;

	/**
	 * @var string|null
	 */
	private $paymentTerms;

	/**
	 * @var string|null
	 */
	private $paymentReference;

	/**
	 * @var string|null
	 */
	private $iban;

	/**
	 * @var string|null
	 */
	private $bic;

	/**
	 * @var string|null
	 */
	private $accountName;

	/**
	 * @var float
	 */
	private $prepaidAmount = 0.0;

	/**
	 * @var string[]
	 */
	private $exemptionReasons = [];

	/**
	 * @param string $typeCode One of the TYPE_ constants, or another UNTDID 1001 invoice code
	 *
	 * @return $this
	 */
	public function setTypeCode($typeCode)
	{
		$this->typeCode = (string) $typeCode;

		return $this;
	}

	/**
	 * @param \DateTimeInterface $date When the goods or services were supplied
	 *
	 * @return $this
	 */
	public function setDeliveryDate(\DateTimeInterface $date)
	{
		$this->deliveryDate = $date;

		return $this;
	}

	/**
	 * @param \DateTimeInterface $date
	 *
	 * @return $this
	 */
	public function setDueDate(\DateTimeInterface $date)
	{
		$this->dueDate = $date;

		return $this;
	}

	/**
	 * @param string $terms e.g. "30 days net"
	 *
	 * @return $this
	 */
	public function setPaymentTerms($terms)
	{
		$this->paymentTerms = $terms;

		return $this;
	}

	/**
	 * @param string $reference The remittance reference the buyer should quote with the transfer
	 *
	 * @return $this
	 */
	public function setPaymentReference($reference)
	{
		$this->paymentReference = $reference;

		return $this;
	}

	/**
	 * The account the buyer pays by credit transfer
	 *
	 * @param string $iban
	 * @param string|null $bic
	 * @param string|null $accountName
	 *
	 * @return $this
	 */
	public function setPaymentAccount($iban, $bic = null, $accountName = null)
	{
		$this->iban = $iban;
		$this->bic = $bic;
		$this->accountName = $accountName;

		return $this;
	}

	/**
	 * @param float $amount What the buyer has already paid towards this invoice
	 *
	 * @return $this
	 */
	public function setPrepaidAmount($amount)
	{
		$this->prepaidAmount = (float) $amount;

		return $this;
	}

	/**
	 * Say why a VAT category charges no VAT, as EN 16931 requires for E, AE, K, G and O
	 *
	 * @param string $vatCategory
	 * @param string $reason e.g. "Reverse charge"
	 *
	 * @return $this
	 */
	public function setExemptionReason($vatCategory, $reason)
	{
		$this->exemptionReasons[$vatCategory] = $reason;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTypeCode()
	{
		return $this->typeCode;
	}

	/**
	 * @return \DateTimeInterface|null
	 */
	public function getDeliveryDate()
	{
		return $this->deliveryDate;
	}

	/**
	 * @return \DateTimeInterface|null
	 */
	public function getDueDate()
	{
		return $this->dueDate;
	}

	/**
	 * @return string|null
	 */
	public function getPaymentTerms()
	{
		return $this->paymentTerms;
	}

	/**
	 * @return string|null
	 */
	public function getPaymentReference()
	{
		return $this->paymentReference;
	}

	/**
	 * @return string|null
	 */
	public function getIban()
	{
		return $this->iban;
	}

	/**
	 * @return string|null
	 */
	public function getBic()
	{
		return $this->bic;
	}

	/**
	 * @return string|null
	 */
	public function getAccountName()
	{
		return $this->accountName;
	}

	/**
	 * @return float
	 */
	public function getPrepaidAmount()
	{
		return $this->prepaidAmount;
	}

	/**
	 * @param string $vatCategory
	 *
	 * @return string|null
	 */
	public function getExemptionReason($vatCategory)
	{
		return isset($this->exemptionReasons[$vatCategory]) ? $this->exemptionReasons[$vatCategory] : null;
	}

	/**
	 * The grand total less what has been prepaid
	 *
	 * @return float
	 */
	public function getDuePayableAmount()
	{
		return round($this->getGrandTotal() - $this->prepaidAmount, 2);
	}

}
