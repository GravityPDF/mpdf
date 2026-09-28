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

}
