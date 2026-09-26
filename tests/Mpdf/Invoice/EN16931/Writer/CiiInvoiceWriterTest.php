<?php

namespace Mpdf\Invoice\EN16931\Writer;

use Mpdf\Invoice\EN16931\Cius\BuyerReferenceCius;
use Mpdf\Invoice\EN16931\Cius\CiusInterface;
use Mpdf\Invoice\EN16931\Cius\France;
use Mpdf\Invoice\EN16931\Cius\XRechnung;
use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\StringWriterInterface;
use Mpdf\Invoice\TradeDocument;
use Mpdf\MpdfException;

class CiiInvoiceWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Where the Factur-X schemas are downloaded from: Mustang's copy of FNFE-MPE and FeRD's, at a pinned commit so they
	 * cannot change under the tests
	 */
	const SCHEMA_SOURCE = 'https://raw.githubusercontent.com/ZUGFeRD/mustangproject/56dc21c9c62fbb0c2bf035116b33a1caa1155290/validator/src/main/resources/schema/ZF_250/';

	/**
	 * The folder and file name of each profile's schema, without its .xsd
	 *
	 * @var string[][]
	 */
	private static $schemas = [
		FacturX::MINIMUM => ['MINIMUM', 'FACTUR-X_MINIMUM'],
		FacturX::BASIC_WL => ['BASIC-WL', 'FACTUR-X_BASICWL'],
		FacturX::EN16931 => ['EN16931', 'FACTUR-X_EN16931'],
	];

	/**
	 * The data types each schema imports, each in a file named after the schema and the type
	 *
	 * @var string[]
	 */
	private static $schemaImports = ['QualifiedDataType', 'ReusableAggregateBusinessInformationEntity', 'UnqualifiedDataType'];

	/**
	 * The schema that could not be downloaded, so the other schema tests skip without waiting on the network again
	 *
	 * @var string|null
	 */
	private static $unreachable;

	/**
	 * Each profile's invoice, the fixture Mustang validated it as, and the specification applied on top, if any
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
			'XRechnung' => [FacturX::EN16931, 'xrechnungInvoice', 'xrechnung.xml', new XRechnung()],
			'EN 16931 France' => [FacturX::EN16931, 'frenchInvoice', 'en16931-france.xml'],
		];
	}

	/**
	 * Each profile's invoice is written as the fixture Mustang validated
	 *
	 * @dataProvider profileProvider
	 *
	 * @param string $profile
	 * @param string $invoice The fixture method building the invoice
	 * @param string $fixture
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface|null $cius
	 */
	public function testWritesTheValidatedInvoice($profile, $invoice, $fixture, $cius = null)
	{
		$writer = $this->writer($profile, $cius);

		$this->assertInstanceOf(StringWriterInterface::class, $writer);
		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/' . $fixture, $writer->write($this->$invoice()));
	}

	/**
	 * The invoice to embed is the XML written, as Factur-X at the level its guideline names: the profile's, or
	 * XRECHNUNG for XRechnung's
	 *
	 * @dataProvider profileProvider
	 *
	 * @param string $profile
	 * @param string $invoice The fixture method building the invoice
	 * @param string $fixture
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface|null $cius
	 */
	public function testEmbedsTheXmlAsFacturXAtItsLevel($profile, $invoice, $fixture, $cius = null)
	{
		$writer = $this->writer($profile, $cius);
		$embedded = $writer->output($this->$invoice())->getInvoice();
		$properties = $embedded->getXmpProperties();
		$file = $embedded->getAssociatedFile();

		$this->assertInstanceOf(FacturX::class, $embedded);
		$this->assertSame($cius instanceof XRechnung ? FacturX::XRECHNUNG : $profile, $properties['ConformanceLevel']);
		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/' . $fixture, $file['content']);
	}

	/**
	 * A writer of the profile, with the specification applied if one is given
	 *
	 * @param string $profile
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface|null $cius
	 *
	 * @return \Mpdf\Invoice\EN16931\Writer\CiiInvoiceWriter
	 */
	private function writer($profile, $cius = null)
	{
		$writer = new CiiInvoiceWriter($profile);

		return $cius instanceof CiusInterface ? $writer->withCius($cius) : $writer;
	}

	/**
	 * Each profile's invoices, and those written with what a fixture lacks
	 *
	 * @return mixed[]
	 */
	public function schemaProvider()
	{
		$invoices = [];
		foreach ($this->profileProvider() as $name => $case) {
			$invoices[$name] = array_merge([$case[0], $case[1]], array_slice($case, 3));
		}

		return $invoices + [
			'EN 16931 VAT due on delivery' => [FacturX::EN16931, 'vatDueOnDeliveryInvoice'],
			'XRechnung not subject to VAT' => [FacturX::EN16931, 'notSubjectToVatInvoice', new XRechnung()],
			'BASIC WL France' => [FacturX::BASIC_WL, 'frenchInvoice', new France()],
		];
	}

	/**
	 * Each profile carries what it allows and no more, in the order its schema demands, naming the first element that
	 * does not. The schemas are downloaded on the first run, and the test skipped when they cannot be.
	 *
	 * @dataProvider schemaProvider
	 *
	 * @param string $profile
	 * @param string $invoice The method building the invoice
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface|null $cius
	 */
	public function testFollowsTheProfileSchema($profile, $invoice, $cius = null)
	{
		$dom = new \DOMDocument();
		$dom->loadXML($this->writer($profile, $cius)->write($this->$invoice()));

		$errors = libxml_use_internal_errors(true);
		$valid = $dom->schemaValidate($this->schema($profile));
		$messages = array_map(function ($error) {
			return trim($error->message);
		}, libxml_get_errors());
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$this->assertTrue($valid, implode("\n", $messages));
	}

	/**
	 * The path to a profile's schema, downloaded with the schemas it imports into the system's temporary folder unless
	 * an earlier run did
	 *
	 * @param string $profile
	 *
	 * @return string
	 */
	private function schema($profile)
	{
		if (self::$unreachable !== null) {
			$this->markTestSkipped('The Factur-X schema could not be downloaded from ' . self::$unreachable);
		}

		list($folder, $name) = self::$schemas[$profile];
		$dir = sys_get_temp_dir() . '/mpdf-factur-x-xsd-56dc21c/' . $folder;
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		$files = [$name . '.xsd'];
		foreach (self::$schemaImports as $import) {
			$files[] = $name . '_urn_un_unece_uncefact_data_standard_' . $import . '_100.xsd';
		}

		foreach ($files as $file) {
			if (is_file($dir . '/' . $file)) {
				continue;
			}

			$url = self::SCHEMA_SOURCE . $folder . '/' . $file;
			$xsd = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
			if ($xsd === false) {
				self::$unreachable = $url;
				$this->markTestSkipped('The Factur-X schema could not be downloaded from ' . $url);
			}

			// Written whole under another name first, so a parallel run never reads half a schema
			$partial = $dir . '/' . $file . '.' . getmypid();
			file_put_contents($partial, $xsd);
			rename($partial, $dir . '/' . $file);
		}

		return $dir . '/' . $name . '.xsd';
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
	 * When the VAT falls due is written on each group of the breakdown
	 */
	public function testWritesWhenTheVatFallsDue()
	{
		$xml = (new CiiInvoiceWriter(FacturX::EN16931))->write($this->vatDueOnDeliveryInvoice());

		$this->assertSame(2, substr_count($xml, '<ram:DueDateTypeCode>29</ram:DueDateTypeCode>'));
	}

	/**
	 * The fixture invoice with its VAT falling due on delivery
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function vatDueOnDeliveryInvoice()
	{
		return $this->invoice()->setVatDueDateCode(Invoice::VAT_DUE_ON_DELIVERY);
	}

	/**
	 * XRechnung names its business process when the invoice gives none, and a rate for VAT category O in the breakdown
	 */
	public function testWritesWhatXRechnungAddsToEn16931()
	{
		$xml = $this->writer(FacturX::EN16931, new XRechnung())->write($this->xrechnungInvoice());

		$this->assertStringContainsString('<ram:ID>' . XRechnung::PEPPOL_BILLING . '</ram:ID>', $xml);
		$this->assertStringContainsString('<ram:ID>urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0</ram:ID>', $xml);

		$xml = $this->writer(FacturX::EN16931, new XRechnung())->write($this->notSubjectToVatInvoice());
		$this->assertStringContainsString("<ram:CategoryCode>O</ram:CategoryCode>\n        <ram:RateApplicablePercent>0</ram:RateApplicablePercent>\n      </ram:ApplicableTradeTax>", $xml);
	}

	/**
	 * XRechnung names the Peppol billing process only when the invoice names none of its own
	 */
	public function testKeepsTheBusinessProcessTheInvoiceNames()
	{
		$xml = $this->writer(FacturX::EN16931, new XRechnung())->write($this->xrechnungInvoice()->setBusinessProcess('urn:example.org:process:1.0'));

		$this->assertStringContainsString('<ram:ID>urn:example.org:process:1.0</ram:ID>', $xml);
		$this->assertStringNotContainsString(XRechnung::PEPPOL_BILLING, $xml);
	}

	/**
	 * A specification of the user's own, built on the interface alone, decides everything a CIUS may: the writer names
	 * no country, so it defers to this one as it does to XRechnung and France
	 */
	public function testAppliesASpecificationOfItsOwn()
	{
		$writer = $this->writer(FacturX::EN16931, new BuyerReferenceCius());
		$xml = $writer->write($this->invoice());

		$this->assertStringContainsString('<ram:ID>' . BuyerReferenceCius::GUIDELINE . '</ram:ID>', $xml);
		$this->assertStringContainsString('<ram:ID>' . BuyerReferenceCius::BUSINESS_PROCESS . '</ram:ID>', $xml);
		$this->assertStringContainsString("<ram:CategoryCode>O</ram:CategoryCode>\n        <ram:RateApplicablePercent>0</ram:RateApplicablePercent>", $writer->write($this->notSubjectToVatInvoice()));

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('EX-1: the invoice needs the buyer\'s reference');

		$writer->write($this->invoice()->setBuyerReference(null));
	}

	/**
	 * XML naming a guideline Factur-X does not know is written, but refused as Factur-X to embed
	 */
	public function testRefusesToEmbedAGuidelineFacturXDoesNotKnow()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Guideline "' . BuyerReferenceCius::GUIDELINE . '" is not a Factur-X guideline');

		$this->writer(FacturX::EN16931, new BuyerReferenceCius())->output($this->invoice());
	}

	/**
	 * An XRechnung between the fixture's parties for a donation not subject to VAT, so naming no VAT identifier
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function notSubjectToVatInvoice()
	{
		$invoice = $this->xrechnungInvoice();
		$invoice->getSeller()->setVatId(null);
		$invoice->getBuyer()->setVatId(null);
		$notSubject = new Invoice('INV-2026-0102', new \DateTime('2026-09-23'), 'EUR', $invoice->getSeller(), $invoice->getBuyer());

		return $notSubject->addLine(new LineItem('Donation', 1, 10, 0, 'O'))
			->setExemptionReason('O', 'Not subject to VAT')
			->setBuyerReference('04011000-12345-34')
			->setDueDate(new \DateTime('2026-10-23'))
			->addPaymentMeans(PaymentMeans::sepaCreditTransfer('FR7630006000011234567890189'));
	}

	/**
	 * A direct debit is written as one whether or not it names its mandate and creditor, which EN 16931 leaves optional
	 */
	public function testWritesADirectDebitWithoutItsMandate()
	{
		$invoice = $this->invoice()->addPaymentMeans(new PaymentMeans(PaymentMeans::DIRECT_DEBIT));
		$xml = (new CiiInvoiceWriter(FacturX::EN16931))->write($invoice);

		$this->assertStringContainsString('<ram:TypeCode>49</ram:TypeCode>', $xml);
		$this->assertStringNotContainsString('CreditorReferenceID', $xml);
		$this->assertStringNotContainsString('DirectDebitMandateID', $xml);
	}

	/**
	 * For France the VAT on debits option is given in the breakdown, and the French rules checked
	 */
	public function testWritesForFrance()
	{
		$xml = $this->writer(FacturX::EN16931, new France())->write($this->frenchInvoice());
		$this->assertStringContainsString("<ram:CategoryCode>S</ram:CategoryCode>\n        <ram:DueDateTypeCode>5</ram:DueDateTypeCode>", $xml);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('BR-FR-08');

		$this->writer(FacturX::EN16931, new France())->write($this->invoice());
	}

	/**
	 * Specifications and the profiles they refuse, and the reason they give
	 *
	 * @return mixed[]
	 */
	public function refusedProfileProvider()
	{
		return [
			'France, MINIMUM' => [new France(), FacturX::MINIMUM, 'France\'s e-invoicing reform does not take the MINIMUM profile; use BASIC WL or EN 16931'],
			'XRechnung, BASIC WL' => [new XRechnung(), FacturX::BASIC_WL, 'XRechnung does not take the BASIC WL profile; use EN 16931'],
			'a specification of its own, BASIC WL' => [new BuyerReferenceCius(), FacturX::BASIC_WL, 'the buyer reference rules does not take the BASIC WL profile; use EN 16931'],
		];
	}

	/**
	 * A specification refuses a profile it does not take as soon as it is applied
	 *
	 * @dataProvider refusedProfileProvider
	 *
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius
	 * @param string $profile
	 * @param string $message
	 */
	public function testRefusesAProfileTheSpecificationDoesNotTake(CiusInterface $cius, $profile, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		(new CiiInvoiceWriter($profile))->withCius($cius);
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
			'XRechnung as a profile' => ['XRECHNUNG', 'invoice', 'Profile "XRECHNUNG" is not one of MINIMUM, BASIC WL, EN 16931'],
			'no lines' => ['EN 16931', 'blankInvoice', 'BR-16: an invoice needs at least one line'],
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
