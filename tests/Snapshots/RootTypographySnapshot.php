<?php

namespace Snapshots;

/**
 * A page styled as CSS frameworks style one: html set to 62.5% so that 1rem is ten pixels' worth, type sized in rem,
 * link colours for :link, :visited and :hover, and rules that start from :root. Written under each value of cssMode,
 * with captions saying what each draws.
 *
 * @group snapshot
 */
abstract class RootTypographySnapshot extends Snapshot
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
		return 'root-typography-' . $this->cssMode();
	}

	/**
	 * The components, each under a caption saying what the standard and the legacy mode draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			/* Reboot */
			:root { --brand: #0d6efd; color: #212529; }
			html { font-size: 62.5%; font-family: sans-serif; }
			body { font-size: 1.6rem; }
			h1 { font-size: 3.2rem; margin: 0 0 1rem 0; }
			h2 { font-size: 2.2rem; margin: 2rem 0 0.8rem 0; }
			p { margin: 0 0 0.8rem 0; }
			p.caption { font-size: 1.1rem; color: #6c757d; }
			small, .small { font-size: 1.2rem; }

			/* Links */
			a { color: #0d6efd; }
			a:link { color: #198754; }
			a:visited { color: #6f42c1; }
			a:hover, a:focus, a:active { color: #dc3545; text-decoration: none; }
			.nav a:not(:hover) { text-decoration: none; font-weight: bold; }

			/* Components */
			.lead { font-size: 2rem; color: #495057; }
			.card { border: 0.1rem solid #ced4da; padding: 1.2rem; font-size: 1.4rem; }
			.card .note { font-size: 0.8em; }
			.btn { font-size: 1.4rem; padding: 0.4rem 1.2rem; border: 0.1rem solid #0d6efd; color: #0d6efd; }
			a.btn:link { color: #ffffff; background-color: #0d6efd; }
			.btn:hover { background-color: #dc3545; }
			table { font-size: 1.4rem; border-collapse: collapse; }
			td { padding: 0.6rem 1.2rem; border-bottom: 0.1rem solid #dee2e6; }
			td.amount { font-size: 1.8rem; }

			/* Rules that start from the root */
			:root .alert { color: #842029; background-color: #f8d7da; padding: 0.8rem 1.2rem; }
			html body .badge { color: #ffffff; background-color: #6c757d; font-size: 1.2rem; }
			:root > .alert { color: #ff0000; }
		</style>

		<h1>Type sized in rem</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cssMode(); ?>. Standard: html is 62.5% of the 11pt default, 6.875pt, so 1rem is 6.875pt. body is 1.6rem, 11pt, and this heading 3.2rem, 22pt. Legacy: html is dropped and rem is read against body, which is itself 1.6 times the default, so every size in rem comes out 1.6 times as large and this heading is 56pt. Standard: the text is sans-serif from html and near-black from :root. Legacy: serif and black.</p>

		<h2>Text</h2>
		<p class="lead">A lead paragraph at 2rem. Standard: 13.75pt, grey. Legacy: 35.2pt, grey.</p>
		<p>Body text at 11pt in standard, 17.6pt in legacy. <small>Small print at 1.2rem: 8.25pt in standard, 21.1pt in legacy.</small></p>

		<h2>Links</h2>
		<p class="caption">a:link against a, :visited and :hover. Standard: the link is green and underlined, as no link is visited or hovered in a PDF. Legacy: it is blue, only the a rule applying.</p>
		<p><a href="#top">A link to the top</a></p>
		<p class="caption">.nav a:not(:hover). Standard: the links are green, bold and not underlined. Legacy: blue and underlined, the rule dropped.</p>
		<p class="nav"><a href="#one">First</a> <a href="#two">Second</a></p>
		<p class="caption">a.btn:link against .btn and .btn:hover. Standard: white on blue. Legacy: blue on white with a blue border, the :link and :hover rules dropped.</p>
		<p><a class="btn" href="#buy">Buy now</a></p>

		<h2>Card</h2>
		<p class="caption">.card at 1.4rem, and .note in it at 0.8em of that. Standard: 9.6pt and 7.7pt. Legacy: 24.6pt and 19.7pt.</p>
		<div class="card"><p>Card text.</p><p class="note">A note in the card.</p></div>

		<h2>Table</h2>
		<p class="caption">table at 1.4rem and td.amount at 1.8rem. Standard: 9.6pt and 12.4pt, rem read against html inside the table too. Legacy: 24.6pt and 44.4pt, rem read against the table's own size.</p>
		<table><tr><td>Consulting</td><td class="amount">1,200.00</td></tr><tr><td>Hosting</td><td class="amount">300.00</td></tr></table>

		<h2>From the root</h2>
		<p class="caption">:root .alert and html body .badge. Standard: the alert is dark red on pink, and the badge white on grey. Legacy: both are plain text, the rules dropped. :root &gt; .alert never matches, as .alert is in body: the alert is not bright red in either.</p>
		<div class="alert">An alert <span class="badge">New</span></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cssMode()]);

		$this->mpdf->WriteHTML($html);
	}

}
