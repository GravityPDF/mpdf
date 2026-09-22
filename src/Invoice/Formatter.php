<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\PresetInterface;
use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * How a printed trade document writes its numbers, amounts, rates, dates and addresses
 *
 * It follows the preset of a country, one of those in Mpdf\Invoice\Preset or a PresetInterface of your own, and the
 * with methods adjust it further:
 *
 *     (new Formatter(new GermanyPreset()))->withCurrencyFormat('GBP', '£%s')
 *
 * Amounts are to the cent but in a currency without one, such as the yen. An address is laid out as its party's
 * country lays them out, whichever preset is used: 75002 Paris, but New York, NY 10118.
 */
class Formatter
{

	use Strict;

	/**
	 * The lines of an address in the countries that do not write the postcode, city and state on one line after the
	 * street, in that order. A Latin-script address in China or Japan follows the Western order.
	 *
	 * @var string[][]
	 */
	private static $countryAddressFormats = [
		'AU' => ['{street}', '{additional}', '{city} {subdivision} {postcode}', '{country}'],
		'CA' => ['{street}', '{additional}', '{city} {subdivision} {postcode}', '{country}'],
		'CN' => ['{street}', '{additional}', '{city}, {subdivision} {postcode}', '{country}'],
		'GB' => ['{street}', '{additional}', '{city}', '{postcode}', '{country}'],
		'IN' => ['{street}', '{additional}', '{city} {postcode}', '{subdivision}', '{country}'],
		'JP' => ['{street}', '{additional}', '{city}, {subdivision} {postcode}', '{country}'],
		'NZ' => ['{street}', '{additional}', '{city} {postcode}', '{country}'],
		'US' => ['{street}', '{additional}', '{city}, {subdivision} {postcode}', '{country}'],
	];

	/**
	 * The lines of an address in every other country
	 *
	 * @var string[]
	 */
	private static $defaultAddressFormat = ['{street}', '{additional}', '{postcode} {city} {subdivision}', '{country}'];

	/**
	 * The ISO 4217 currencies with no minor unit, whose amounts are written whole
	 *
	 * @var string[]
	 */
	private static $wholeCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

	/**
	 * @var string
	 */
	private $decimalPoint;

	/**
	 * @var string
	 */
	private $thousandsSeparator;

	/**
	 * @var int[]
	 */
	private $groupingSizes;

	/**
	 * @var int
	 */
	private $minimumGroupingDigits;

	/**
	 * @var string
	 */
	private $dateFormat;

	/**
	 * @var string[]
	 */
	private $currencyFormats;

	/**
	 * @var string
	 */
	private $percentFormat;

	/**
	 * @var string[][]
	 */
	private $addressFormats;

	/**
	 * @param \Mpdf\Invoice\Preset\PresetInterface $preset
	 */
	public function __construct(PresetInterface $preset)
	{
		$this->decimalPoint = $preset->getDecimalPoint();
		$this->thousandsSeparator = $preset->getThousandsSeparator();
		$this->groupingSizes = $preset->getGroupingSizes();
		$this->minimumGroupingDigits = $preset->getMinimumGroupingDigits();
		$this->dateFormat = $preset->getDateFormat();
		$this->currencyFormats = $preset->getCurrencyFormats();
		$this->percentFormat = $preset->getPercentFormat();
		$this->addressFormats = self::$countryAddressFormats;
	}

	/**
	 * A copy writing amounts in a currency by a format of its own; a currency without one has its code after the amount
	 *
	 * @param string $currency ISO 4217 code
	 * @param string $format A sprintf() format for the amount, e.g. '$%s'
	 *
	 * @return self
	 */
	public function withCurrencyFormat($currency, $format)
	{
		$formatter = clone $this;
		$formatter->currencyFormats[$currency] = $format;

		return $formatter;
	}

	/**
	 * A copy writing rates by another format
	 *
	 * @param string $format A sprintf() format for the rate, e.g. '%s%%'
	 *
	 * @return self
	 */
	public function withPercentFormat($format)
	{
		$formatter = clone $this;
		$formatter->percentFormat = $format;

		return $formatter;
	}

