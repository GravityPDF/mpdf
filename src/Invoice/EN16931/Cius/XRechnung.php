<?php

namespace Mpdf\Invoice\EN16931\Cius;

use Mpdf\Invoice\EN16931\Invoice;
use Mpdf\Invoice\FacturX;
use Mpdf\Invoice\Totals;

/**
 * Germany's XRechnung 3.0, written on the EN 16931 profile
 *
 * It names its own guideline, which FacturX reads as the XRECHNUNG level and embeds as xrechnung.xml, names the Peppol
 * billing process when the invoice gives none, and gives category O a rate of 0 in the VAT breakdown. XRechnung is
 * usually sent as XML on its own rather than embedded in a PDF.
 *
 * @see https://xeinkauf.de/xrechnung/
 */
class XRechnung extends EN16931
{

	/**
	 * The business process XRechnung requires, and names when the invoice gives none
	 */
	const PEPPOL_BILLING = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'XRechnung';
	}

	/**
	 * XRechnung carries all of EN 16931, so is written on that profile alone
	 *
	 * @return string[]
	 */
	public function getProfiles()
	{
		return [FacturX::EN16931];
	}

	/**
	 * @return string
	 */
	public function getGuideline()
	{
		return FacturX::GUIDELINE_XRECHNUNG . '_3.0';
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 *
	 * @return string
	 */
	public function getBusinessProcess(Invoice $invoice)
	{
		$process = $invoice->getBusinessProcess();

		return $process === null ? self::PEPPOL_BILLING : $process;
	}

	/**
	 * XRechnung gives every category a rate in the VAT breakdown, 0 for O (BR-DE-14)
	 *
	 * Lines, allowances and charges in O still carry none, as EN 16931's BR-O-05 to BR-O-07 require.
	 *
	 * @see https://github.com/itplr-kosit/xrechnung-schematron KoSIT's XRechnung Schematron
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public function givesRate($category)
	{
		return true;
	}

	/**
	 * @param \Mpdf\Invoice\EN16931\Invoice $invoice
	 * @param \Mpdf\Invoice\Totals $totals The invoice's totals, worked out once for every rule
	 *
	 * @return string[]
	 */
	public function broken(Invoice $invoice, Totals $totals)
	{
		$seller = $invoice->getSeller();
		$broken = [];

		if (!$invoice->getPaymentMeans()) {
			$broken[] = 'BR-DE-1: the invoice needs a way to pay; call addPaymentMeans()';
		}

		if ($seller->getContactName() === null || $seller->getContactPhone() === null || $seller->getContactEmail() === null) {
			$broken[] = 'BR-DE-2: the seller needs a contact with a name, phone number and email address; call setContact()';
		}

		$parties = ['seller' => ['BR-DE-3/4', $seller], 'buyer' => ['BR-DE-8/9', $invoice->getBuyer()]];
		if ($invoice->getDeliverTo() !== null) {
			$parties['delivery address'] = ['BR-DE-10/11', $invoice->getDeliverTo()];
		}
		foreach ($parties as $name => $party) {
			if ($party[1]->getCity() === null || $party[1]->getPostcode() === null) {
				$broken[] = sprintf('%s: the %s needs a city and postcode', $party[0], $name);
			}
		}

		if ($invoice->getBuyerReference() === null) {
			$broken[] = 'BR-DE-15: the invoice needs the buyer\'s reference, a Leitweg-ID for a public buyer; call setBuyerReference()';
		}

		if (!$this->bothReachable($invoice)) {
			$broken[] = 'PEPPOL-EN16931-R010/R020: the seller and the buyer need an electronic address; call setElectronicAddress()';
		}

		$debit = false;
		$transferOrCard = false;
		foreach ($invoice->getPaymentMeans() as $means) {
			if ($means->isDirectDebit()) {
				$debit = true;
				if ($means->getMandateReference() === null) {
					$broken[] = 'PEPPOL-EN16931-R061: a direct debit needs its mandate; use PaymentMeans::sepaDirectDebit()';
				}
			} elseif ($means->isCreditTransfer() || $means->isCard()) {
				$transferOrCard = true;
			}
		}
		if ($debit && $transferOrCard) {
			$broken[] = 'BR-DE-23/24: a direct debit cannot be offered beside a transfer or a card';
		}

		foreach (explode("\n", (string) $invoice->getPaymentTerms()) as $line) {
			if (strpos($line, '#') === 0 && !preg_match('/^#SKONTO#TAGE=[0-9]+#PROZENT=[0-9]+\.[0-9]{2}(#BASISBETRAG=-?[0-9]+\.[0-9]{2})?#$/', $line)) {
				$broken[] = 'BR-DE-18: a line of the payment terms starting # must read #SKONTO#TAGE=14#PROZENT=2.00#';
			}
		}

		return $broken;
	}

}
