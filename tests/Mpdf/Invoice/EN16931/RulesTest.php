<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\LineItem;
use Mpdf\MpdfException;

class RulesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Every invoice the fixtures build keeps to the rules
	 */
	public function testPassesTheFixtures()
	{
		foreach (['invoice', 'reverseChargeInvoice', 'shopInvoice', 'creditNote', 'intraCommunityInvoice'] as $fixture) {
			$this->assertSame([], Rules::broken($this->$fixture()), $fixture);
		}
	}

	/**
	 * A fixture broken in one way, and the rule that says so
	 *
	 * @return mixed[]
	 */
	public function brokenProvider()
	{
		return [
			'seller unidentified' => ['invoice', function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null)->setLegalId(null);
			}, 'BR-CO-26'],
			'standard rate without the seller taxed' => ['invoice', function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null);
			}, 'BR-S-2'],
			'reverse charge without the buyer identified' => ['reverseChargeInvoice', function (Invoice $invoice) {
				$invoice->getBuyer()->setVatId(null);
			}, 'BR-AE-2'],
			'intra-community without a delivery address' => ['blankInvoice', function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Paper', 1, 10, 0, 'K'))->setExemptionReason('K', 'Intra-community supply');
			}, 'BR-IC-12'],
			'export without the seller\'s VAT identifier' => ['blankInvoice', function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null);
				$invoice->addLine(new LineItem('Paper', 1, 10, 0, 'G'))->setExemptionReason('G', 'Export');
			}, 'BR-G-2'],
			'not subject to VAT beside another category' => ['invoice', function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Donation', 1, 10, 0, 'O'))->setExemptionReason('O', 'Not subject to VAT');
			}, 'BR-O-11'],
			'not subject to VAT naming a VAT identifier' => ['blankInvoice', function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Donation', 1, 10, 0, 'O'))->setExemptionReason('O', 'Not subject to VAT');
			}, 'BR-O-2'],
			'exempt without a reason' => ['blankInvoice', function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Lesson', 1, 10, 0, 'E'));
			}, 'BR-E-10'],
			'standard rate with an exemption reason' => ['invoice', function (Invoice $invoice) {
				$invoice->setExemptionReason('S', 'Why');
			}, 'BR-S-10'],
			'amount due without terms or a due date' => ['creditNote', function (Invoice $invoice) {
				$invoice->setTypeCode(Invoice::TYPE_INVOICE)->setPaymentTerms(null);
			}, 'BR-CO-25'],
			'allowance without a reason' => ['invoice', function (Invoice $invoice) {
				$invoice->addAllowanceCharge(AllowanceCharge::allowance(5)->setVat(20));
			}, 'BR-33'],
			'line charge without a reason' => ['blankInvoice', function (Invoice $invoice) {
				$invoice->addLine((new LineItem('Desk', 1, 100, 20))->addAllowanceCharge(AllowanceCharge::charge(5)));
			}, 'BR-44'],
		];
	}

	/**
	 * An invoice that breaks a rule is refused, the message naming the rule
	 *
	 * @dataProvider brokenProvider
	 *
	 * @param string $fixture
	 * @param callable $break
	 * @param string $rule
	 */
	public function testNamesTheRuleBroken($fixture, $break, $rule)
	{
		$invoice = $this->$fixture();
		call_user_func($break, $invoice);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($rule . ':');

		Rules::check($invoice);
	}

}