	/**
	 * A copy laying out the addresses of a country another way
	 *
	 * @param string $country ISO 3166-1 alpha-2 code
	 * @param string[] $lines Each line from {street}, {additional}, {postcode}, {city}, {subdivision} and {country},
	 *                        e.g. ['{street}', '{city} {postcode}', '{country}']
	 *
	 * @return self
	 */
	public function withAddressFormat($country, array $lines)
	{
		$formatter = clone $this;
		$formatter->addressFormats[$country] = $lines;

		return $formatter;
	}

	/**
	 * A quantity, to at most four decimals and without trailing zeros
	 *
	 * @param float $number
	 *
	 * @return string
	 */
	public function number($number)
	{
		$decimal = NumericString::decimal($number, 4);
		$point = strpos($decimal, '.');

		return $this->separate((float) $decimal, $point === false ? 0 : strlen($decimal) - $point - 1);
	}

	/**
	 * A rate, such as a VAT rate, as a number with its percent sign
	 *
	 * @param float $rate
	 *
	 * @return string
	 */
	public function percent($rate)
	{
		return sprintf($this->percentFormat, $this->number($rate));
	}

	/**
	 * An amount to the cent, or whole in a currency without cents, the sign ahead of any symbol: -$100.00
	 *
	 * @param float $amount
	 * @param string $currency ISO 4217 code
	 *
	 * @return string
	 */
	public function money($amount, $currency)
	{
		$decimals = in_array($currency, self::$wholeCurrencies, true) ? 0 : 2;
		$amount = round($amount, $decimals);
		$format = isset($this->currencyFormats[$currency]) ? $this->currencyFormats[$currency] : '%s ' . $currency;
		$money = sprintf($format, $this->separate(abs($amount), $decimals));

		return $amount < 0 ? '-' . $money : $money;
	}

	/**
	 * A party's address as the lines its country lays it out in, each closed up around the parts it has none of, and
	 * without the lines left empty
	 *
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return string[]
	 */
	public function address(Party $party)
	{
		$country = $party->getCountryCode();
		$lines = isset($this->addressFormats[$country]) ? $this->addressFormats[$country] : self::$defaultAddressFormat;

		$parts = [
			'{street}' => (string) $party->getStreet(),
			'{additional}' => (string) $party->getAdditionalStreet(),
			'{postcode}' => (string) $party->getPostcode(),
			'{city}' => (string) $party->getCity(),
			'{subdivision}' => (string) $party->getCountrySubdivision(),
			'{country}' => $country,
		];

		$address = [];
		foreach ($lines as $line) {
			// Close the gaps the missing parts leave, then any comma left at either end
			$line = trim(preg_replace(['/\s+/', '/ ?(, ?)+/'], [' ', ', '], strtr($line, $parts)), ' ,');
			if ($line !== '') {
				$address[] = $line;
			}
		}

		return $address;
	}

	/**
	 * A number to so many decimals, its digits grouped and its decimal point as the preset writes them
	 *
	 * @param float $number
	 * @param int $decimals
	 *
	 * @return string
	 */
	private function separate($number, $decimals)
	{
		$parts = explode('.', number_format(abs($number), $decimals, '.', ''));
		$integer = $parts[0];

		$size = $this->groupingSizes[0];
		if (strlen($integer) >= $size + $this->minimumGroupingDigits) {
			$groups = [substr($integer, -$size)];
			$integer = substr($integer, 0, -$size);
			$size = $this->groupingSizes[count($this->groupingSizes) - 1];
			while (strlen($integer) > $size) {
				array_unshift($groups, substr($integer, -$size));
				$integer = substr($integer, 0, -$size);
			}
			array_unshift($groups, $integer);
			$integer = implode($this->thousandsSeparator, $groups);
		}

		return ($number < 0 ? '-' : '') . $integer . (isset($parts[1]) ? $this->decimalPoint . $parts[1] : '');
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
