<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\AbstractPreset;
use Mpdf\Invoice\Preset\AustraliaPreset;
use Mpdf\Invoice\Preset\BelgiumDutchPreset;
use Mpdf\Invoice\Preset\BelgiumFrenchPreset;
use Mpdf\Invoice\Preset\CanadaEnglishPreset;
use Mpdf\Invoice\Preset\CanadaQuebecPreset;
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
use Mpdf\Invoice\Preset\SwedenPreset;
use Mpdf\Invoice\Preset\UnitedKingdomPreset;
use Mpdf\Invoice\Preset\UnitedStatesPreset;
use Mpdf\MpdfException;

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
		$nbsp = AbstractPreset::NBSP;

		return [
			'United States' => [new UnitedStatesPreset(), 'USD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '09/23/2026'],
			'Canada, English' => [new CanadaEnglishPreset(), 'CAD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '2026-09-23'],
			'Canada, Quebec' => [new CanadaQuebecPreset(), 'CAD', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}$", "-100,00{$nbsp}$", "5,5{$nbsp}%", '2026-09-23'],
			'Australia' => [new AustraliaPreset(), 'AUD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '23/09/2026'],
			'New Zealand' => [new NewZealandPreset(), 'NZD', '1,234,567.25', '$1,021.11', '-$100.00', '5.5%', '23/09/2026'],
			'United Kingdom' => [new UnitedKingdomPreset(), 'GBP', '1,234,567.25', '£1,021.11', '-£100.00', '5.5%', '23/09/2026'],
			'China' => [new ChinaPreset(), 'CNY', '1,234,567.25', '¥1,021.11', '-¥100.00', '5.5%', '2026-09-23'],
			'Japan' => [new JapanPreset(), 'JPY', '1,234,567.25', '¥1,021.11', '-¥100', '5.5%', '2026/09/23'],
			'India' => [new IndiaPreset(), 'INR', '12,34,567.25', '₹1,021.11', '-₹100.00', '5.5%', '23/09/2026'],
			'Germany' => [new GermanyPreset(), 'EUR', '1.234.567,25', "1.021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23.09.2026'],
			'France' => [new FrancePreset(), 'EUR', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23/09/2026'],
			'Italy' => [new ItalyPreset(), 'EUR', '1.234.567,25', "1.021,11{$nbsp}€", "-100,00{$nbsp}€", '5,5%', '23/09/2026'],
			'Spain' => [new SpainPreset(), 'EUR', '1.234.567,25', "1021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23/09/2026'],
			'Poland' => [new PolandPreset(), 'PLN', "1{$nbsp}234{$nbsp}567,25", "1021,11{$nbsp}zł", "-100,00{$nbsp}zł", '5,5%', '23.09.2026'],
			'Romania' => [new RomaniaPreset(), 'RON', '1.234.567,25', "1.021,11{$nbsp}lei", "-100,00{$nbsp}lei", "5,5{$nbsp}%", '23.09.2026'],
			'Netherlands' => [new NetherlandsPreset(), 'EUR', '1.234.567,25', "€{$nbsp}1.021,11", "-€{$nbsp}100,00", '5,5%', '23-09-2026'],
			'Belgium, Dutch' => [new BelgiumDutchPreset(), 'EUR', '1.234.567,25', "€{$nbsp}1.021,11", "-€{$nbsp}100,00", '5,5%', '23/09/2026'],
			'Belgium, French' => [new BelgiumFrenchPreset(), 'EUR', '1.234.567,25', "1.021,11{$nbsp}€", "-100,00{$nbsp}€", "5,5{$nbsp}%", '23/09/2026'],
			'Czechia' => [new CzechiaPreset(), 'CZK', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}Kč", "-100,00{$nbsp}Kč", "5,5{$nbsp}%", '23.09.2026'],
			'Portugal' => [new PortugalPreset(), 'EUR', "1{$nbsp}234{$nbsp}567,25", "1021,11{$nbsp}€", "-100,00{$nbsp}€", '5,5%', '23/09/2026'],
			'Sweden' => [new SwedenPreset(), 'SEK', "1{$nbsp}234{$nbsp}567,25", "1{$nbsp}021,11{$nbsp}kr", "-100,00{$nbsp}kr", "5,5{$nbsp}%", '2026-09-23'],
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
		$this->assertSame('10.211,11' . AbstractPreset::NBSP . '€', (new Formatter(new SpainPreset()))->money(10211.11, 'EUR'));
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
		$this->assertSame('1.021 JPY', (new Formatter(new GermanyPreset()))->money(1021, 'JPY'));
	}

	/**
	 * A date format given replaces the preset's
	 */
	public function testTakesADateFormat()
	{
		$formatter = (new Formatter(new UnitedKingdomPreset()))->withDateFormat('Y-m-d');

		$this->assertSame('2026-09-23', $formatter->date(new \DateTime('2026-09-23')));
	}

	/**
	 * Each country's preset and a date written out as it does, in its language and with the month as a date takes it
	 *
	 * @return mixed[]
	 */
	public function longDateProvider()
	{
		return [
			'United States' => [new UnitedStatesPreset(), 'September 23, 2026'],
			'Canada, English' => [new CanadaEnglishPreset(), 'September 23, 2026'],
			'Canada, Quebec' => [new CanadaQuebecPreset(), '23 septembre 2026'],
			'Australia' => [new AustraliaPreset(), '23 September 2026'],
			'New Zealand' => [new NewZealandPreset(), '23 September 2026'],
			'United Kingdom' => [new UnitedKingdomPreset(), '23 September 2026'],
			'China' => [new ChinaPreset(), '2026年9月23日'],
			'Japan' => [new JapanPreset(), '2026年9月23日'],
			'India' => [new IndiaPreset(), '23 September 2026'],
			'Germany' => [new GermanyPreset(), '23. September 2026'],
			'France' => [new FrancePreset(), '23 septembre 2026'],
			'Italy' => [new ItalyPreset(), '23 settembre 2026'],
			'Spain' => [new SpainPreset(), '23 de septiembre de 2026'],
			'Poland' => [new PolandPreset(), '23 września 2026'],
			'Romania' => [new RomaniaPreset(), '23 septembrie 2026'],
			'Netherlands' => [new NetherlandsPreset(), '23 september 2026'],
			'Belgium, Dutch' => [new BelgiumDutchPreset(), '23 september 2026'],
			'Belgium, French' => [new BelgiumFrenchPreset(), '23 septembre 2026'],
			'Czechia' => [new CzechiaPreset(), '23. září 2026'],
			'Portugal' => [new PortugalPreset(), '23 de setembro de 2026'],
			'Sweden' => [new SwedenPreset(), '23 september 2026'],
		];
	}

	/**
	 * A formatter asked for long dates writes them as its country does
	 *
	 * @dataProvider longDateProvider
	 *
	 * @param \Mpdf\Invoice\Preset\PresetInterface $preset
	 * @param string $expected
	 */
	public function testWritesLongDatesAsTheCountryDoes($preset, $expected)
	{
		$this->assertSame($expected, (new Formatter($preset))->withLongDates()->date(new \DateTime('2026-09-23')));
	}

	/**
	 * {month} gives the month's name in the preset's language in any date format, its letters left alone
	 */
	public function testNamesTheMonthInAnyDateFormat()
	{
		$date = new \DateTime('2020-01-01');

		$this->assertSame('1st January 2020', (new Formatter(new UnitedKingdomPreset()))->withDateFormat('jS {month} Y')->date($date));
		$this->assertSame('am 1. Januar 2020', (new Formatter(new GermanyPreset()))->withDateFormat('\a\m j. {month} Y')->date($date));
		$this->assertSame('1 stycznia 2020', (new Formatter(new PolandPreset()))->withDateFormat('j {month} Y')->date($date));
	}

	/**
	 * A with method returns an adjusted copy, leaving the formatter it was called on as it was
	 */
	public function testAdjustsACopy()
	{
		$germany = new Formatter(new GermanyPreset());
		$withPounds = $germany->withCurrencyFormat('GBP', '£%s');

		$this->assertSame('£1.021,11', $withPounds->money(1021.11, 'GBP'));
		$this->assertSame('1.021,11' . AbstractPreset::NBSP . '€', $withPounds->money(1021.11, 'EUR'));
		$this->assertSame('1.021,11 GBP', $germany->money(1021.11, 'GBP'));
	}

	/**
	 * An address in each country whose layout differs, and in two that share the usual one, down to one with nothing
	 * but its country
	 *
	 * @return mixed[]
	 */
	public function addressProvider()
	{
		return [
			'France' => ['FR', '12 rue de la Paix', '75002', 'Paris', null, ['12 rue de la Paix', '75002 Paris', 'FR']],
			'Italy with a province' => ['IT', 'Via Roma 1', '00144', 'Roma', 'RM', ['Via Roma 1', '00144 Roma RM', 'IT']],
			'United States' => ['US', '350 Fifth Avenue', '10118', 'New York', 'NY', ['350 Fifth Avenue', 'New York, NY 10118', 'US']],
			'United States without a state' => ['US', '350 Fifth Avenue', '10118', 'New York', null, ['350 Fifth Avenue', 'New York, 10118', 'US']],
			'Canada' => ['CA', '1 Front Street', 'M5V 2T6', 'Toronto', 'ON', ['1 Front Street', 'Toronto ON M5V 2T6', 'CA']],
			'Australia' => ['AU', '1 George Street', '2000', 'Sydney', 'NSW', ['1 George Street', 'Sydney NSW 2000', 'AU']],
			'New Zealand' => ['NZ', '1 Queen Street', '1010', 'Auckland', null, ['1 Queen Street', 'Auckland 1010', 'NZ']],
			'United Kingdom' => ['GB', '10 Downing Street', 'SW1A 2AA', 'London', null, ['10 Downing Street', 'London', 'SW1A 2AA', 'GB']],
			'India' => ['IN', '1 Marine Drive', '400020', 'Mumbai', 'Maharashtra', ['1 Marine Drive', 'Mumbai 400020', 'Maharashtra', 'IN']],
			'China' => ['CN', '1 Jianguomenwai Avenue', '100020', 'Chaoyang District', 'Beijing', ['1 Jianguomenwai Avenue', 'Chaoyang District, Beijing 100020', 'CN']],
			'Japan' => ['JP', '1-1 Chiyoda', '100-0001', 'Chiyoda-ku', 'Tokyo', ['1-1 Chiyoda', 'Chiyoda-ku, Tokyo 100-0001', 'JP']],
			'nothing but the country' => ['US', null, null, null, null, ['US']],
		];
	}

	/**
	 * An address is laid out as its party's country lays it out, each line closed up around the parts missing from it
	 * and the lines left empty dropped, whatever the preset
	 *
	 * @dataProvider addressProvider
	 *
	 * @param string $country
	 * @param string|null $street
	 * @param string|null $postcode
	 * @param string|null $city
	 * @param string|null $subdivision
	 * @param string[] $expected
	 */
	public function testLaysOutTheAddressOfTheCountry($country, $street, $postcode, $city, $subdivision, array $expected)
	{
		$party = (new Party('Buyer', $country))->setAddress($street, $postcode, $city);
		if ($subdivision !== null) {
			$party->setCountrySubdivision($subdivision);
		}

		$this->assertSame($expected, (new Formatter(new UnitedStatesPreset()))->address($party));
		$this->assertSame($expected, (new Formatter(new GermanyPreset()))->address($party));
	}

	/**
	 * A country's layout given replaces the built-in one or adds one, and a comma left beside a missing part is closed up
	 */
	public function testTakesAddressFormats()
	{
		$formatter = (new Formatter(new UnitedStatesPreset()))
			->withAddressFormat('US', ['{street}', '{postcode} {city}'])
			->withAddressFormat('BR', ['{street}', '{city}, {subdivision}, {postcode}', '{country}']);
		$us = (new Party('Buyer', 'US'))->setAddress('350 Fifth Avenue', '10118', 'New York', 'Suite 4200');

		$this->assertSame(['350 Fifth Avenue', '10118 New York'], $formatter->address($us));
		$this->assertSame(['Avenida Paulista 1', 'São Paulo, SP, 01310-100', 'BR'], $formatter->address($this->brazilian()->setCountrySubdivision('SP')));
		$this->assertSame(['Avenida Paulista 1', 'São Paulo, 01310-100', 'BR'], $formatter->address($this->brazilian()));
	}

	/**
	 * A second street line goes after the first
	 */
	public function testKeepsTheSecondStreetLine()
	{
		$party = (new Party('Buyer', 'GB'))->setAddress('Flat 2', 'SW1A 2AA', 'London', '10 Downing Street');

		$this->assertSame(['Flat 2', '10 Downing Street', 'London', 'SW1A 2AA', 'GB'], (new Formatter(new UnitedKingdomPreset()))->address($party));
	}

	/**
	 * A number keeps up to four decimals and no trailing zeros, and a nearly zero amount has no sign
	 */
	public function testRoundsAndLeavesOutWhatIsNotThere()
	{
		$formatter = new Formatter(new UnitedKingdomPreset());

		$this->assertSame('7.5', $formatter->number(7.5));
		$this->assertSame('0.3333', $formatter->number(1 / 3));
		$this->assertSame('3', $formatter->number(3.0));
		$this->assertSame('-1,500.25', $formatter->number(-1500.25));
		$this->assertSame('0.00 EUR', $formatter->money(-0.00001, 'EUR'));
	}

	/**
	 * An amount keeps the decimals it has beyond its currency's minor unit, up to four, so a printed unit price is the
	 * one the XML carries
	 */
	public function testKeepsTheDecimalsAnAmountHas()
	{
		$formatter = new Formatter(new UnitedKingdomPreset());

		$this->assertSame('£0.125', $formatter->money(0.125, 'GBP'));
		$this->assertSame('£1.50', $formatter->money(1.5, 'GBP'));
		$this->assertSame('£0.3333', $formatter->money(1 / 3, 'GBP'));
		$this->assertSame('1,021.5 JPY', $formatter->money(1021.5, 'JPY'));
	}

	/**
	 * A preset that gives no date format is refused, since no country's is assumed
	 */
	public function testRefusesAPresetWithoutADateFormat()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('gives no date format');

		new Formatter($this->getMockForAbstractClass(AbstractPreset::class));
	}

	/**
	 * An address keeps the party's own spacing and commas, which only the layout's are closed up around
	 */
	public function testLeavesThePartysOwnTextAlone()
	{
		$party = (new Party('Shop', 'FR'))->setAddress('Bâtiment A ,  12 rue de la Paix', '75002', 'Paris');

		$this->assertSame(['Bâtiment A ,  12 rue de la Paix', '75002 Paris', 'FR'], (new Formatter(new FrancePreset()))->address($party));
	}

	/**
	 * A buyer in São Paulo, with no state yet
	 *
	 * @return \Mpdf\Invoice\Party
	 */
	private function brazilian()
	{
		return (new Party('Buyer', 'BR'))->setAddress('Avenida Paulista 1', '01310-100', 'São Paulo');
	}

}
