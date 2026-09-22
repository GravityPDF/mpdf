<?php

namespace Mpdf\Invoice;

class FormatterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each formatter with its currency, and what it makes of a quantity, an amount, a refund, a rate and a date
	 *
	 * @return mixed[]
	 */
	public function formatterProvider()
	{
		$french = (new Formatter(',', "\xc2\xa0", 'd/m/Y'))->withCurrencyFormat('EUR', "%s\xc2\xa0€")->withPercentFormat("%s\xc2\xa0%%");

		return [
			'default' => [new Formatter(), 'EUR', '1,500.25', '1,021.11 EUR', '-100.00 EUR', '5.5%', '2026-09-23'],
			'USD' => [Formatter::usd(), 'USD', '1,500.25', '$1,021.11', '-$100.00', '5.5%', '09/23/2026'],
			'EUR' => [Formatter::eur(), 'EUR', '1.500,25', "1.021,11\xc2\xa0€", "-100,00\xc2\xa0€", '5,5%', '23.09.2026'],
			'French' => [$french, 'EUR', "1\xc2\xa0500,25", "1\xc2\xa0021,11\xc2\xa0€", "-100,00\xc2\xa0€", "5,5\xc2\xa0%", '23/09/2026'],
		];
	}

	/**
	 * A formatter writes quantities, amounts, rates and dates by its conventions, the sign of a refund ahead of any symbol
	 *
	 * @dataProvider formatterProvider
	 *
	 * @param \Mpdf\Invoice\Formatter $formatter
	 * @param string $currency
	 * @param string $number
	 * @param string $money
	 * @param string $refund
	 * @param string $percent
	 * @param string $date
	 */
	public function testFormats(Formatter $formatter, $currency, $number, $money, $refund, $percent, $date)
	{
		$this->assertSame($number, $formatter->number(1500.25));
		$this->assertSame($money, $formatter->money(1021.11, $currency));
		$this->assertSame($refund, $formatter->money(-100, $currency));
		$this->assertSame($percent, $formatter->percent(5.5));
		$this->assertSame($date, $formatter->date(new \DateTime('2026-09-23')));
	}

	/**
	 * A currency without a format of its own is written with its code after the amount
	 */
	public function testWritesAnotherCurrencyByItsCode()
	{
		$this->assertSame('1,021.11 EUR', Formatter::usd()->money(1021.11, 'EUR'));
		$this->assertSame('1.021,11 USD', Formatter::eur()->money(1021.11, 'USD'));
	}

	/**
	 * A with method returns an adjusted copy, leaving the formatter it was called on as it was
	 */
	public function testAdjustsACopy()
	{
		$eur = Formatter::eur();
		$withPounds = $eur->withCurrencyFormat('GBP', '£%s');

		$this->assertSame('£1.021,11', $withPounds->money(1021.11, 'GBP'));
		$this->assertSame("1.021,11\xc2\xa0€", $withPounds->money(1021.11, 'EUR'));
		$this->assertSame('1.021,11 GBP', $eur->money(1021.11, 'GBP'));
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
	}

	/**
	 * A country's format given replaces the built-in one or adds one, and a comma left beside a missing part is closed up
	 */
	public function testTakesLocalityFormats()
	{
		$formatter = (new Formatter())
			->withLocalityFormat('US', '{postcode} {city}')
			->withLocalityFormat('BR', '{city}, {subdivision}, {postcode}');

		$this->assertSame('10118 New York', $formatter->locality((new Party('Buyer', 'US'))->setAddress('1 Main Street', '10118', 'New York')));
		$this->assertSame('São Paulo, SP, 01310-100', $formatter->locality($this->brazilian()->setCountrySubdivision('SP')));
		$this->assertSame('São Paulo, 01310-100', $formatter->locality($this->brazilian()));
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

	/**
	 * A buyer in São Paulo, with no state yet
	 *
	 * @return \Mpdf\Invoice\Party
	 */
	private function brazilian()
	{
		return (new Party('Buyer', 'BR'))->setAddress('1 Main Street', '01310-100', 'São Paulo');
	}

}
