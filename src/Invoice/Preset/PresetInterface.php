<?php

namespace Mpdf\Invoice\Preset;

/**
 * A convention for writing numbers, amounts, rates and dates, which a Formatter starts from
 */
interface PresetInterface
{

	/**
	 * @return string
	 */
	public function getDecimalPoint();

	/**
	 * @return string
	 */
	public function getThousandsSeparator();

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
