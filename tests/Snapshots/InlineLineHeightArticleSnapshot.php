<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * A newsletter article whose stylesheet sets line-height on inline elements, as sites do: a drop cap, footnote marks
 * with line-height: 0, badges, a pull quote, prices in a list, and quantities in an order table with a nested table.
 * Written under each value of cssMode, with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class InlineLineHeightArticleSnapshot extends Snapshot
{

	/**
	 * @return string A CssMode value
	 */
	abstract protected function mode();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-line-height-article-' . $this->mode();
	}

	/**
	 * The article, each part under a caption saying what standard and legacy mode draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 10pt; color: #1f2933; }
			h1 { font-size: 18pt; margin: 0 0 1mm 0; }
			h2 { font-size: 12pt; margin: 5mm 0 1mm 0; }
			p { margin: 0 0 2mm 0; }
			p.caption { font-size: 7.5pt; color: #7b8794; margin: 0 0 1.5mm 0; }
			.shaded { background-color: #e4f0fb; }

			.fn { font-size: 75%; line-height: 0; vertical-align: super; color: #2d6a4f; }
			.dropcap { font-size: 30pt; line-height: 1; color: #c0392b; font-weight: bold; }
			.badge { font-size: 7pt; line-height: 1; color: #ffffff; background-color: #3e4c59; }
			.pull { font-size: 13pt; font-style: italic; line-height: 2; color: #2d6a4f; }
			.price { font-size: 16pt; font-weight: bold; line-height: 1.1; }
			.qty { font-size: 14pt; line-height: 12mm; }
			.note { line-height: 2; }

			table.order { border-collapse: collapse; width: 100%; }
			table.order th { background-color: #3e4c59; color: #ffffff; padding: 1mm 2mm; text-align: left; }
			table.order td { border-bottom: 0.2mm solid #cbd2d9; padding: 1mm 2mm; }
			table.options td { border: 0.2mm solid #9aa5b1; padding: 0.5mm 1mm; font-size: 8pt; }
		</style>

		<h1>Spring newsletter</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->mode(); ?>.</p>

		<h2>Drop cap</h2>
		<p class="caption">The red capital is 30pt with line-height: 1. Standard: the first line is 30pt high, so the blue background fits snugly over the capital. Legacy: the first line is as high as the capital's normal line, leaving a gap below it.</p>
		<p class="shaded"><span class="dropcap">W</span>elcome to the spring issue. This season we look at gardens, a new delivery service, and the results of the reader survey, which drew more replies than ever.</p>

		<h2>Footnote marks</h2>
		<p class="caption">The green footnote marks are raised, with line-height: 0 so that they do not push the lines apart. Standard: the two lines of the paragraph are as far apart as the two lines after it. Legacy: the first line, which holds the marks, is taller.</p>
		<p class="shaded">Survey replies rose by a third<span class="fn">1</span> on last year, and most readers<span class="fn">2</span> asked for more recipes and fewer<br>onions. The full figures are on the website, with the method used.<br>Thank you to everyone who took part.</p>

		<h2>Badges and a pull quote</h2>
		<p class="caption">The dark badges have line-height: 1, below the paragraph's, so the lines holding them are no taller in either mode. The green quote has line-height: 2. Standard: its line is twice its 13pt font, 26pt high. Legacy: it is as high as the quote's normal line.</p>
		<p class="shaded">Filed under <span class="badge">&nbsp;GARDENS&nbsp;</span> and <span class="badge">&nbsp;SURVEY&nbsp;</span>. One reader wrote: <span class="pull">"the best issue yet"</span>, and we hope you agree.</p>

		<h2>Plans</h2>
		<p class="caption">Each price is 16pt with line-height: 1.1. Standard: each item is 17.6pt high, so the list is tighter. Legacy: each item is the price's normal line height.</p>
		<ul class="shaded">
			<li><span class="price">$9</span> a month for the weekly box</li>
			<li><span class="price">$15</span> a month with fruit</li>
			<li><span class="price">$24</span> a month for a family</li>
		</ul>

		<h2>Your order</h2>
		<p class="caption">The quantities are 14pt with line-height: 12mm. Standard: each row with a quantity is 12mm plus its padding high, and the nested table of options sits in a taller row. Legacy: the rows are only as high as the 14pt text. The note under the table has line-height: 2 on its span, making its first line taller in standard mode only.</p>
		<table class="order">
			<thead>
				<tr><th>Item</th><th>Options</th><th>Qty</th></tr>
			</thead>
			<tbody>
				<tr>
					<td>Weekly box</td>
					<td>
						<table class="options"><tr><td>Small</td><td>No onions</td></tr></table>
					</td>
					<td><span class="qty">2</span></td>
				</tr>
				<tr>
					<td>Fruit add-on</td>
					<td>Seasonal</td>
					<td><span class="qty">1</span></td>
				</tr>
			</tbody>
		</table>
		<p class="shaded"><span class="note">Deliveries arrive on Thursdays between 8am and noon.</span><br>Change your order by Monday.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->mode()]);

		$this->mpdf->WriteHTML($html);
	}

}
