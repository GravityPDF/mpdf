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
		$totals = $this->invoice()->getTotals();

		$this->assertSame(938.97, $totals->getLineTotal());
		$this->assertSame(182.14, $totals->getTaxTotal());
		$this->assertSame(1121.11, $totals->getGrandTotal());
		$this->assertSame(1021.11, $totals->getDuePayableAmount());
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
		], $invoice->getTotals()->getVatBreakdown());
	}

	/**
	 * A line's allowances and charges count towards its net amount, and the invoice's towards the base VAT is charged on,
	 * in their own category
	 */
	public function testAddsUpAllowancesAndCharges()
	{
		$invoice = $this->shopInvoice();

		$totals = $invoice->getTotals();

		$this->assertSame(450.0, $invoice->getLines()[0]->getNetAmount());
		$this->assertSame(592.0, $totals->getLineTotal());
		$this->assertSame(59.2, $totals->getAllowanceTotal());
		$this->assertSame(24.9, $totals->getChargeTotal());
		$this->assertSame(557.7, $totals->getTaxBasisTotal());
		$this->assertSame(111.54, $totals->getTaxTotal());
		$this->assertSame(669.24, $totals->getGrandTotal());
		$this->assertSame(0.0, $totals->getDuePayableAmount());

		$invoice->addAllowanceCharge(AllowanceCharge::charge(10, 'Gift wrap')->setVat(5.5));
		$this->assertSame([
			['category' => 'S', 'rate' => 20.0, 'basis' => 557.7, 'amount' => 111.54],
			['category' => 'S', 'rate' => 5.5, 'basis' => 10.0, 'amount' => 0.55],
		], $invoice->getTotals()->getVatBreakdown());
	}

	/**
	 * The totals stay as they were taken when the invoice changes afterwards
	 */
	public function testTakesTheTotalsAsTheyStand()
	{
		$invoice = $this->invoice();
		$totals = $invoice->getTotals();
		$invoice->addLine(new LineItem('Pen', 1, 10, 20));

		$this->assertSame(938.97, $totals->getLineTotal());
		$this->assertSame(948.97, $invoice->getTotals()->getLineTotal());
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
			'invoice allowance exempt with a rate' => [function () {
				AllowanceCharge::allowance(5, 'Discount')->setVat(20, 'E');
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
			'unknown VAT due date code' => [function () {
				$this->invoice()->setVatDueDateCode('3');
			}, 'VAT due date code "3" is not one of 5, 29 or 72'],
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
	 * VAT on debits is VAT falling due on the invoice date
	 */
	public function testTakesVatOnDebitsAsTheInvoiceDate()
	{
		$invoice = $this->invoice()->setVatOnDebits();
		$this->assertSame(Invoice::VAT_DUE_ON_INVOICE, $invoice->getVatDueDateCode());
		$this->assertTrue($invoice->isVatOnDebits());

		$invoice->setVatDueDateCode(Invoice::VAT_DUE_ON_PAYMENT);
		$this->assertFalse($invoice->isVatOnDebits());

		$this->assertNull($invoice->setVatOnDebits(false)->getVatDueDateCode());
	}

	/**
	 * An IBAN given in groups of four is written without its spaces, and an account number that is not one as given
	 */
	public function testTakesAnIbanWithSpaces()
	{
		$iban = PaymentMeans::sepaCreditTransfer('fr76 3000 6000 0112 3456 7890 189');
		$this->assertTrue($iban->isIban());
		$this->assertSame('FR7630006000011234567890189', $iban->getAccount());

		$account = PaymentMeans::creditTransfer('12 345 678');
		$this->assertFalse($account->isIban());
		$this->assertSame('12 345 678', $account->getAccount());

		$this->assertSame('DE02120300000000202051', PaymentMeans::sepaDirectDebit('M-1', 'DE02 1203 0000 0000 2020 51', 'DE98ZZZ09999999999')->getDebitedAccount());
	}

	/**
	 * Each way to pay knows whether it is a transfer, a direct debit or a card
	 */
	public function testKnowsTheKindOfPayment()
	{
		$transfer = PaymentMeans::creditTransfer('12345678');
		$debit = new PaymentMeans(PaymentMeans::DIRECT_DEBIT);
		$card = PaymentMeans::card('1234', null, PaymentMeans::CREDIT_CARD);
		$cash = new PaymentMeans(PaymentMeans::CASH);

		$this->assertSame([true, false, false], [$transfer->isCreditTransfer(), $transfer->isDirectDebit(), $transfer->isCard()]);
		$this->assertSame([false, true, false], [$debit->isCreditTransfer(), $debit->isDirectDebit(), $debit->isCard()]);
		$this->assertSame([false, false, true], [$card->isCreditTransfer(), $card->isDirectDebit(), $card->isCard()]);
		$this->assertSame([false, false, false], [$cash->isCreditTransfer(), $cash->isDirectDebit(), $cash->isCard()]);
	}

}
