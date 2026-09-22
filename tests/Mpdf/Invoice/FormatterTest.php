<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\AustraliaPreset;
use Mpdf\Invoice\Preset\BelgiumPreset;
use Mpdf\Invoice\Preset\CanadaPreset;
use Mpdf\Invoice\Preset\ChinaPreset;
use Mpdf\Invoice\Preset\CzechiaPreset;
use Mpdf\Invoice\Preset\FrancePreset;
use Mpdf\Invoice\Preset\GermanyPreset;
use Mpdf\Invoice\Preset\IndiaPreset;
use Mpdf\Invoice\Preset\ItalyPreset;
use Mpdf\Invoice\Preset\JapanPreset;
use Mpdf\Invoice\Preset\NetherlandsPreset;
use Mpdf\Invoice\Preset\NewZealandPreset;
use Mpdf\Invoice\Preset\PolandPreset;
use Mpdf\Invoice\Preset\PortugalPreset;
use Mpdf\Invoice\Preset\RomaniaPreset;
use Mpdf\Invoice\Preset\SpainPreset;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;
use Mpdf\Invoice\Preset\UnitedStatesPreset;

class FormatterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each country's preset with its currency, and what a formatter following it makes of a large number, an amount of
	 * four digits, a refund, a rate and a date
	 *
	 * @return mixed[]
	 */
	public function presetProvider()
	{
		$nbsp = "\xc2\xa0";

		return [
			'United States' => [new UnitedStatesPreset(), 'USD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '09/23/2026'],
			'Canada' => [new CanadaPreset(), 'CAD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '2026-09-23'],
			'Australia' => [new AustraliaPreset(), 'AUD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '23/09/2026'],
			'New Zealand' => [new NewZealandPreset(), 'NZD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '23/09/2026'],
			'United Kingdom' => [new UnitedKingdomPreset(), 'GBP', '1,234,567.25', '£1,021.11', '-£100.00', '5.5%', '23/09/2026'],
			'China' => [new ChinaPreset(), 'CNY', '1,234,567.25', '¥1,021.11', '-¥100.00', '5.5%', '2026-09-23'],
			'Japan' => [new JapanPreset(), 'JPY', '1,234,567.25', '¥1,021', '-¥100', '5.5%', '2026/09/23'],
			'India' => [new IndiaPreset(), 'INR', '12,34,567.25', '₹1,021.11', '-₹100.00', '5.5%', '23/09/2026'],
			'Germany' => [new GermanyPreset(), 'EUR', '1.234.567,25', "1.021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23.09.2026'],
			'France' => [new FrancePreset(), 'EUR', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23/09/2026'],
			'Italy' => [new ItalyPreset(), 'EUR', '1.234.567,25', "1.021,11{$nbsp}€", "-100,00{$nbsp}€", '5,5%', '23/09/2026'],
			'Spain' => [new SpainPreset(), 'EUR', '1.234.567,25', "1021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23/09/2026'],
			'Poland' => [new PolandPreset(), 'PLN', "1{$nbsp}234{$nbsp}567,25", "1021,11{$nbsp}zł", "-100,00{$nbsp}zł", '5,5%', '23.09.2026'],
			'Romania' => [new RomaniaPreset(), 'RON', '1.234.567,25', "1.021,11{$nbsp}lei", "-100,00{$nbsp}lei", "5,5{$nbsp}%", '23.09.2026'],
			'Netherlands' => [new NetherlandsPreset(), 'EUR', '1.234.567,25', "€{$nbsp}1.021,11", "-€{$nbsp}100,00", '5,5%', '23-09-2026'],
			'Belgium' => [new BelgiumPreset(), 'EUR', '1.234.567,25', "€{$nbsp}1.021,11", "-€{$nbsp}100,00", '5,5%', '23/09/2026'],
			'Czechia' => [new CzechiaPreset(), 'CZK', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}Kč", "-100,00{$nbsp}Kč", "5,5{$nbsp}%", '23.09.2026'],
			'Portugal' => [new PortugalPreset(), 'EUR', "1{$nbsp}234{$nbsp}567,25", "1021,11{$nbsp}€", "-100,00{$nbsp}€", '5,5%', '23/09/2026'],
		];
	}

	/**
	 * A formatter writes numbers, amounts, rates and dates as its country does, the sign of a refund ahead of any symbol
	 *
	 * @dataProvider presetProvider
	 *
	 * @param \Mpdf\Invoice\Preset\PresetInterface $preset
	 * @param string $currency The country's currency
	 * @param string $number
	 * @param string $money
	 * @param string $refund
	 * @param string $percent
	 * @param string $date
	 */
	public function testFormatsAsTheCountryDoes($preset, $currency, $number, $money, $refund, $percent, $date)
	{
		$formatter = new Formatter($preset);

		$this->assertSame($number, $formatter->number(1234567.25));
		$this->assertSame($money, $formatter->money(1021.11, $currency));
		$this->assertSame($refund, $formatter->money(-100, $currency));
		$this->assertSame($percent, $formatter->percent(5.5));
		$this->assertSame($date, $formatter->date(new \DateTime('2026-09-23')));
	}

	/**
	 * A country that writes a four-digit number whole still groups one of five, and India groups a crore in pairs
	 */
	public function testGroupsDigitsAsTheCountryDoes()
	{
		$this->assertSame("10.211,11\xc2\xa0€", (new Formatter(new SpainPreset()))->money(10211.11, 'EUR'));
		$this->assertSame('₹1,23,45,678.00', (new Formatter(new IndiaPreset()))->money(12345678, 'INR'));
		$this->assertSame('₹999.00', (new Formatter(new IndiaPreset()))->money(999, 'INR'));
	}

	/**
	 * A currency the preset has no format for is written with its code after the amount, and one without a minor unit
	 * is written whole whatever the preset
	 */
	public function testWritesAnotherCurrencyByItsCode()
	{
		$this->assertSame('1,021.11 EUR', (new Formatter(new UnitedStatesPreset()))->money(1021.11, 'EUR'));
		$this->assertSame('1.021,11 USD', (new Formatter(new GermanyPreset()))->money(1021.11, 'USD'));
		$this->assertSame('1.022 JPY', (new Formatter(new GermanyPreset()))->money(1021.5, 'JPY'));
	}

	/**
	 * A with method returns an adjusted copy, leaving the formatter it was called on as it was
	 */
	public function testAdjustsACopy()
	{
		$germany = new Formatter(new GermanyPreset());
		$withPounds = $germany->withCurrencyFormat('GBP', '£%s');

		$this->assertSame('£1.021,11', $withPounds->money(1021.11, 'GBP'));
		$this->assertSame("1.021,11\xc2\xa0€", $withPounds->money(1021.11, 'EUR'));
		$this->assertSame('1.021,11 GBP', $germany->money(1021.11, 'GBP'));
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
	 * missing, whatever the preset
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

		$this->assertSame($expected, (new Formatter(new UnitedStatesPreset()))->locality($party));
		$this->assertSame($expected, (new Formatter(new GermanyPreset()))->locality($party));
	}

	/**
	 * A country's format given replaces the built-in one or adds one, and a comma left beside a missing part is closed up
	 */
	public function testTakesLocalityFormats()
	{
		$formatter = (new Formatter(new UnitedStatesPreset()))
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
		$formatter = new Formatter(new UnitedKingdomPreset());

		$this->assertSame('7.5', $formatter->number(7.5));
		$this->assertSame('0.3333', $formatter->number(1 / 3));
		$this->assertSame('3', $formatter->number(3.0));
		$this->assertSame('-1,500.25', $formatter->number(-1500.25));
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
