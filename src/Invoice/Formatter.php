<?php

namespace Mpdf\Invoice;

use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * How a printed trade document writes its numbers, amounts, dates and addresses: the separators, the date format,
 * where each currency's symbol goes, and the order of an address's postcode, city and state in each country
 *
 * The default writes 1,021.11 EUR and 2026-09-23. usd() and eur() are presets to start from, and the constructor takes
 * any other convention, e.g. for France new Formatter(',', "\xc2\xa0", 'd/m/Y', ['EUR' => "%s\xc2\xa0€"]).
 * Addresses follow their party's country whichever convention is used: 75002 Paris, but New York, NY 10118.
 */
class Formatter
{

	use Strict;

	/**
	 * The address line of the postcode, city and state in the countries that do not write the postcode first
	 *
	 * @var string[]
	 */
	private static $countryLocalityFormats = [
		'AU' => '{city} {subdivision} {postcode}',
		'CA' => '{city} {subdivision} {postcode}',
		'GB' => '{city} {subdivision} {postcode}',
		'US' => '{city}, {subdivision} {postcode}',
	];

	/**
	 * @var string
	 */
	private $decimalPoint;

	/**
	 * @var string
	 */
	private $thousandsSeparator;

	/**
	 * @var string
	 */
	private $dateFormat;

	/**
	 * @var string[]
	 */
	private $currencyFormats;

	/**
	 * @var string[]
	 */
	private $localityFormats;

	/**
	 * @param string $decimalPoint
	 * @param string $thousandsSeparator
	 * @param string $dateFormat As DateTimeInterface::format() takes it
	 * @param string[] $currencyFormats A sprintf() format for amounts in each ISO 4217 currency, e.g. ['USD' => '$%s'];
	 *                                  others are written with their code after them
	 * @param string[] $localityFormats The address line of the postcode, city and state in each ISO 3166-1 country, from
	 *                                  {postcode}, {city} and {subdivision}, over the built-in ones for AU, CA, GB and
	 *                                  US; others are written {postcode} {city} {subdivision}
	 */
	public function __construct($decimalPoint = '.', $thousandsSeparator = ',', $dateFormat = 'Y-m-d', array $currencyFormats = [], array $localityFormats = [])
	{
		$this->decimalPoint = $decimalPoint;
		$this->thousandsSeparator = $thousandsSeparator;
		$this->dateFormat = $dateFormat;
		$this->currencyFormats = $currencyFormats;
		$this->localityFormats = $localityFormats + self::$countryLocalityFormats;
	}

	/**
	 * The United States convention: $1,021.11 and 09/23/2026
	 *
	 * @return self
	 */
	public static function usd()
	{
		return new self('.', ',', 'm/d/Y', ['USD' => '$%s']);
	}

	/**
	 * The convention of Germany and much of the euro area: 1.021,11 € and 23.09.2026
	 *
	 * @return self
	 */
	public static function eur()
	{
		return new self(',', '.', 'd.m.Y', ['EUR' => "%s\xc2\xa0€"]);
	}

	/**
	 * A quantity or rate, to at most four decimals and without trailing zeros
	 *
	 * @param float $number
	 *
	 * @return string
	 */
	public function number($number)
	{
		$decimal = NumericString::decimal($number, 4);
		$point = strpos($decimal, '.');

		return number_format((float) $decimal, $point === false ? 0 : strlen($decimal) - $point - 1, $this->decimalPoint, $this->thousandsSeparator);
	}

	/**
	 * An amount to the cent in its currency, the sign ahead of any symbol: -$100.00
	 *
	 * @param float $amount
	 * @param string $currency ISO 4217 code
	 *
	 * @return string
	 */
	public function money($amount, $currency)
	{
		$amount = round($amount, 2);
		$format = isset($this->currencyFormats[$currency]) ? $this->currencyFormats[$currency] : '%s ' . $currency;
		$money = sprintf($format, number_format(abs($amount), 2, $this->decimalPoint, $this->thousandsSeparator));

		return $amount < 0 ? '-' . $money : $money;
	}

	/**
	 * The address line of a party's postcode, city and state in the order of its country, closed up around whichever of
	 * them it has none of
	 *
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return string Empty when it has none of them
	 */
	public function locality(Party $party)
	{
		$country = $party->getCountryCode();
		$format = isset($this->localityFormats[$country]) ? $this->localityFormats[$country] : '{postcode} {city} {subdivision}';

		$line = strtr($format, [
			'{postcode}' => (string) $party->getPostcode(),
			'{city}' => (string) $party->getCity(),
			'{subdivision}' => (string) $party->getCountrySubdivision(),
		]);

		return trim(preg_replace(['/\s+/', '/\s+,/', '/,(?=,)/'], [' ', ',', ''], $line), ' ,');
	}

	/**
	 * @param \DateTimeInterface|null $date
	 *
	 * @return string|null Null when there is no date
	 */
	public function date($date)
	{
		return $date !== null ? $date->format($this->dateFormat) : null;
	}

}
