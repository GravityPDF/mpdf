<?php

namespace Mpdf\EInvoice\EN16931\Cii;

use Mpdf\EInvoice\EN16931\InvoiceFixtures;
use Mpdf\EInvoice\PdfA3\FacturX;
use Mpdf\Invoice\TradeDocument;
use Mpdf\MpdfException;
use Mpdf\PageStreams;

class InvoiceGeneratorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;
	use PageStreams;

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
		$xml = (new InvoiceGenerator($profile))->generate($this->$invoice());

		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/xml/einvoice/' . $fixture, $xml);
	}

	/**
	 * The XML generated is the XML SetFacturX() needs: it finds the profile from it and embeds it
	 */
	public function testMakesTheXmlSetFacturXTakes()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B']);
		$mpdf->WriteHTML('<h1>Invoice INV-2026-0001</h1>');
		$mpdf->SetFacturX((new InvoiceGenerator(FacturX::BASIC_WL))->generate($this->invoice()));
		$output = $this->output($mpdf);

		$this->assertStringContainsString('<fx:ConformanceLevel>BASIC WL</fx:ConformanceLevel>', $output);
		$this->assertStringContainsString('/EmbeddedFiles << /Names [(factur-x.xml)', $output);
	}

	/**
	 * Documents and profiles the generator refuses, and the reason it gives
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

		(new InvoiceGenerator($profile))->generate($this->$invoice());
	}

	/**
	 * A trade document that is not an invoice is refused
	 */
	public function testRefusesADocumentThatIsNotAnInvoice()
	{
		$document = $this->getMockBuilder(TradeDocument::class)->disableOriginalConstructor()->getMockForAbstractClass();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('writes invoices, not');

		(new InvoiceGenerator('EN 16931'))->generate($document);
	}

}
