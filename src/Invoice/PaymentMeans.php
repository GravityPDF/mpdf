<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * How the buyer pays: by credit transfer to an account, by SEPA direct debit, by card, or by another UNTDID 4461 means
 *
 *     PaymentMeans::sepaCreditTransfer('FR7630006000011234567890189', 'AGRIFRPP', 'Seller SARL')
 *     PaymentMeans::sepaDirectDebit('MANDATE-42', 'DE02120300000000202051', 'DE98ZZZ09999999999')
 *     PaymentMeans::card('1234', 'J Smith')
 */
class PaymentMeans
{

	use Strict;

	const CASH = '10';

	const CREDIT_TRANSFER = '30';

	const BANK_CARD = '48';

	const DIRECT_DEBIT = '49';

	const CREDIT_CARD = '54';

	const DEBIT_CARD = '55';

	const SEPA_CREDIT_TRANSFER = '58';

	const SEPA_DIRECT_DEBIT = '59';

	/**
	 * @var string
	 */
	private $typeCode;

	/**
	 * @var string|null
	 */
	private $information;

	/**
	 * @var string|null
	 */
	private $account;

	/**
	 * @var bool
	 */
	private $iban = false;

	/**
	 * @var string|null
	 */
	private $bic;

	/**
	 * @var string|null
	 */
	private $accountName;

	/**
	 * @var string|null
	 */
	private $mandateReference;

	/**
	 * @var string|null
	 */
	private $debitedAccount;

	/**
	 * @var string|null
	 */
	private $creditorId;

	/**
	 * @var string|null
	 */
	private $cardNumber;

	/**
	 * @var string|null
	 */
	private $cardholderName;

	/**
	 * @param string $typeCode One of the constants, or another UNTDID 4461 code
	 */
	public function __construct($typeCode)
	{
		$this->typeCode = (string) $typeCode;
	}

	/**
	 * A transfer to the seller's account in the SEPA area
	 *
	 * @param string $iban
	 * @param string|null $bic
	 * @param string|null $accountName
	 *
	 * @return self
	 */
	public static function sepaCreditTransfer($iban, $bic = null, $accountName = null)
	{
		return (new self(self::SEPA_CREDIT_TRANSFER))->setPayeeAccount($iban, $bic, $accountName);
	}

	/**
	 * A transfer to the seller's account outside SEPA
	 *
	 * @param string $account An IBAN, or the account number the bank uses
	 * @param string|null $bic
	 * @param string|null $accountName
	 *
	 * @return self
	 */
	public static function creditTransfer($account, $bic = null, $accountName = null)
	{
		return (new self(self::CREDIT_TRANSFER))->setPayeeAccount($account, $bic, $accountName);
	}

	/**
	 * A SEPA direct debit the seller collects from the buyer's account
	 *
	 * @param string $mandateReference The reference of the buyer's mandate
	 * @param string $debitedIban The buyer's account, with or without spaces
	 * @param string $creditorId The seller's SEPA creditor identifier
	 *
	 * @return self
	 */
	public static function sepaDirectDebit($mandateReference, $debitedIban, $creditorId)
	{
		$means = new self(self::SEPA_DIRECT_DEBIT);
		$means->mandateReference = $mandateReference;
		$means->debitedAccount = strtoupper(str_replace(' ', '', $debitedIban));
		$means->creditorId = $creditorId;

		return $means;
	}

	/**
	 * A payment by card
	 *
	 * @param string $lastDigits The last four to six digits of the card, never the whole number
	 * @param string|null $cardholderName
	 * @param string $typeCode BANK_CARD, CREDIT_CARD or DEBIT_CARD
	 *
	 * @return self
	 *
	 * @throws \Mpdf\MpdfException When given more than the ten characters EN 16931 allows, lest a whole card number be written
	 */
	public static function card($lastDigits, $cardholderName = null, $typeCode = self::BANK_CARD)
	{
		if (strlen($lastDigits) > 10) {
			throw new MpdfException('Give a card\'s last four to six digits, never its whole number (EN 16931 BR-51)');
		}

		$means = new self($typeCode);
		$means->cardNumber = (string) $lastDigits;
		$means->cardholderName = $cardholderName;

		return $means;
	}

	/**
	 * @param string $account An IBAN, with or without spaces, or the account number the bank uses
	 * @param string|null $bic
	 * @param string|null $accountName
	 *
	 * @return $this
	 */
	private function setPayeeAccount($account, $bic, $accountName)
	{
		$iban = strtoupper(str_replace(' ', '', $account));
		$this->iban = (bool) preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban);
		$this->account = $this->iban ? $iban : $account;
		$this->bic = $bic;
		$this->accountName = $accountName;

		return $this;
	}

	/**
	 * @param string $information How to pay in words, e.g. "PayPal"
	 *
	 * @return $this
	 */
	public function setInformation($information)
	{
		$this->information = $information;

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
	 * @return bool
	 */
	public function isCreditTransfer()
	{
		return in_array($this->typeCode, [self::CREDIT_TRANSFER, self::SEPA_CREDIT_TRANSFER], true);
	}

	/**
	 * @return bool
	 */
	public function isDirectDebit()
	{
		return in_array($this->typeCode, [self::DIRECT_DEBIT, self::SEPA_DIRECT_DEBIT], true);
	}

	/**
	 * @return bool
	 */
	public function isCard()
	{
		return in_array($this->typeCode, [self::BANK_CARD, self::CREDIT_CARD, self::DEBIT_CARD], true);
	}

	/**
	 * @return string|null
	 */
	public function getInformation()
	{
		return $this->information;
	}

	/**
	 * The seller's account a transfer goes to
	 *
	 * @return string|null
	 */
	public function getAccount()
	{
		return $this->account;
	}

	/**
	 * Whether the seller's account is an IBAN rather than a bank's own account number
	 *
	 * @return bool
	 */
	public function isIban()
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
	 * @return string|null
	 */
	public function getMandateReference()
	{
		return $this->mandateReference;
	}

	/**
	 * The buyer's account a direct debit is taken from
	 *
	 * @return string|null
	 */
	public function getDebitedAccount()
	{
		return $this->debitedAccount;
	}

	/**
	 * @return string|null
	 */
	public function getCreditorId()
	{
		return $this->creditorId;
	}

	/**
	 * @return string|null
	 */
	public function getCardNumber()
	{
		return $this->cardNumber;
	}

	/**
	 * @return string|null
	 */
	public function getCardholderName()
	{
		return $this->cardholderName;
	}

}
