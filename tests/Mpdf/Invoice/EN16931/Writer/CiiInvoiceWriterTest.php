<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\PdfA3\FacturX;
use Mpdf\Invoice\TradeDocument;
use Mpdf\Invoice\WriterInterface;
use Mpdf\MpdfException;

class CiiInvoiceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * The schema each profile's XML must follow
	 *
	 * @var string[]
	 */
	private static $schemas = [
		FacturX::MINIMUM => 'MINIMUM/FACTUR-X_MINIMUM.xsd',
		FacturX::BASIC_WL => 'BASIC-WL/FACTUR-X_BASICWL.xsd',
		FacturX::EN16931 => 'EN16931/FACTUR-X_EN16931.xsd',
	];

	/**
	 * Each profile's invoice and the fixture Mustang validated it as
	 *
	 * @return mixed[]
	 */
	public function profileProvider()
	{
		return [
			'MINIMUM' => [FacturX::MINIMUM, 'minimumInvoice', 'minimum.xml'],
			'BASIC WL' => [FacturX::BASIC_WL, 'invoice', 'basic-wl.xml'],
			'EN 16931' => [FacturX::EN16931, 'invoice', 'en16931.xml'],
			'EN 16931 reverse charge' => [FacturX::EN16931, 'reverseChargeInvoice', 'en16931-reverse-charge.xml'],
			'BASIC WL shop order' => [FacturX::BASIC_WL, 'shopInvoice', 'basic-wl-shop.xml'],
			'EN 16931 shop order' => [FacturX::EN16931, 'shopInvoice', 'en16931-shop.xml'],
			'EN 16931 credit note' => [FacturX::EN16931, 'creditNote', 'en16931-credit-note.xml'],
			'BASIC WL intra-community' => [FacturX::BASIC_WL, 'intraCommunityInvoice', 'basic-wl-intra-community.xml'],
			'EN 16931 intra-community' => [FacturX::EN16931, 'intraCommunityInvoice', 'en16931-intra-community.xml'],
		];
	}

	/**
	 * Each profile carries what it allows and no more, in the order its schema demands
	 *
	 * @dataProvider profileProvider
	 *
	 * @param string $profile
	 * @param string $invoice The fixture method building the invoice
	 * @param string $fixture
	 */
	public function testWritesTheValidatedInvoice($profile, $invoice, $fixture)
	{
		$writer = new CiiInvoiceWriter($profile);
		$xml = $writer->write($this->$invoice());

		$this->assertSame(WriterInterface::XML, $writer->getFormat());
		$this->assertMatchesSchema($xml, self::$schemas[$profile]);

		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/' . $fixture, $xml);
	}

	/**
	 * A state follows the country from BASIC WL up, and MINIMUM, which carries only the seller's country, leaves it out
	 */
	public function testWritesTheCountrySubdivision()
	{
		$invoice = $this->minimumInvoice();
		$invoice->getSeller()->setCountrySubdivision('Île-de-France');

		$this->assertStringContainsString(
			"<ram:CountryID>FR</ram:CountryID>\n          <ram:CountrySubDivisionName>Île-de-France</ram:CountrySubDivisionName>\n        </ram:PostalTradeAddress>",
			(new CiiInvoiceWriter(FacturX::BASIC_WL))->write($invoice)
		);
		$this->assertStringNotContainsString('CountrySubDivisionName', (new CiiInvoiceWriter(FacturX::MINIMUM))->write($invoice));
	}

	/**
	 * The XML follows the profile's Factur-X schema, naming the first element that does not
	 *
	 * @param string $xml
	 * @param string $schema
	 */
	private function assertMatchesSchema($xml, $schema)
	{
		$dom = new \DOMDocument();
		$dom->loadXML($xml);

		$errors = libxml_use_internal_errors(true);
		$valid = $dom->schemaValidate(__DIR__ . '/../../../../data/invoice/xsd/' . $schema);
		$messages = array_map(function ($error) {
			return trim($error->message);
		}, libxml_get_errors());
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$this->assertTrue($valid, implode("\n", $messages));
	}

	/**
	 * An invoice that breaks an EN 16931 rule is refused from BASIC WL up, naming the rule
	 */
	public function testRefusesAnInvoiceThatBreaksTheRules()
	{
		$invoice = $this->intraCommunityInvoice();
		$invoice->getBuyer()->setVatId(null);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('BR-IC-2');

		(new CiiInvoiceWriter(FacturX::BASIC_WL))->write($invoice);
	}

	/**
	 * Documents and profiles the writer refuses, and the reason it gives
	 *
	 * @return mixed[]
	 */
	public function refusedProvider()
	{
		return [
			'unknown profile' => ['EXTENDED', 'invoice', 'Profile "EXTENDED" is not one of MINIMUM, BASIC WL, EN 16931'],
			'no lines' => ['EN 16931', 'blankInvoice', 'An invoice needs at least one line'],
			'prepaid MINIMUM' => ['MINIMUM', 'invoice', 'MINIMUM cannot carry a prepaid amount'],
		];
	}

	/**
	 * An invoice the profile cannot express is refused rather than written wrong
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param string $profile
	 * @param string $invoice The fixture method building the invoice
	 * @param string $message
	 */
	public function testRefusesWhatItCannotWrite($profile, $invoice, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		(new CiiInvoiceWriter($profile))->write($this->$invoice());
	}

	/**
	 * A trade document that is not an invoice is refused
	 */
	public function testRefusesADocumentThatIsNotAnInvoice()
	{
		$document = $this->getMockBuilder(TradeDocument::class)->disableOriginalConstructor()->getMockForAbstractClass();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('writes invoices, not');

		(new CiiInvoiceWriter('EN 16931'))->write($document);
	}

}
