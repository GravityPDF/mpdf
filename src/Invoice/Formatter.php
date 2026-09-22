<?php

namespace Mpdf\Invoice;

use Mpdf\Strict;
use Mpdf\Utils\NumericString;

/**
 * How a printed trade document writes its numbers, amounts and dates: the separators, the date format, and where each
 * currency's symbol goes
 *
 * The default writes 1,021.11 EUR and 2026-09-23. usd() and eur() are presets to start from, and the constructor takes
 * any other convention, e.g. for France new Formatter(',', "\xc2\xa0", 'd/m/Y', ['EUR' => "%s\xc2\xa0€"]).
 */
class Formatter
{

	use Strict;

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
	 * @param string $decimalPoint
	 * @param string $thousandsSeparator
	 * @param string $dateFormat As DateTimeInterface::format() takes it
	 * @param string[] $currencyFormats A sprintf() format for amounts in each ISO 4217 currency, e.g. ['USD' => '$%s'];
	 *                                  others are written with their code after them
	 */
	public function __construct($decimalPoint = '.', $thousandsSeparator = ',', $dateFormat = 'Y-m-d', array $currencyFormats = [])
	{
		$this->decimalPoint = $decimalPoint;
		$this->thousandsSeparator = $thousandsSeparator;
		$this->dateFormat = $dateFormat;
		$this->currencyFormats = $currencyFormats;
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
	 * @param \DateTimeInterface|null $date
	 *
	 * @return string|null Null when there is no date
	 */
	public function date($date)
	{
		return $date !== null ? $date->format($this->dateFormat) : null;
	}

}
