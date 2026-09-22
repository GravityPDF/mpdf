<?php

namespace Mpdf\Invoice;

class FormatterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each formatter with its currency, and what it makes of a number, an amount in that currency and in another,
	 * a refund and a date
	 *
	 * @return mixed[]
	 */
	public function formatterProvider()
	{
		return [
			'default' => [new Formatter(), 'EUR', '1,500.25', '1,021.11 EUR', '1,021.11 USD', '-100.00 EUR', '2026-09-23'],
			'USD' => [Formatter::usd(), 'USD', '1,500.25', '$1,021.11', '1,021.11 EUR', '-$100.00', '09/23/2026'],
			'EUR' => [Formatter::eur(), 'EUR', '1.500,25', "1.021,11\xc2\xa0€", '1.021,11 USD', "-100,00\xc2\xa0€", '23.09.2026'],
			'French' => [
				new Formatter(',', "\xc2\xa0", 'd/m/Y', ['EUR' => "%s\xc2\xa0€"]),
				'EUR',
				"1\xc2\xa0500,25",
				"1\xc2\xa0021,11\xc2\xa0€",
				"1\xc2\xa0021,11 USD",
				"-100,00\xc2\xa0€",
				'23/09/2026',
			],
		];
	}

	/**
	 * A formatter writes numbers, amounts and dates by its conventions, and a currency it has no format for by its code
	 *
	 * @dataProvider formatterProvider
	 *
	 * @param \Mpdf\Invoice\Formatter $formatter
	 * @param string $currency The currency the formatter has a format for
	 * @param string $number
	 * @param string $money
	 * @param string $otherMoney The same amount in the other of EUR and USD
	 * @param string $refund
	 * @param string $date
	 */
	public function testFormats(Formatter $formatter, $currency, $number, $money, $otherMoney, $refund, $date)
	{
		$this->assertSame($number, $formatter->number(1500.25));
		$this->assertSame($money, $formatter->money(1021.11, $currency));
		$this->assertSame($otherMoney, $formatter->money(1021.11, $currency === 'EUR' ? 'USD' : 'EUR'));
		$this->assertSame($refund, $formatter->money(-100, $currency));
		$this->assertSame($date, $formatter->date(new \DateTime('2026-09-23')));
	}

	/**
	 * A number keeps up to four decimals and no trailing zeros, a nearly zero amount has no sign, and no date is null
	 */
	public function testRoundsAndLeavesOutWhatIsNotThere()
	{
		$formatter = new Formatter();

		$this->assertSame('7.5', $formatter->number(7.5));
		$this->assertSame('0.3333', $formatter->number(1 / 3));
		$this->assertSame('3', $formatter->number(3.0));
		$this->assertSame('0.00 EUR', $formatter->money(-0.001, 'EUR'));
		$this->assertNull($formatter->date(null));
	}

}
