<?php

namespace Snapshots;

/**
 * An invoice built the way an HTML email template is: nested tables laid out by width, bgcolor, align, valign,
 * cellpadding and border, and text coloured by font color, with a newer stylesheet laid over it that restyles some of
 * what the attributes set. Written under each value of cssMode, with captions saying what each cascade draws.
 *
 * @group snapshot
 */
abstract class EmailInvoiceTemplateSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cssMode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'email-invoice-template-' . $this->cssMode();
	}

	/**
	 * The template, with a caption for each part saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 1mm 0; }

			/* The newer stylesheet */
			.masthead .tagline { color: #9ec5fe; }
			p.greeting { text-align: left; }
			table.items th { background-color: #0a58ca; color: #ffffff; }
			table.items td.amount { text-align: right; }
			table.summary { border: 0.6mm solid #0a58ca; }
			hr.divider { color: #0a58ca; }
		</style>

		<p class="caption">Written with cssMode set to <?php echo $this->cssMode(); ?>.</p>

		<h2>Masthead</h2>
		<p class="caption">A table filled by bgcolor, with font color and size. Both: white "ACME Supplies" and yellow "INVOICE" on dark blue, and a light blue tagline, .tagline beating its font color.</p>
		<table class="masthead" width="100%" cellpadding="8" cellspacing="0" bgcolor="#003366">
			<tr>
				<td align="left" valign="top"><font color="#ffffff" size="5"><b>ACME Supplies</b></font><br /><font class="tagline" color="#ffffff">Everything for the workshop</font></td>
				<td align="right" valign="top" width="30%"><font color="#ffcc00" size="4">INVOICE</font><br /><font color="#ffffff">No. 10042</font></td>
			</tr>
		</table>

		<h2>Greeting</h2>
		<p class="caption">p align="center" against p.greeting. Standard: the greeting is at the left, the rule beating the attribute. Legacy: it is centred, the attribute beating the rule. The paragraph with only its attribute is centred in both.</p>
		<p class="greeting" align="center">Dear customer, thank you for your order.</p>
		<p align="center">Your order has been sent.</p>

		<h2>Items</h2>
		<p class="caption">th bgcolor against table.items th, and td align="center" against td.amount. Both: the headings are white on blue, the rule beating bgcolor. Standard: the amounts are at the right, the rule beating the attribute. Legacy: they are centred. The quantities, with only their attribute, are centred in both, and every cell has a thin black border from border="1".</p>
		<table class="items" width="100%" cellpadding="5" cellspacing="0" border="1">
			<tr><th bgcolor="#cccccc" align="left">Item</th><th bgcolor="#cccccc" width="15%">Qty</th><th bgcolor="#cccccc" width="20%">Amount</th></tr>
			<tr><td>Widgets</td><td align="center">4</td><td class="amount" align="center">40.00</td></tr>
			<tr><td>Sprockets</td><td align="center">12</td><td class="amount" align="center">96.00</td></tr>
			<tr><td bgcolor="#fff3cd">Gaskets (back-ordered)</td><td align="center">3</td><td class="amount" align="center">7.50</td></tr>
		</table>

		<h2>Summary</h2>
		<p class="caption">table border="1" against table.summary. Standard: the table's own border is thick and blue, the rule beating the attribute, and its cells keep a thin black border. Legacy: every border is thin and black. The total is on one line in both, by nowrap.</p>
		<table class="summary" width="60%" cellpadding="4" border="1" align="right">
			<tr><td>Subtotal</td><td align="right">143.50</td></tr>
			<tr><td>Delivery</td><td align="right">10.00</td></tr>
			<tr><td bgcolor="#e7f1ff"><b>Total due</b></td><td bgcolor="#e7f1ff" align="right" nowrap="nowrap"><b>153.50 AUD</b></td></tr>
		</table>

		<h2>Footer</h2>
		<p class="caption">hr color against hr.divider, and color on a link and a span. Both: the rule is blue, hr.divider beating the attribute. Standard: the rule is half as wide as the page, at its left, and the link is blue and the note black, neither element taking a color attribute. Legacy: the rule is as wide as the page, and the link is blue and the note red.</p>
		<hr class="divider" width="50%" align="left" color="#999999" />
		<p><a href="#pay" color="#ff0000">Pay online</a> <span color="#ff0000">(due within 30 days)</span></p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
