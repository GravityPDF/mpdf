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
		$writer = new CiiInvoiceWriter($profile);
		$xml = $writer->write($this->$invoice());

		$this->assertSame(WriterInterface::XML, $writer->getFormat());

		$this->assertStringEqualsFile(__DIR__ . '/../../../../data/invoice/' . $fixture, $xml);
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
