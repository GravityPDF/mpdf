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
	 * Addresses in countries that write the postcode first and in those that do not, down to one with none of its parts
	 *
	 * @return mixed[]
	 */
	public function localityProvider()
	{
		return [
			'France' => ['FR', '75002', 'Paris', null, '75002 Paris'],
			'Italy with a province' => ['IT', '00144', 'Roma', 'RM', '00144 Roma RM'],
			'United States' => ['US', '10118', 'New York', 'NY', 'New York, NY 10118'],
			'United States without a state' => ['US', '10118', 'New York', null, 'New York, 10118'],
			'United States without a city' => ['US', '10118', null, 'NY', 'NY 10118'],
			'Canada' => ['CA', 'M5V 2T6', 'Toronto', 'ON', 'Toronto ON M5V 2T6'],
			'United Kingdom' => ['GB', 'SW1A 1AA', 'London', null, 'London SW1A 1AA'],
			'none' => ['US', null, null, null, ''],
		];
	}

	/**
	 * The postcode, city and state are written in the order of the party's country, closed up around whichever are
	 * missing, whatever the currency convention
	 *
	 * @dataProvider localityProvider
	 *
	 * @param string $country
	 * @param string|null $postcode
	 * @param string|null $city
	 * @param string|null $subdivision
	 * @param string $expected
	 */
	public function testWritesTheLocalityOfTheCountry($country, $postcode, $city, $subdivision, $expected)
	{
		$party = (new Party('Buyer', $country))->setAddress('1 Main Street', $postcode, $city);
		if ($subdivision !== null) {
			$party->setCountrySubdivision($subdivision);
		}

		$this->assertSame($expected, (new Formatter())->locality($party));
		$this->assertSame($expected, Formatter::usd()->locality($party));
		$this->assertSame($expected, Formatter::eur()->locality($party));
	}

	/**
	 * A country's format given replaces the built-in one, and one given for another country is added
	 */
	public function testTakesLocalityFormats()
	{
		$formatter = new Formatter('.', ',', 'Y-m-d', [], ['US' => '{postcode} {city}', 'BR' => '{city} - {subdivision} {postcode}']);
		$us = (new Party('Buyer', 'US'))->setAddress('1 Main Street', '10118', 'New York');
		$brazil = (new Party('Buyer', 'BR'))->setAddress('1 Main Street', '01310-100', 'São Paulo')->setCountrySubdivision('SP');

		$this->assertSame('10118 New York', $formatter->locality($us));
		$this->assertSame('São Paulo - SP 01310-100', $formatter->locality($brazil));
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
