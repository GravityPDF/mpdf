<?php

namespace Snapshots;

/**
 * An article whose tables sit in styled blocks: a dark callout with a table of figures, a list of steps holding a
 * table, a quotation with a sized table in it, and a panel cell holding a nested table of headers. Written under each
 * value of cssMode, with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class TableInBlocksArticleSnapshot extends Snapshot
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
		return 'table-in-blocks-article-' . $this->cascade();
	}

	/**
	 * The article, each part under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-family: serif; font-size: 10pt; color: #1f2933; }
			h1 { font-size: 17pt; margin: 0 0 1mm 0; color: #7b341e; }
			h2 { font-size: 11pt; margin: 5mm 0 1mm 0; color: #7b341e; }
			p.caption { font-family: sans-serif; font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; font-style: normal; line-height: normal; text-align: left; letter-spacing: 0; }
			td, th { border: 0.2mm solid #cbd2d9; padding: 1mm 2mm; }

			.callout { background-color: #243b53; color: #f0f4f8; font-family: sans-serif; padding: 2mm 3mm; letter-spacing: 0.2mm; }
			.callout td { border-color: #486581; }

			ol.steps li { color: #0b6e4f; font-style: italic; margin-bottom: 2mm; }

			blockquote { font-size: 12pt; line-height: 1.8; text-align: right; color: #7b341e; margin: 2mm 10mm; }
			blockquote table { font-size: 75%; }

			td.panel { color: #334e68; font-family: monospace; text-align: right; line-height: 2; width: 150mm; }
		</style>

		<h1>Kiln firing notes</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>

		<h2>Firing summary</h2>
		<p class="caption">The callout is a dark block that sets pale sans-serif text spaced 0.2mm. Standard: the table in it has pale, spaced, sans-serif cells too. Legacy: its cells are in the body's dark serif, hard to read on the dark background.</p>
		<div class="callout">
			<p>Three firings this month, all to cone 6.</p>
			<table>
				<tr><td>Bisque</td><td>1,000 &deg;C</td><td>9 hours</td></tr>
				<tr><td>Glaze</td><td>1,222 &deg;C</td><td>11 hours</td></tr>
			</table>
		</div>

		<h2>Loading steps</h2>
		<p class="caption">Each list item is green and italic. Standard: the table in the second step is green and italic too. Legacy: it is dark and upright.</p>
		<ol class="steps">
			<li>Stack the shelves on three posts.</li>
			<li>Space the pots by size:
				<table>
					<tr><td>Mugs</td><td>1 cm apart</td></tr>
					<tr><td>Bowls</td><td>2 cm apart</td></tr>
				</table>
			</li>
		</ol>

		<h2>From the kiln log</h2>
		<p class="caption">The quotation sets 12pt brown text, 1.8 apart and aligned right, and its table sets font-size: 75%. Standard: the table's cells are 9pt, brown, 1.8 apart and aligned right, and its th is aligned right. Legacy: they are 7.5pt, a quarter below the document's 10pt, dark, 1.2 apart and aligned left, and the th is centred.</p>
		<blockquote>
			The last load came out even from top to bottom.
			<table>
				<tr><th style="width: 40mm">Shelf</th><th style="width: 40mm">Cone</th></tr>
				<tr><td>Top<br>(near the lid)</td><td>6</td></tr>
			</table>
		</blockquote>

		<h2>Panel</h2>
		<p class="caption">The panel cell sets slate monospace text aligned right, 2 apart, and holds a nested table. Standard: the nested cells are slate monospace, aligned right and 2 apart, and its th is aligned right. Legacy: they are in the body's dark serif, aligned right and 1.2 apart, and its th is centred.</p>
		<table>
			<tr><td class="panel">Cone chart
				<table>
					<tr><th style="width: 40mm">Cone</th><th style="width: 40mm">Temperature</th></tr>
					<tr><td>5<br>mid-fire</td><td>1,196 &deg;C</td></tr>
				</table>
			</td></tr>
		</table>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
