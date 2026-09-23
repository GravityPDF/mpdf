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
	private $countrySubdivision;

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
	private $taxNumber;

	/**
	 * @var string|null
	 */
	private $electronicAddress;

	/**
	 * @var string|null
	 */
	private $electronicAddressScheme;

	/**
	 * @var string|null
	 */
	private $contactName;

	/**
	 * @var string|null
	 */
	private $contactPhone;

	/**
	 * @var string|null
	 */
	private $contactEmail;

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
	 * @param string $subdivision The state, province or region, e.g. NY
	 *
	 * @return $this
	 */
	public function setCountrySubdivision($subdivision)
	{
		$this->countrySubdivision = $subdivision;

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
	 * @param string $taxNumber The number the seller's tax office knows it by, for a seller without a VAT identifier,
	 *                          e.g. a German Steuernummer
	 *
	 * @return $this
	 */
	public function setTaxNumber($taxNumber)
	{
		$this->taxNumber = $taxNumber;

		return $this;
	}

	/**
	 * The address the party receives electronic invoices at
	 *
	 * @param string $address
	 * @param string $scheme The CEF EAS scheme of the address: EM for an email address, 0204 for a German Leitweg-ID,
	 *                       0088 for a GLN, 0208 for a Belgian company number, 0225 for a French routing identifier
	 *
	 * @return $this
	 */
	public function setElectronicAddress($address, $scheme = 'EM')
	{
		$this->electronicAddress = $address;
		$this->electronicAddressScheme = $scheme;

		return $this;
	}

	/**
	 * The person to contact at the party
	 *
	 * @param string $name
	 * @param string|null $phone
	 * @param string|null $email
	 *
	 * @return $this
	 */
	public function setContact($name, $phone = null, $email = null)
	{
		$this->contactName = $name;
		$this->contactPhone = $phone;
		$this->contactEmail = $email;

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
	public function getCountrySubdivision()
	{
		return $this->countrySubdivision;
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
	public function getTaxNumber()
	{
		return $this->taxNumber;
	}

	/**
	 * @return string|null
	 */
	public function getElectronicAddress()
	{
		return $this->electronicAddress;
	}

	/**
	 * @return string|null
	 */
	public function getElectronicAddressScheme()
	{
		return $this->electronicAddressScheme;
	}

	/**
	 * @return string|null
	 */
	public function getContactName()
	{
		return $this->contactName;
	}

	/**
	 * @return string|null
	 */
	public function getContactPhone()
	{
		return $this->contactPhone;
	}

	/**
	 * @return string|null
	 */
	public function getContactEmail()
	{
		return $this->contactEmail;
	}

}
