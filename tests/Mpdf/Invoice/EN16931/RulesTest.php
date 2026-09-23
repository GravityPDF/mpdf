<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PaymentMeans;
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
	 * The XRechnung fixture keeps to the rules XRechnung adds, which the plain invoice does not
	 */
	public function testPassesTheXRechnungFixture()
	{
		$this->assertSame([], Rules::brokenInXRechnung($this->xrechnungInvoice()));
		$this->assertNotEmpty(Rules::brokenInXRechnung($this->invoice()));
	}

	/**
	 * The XRechnung fixture broken in one way, and the rule that says so
	 *
	 * @return mixed[]
	 */
	public function brokenInXRechnungProvider()
	{
		return [
			'no way to pay' => [function (Invoice $invoice) {
				return $this->withoutPaymentMeans($invoice);
			}, 'BR-DE-1'],
			'seller contact without a phone number' => [function (Invoice $invoice) {
				$invoice->getSeller()->setContact('Accounts', null, 'accounts@seller.example');
			}, 'BR-DE-2'],
			'buyer without a postcode' => [function (Invoice $invoice) {
				$invoice->getBuyer()->setAddress('Hauptstraße 1', null, 'Berlin');
			}, 'BR-DE-8/9'],
			'no buyer reference' => [function (Invoice $invoice) {
				$invoice->setBuyerReference(null);
			}, 'BR-DE-15'],
			'buyer without an electronic address' => [function (Invoice $invoice) {
				$invoice->getBuyer()->setElectronicAddress(null, null);
			}, 'PEPPOL-EN16931-R010/R020'],
			'direct debit beside a transfer' => [function (Invoice $invoice) {
				$invoice->addPaymentMeans(PaymentMeans::sepaDirectDebit('MANDATE-42', 'DE02120300000000202051', 'FR98ZZZ999999'));
			}, 'BR-DE-23/24'],
			'direct debit without a mandate' => [function (Invoice $invoice) {
				return $this->withoutPaymentMeans($invoice)->addPaymentMeans(new PaymentMeans(PaymentMeans::SEPA_DIRECT_DEBIT));
			}, 'PEPPOL-EN16931-R061'],
			'Skonto in the wrong form' => [function (Invoice $invoice) {
				$invoice->setPaymentTerms("#SKONTO#TAGE=14#PROZENT=2#\n");
			}, 'BR-DE-18'],
		];
	}

	/**
	 * An invoice that breaks a rule XRechnung adds is refused for XRechnung, the message naming the rule
	 *
	 * @dataProvider brokenInXRechnungProvider
	 *
	 * @param callable $break
	 * @param string $rule
	 */
	public function testNamesTheXRechnungRuleBroken($break, $rule)
	{
		$invoice = $this->xrechnungInvoice();
		$broken = call_user_func($break, $invoice);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($rule . ':');

		Rules::checkXRechnung($broken instanceof Invoice ? $broken : $invoice);
	}

	/**
	 * The French fixture keeps to the rules France's reform adds, which the plain invoice does not
	 */
	public function testPassesTheFrenchFixture()
	{
		$this->assertSame([], Rules::brokenInFrance($this->frenchInvoice()));
		$this->assertNotEmpty(Rules::brokenInFrance($this->invoice()));
	}

	/**
	 * The French fixture broken in one way, and the rule that says so
	 *
	 * @return mixed[]
	 */
	public function brokenInFranceProvider()
	{
		return [
			'number too long' => [function (Invoice $invoice) {
				return $this->copy($invoice, str_repeat('9', 36));
			}, 'BR-FR-01/02'],
			'no recovery fee mention' => [function (Invoice $invoice) {
				return $this->copy($invoice)->addNote('Pénalités de retard', 'PMD')->addNote('Pas d’escompte', 'AAB');
			}, 'BR-FR-05/06'],
			'no cadre de facturation' => [function (Invoice $invoice) {
				$invoice->setBusinessProcess(null);
			}, 'BR-FR-08'],
			'seller identified by SIRET' => [function (Invoice $invoice) {
				$invoice->getSeller()->setLegalId('12345678900012', '0009');
			}, 'BR-FR-10'],
			'buyer reached by email' => [function (Invoice $invoice) {
				$invoice->getBuyer()->setElectronicAddress('ap@acheteur.example');
			}, 'BR-FR-21'],
			'credit note naming no invoice' => [function (Invoice $invoice) {
				$invoice->setTypeCode(Invoice::TYPE_CREDIT_NOTE);
			}, 'BR-FR-CO-05'],
			'foreign VAT rate' => [function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Livre', 1, 10, 19));
			}, 'BR-FR-16'],
		];
	}

	/**
	 * An invoice that breaks a rule France's reform adds is refused for France, the message naming the rule
	 *
	 * @dataProvider brokenInFranceProvider
	 *
	 * @param callable $break
	 * @param string $rule
	 */
	public function testNamesTheFrenchRuleBroken($break, $rule)
	{
		$invoice = $this->frenchInvoice();
		$broken = call_user_func($break, $invoice);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($rule . ':');

		Rules::checkFrance($broken instanceof Invoice ? $broken : $invoice);
	}

	/**
	 * The French invoice again under another number, with its lines, business process and payment but no notes
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param string|null $id
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function copy(Invoice $invoice, $id = null)
	{
		$copy = new Invoice($id !== null ? $id : $invoice->getId(), $invoice->getIssueDate(), 'EUR', $invoice->getSeller(), $invoice->getBuyer());
		foreach ($invoice->getLines() as $line) {
			$copy->addLine($line);
		}
		foreach ($invoice->getPaymentMeans() as $means) {
			$copy->addPaymentMeans($means);
		}
		if ($id !== null) {
			foreach ($invoice->getNotes() as $note) {
				$copy->addNote($note['content'], $note['subjectCode']);
			}
		}

		return $copy->setBusinessProcess('S1')->setDueDate($invoice->getDueDate());
	}

	/**
	 * The invoice again, with its lines and parties but no way to pay
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function withoutPaymentMeans(Invoice $invoice)
	{
		$copy = new Invoice($invoice->getId(), $invoice->getIssueDate(), $invoice->getCurrency(), $invoice->getSeller(), $invoice->getBuyer());
		foreach ($invoice->getLines() as $line) {
			$copy->addLine($line);
		}

		return $copy->setBuyerReference($invoice->getBuyerReference())->setPaymentTerms($invoice->getPaymentTerms());
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
