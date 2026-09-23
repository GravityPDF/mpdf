<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\StringWriterInterface;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * The parts shared by UN/CEFACT Cross Industry writers: the ram and udt namespaces, the date and amount formats,
 * and the profile that decides how much of a document is written
 */
abstract class CiiWriter implements StringWriterInterface
{

	use Strict;

	/**
	 * @var string[]
	 */
	private static $namespaces = [
		'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
		'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
		'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
	];

	/**
	 * @var string
	 */
	private $profile;

	/**
	 * @param string $profile One of getProfiles()
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function __construct($profile)
	{
		$profile = strtoupper($profile);
		if (!in_array($profile, $this->getProfiles(), true)) {
			throw new MpdfException(sprintf('Profile "%s" is not one of %s', $profile, implode(', ', $this->getProfiles())));
		}

		$this->profile = $profile;
	}

	/**
	 * The profiles the writer writes, from the one carrying least to the one carrying most
	 *
	 * @return string[]
	 */
	abstract protected function getProfiles();

	/**
	 * @return string
	 */
	protected function getProfile()
	{
		return $this->profile;
	}

	/**
	 * Whether the profile being written is the given one or a larger one
	 *
	 * @param string $profile
	 *
	 * @return bool
	 */
	protected function includes($profile)
	{
		$profiles = $this->getProfiles();

		return array_search($this->profile, $profiles, true) >= array_search($profile, $profiles, true);
	}

	/**
	 * Create the document's root element, declaring the given rsm namespace and the shared qdt, ram and udt ones
	 *
	 * @param string $root The root element, e.g. rsm:CrossIndustryInvoice
	 * @param string $rsmNamespace
	 *
	 * @return \DOMElement
	 *
	 * @throws \Mpdf\MpdfException
	 */
	protected function createRoot($root, $rsmNamespace)
	{
		if (!class_exists('DOMDocument')) {
			throw new MpdfException('Writing invoice XML requires the DOM extension');
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;

		$element = $dom->createElementNS($rsmNamespace, $root);
		foreach (self::$namespaces as $prefix => $uri) {
			$element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri);
		}

		return $dom->appendChild($element);
	}

	/**
	 * Append a prefixed element, with text when a value is given
	 *
	 * @param \DOMElement $parent
	 * @param string $name e.g. ram:ID
	 * @param string|null $value
	 * @param string[] $attributes
	 *
	 * @return \DOMElement
	 */
	protected function append(\DOMElement $parent, $name, $value = null, array $attributes = [])
	{
		list($prefix) = explode(':', $name);
		$dom = $parent->ownerDocument;

		$element = $dom->createElementNS($parent->lookupNamespaceURI($prefix), $name);
		if ($value !== null) {
			$element->appendChild($dom->createTextNode((string) $value));
		}
		foreach ($attributes as $attribute => $attributeValue) {
			$element->setAttribute($attribute, $attributeValue);
		}

		return $parent->appendChild($element);
	}

	/**
	 * Append an element only when there is a value for it
	 *
	 * @param \DOMElement $parent
	 * @param string $name
	 * @param string|null $value
	 */
	protected function appendIfSet(\DOMElement $parent, $name, $value)
	{
		if ($value !== null) {
			$this->append($parent, $name, $value);
		}
	}

	/**
	 * Append a date in CII's CCYYMMDD form (format 102)
	 *
	 * @param \DOMElement $parent
	 * @param string $name
	 * @param \DateTimeInterface $date
	 * @param string $prefix udt for most dates, qdt for a referenced document's
	 *
	 * @return \DOMElement
	 */
	protected function appendDate(\DOMElement $parent, $name, \DateTimeInterface $date, $prefix = 'udt')
	{
		$element = $this->append($parent, $name);
		$this->append($element, $prefix . ':DateTimeString', $date->format('Ymd'), ['format' => '102']);

		return $element;
	}

	/**
	 * A monetary amount, to the cent
	 *
	 * @param float $amount
	 *
	 * @return string
	 */
	protected function amount($amount)
	{
		return number_format($amount, 2, '.', '');
	}

	/**
	 * A quantity, price or rate, to at most four decimals and without trailing zeros
	 *
	 * @param float $value
	 *
	 * @return string
	 */
	protected function decimal($value)
	{
		return NumericString::decimal($value, 4);
	}

}
