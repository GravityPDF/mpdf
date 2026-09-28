<?php

namespace Mpdf\Invoice;

use Mpdf\InvoiceFixtures;

/**
 * Invoice XML written by easybill/e-invoicing, the package the documentation points to, reads and embeds as the XML it
 * was read from
 *
 * The package needs PHP 8.3, so it is not in require-dev; the einvoice-interop workflow installs it and runs this group
 * on PHP 8.5, where a missing package fails the tests; elsewhere they are skipped.
 *
 * @group interop
 */
class EasybillEInvoicingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Skip below PHP 8.3 or unless easybill/e-invoicing is installed, or fail in the einvoice-interop workflow
	 */
	protected function set_up()
	{
		parent::set_up();

		if (PHP_VERSION_ID < 80300) {
			$this->markTestSkipped('easybill/e-invoicing needs PHP 8.3');
		}

		if (!class_exists('easybill\eInvoicing\Transformer')) {
			if (getenv('EINVOICE_INTEROP')) {
				$this->fail('easybill/e-invoicing is not installed, but the einvoice-interop workflow needs it');
			}

			$this->markTestSkipped('easybill/e-invoicing is not installed; run composer require --dev easybill/e-invoicing');
		}
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
		foreach (array_merge(glob(__DIR__ . '/../../data/invoice/*.xml'), glob(__DIR__ . '/../../data/invoice/ubl/*.xml')) as $path) {
			$fixture = basename(dirname($path)) === 'ubl' ? 'ubl/' . basename($path) : basename($path);
			if (!in_array($fixture, ['extended.xml', 'ubl/en16931-shop.xml'], true)) {
				$fixtures[$fixture] = [$fixture];
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
