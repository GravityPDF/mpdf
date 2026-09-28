<?php

namespace Mpdf;

/**
 * The invoice XML fixtures in tests/data/invoice
 */
trait InvoiceFixtures
{

	/**
	 * The XML of a fixture in tests/data/invoice
	 *
	 * @param string $fixture
	 *
	 * @return string
	 */
	private function invoiceXml($fixture = 'en16931.xml')
	{
		return file_get_contents(__DIR__ . '/../data/invoice/' . $fixture);
	}

	/**
	 * Each fixture in tests/data/invoice/ubl, the UBL stating the same invoice as the Cross Industry Invoice fixture of
	 * the same name
	 *
	 * @return string[][]
	 */
	public function ublTwinProvider()
	{
		$fixtures = [];
		foreach (glob(__DIR__ . '/../data/invoice/ubl/*.xml') as $path) {
			$fixtures[basename($path)] = [basename($path)];
		}

		return $fixtures;
	}

}
