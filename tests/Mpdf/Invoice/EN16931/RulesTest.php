<?php

namespace Mpdf\Invoice\EN16931;

use Mpdf\Invoice\AllowanceCharge;
use Mpdf\Invoice\EN16931\Cius\BuyerReferenceCius;
use Mpdf\Invoice\EN16931\Cius\EN16931;
use Mpdf\Invoice\EN16931\Cius\France;
use Mpdf\Invoice\EN16931\Cius\XRechnung;
use Mpdf\Invoice\LineItem;
use Mpdf\Invoice\PaymentMeans;
use Mpdf\MpdfException;

class RulesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use InvoiceFixtures;

	/**
	 * Each fixture, and the rules it keeps to
	 *
	 * @return mixed[]
	 */
	public function fixtureProvider()
	{
		return [
			'invoice' => ['invoice', new EN16931()],
			'reverse charge' => ['reverseChargeInvoice', new EN16931()],
			'shop' => ['shopInvoice', new EN16931()],
			'credit note' => ['creditNote', new EN16931()],
			'intra-community' => ['intraCommunityInvoice', new EN16931()],
			'XRechnung' => ['xrechnungInvoice', new XRechnung()],
			'France' => ['frenchInvoice', new France()],
		];
	}

	/**
	 * Every invoice the fixtures build keeps to its rules
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param string $fixture
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius The specification applied on top of EN 16931
	 */
	public function testPassesTheFixtures($fixture, $cius)
	{
		$this->assertSame([], Rules::broken($this->$fixture(), $cius));
	}

	/**
	 * The plain invoice breaks the rules XRechnung and France's reform add
	 */
	public function testTheExtraRulesAskForMore()
	{
		$this->assertNotEmpty(Rules::broken($this->invoice(), new XRechnung()));
		$this->assertNotEmpty(Rules::broken($this->invoice(), new France()));
	}

	/**
	 * A fixture broken in one way, the rules it is checked against, and the rule that says so. A break that cannot
	 * change the fixture in place returns a new invoice.
	 *
	 * @return mixed[]
	 */
	public function brokenProvider()
	{
		return [
			'seller unidentified' => ['invoice', new EN16931(), function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null)->setLegalId(null);
			}, 'BR-CO-26'],
			'standard rate without the seller taxed' => ['invoice', new EN16931(), function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null);
			}, 'BR-S-2'],
			'reverse charge without the buyer identified' => ['reverseChargeInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->getBuyer()->setVatId(null);
			}, 'BR-AE-2'],
			'intra-community without a delivery address' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Paper', 1, 10, 0, 'K'))->setExemptionReason('K', 'Intra-community supply');
			}, 'BR-IC-12'],
			'intra-community without a reason' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Paper', 1, 10, 0, 'K'))->setDeliverTo($invoice->getBuyer());
			}, 'BR-IC-10'],
			'export without the seller\'s VAT identifier' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->getSeller()->setVatId(null);
				$invoice->addLine(new LineItem('Paper', 1, 10, 0, 'G'))->setExemptionReason('G', 'Export');
			}, 'BR-G-2'],
			'not subject to VAT beside another category' => ['invoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Donation', 1, 10, 0, 'O'))->setExemptionReason('O', 'Not subject to VAT');
			}, 'BR-O-11'],
			'not subject to VAT naming a VAT identifier' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Donation', 1, 10, 0, 'O'))->setExemptionReason('O', 'Not subject to VAT');
			}, 'BR-O-2'],
			'exempt without a reason' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Lesson', 1, 10, 0, 'E'));
			}, 'BR-E-10'],
			'standard rate with an exemption reason' => ['invoice', new EN16931(), function (Invoice $invoice) {
				$invoice->setExemptionReason('S', 'Why');
			}, 'BR-S-10'],
			'amount due without terms or a due date' => ['creditNote', new EN16931(), function (Invoice $invoice) {
				$invoice->setTypeCode(Invoice::TYPE_INVOICE)->setPaymentTerms(null);
			}, 'BR-CO-25'],
			'allowance without a reason' => ['invoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addAllowanceCharge(AllowanceCharge::allowance(5)->setVat(20));
			}, 'BR-33'],
			'line charge without a reason' => ['blankInvoice', new EN16931(), function (Invoice $invoice) {
				$invoice->addLine((new LineItem('Desk', 1, 100, 20))->addAllowanceCharge(AllowanceCharge::charge(5)));
			}, 'BR-44'],

			'XRechnung: no way to pay' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				return $this->rebuild($invoice, ['paymentMeans']);
			}, 'BR-DE-1'],
			'XRechnung: seller contact without a phone number' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->getSeller()->setContact('Accounts', null, 'accounts@seller.example');
			}, 'BR-DE-2'],
			'XRechnung: buyer without a postcode' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->getBuyer()->setAddress('Hauptstraße 1', null, 'Berlin');
			}, 'BR-DE-8/9'],
			'XRechnung: no buyer reference' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->setBuyerReference(null);
			}, 'BR-DE-15'],
			'XRechnung: buyer without an electronic address' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->getBuyer()->setElectronicAddress(null, null);
			}, 'PEPPOL-EN16931-R010/R020'],
			'XRechnung: direct debit beside a transfer' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->addPaymentMeans(PaymentMeans::sepaDirectDebit('MANDATE-42', 'DE02120300000000202051', 'FR98ZZZ999999'));
			}, 'BR-DE-23/24'],
			'XRechnung: direct debit without a mandate' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				return $this->rebuild($invoice, ['paymentMeans'])->addPaymentMeans(new PaymentMeans(PaymentMeans::SEPA_DIRECT_DEBIT));
			}, 'PEPPOL-EN16931-R061'],
			'XRechnung: Skonto in the wrong form' => ['xrechnungInvoice', new XRechnung(), function (Invoice $invoice) {
				$invoice->setPaymentTerms("#SKONTO#TAGE=14#PROZENT=2#\n");
			}, 'BR-DE-18'],

			'own specification: no buyer reference' => ['invoice', new BuyerReferenceCius(), function (Invoice $invoice) {
				$invoice->setBuyerReference(null);
			}, 'EX-1'],

			'France: number too long' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				return $this->rebuild($invoice, [], str_repeat('9', 36));
			}, 'BR-FR-01/02'],
			'France: no recovery fee mention' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				return $this->rebuild($invoice, ['notes'])->addNote('Pénalités de retard', 'PMD')->addNote('Pas d’escompte', 'AAB');
			}, 'BR-FR-05/06'],
			'France: no cadre de facturation' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				$invoice->setBusinessProcess(null);
			}, 'BR-FR-08'],
			'France: seller identified by SIRET' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				$invoice->getSeller()->setLegalId('12345678900012', '0009');
			}, 'BR-FR-10'],
			'France: buyer reached by email' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				$invoice->getBuyer()->setElectronicAddress('ap@acheteur.example');
			}, 'BR-FR-21'],
			'France: credit note naming no invoice' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				$invoice->setTypeCode(Invoice::TYPE_CREDIT_NOTE);
			}, 'BR-FR-CO-05'],
			'France: foreign VAT rate' => ['frenchInvoice', new France(), function (Invoice $invoice) {
				$invoice->addLine(new LineItem('Livre', 1, 10, 19));
			}, 'BR-FR-16'],
		];
	}

	/**
	 * An invoice that breaks a rule is refused under the rules it is checked against, the message naming the rule
	 *
	 * @dataProvider brokenProvider
	 *
	 * @param string $fixture
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius The specification applied on top of EN 16931
	 * @param callable $break
	 * @param string $rule
	 */
	public function testNamesTheRuleBroken($fixture, $cius, $break, $rule)
	{
		$invoice = $this->$fixture();
		$broken = call_user_func($break, $invoice);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($rule . ':');

		Rules::check($broken instanceof Invoice ? $broken : $invoice, $cius);
	}

	/**
	 * A specification, and the standards a refusal under it names
	 *
	 * @return mixed[]
	 */
	public function standardsProvider()
	{
		return [
			'EN 16931 alone' => [new EN16931(), "The invoice breaks EN 16931:\n- "],
			'with XRechnung' => [new XRechnung(), "The invoice breaks EN 16931 and XRechnung:\n- "],
			'with a specification of its own' => [new BuyerReferenceCius(), "The invoice breaks EN 16931 and the buyer reference rules:\n- "],
		];
	}

	/**
	 * The message names the standards the invoice is checked against
	 *
	 * @dataProvider standardsProvider
	 *
	 * @param \Mpdf\Invoice\EN16931\Cius\CiusInterface $cius
	 * @param string $message
	 */
	public function testNamesTheStandards($cius, $message)
	{
		$invoice = $this->invoice();
		// Unidentified, the seller breaks BR-CO-26 whatever the specification
		$invoice->getSeller()->setVatId(null)->setLegalId(null);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		Rules::check($invoice, $cius);
	}

	/**
	 * The invoice again, for a break that needs what the model cannot take away or change once set: its number, its
	 * notes or its ways to pay. It keeps its parties, lines, business process, buyer reference, due date and terms.
	 *
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param string[] $drop What it leaves out: 'notes', 'paymentMeans' or both
	 * @param string|null $id A number in place of its own
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	private function rebuild(Invoice $invoice, array $drop = [], $id = null)
	{
		$copy = new Invoice($id !== null ? $id : $invoice->getId(), $invoice->getIssueDate(), $invoice->getCurrency(), $invoice->getSeller(), $invoice->getBuyer());
		foreach ($invoice->getLines() as $line) {
			$copy->addLine($line);
		}
		foreach (in_array('paymentMeans', $drop, true) ? [] : $invoice->getPaymentMeans() as $means) {
			$copy->addPaymentMeans($means);
		}
		foreach (in_array('notes', $drop, true) ? [] : $invoice->getNotes() as $note) {
			$copy->addNote($note['content'], $note['subjectCode']);
		}

		if ($invoice->getDueDate() !== null) {
			$copy->setDueDate($invoice->getDueDate());
		}

		return $copy->setBusinessProcess($invoice->getBusinessProcess())
			->setBuyerReference($invoice->getBuyerReference())
			->setPaymentTerms($invoice->getPaymentTerms());
	}

}
