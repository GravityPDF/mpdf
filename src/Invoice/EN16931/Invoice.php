<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\Party;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\TradeDocument;
use Mpdf\MpdfException;
use Mpdf\Utils\Arrays;

/**
 * An EN 16931 invoice or credit note: where the goods went, how it is to be paid, and which invoice it corrects
 */
class Invoice extends TradeDocument
{

	const TYPE_INVOICE = '380';

	const TYPE_CREDIT_NOTE = '381';

	const TYPE_CORRECTED = '384';

	const TYPE_PREPAYMENT = '386';

	const TYPE_SELF_BILLED = '389';

	/**
	 * VAT falls due on the invoice date, which France calls VAT on debits
	 */
	const VAT_DUE_ON_INVOICE = '5';

	/**
	 * VAT falls due on the delivery date
	 */
	const VAT_DUE_ON_DELIVERY = '29';

	/**
	 * VAT falls due when the invoice is paid
	 */
	const VAT_DUE_ON_PAYMENT = '72';

	/**
	 * @var string
	 */
	private $typeCode = self::TYPE_INVOICE;

	/**
	 * @var string|null
	 */
	private $businessProcess;

	/**
	 * @var \DateTimeInterface|null
	 */
	private $deliveryDate;

	/**
	 * @var \Mpdf\Invoice\Party|null
	 */
	private $deliverTo;

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
	 * @var \Mpdf\Invoice\PaymentMeans[]
	 */
	private $paymentMeans = [];

	/**
	 * @var float
	 */
	private $prepaidAmount = 0.0;

	/**
	 * @var string[]
	 */
	private $exemptionReasons = [];

	/**
	 * @var array[] Each with id and issueDate
	 */
	private $precedingInvoices = [];

	/**
	 * @var string|null
	 */
	private $vatDueDateCode;

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
	 * @param string $businessProcess The business process the invoice belongs to: in France the cadre de facturation,
	 *                                B1 for goods, S1 for services or M1 for both, B2, S2 or M2 when already paid, and so
	 *                                on; for XRechnung the Peppol process, which the writer names when none is given
	 *
	 * @return $this
	 */
	public function setBusinessProcess($businessProcess)
	{
		$this->businessProcess = $businessProcess;

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
	 * Where the goods were delivered, when not to the buyer; EN 16931 requires its country for an intra-community
	 * supply (VAT category K)
	 *
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return $this
	 */
	public function setDeliverTo(Party $party)
	{
		$this->deliverTo = $party;

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
	 * @param string $reference The remittance reference the buyer should quote with the payment
	 *
	 * @return $this
	 */
	public function setPaymentReference($reference)
	{
		$this->paymentReference = $reference;

		return $this;
	}

	/**
	 * A way the buyer can pay, e.g. PaymentMeans::sepaCreditTransfer($iban); give one for each account the buyer may
	 * pay into
	 *
	 * @param \Mpdf\Invoice\PaymentMeans $means
	 *
	 * @return $this
	 */
	public function addPaymentMeans(PaymentMeans $means)
	{
		$this->paymentMeans[] = $means;

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
	 * When the VAT falls due (BT-8), as a VAT_DUE_ constant
	 *
	 * @param string|null $code
	 *
	 * @return $this
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function setVatDueDateCode($code)
	{
		if ($code !== null && !in_array($code, [self::VAT_DUE_ON_INVOICE, self::VAT_DUE_ON_DELIVERY, self::VAT_DUE_ON_PAYMENT], true)) {
			throw new MpdfException(sprintf('VAT due date code "%s" is not one of 5, 29 or 72', $code));
		}

		$this->vatDueDateCode = $code;

		return $this;
	}

	/**
	 * The seller pays the VAT on these services when it invoices them rather than when it is paid, the French option
	 * pour le paiement de la TVA d'après les débits
	 *
	 * @param bool $onDebits
	 *
	 * @return $this
	 */
	public function setVatOnDebits($onDebits = true)
	{
		return $this->setVatDueDateCode($onDebits ? self::VAT_DUE_ON_INVOICE : null);
	}

	/**
	 * An invoice this one corrects or credits, which the law requires a credit note or corrected invoice to name
	 *
	 * @param string $id Its number
	 * @param \DateTimeInterface|null $issueDate
	 *
	 * @return $this
	 */
	public function addPrecedingInvoice($id, $issueDate = null)
	{
		$this->precedingInvoices[] = ['id' => $id, 'issueDate' => $issueDate];

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
	 * @return string|null
	 */
	public function getBusinessProcess()
	{
		return $this->businessProcess;
	}

	/**
	 * @return \DateTimeInterface|null
	 */
	public function getDeliveryDate()
	{
		return $this->deliveryDate;
	}

	/**
	 * @return \Mpdf\Invoice\Party|null
	 */
	public function getDeliverTo()
	{
		return $this->deliverTo;
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
	 * @return \Mpdf\Invoice\PaymentMeans[]
	 */
	public function getPaymentMeans()
	{
		return $this->paymentMeans;
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
		return Arrays::get($this->exemptionReasons, $vatCategory, null);
	}

	/**
	 * @return array[] Each with id and issueDate
	 */
	public function getPrecedingInvoices()
	{
		return $this->precedingInvoices;
	}

	/**
	 * @return string|null
	 */
	public function getVatDueDateCode()
	{
		return $this->vatDueDateCode;
	}

	/**
	 * @return bool
	 */
	public function isVatOnDebits()
	{
		return $this->vatDueDateCode === self::VAT_DUE_ON_INVOICE;
	}

}
