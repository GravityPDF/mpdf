<?php

namespace Mpdf\Invoice;

use Mpdf\Strict;

/**
 * A seller or buyer on a trade document
 */
class Party
{

	use Strict;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $countryCode;

	/**
	 * @var string|null
	 */
	private $street;

	/**
	 * @var string|null
	 */
	private $additionalStreet;

	/**
	 * @var string|null
	 */
	private $postcode;

	/**
	 * @var string|null
	 */
	private $city;

	/**
	 * @var string|null
	 */
	private $vatId;

	/**
	 * @var string|null
	 */
	private $legalId;

	/**
	 * @var string|null
	 */
	private $legalIdScheme;

	/**
	 * @var string|null
	 */
	private $email;

	/**
	 * @param string $name
	 * @param string $countryCode ISO 3166-1 alpha-2, e.g. FR
	 */
	public function __construct($name, $countryCode)
	{
		$this->name = $name;
		$this->countryCode = strtoupper($countryCode);
	}

	/**
	 * @param string $street
	 * @param string $postcode
	 * @param string $city
	 * @param string|null $additionalStreet A second address line
	 *
	 * @return $this
	 */
	public function setAddress($street, $postcode, $city, $additionalStreet = null)
	{
		$this->street = $street;
		$this->postcode = $postcode;
		$this->city = $city;
		$this->additionalStreet = $additionalStreet;

		return $this;
	}

	/**
	 * @param string $vatId The VAT identifier with its country prefix, e.g. FR32123456789
	 *
	 * @return $this
	 */
	public function setVatId($vatId)
	{
		$this->vatId = $vatId;

		return $this;
	}

	/**
	 * @param string $legalId The registration number of the legal entity, e.g. a SIRET or company number
	 * @param string|null $scheme The ISO 6523 scheme of the number, e.g. 0002 for SIRENE
	 *
	 * @return $this
	 */
	public function setLegalId($legalId, $scheme = null)
	{
		$this->legalId = $legalId;
		$this->legalIdScheme = $scheme;

		return $this;
	}

	/**
	 * @param string $email The address the party receives electronic documents at
	 *
	 * @return $this
	 */
	public function setEmail($email)
	{
		$this->email = $email;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getCountryCode()
	{
		return $this->countryCode;
	}

	/**
	 * @return string|null
	 */
	public function getStreet()
	{
		return $this->street;
	}

	/**
	 * @return string|null
	 */
	public function getAdditionalStreet()
	{
		return $this->additionalStreet;
	}

	/**
	 * @return string|null
	 */
	public function getPostcode()
	{
		return $this->postcode;
	}

	/**
	 * @return string|null
	 */
	public function getCity()
	{
		return $this->city;
	}

	/**
	 * @return string|null
	 */
	public function getVatId()
	{
		return $this->vatId;
	}

	/**
	 * @return string|null
	 */
	public function getLegalId()
	{
		return $this->legalId;
	}

	/**
	 * @return string|null
	 */
	public function getLegalIdScheme()
	{
		return $this->legalIdScheme;
	}

	/**
	 * @return string|null
	 */
	public function getEmail()
	{
		return $this->email;
	}

}
