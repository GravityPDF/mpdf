<?php

namespace Mpdf\Invoice;

use Mpdf\InteropPackage;
use Mpdf\PageStreams;

/**
 * Invoice XML written by horstoeko/zugferd, the package the documentation points to, embeds as it was written and
 * reads back through that package's own PDF reader
 *
 * The package needs PHP 7.3, so it is not in require-dev; the einvoice-interop workflow installs it and runs this
 * group.
 *
 * @group interop
 */
class HorstoekoZugferdTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InteropPackage;
	use PageStreams;

	/**
	 * Skip unless horstoeko/zugferd is installed
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->requirePackage('horstoeko\zugferd\ZugferdDocumentBuilder', 'horstoeko/zugferd');
	}

	/**
	 * Each horstoeko profile, by the name of its ZugferdProfiles constant, with the file it is embedded as
	 *
	 * @return string[][]
	 */
	public function profileProvider()
	{
		return [
			['PROFILE_MINIMUM', 'factur-x.xml'],
			['PROFILE_BASICWL', 'factur-x.xml'],
			['PROFILE_BASIC', 'factur-x.xml'],
			['PROFILE_EN16931', 'factur-x.xml'],
			['PROFILE_EXTENDED', 'factur-x.xml'],
			['PROFILE_XRECHNUNG_3', 'xrechnung.xml'],
		];
	}

	/**
	 * The level is read from the XML the package writes, and the package finds that XML, unchanged, in the document
	 *
	 * @dataProvider profileProvider
	 *
	 * @param string $profile
	 * @param string $filename
	 */
	public function testReadsBackWhatThePackageWrote($profile, $filename)
	{
		$profileId = constant('horstoeko\zugferd\ZugferdProfiles::' . $profile);
		$xml = $this->build($profileId);

		$mpdf = $this->pdfA3();
		$mpdf->WriteHTML('<h1>Invoice INV-2026-0100</h1>');
		$mpdf->SetEmbeddedInvoice(new FacturX($xml));
		$pdf = $this->output($mpdf);

		$this->assertStringContainsString('(' . $filename . ')', $pdf);
		$this->assertSame($xml, \horstoeko\zugferd\ZugferdDocumentPdfReader::getXmlFromContent($pdf));

		$reader = \horstoeko\zugferd\ZugferdDocumentPdfReader::readAndGuessFromContent($pdf);
		$reader->getDocumentInformation($number, $type, $date, $currency, $taxCurrency, $name, $language, $period);
		$this->assertSame($profileId, $reader->getProfileId());
		$this->assertSame('INV-2026-0100', $number);
	}

	/**
	 * A one-line invoice at the given profile, written by horstoeko/zugferd; each profile keeps what it carries
	 *
	 * @param int $profileId
	 *
	 * @return string
	 */
	private function build($profileId)
	{
		return \horstoeko\zugferd\ZugferdDocumentBuilder::createNew($profileId)
			->setDocumentInformation('INV-2026-0100', '380', new \DateTime('2026-09-23'), 'EUR')
			->setDocumentBuyerReference('BR-42')
			->setDocumentSeller('Seller SARL')
			->addDocumentSellerTaxRegistration('VA', 'FR32123456789')
			->setDocumentSellerAddress('12 rue de la Paix', null, null, '75002', 'Paris', 'FR')
			->setDocumentBuyer('Buyer GmbH')
			->setDocumentBuyerAddress('Hauptstraße 1', null, null, '10115', 'Berlin', 'DE')
			->addNewPosition('1')
			->setDocumentPositionProductDetails('Desk')
			->setDocumentPositionNetPrice(250)
			->setDocumentPositionQuantity(2, 'H87')
			->addDocumentPositionTax('S', 'VAT', 20)
			->setDocumentPositionLineSummation(500)
			->addDocumentTax('S', 'VAT', 500, 100, 20)
			->setDocumentSummation(600, 600, 500, 0, 0, 500, 100)
			->getContent();
	}

}
