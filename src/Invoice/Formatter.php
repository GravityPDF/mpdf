<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\DefaultPreset;
use Mpdf\Invoice\Preset\PresetInterface;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * How a printed trade document writes its numbers, amounts, rates, dates and addresses
 *
 * It follows a preset: DefaultPreset (1,021.11 EUR, 20% and 2026-09-23) unless given UsdPreset, EurPreset or a
 * convention of your own. The with methods adjust it further:
 *
 *     (new Formatter(new EurPreset()))->withCurrencyFormat('GBP', '£%s')
 *
 * Addresses follow their party's country whichever preset is used: 75002 Paris, but New York, NY 10118.
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
	 * @var string
	 */
	private $percentFormat;

	/**
	 * @var string[]
	 */
	private $localityFormats;

	/**
	 * @param \Mpdf\Invoice\Preset\PresetInterface|null $preset DefaultPreset when null
	 *
	 * @throws \Mpdf\MpdfException When the preset is not a PresetInterface
	 */
	public function __construct($preset = null)
	{
		if ($preset === null) {
			$preset = new DefaultPreset();
		}

		if (!$preset instanceof PresetInterface) {
			throw new MpdfException('A Formatter\'s preset must implement ' . PresetInterface::class);
		}

		$this->decimalPoint = $preset->getDecimalPoint();
		$this->thousandsSeparator = $preset->getThousandsSeparator();
		$this->dateFormat = $preset->getDateFormat();
		$this->currencyFormats = $preset->getCurrencyFormats();
		$this->percentFormat = $preset->getPercentFormat();
		$this->localityFormats = self::$countryLocalityFormats;
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
	 * A copy writing the addresses of a country with their postcode, city and state in another order. AU, CA, GB and
	 * US are built in, and any other country is written {postcode} {city} {subdivision}.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code
	 * @param string $format From {postcode}, {city} and {subdivision}
	 *
	 * @return self
	 */
	public function withLocalityFormat($country, $format)
	{
		$formatter = clone $this;
		$formatter->localityFormats[$country] = $format;

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

		return number_format((float) $decimal, $point === false ? 0 : strlen($decimal) - $point - 1, $this->decimalPoint, $this->thousandsSeparator);
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

		// Close the gaps the missing parts leave, then any comma left at either end
		return trim(preg_replace(['/\s+/', '/ ?(, ?)+/'], [' ', ', '], $line), ' ,');
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
