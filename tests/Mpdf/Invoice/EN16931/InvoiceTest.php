<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\LineItem;
use Mpdf\MpdfException;

class InvoiceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Each line is rounded to the cent before it is added, and the VAT of each rate is worked out on that rate's total
	 */
	public function testAddsUpTheLines()
	{
		$invoice = $this->invoice();

		$this->assertSame(938.97, $invoice->getLineTotal());
		$this->assertSame(182.14, $invoice->getTaxTotal());
		$this->assertSame(1121.11, $invoice->getGrandTotal());
		$this->assertSame(1021.11, $invoice->getDuePayableAmount());
	}

	/**
	 * Lines of the same category and rate share one group of the breakdown
	 */
	public function testGroupsTheVatByCategoryAndRate()
	{
		$invoice = $this->invoice();
		$invoice->addLine(new LineItem('Pen', 2, 1.505, 20));
		$invoice->addLine(new LineItem('Export', 1, 50, 0, 'G'));

		$this->assertSame([
			['category' => 'S', 'rate' => 20.0, 'basis' => 903.01, 'amount' => 180.6],
			['category' => 'S', 'rate' => 5.5, 'basis' => 38.97, 'amount' => 2.14],
			['category' => 'G', 'rate' => 0.0, 'basis' => 50.0, 'amount' => 0.0],
		], $invoice->getVatBreakdown());
	}

	/**
	 * A line under a VAT category EN 16931 does not know is refused
	 */
	public function testRefusesAnUnknownVatCategory()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('VAT category "X" is not one of S, Z, E, AE, K, G, O, L, M');

		new LineItem('Widget', 1, 10, 20, 'X');
	}

}
