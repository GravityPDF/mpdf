<?php

namespace Snapshots;

use Mpdf\Invoice\EN16931\InvoiceFixtures;
use Mpdf\Invoice\LineItem;

/**
 * An invoice of more lines than fit on a page: the column headings repeat on the next, and the totals follow the last
 * line
 *
 * @group snapshot
 */
class InvoiceManyLinesSnapshotTest extends InvoiceSnapshot
{

	use InvoiceFixtures;

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'invoice-many-lines';
	}

	/**
	 * Forty lines at three VAT rates, every fifth with a description long enough to wrap
	 *
	 * @return \Mpdf\Invoice\EN16931\Invoice
	 */
	protected function getInvoice()
	{
		$invoice = $this->blankInvoice();
		$rates = [20, 10, 5.5];

		for ($i = 1; $i <= 40; $i++) {
			$line = new LineItem('Item ' . $i, $i % 7 + 1, 4.75 * $i, $rates[$i % 3]);
			if ($i % 5 === 0) {
				$line->setDescription('Delivered in instalments over the quarter, as agreed in the framework contract signed at the start of the year');
			}
			$invoice->addLine($line);
		}

		return $invoice;
	}

}
