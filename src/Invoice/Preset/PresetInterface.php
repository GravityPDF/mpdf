<?php

namespace Mpdf\Invoice\Preset;

/**
 * How a country writes numbers, amounts, rates and dates, which a Formatter follows. Addresses are not part of it: the
 * Formatter lays each out as its party's country does, whatever the preset.
 */
interface PresetInterface
{

	/**
	 * @return string
	 */
	public function getDecimalPoint();

	/**
	 * @return string The separator between groups of digits
	 */
	public function getThousandsSeparator();

	/**
	 * The size of the group of digits nearest the decimal point, then of every group before it when that differs
	 *
	 * @return int[] [3] for 1,234,567, or [3, 2] for India's 12,34,567
	 */
	public function getGroupingSizes();

	/**
	 * How many digits must come before the first group for it to be separated: 2 when 1021 is written whole but
	 * 10,211 is not
	 *
	 * @return int
	 */
	public function getMinimumGroupingDigits();

	/**
	 * @return string As DateTimeInterface::format() takes it
	 */
	public function getDateFormat();

	/**
	 * A sprintf() format for amounts in each currency that has its own; the rest are written with their code after them
	 *
	 * @return string[] By ISO 4217 code, e.g. ['USD' => '$%s']
	 */
	public function getCurrencyFormats();

	/**
	 * @return string A sprintf() format for a rate, e.g. '%s%%'
	 */
	public function getPercentFormat();

}
