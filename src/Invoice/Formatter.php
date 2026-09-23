<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\PresetInterface;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\Arrays;
use Mpdf\Utils\NumericString;

/**
 * How a printed trade document writes its numbers, amounts, rates, dates and addresses
 *
 * It follows the preset of a country, one of those in Mpdf\Invoice\Preset or a PresetInterface of your own, including
 * its month names. withDateFormat() and the other with methods return a copy adjusted further:
 *
 *     (new Formatter(new GermanyPreset()))->withCurrencyFormat('GBP', '£%s')
 *
 * Amounts have two decimals, or none in a currency without a minor unit such as the yen, and up to four when they
 * carry more, as a unit price of 0.125 does. An address is laid out as its party's country lays it out, whichever
 * preset is used: 75002 Paris, but New York, NY 10118.
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
	private $addressFormats = [
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
	 * @var bool[]
	 */
	private static $wholeCurrencies = [
		'BIF' => true, 'CLP' => true, 'DJF' => true, 'GNF' => true, 'ISK' => true, 'JPY' => true, 'KMF' => true, 'KRW' => true,
		'PYG' => true, 'RWF' => true, 'UGX' => true, 'VND' => true, 'VUV' => true, 'XAF' => true, 'XOF' => true, 'XPF' => true,
	];

	/**
	 * @var \Mpdf\Invoice\Preset\PresetInterface
	 */
	private $preset;

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
	 * @param \Mpdf\Invoice\Preset\PresetInterface $preset
	 *
	 * @throws \Mpdf\MpdfException When the preset gives no date format
	 */
	public function __construct(PresetInterface $preset)
	{
		if (!is_string($preset->getDateFormat())) {
			throw new MpdfException(sprintf('%s gives no date format', get_class($preset)));
		}

		$this->preset = $preset;
		$this->dateFormat = $preset->getDateFormat();
		$this->currencyFormats = $preset->getCurrencyFormats();
		$this->percentFormat = $preset->getPercentFormat();
	}

	/**
	 * A copy writing dates by another format
	 *
	 * @param string $format As DateTimeInterface::format() takes it, with {month} for the month's name in the preset's
	 *                       language, e.g. 'Y-m-d' or 'jS {month} Y'
	 *
	 * @return self
	 */
	public function withDateFormat($format)
	{
		$formatter = clone $this;
		$formatter->dateFormat = $format;

		return $formatter;
	}

	/**
	 * A copy writing dates out as the preset does in a letter: 23. September 2026, or September 23, 2026
	 *
	 * @return self
	 */
	public function withLongDates()
	{
		return $this->withDateFormat($this->preset->getLongDateFormat());
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
		return $this->separate(NumericString::decimal($number, 4));
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
	 * An amount to the cent, or whole in a currency without cents, and to at most four decimals when it has more; the
	 * sign goes ahead of any symbol: -$100.00
	 *
	 * @param float $amount
	 * @param string $currency ISO 4217 code
	 *
	 * @return string
	 */
	public function money($amount, $currency)
	{
		$decimal = NumericString::decimal($amount, 4);
		$parts = explode('.', ltrim($decimal, '-') . '.');
		$fraction = str_pad($parts[1], isset(self::$wholeCurrencies[$currency]) ? 0 : 2, '0');
		$format = Arrays::get($this->currencyFormats, $currency, '%s ' . $currency);
		$money = sprintf($format, $this->separate($parts[0] . ($fraction !== '' ? '.' . $fraction : '')));

		return $decimal[0] === '-' ? '-' . $money : $money;
	}

	/**
	 * A date in the preset's format, or the one given to withDateFormat()
	 *
	 * @param \DateTimeInterface $date
	 *
	 * @return string
	 */
	public function date(\DateTimeInterface $date)
	{
		// The name goes in after formatting, so its letters are not read as format characters
		$formatted = $date->format(str_replace('{month}', "\x01", $this->dateFormat));

		$monthNames = $this->preset->getMonthNames();

		return str_replace("\x01", $monthNames[$date->format('n') - 1], $formatted);
	}

	/**
	 * A party's address in the lines its country lays it out in, leaving out the parts it has none of and any line
	 * they leave empty
	 *
	 * @param \Mpdf\Invoice\Party $party
	 *
	 * @return string[]
	 */
	public function address(Party $party)
	{
		$country = $party->getCountryCode();
		$lines = Arrays::get($this->addressFormats, $country, self::$defaultAddressFormat);

		$parts = [
			'{street}' => (string) $party->getStreet(),
			'{additional}' => (string) $party->getAdditionalStreet(),
			'{postcode}' => (string) $party->getPostcode(),
			'{city}' => (string) $party->getCity(),
			'{subdivision}' => (string) $party->getCountrySubdivision(),
			'{country}' => $country,
		];

		$missing = array_keys($parts, '', true);

		$address = [];
		foreach ($lines as $line) {
			// Take the missing parts out of the layout before filling it, closing the gaps and commas they leave, so the
			// party's own text is never touched
			$line = trim(preg_replace(['/\s+/', '/ ?(, ?)+/'], [' ', ', '], str_replace($missing, '', $line)), ' ,');
			if ($line !== '') {
				$address[] = strtr($line, $parts);
			}
		}

		return $address;
	}

	/**
	 * A plain decimal such as -1234567.25 with its digits grouped and its decimal point as the preset writes them
	 *
	 * @param string $decimal
	 *
	 * @return string
	 */
	private function separate($decimal)
	{
		$sign = $decimal[0] === '-' ? '-' : '';
		$parts = explode('.', ltrim($decimal, '-'));
		$integer = $parts[0];
		$sizes = $this->preset->getGroupingSizes();
		$size = $sizes[0];
		$laterSize = end($sizes);

		if (strlen($integer) >= $size + $this->preset->getMinimumGroupingDigits()) {
			$groups = [];
			while (strlen($integer) > $size) {
				array_unshift($groups, substr($integer, -$size));
				$integer = substr($integer, 0, -$size);
				$size = $laterSize;
			}
			array_unshift($groups, $integer);
			$integer = implode($this->preset->getThousandsSeparator(), $groups);
		}

		return $sign . $integer . (isset($parts[1]) ? $this->preset->getDecimalPoint() . $parts[1] : '');
	}

}
