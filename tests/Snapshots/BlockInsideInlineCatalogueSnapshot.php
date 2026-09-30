<?php

namespace Snapshots;

/**
 * A product catalogue written the way HTML5 templates and rich-text editors write it: cards that are links around
 * blocks, a rich-text answer whose font and colour are set on a span around its paragraphs, a table whose cells wrap
 * blocks in strong, and a quotation inside em. Written under each value of cssMode, with captions saying what each
 * draws.
 *
 * @group snapshot
 */
abstract class BlockInsideInlineCatalogueSnapshot extends Snapshot
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
		return 'block-inside-inline-catalogue-' . $this->cascade();
	}

	/**
	 * The catalogue, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: sans-serif; font-size: 9pt; color: #2d3436; }
			h1 { font-size: 16pt; margin: 0 0 1mm 0; color: #0a3d62; }
			h2 { font-size: 11pt; margin: 5mm 0 1mm 0; color: #0a3d62; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; font-weight: normal; font-style: normal; text-decoration: none; }

			a.card { color: #1e6fd9; text-decoration: none; font-weight: bold; }
			a.card .title { font-size: 11pt; text-decoration: underline; }
			a.card .body { font-size: 8.5pt; margin: 0.5mm 0 3mm 0; }
			a.card .price { color: #b33939; }

			.answer { font-family: serif; font-size: 10pt; color: #6d214f; }
			.answer p { margin: 0 0 1.5mm 0; }

			table.specs { border-collapse: collapse; width: 100%; }
			table.specs td { border: 0.2mm solid #b2bec3; padding: 1mm 2mm; vertical-align: top; }
			table.specs strong { color: #0a3d62; }

			blockquote { margin: 1mm 0 1mm 6mm; padding-left: 2mm; border-left: 0.6mm solid #b2bec3; }
		</style>

		<h1>Spring catalogue</h1>

		<h2>Featured</h2>
		<p class="caption">Each card is a link around three divs. Standard: every line of a card is bold and blue, the title larger and underlined, the price red, and each line links to its product. Legacy: the divs start from the page's style, so nothing is bold or blue and nothing links, and the rules for the title and the price go through the link, an inline element, so they are dropped and every line is plain.</p>
		<a class="card" href="https://example.com/lamp">
			<div class="title">Desk lamp</div>
			<div class="body">Brushed steel, warm light, three brightness settings.</div>
			<div class="body price">$49</div>
		</a>
		<a class="card" href="https://example.com/chair">
			<div class="title">Reading chair</div>
			<div class="body">Oak frame with a wool cushion.</div>
			<div class="body price">$320</div>
		</a>

		<h2>Customer question</h2>
		<p class="caption">A rich-text answer: a purple, 10pt serif span around two paragraphs, and a sentence after them. Standard: all three are purple 10pt serif. Legacy: the paragraphs and the sentence after them are in the page's black sans-serif.</p>
		<span class="answer"><p>Yes, the lamp takes any E27 bulb up to 60W.</p><p>We ship it with a warm LED bulb fitted.</p>Thanks for asking.</span>

		<h2>Specifications</h2>
		<p class="caption">Each cell wraps two divs in strong. Standard: the divs are bold and dark blue, and "Measured in store" after the strong is plain. Legacy: the divs are bold and dark blue too, but so is "Measured in store", since the end of the strong restores nothing.</p>
		<table class="specs"><tr>
			<td><strong><div>Desk lamp</div><div>45 cm, 1.2 kg</div></strong> Measured in store</td>
			<td><strong><div>Reading chair</div><div>92 cm, 14 kg</div></strong> Measured in store</td>
		</tr></table>

		<h2>From our reviewers</h2>
		<p class="caption">A quotation inside em. Standard: the quotation and "Rated five stars" after it are italic. Legacy: only "Our reviewer wrote" is italic.</p>
		<em>Our reviewer wrote:<blockquote>The chair is the most comfortable I have tested this year.</blockquote>Rated five stars.</em>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
