<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PaymentMeans;
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
	 * A line's allowances and charges count towards its net amount, and the invoice's towards the base VAT is charged on,
	 * in their own category
	 */
	public function testAddsUpAllowancesAndCharges()
	{
		$invoice = $this->shopInvoice();

		$this->assertSame(450.0, $invoice->getLines()[0]->getNetAmount());
		$this->assertSame(592.0, $invoice->getLineTotal());
		$this->assertSame(59.2, $invoice->getAllowanceTotal());
		$this->assertSame(24.9, $invoice->getChargeTotal());
		$this->assertSame(557.7, $invoice->getTaxBasisTotal());
		$this->assertSame(111.54, $invoice->getTaxTotal());
		$this->assertSame(669.24, $invoice->getGrandTotal());
		$this->assertSame(0.0, $invoice->getDuePayableAmount());

		$invoice->addAllowanceCharge(AllowanceCharge::charge(10, 'Gift wrap')->setVat(5.5));
		$this->assertSame([
			['category' => 'S', 'rate' => 20.0, 'basis' => 557.7, 'amount' => 111.54],
			['category' => 'S', 'rate' => 5.5, 'basis' => 10.0, 'amount' => 0.55],
		], $invoice->getVatBreakdown());
	}

	/**
	 * A percentage allowance or charge works its amount out from its basis
	 */
	public function testWorksOutAPercentage()
	{
		$allowance = AllowanceCharge::percentAllowance(12.5, 938.97, 'Early booking');

		$this->assertSame(117.37, $allowance->getAmount());
		$this->assertSame(12.5, $allowance->getPercent());
		$this->assertSame(938.97, $allowance->getBasis());
		$this->assertSame(-117.37, $allowance->getSignedAmount());
		$this->assertSame(28.17, AllowanceCharge::percentCharge(3, 938.97, 'Card surcharge')->getAmount());
	}

	/**
	 * What EN 16931 cannot express is refused where it is made, and the message says what to do instead
	 *
	 * @return mixed[]
	 */
	public function refusedProvider()
	{
		return [
			'negative price' => [function () {
				new LineItem('Refund', 1, -10, 20);
			}, 'negative quantity or an allowance'],
			'standard rate of 0' => [function () {
				new LineItem('Widget', 1, 10, 0);
			}, 'use Z for zero rated'],
			'exempt with a rate' => [function () {
				new LineItem('Lesson', 1, 10, 20, 'E');
			}, 'VAT category E charges no VAT'],
			'negative allowance' => [function () {
				AllowanceCharge::allowance(-5, 'Discount');
			}, 'positive amount'],
			'whole card number' => [function () {
				PaymentMeans::card('4242424242424242');
			}, 'never its whole number'],
			'invoice allowance without VAT' => [function () {
				$this->invoice()->addAllowanceCharge(AllowanceCharge::allowance(5, 'Discount'));
			}, 'call setVat()'],
		];
	}

	/**
	 * A price, rate or allowance EN 16931 cannot express is refused rather than written wrong
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param callable $make
	 * @param string $message
	 */
	public function testRefusesWhatCannotBeExpressed($make, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		call_user_func($make);
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
