<?php

namespace Snapshots;

/**
 * A product brochure set the way a design system writes it, in em: a lead paragraph with em letter spacing and
 * smaller runs in it, cards with em padding and a note that takes its card's padding through inherit, a price table
 * whose row group and rows scale their text, a positioned offer badge, a Hebrew testimonial whose span wraps a block,
 * and a highlighted run that a block interrupts. Written under each value of cssMode, with captions saying what each
 * draws.
 *
 * @group snapshot
 */
abstract class ComputedValuesBrochureSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cascade();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'computed-values-brochure-' . $this->cascade();
	}

	/**
	 * The brochure, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: dejavusans; font-size: 9pt; color: #2d3436; }
			h1 { font-size: 2em; margin: 0 0 1mm 0; color: #0a3d62; letter-spacing: 0.05em; }
			h2 { font-size: 1.3em; margin: 5mm 0 1mm 0; color: #0a3d62; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; letter-spacing: 0; word-spacing: 0; }

			.lead { font-size: 1.4em; letter-spacing: 0.08em; word-spacing: 0.3em; color: #0a3d62; }
			.lead small { font-size: 60%; color: #636e72; }

			.card { font-size: 1.2em; padding: 0.8em; margin: 0 0 2mm 0; border: 0.3mm solid #82ccdd; background-color: #f1f9fc; }
			.card .note { font-size: 0.75em; padding: inherit; border: 0.2mm dashed #82ccdd; }

			table.prices { border-collapse: collapse; font-size: 1.1em; }
			table.prices td, table.prices th { border: 0.2mm solid #b2bec3; padding: 1mm 2mm; }
			table.prices thead { font-size: 120%; color: #0a3d62; }
			table.prices tr.small { font-size: 80%; }

			.badge { position: absolute; top: 240mm; left: 140mm; width: 45mm; background-color: #e55039; color: #ffffff; text-align: center; padding: 2mm; }
			.highlight { font-style: italic; text-shadow: 0.3mm 0.3mm #f6b93b; }
		</style>

		<div class="badge"><p>Launch offer<br>20% off<br>until June</p></div>

		<h1>The Harbour chair</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>
		<p class="caption">The red badge, at the foot of the page, is a positioned block with line-height: normal. Standard: its three lines are as far apart as lines of the same size in the flow. Legacy: they are set at a fixed 1.33.</p>

		<p class="caption">The lead paragraph sets letter-spacing: 0.08em and word-spacing: 0.3em at 12.6pt, and the small run in it is 60% of that. Standard: the small run is spaced as widely as the paragraph. Legacy: its spacing is read again at its own size, and is narrower.</p>
		<p class="lead">Hand-built in oak, finished in linseed oil <small>and guaranteed for ten years</small></p>

		<h2>Why it lasts</h2>
		<p class="caption">Each card has padding: 0.8em at 10.8pt; the note in it is 0.75em and sets padding: inherit. Standard: the note's text is indented as far as the card's. Legacy: the note has no padding of its own.</p>
		<div class="card">Solid oak frame, jointed without screws.
			<div class="note">Joints are pegged, so the frame can be tightened after years of use.</div>
		</div>
		<div class="card">Seat woven from paper cord.
			<div class="note">The cord is replaceable, and a kit comes with every chair.</div>
		</div>

		<h2>Prices</h2>
		<p class="caption">The table is 1.1em of the body's 9pt, its header row group 120% of that, and the small row 80%. Standard: the header is 11.9pt and the small row 7.9pt. Legacy: the header and the small row are 9.9pt, as a row group and a row hand their cells nothing.</p>
		<table class="prices">
			<thead><tr><th>Finish</th><th>Price</th></tr></thead>
			<tbody>
				<tr><td>Natural oak</td><td>&#x20ac;480</td></tr>
				<tr><td>Smoked oak</td><td>&#x20ac;540</td></tr>
				<tr class="small"><td>Delivery in the EU</td><td>&#x20ac;35</td></tr>
			</tbody>
		</table>

		<h2>What customers say</h2>
		<p class="caption">A right-to-left span wraps the testimonial and a block that credits it. Standard: after the block, as before it, the Hebrew word is to the right of the brand name. Legacy: after the block it is to the left.</p>
		<div><span dir="rtl">&#x5e0;&#x5d5;&#x5d7; Harbour<div style="font-size: 0.8em; color: #636e72">from a customer in Haifa</div>&#x5de;&#x5d5;&#x5de;&#x5dc;&#x5e5; Harbour</span></div>

		<p class="caption">A highlighted run, italic with a gold shadow, is interrupted by a block. Standard: the words before the run are upright and unshadowed. Legacy: they take the run's shadow, and the block and the words after it lose the highlight.</p>
		<div>Order before June and <span class="highlight">the delivery is free<div>for every chair in the range</div>anywhere in the EU</span>.</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);
		$this->mpdf->WriteHTML($html);
	}

}
