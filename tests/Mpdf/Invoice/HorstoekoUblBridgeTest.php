<?php

namespace Mpdf\Invoice;

use Mpdf\Invoice\Preset\UnitedKingdomPreset;
use Mpdf\InvoiceFixtures;

/**
 * UBL converted from Cross Industry Invoice XML by horstoeko/zugferdublbridge, the package the documentation points to
 * for UBL, reads and prints as the XML it was converted from
 *
 * The package needs PHP 7.3, so it is not in require-dev; the einvoice-interop workflow installs it before running
 * this group, and elsewhere the tests are skipped.
 *
 * @group interop
 */
class HorstoekoUblBridgeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Skip unless horstoeko/zugferdublbridge is installed
	 */
	protected function set_up()
	{
		parent::set_up();

		if (!class_exists('horstoeko\zugferdublbridge\XmlConverterCiiToUbl')) {
			$this->markTestSkipped('horstoeko/zugferdublbridge is not installed; run composer require --dev horstoeko/zugferdublbridge');
		}
	}

	/**
	 * The fixtures in tests/data/invoice whose every field the package carries into UBL
	 *
	 * It leaves out a preceding invoice's issue date, when VAT is due (BT-8) and a MINIMUM invoice's VAT total, and UBL
	 * has no form for EXTENDED's structured payment terms, so the fixtures with those are not here.
	 *
	 * @return string[][]
	 */
	public function fixtureProvider()
	{
		$fixtures = [];
		foreach (['basic', 'basic-wl', 'en16931', 'en16931-intra-community', 'en16931-many-lines', 'en16931-not-subject', 'en16931-reverse-charge', 'en16931-shop', 'en16931-uk', 'en16931-usd', 'xrechnung'] as $fixture) {
			$fixtures[$fixture] = [$fixture . '.xml'];
		}

		return $fixtures;
	}

	/**
	 * UBL the package converts a fixture to reads as the same invoice as the fixture
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param string $fixture
	 */
	public function testReadsWhatThePackageConverted($fixture)
	{
		$xml = $this->invoiceXml($fixture);

		$this->assertEquals((new CiiInvoiceReader())->read($xml), (new UblInvoiceReader())->read($this->convert($xml)));
	}

	/**
	 * An invoice built with horstoeko/zugferd prints the same from its XML and from the UBL converted from it
	 */
	public function testPrintsTheBuiltInvoiceTheSameInEachSyntax()
	{
		$xml = \horstoeko\zugferd\ZugferdDocumentBuilder::createNew(\horstoeko\zugferd\ZugferdProfiles::PROFILE_EN16931)
			->setDocumentInformation('INV-2026-0100', '380', new \DateTime('2026-09-23'), 'EUR')
			->setDocumentBuyerReference('BR-42')
			->setDocumentSeller('Seller Ltd')
			->addDocumentSellerTaxRegistration('VA', 'GB123456789')
			->setDocumentSellerAddress('1 High Street', null, null, 'SW1A 1AA', 'London', 'GB')
			->setDocumentBuyer('Buyer GmbH')
			->setDocumentBuyerAddress('Hauptstraße 1', null, null, '10115', 'Berlin', 'DE')
			->addDocumentPaymentTerm('30 days net', new \DateTime('2026-10-23'))
			->addNewPosition('1')
			->setDocumentPositionProductDetails('Consulting')
			->setDocumentPositionNetPrice(120)
			->setDocumentPositionQuantity(7.5, 'HUR')
			->addDocumentPositionTax('S', 'VAT', 20)
			->setDocumentPositionLineSummation(900)
			->addDocumentTax('S', 'VAT', 900, 180, 20)
			->setDocumentSummation(1080, 1080, 900, 0, 0, 900, 180)
			->getContent();
		$writer = new HtmlInvoiceWriter(new Formatter(new UnitedKingdomPreset()));

		$html = $writer->write($xml);

		$this->assertStringContainsString('7.5 hours', $html);
		$this->assertSame($html, $writer->write($this->convert($xml)));
	}

	/**
	 * Cross Industry Invoice XML converted to UBL by the package
	 *
	 * @param string $xml
	 *
	 * @return string
	 */
	private function convert($xml)
	{
		return \horstoeko\zugferdublbridge\XmlConverterCiiToUbl::fromString($xml)->convert()->saveXmlString();
	}

}
