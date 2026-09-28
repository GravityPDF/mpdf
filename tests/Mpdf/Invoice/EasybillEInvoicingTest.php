<?php

namespace Mpdf\Invoice;

use Mpdf\InteropPackage;
use Mpdf\InvoiceFixtures;

/**
 * Invoice XML written by easybill/e-invoicing, the package the documentation points to, reads and embeds as the XML it
 * was read from
 *
 * The package needs PHP 8.3, so it is not in require-dev; the einvoice-interop workflow installs it and runs this group
 * on PHP 8.5.
 *
 * @group interop
 */
class EasybillEInvoicingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InteropPackage;
	use InvoiceFixtures;

	/**
	 * Skip unless easybill/e-invoicing is installed
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->requirePackage('easybill\eInvoicing\Transformer', 'easybill/e-invoicing');
	}

	/**
	 * The fixtures in tests/data/invoice and tests/data/invoice/ubl whose every field the package keeps
	 *
	 * It keeps one set of payment terms, so EXTENDED's structured discount and penalty terms are lost, and its UBL has no
	 * CardAccount, so those fixtures are not here.
	 *
	 * @return string[][]
	 */
	public function fixtureProvider()
	{
		$fixtures = [];
		foreach (['', 'ubl/'] as $directory) {
			foreach (glob(__DIR__ . '/../../data/invoice/' . $directory . '*.xml') as $path) {
				$fixture = $directory . basename($path);
				if (!in_array($fixture, ['extended.xml', 'ubl/en16931-shop.xml'], true)) {
					$fixtures[$fixture] = [$fixture];
				}
			}
		}

		return $fixtures;
	}

	/**
	 * A fixture the package reads and writes again reads as the same invoice, and a Cross Industry Invoice embeds at the
	 * same level under the same name
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param string $fixture
	 */
	public function testReadsWhatThePackageWrites($fixture)
	{
		$xml = $this->invoiceXml($fixture);
		$result = \easybill\eInvoicing\Reader::create()->read($xml);
		$this->assertTrue($result->isSuccess());

		$written = \easybill\eInvoicing\Transformer::create()->transformToXml($result->getDocument());

		$this->assertEquals((new InvoiceReader())->read($xml), (new InvoiceReader())->read($written));
		if (strpos($fixture, 'ubl/') !== 0) {
			$this->assertSame((new FacturX($xml))->getXmpProperties(), (new FacturX($written))->getXmpProperties());
		}
	}

}
