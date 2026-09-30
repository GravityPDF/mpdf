<?php

namespace Snapshots;

/**
 * A page styled the way a CSS framework and a theme style one: components, utility classes, ids, and descendant and
 * child rules that compete for the same elements on specificity and source order. Written under each value of
 * cssMode, with captions saying what each cascade draws.
 *
 * @group snapshot
 */
abstract class FrameworkCascadeSnapshot extends Snapshot
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
		return 'framework-cascade-' . $this->cascade();
	}

	/**
	 * The components, each under a caption saying what the standard and the legacy cascade draw
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			/* Base */
			body { font-family: sans-serif; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 4mm 0 1mm 0; color: #000000; }
			p.caption { font-size: 7.5pt; color: #6c757d; margin: 0 0 1.5mm 0; }
			article p { font-size: 9pt; color: #343a40; margin: 1mm 0; }

			/* Components */
			.navbar { background-color: #212529; padding: 2mm 3mm; }
			.navbar > .container a { color: #adb5bd; text-decoration: none; }
			#brand { color: #ffc107; font-weight: bold; }

			.card { border: 0.3mm solid #ced4da; padding: 2mm 3mm; margin-bottom: 2mm; }
			.card h5 { font-size: 11pt; color: #212529; margin: 0 0 1mm 0; }
			h5.card-title { color: #0d6efd; }
			.lead { font-size: 12pt; color: #6c757d; }

			.nav .nav-link { color: #0d6efd; }
			#sidebar a { color: #6f42c1; font-weight: bold; }

			.list-group { margin: 0; padding: 0; list-style-type: none; }
			.list-group-item { padding: 1mm 2mm; border: 0.2mm solid #dee2e6; color: #212529; background-color: #ffffff; }
			.list-group .list-group-item { background-color: #f8f9fa; }
			.list-group-item.active { color: #ffffff; background-color: #0d6efd; }

			table.table { border-collapse: collapse; width: 100%; }
			.table td, .table th { padding: 1mm 2mm; border-bottom: 0.2mm solid #dee2e6; text-align: left; }
			.table-striped tr:nth-child(odd) td { background-color: #f2f2f2; }
			.title { color: #0d6efd; font-weight: bold; }

			/* Utilities, last as frameworks write them */
			td.text-end, th.text-end { text-align: right; }
			.muted { color: #adb5bd; }
		</style>

		<h1>A framework's stylesheet</h1>
		<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>.</p>

		<h2>Navbar</h2>
		<p class="caption">#brand against .navbar &gt; .container a. Standard: "Brand" is yellow and bold, the id beating the child rule. Legacy: the child rule is dropped, as mPDF v7 read no child combinator, so "Brand" is yellow and "Home" and "Docs" are blue, all three underlined as links are by default.</p>
		<div class="navbar"><div class="container"><a id="brand" href="#top">Brand</a> <a href="#home">Home</a> <a href="#docs">Docs</a></div></div>

		<h2>Card</h2>
		<p class="caption">h5.card-title against .card h5, as heavy and written later. Standard: the title is blue. Legacy: it is black, the descendant rule coming last.</p>
		<p class="caption">.lead against article p. Standard: the lead paragraph is large and grey, a class outweighing two tags. Legacy: it is the size and colour of the paragraph after it.</p>
		<div class="card">
			<h5 class="card-title">Card title</h5>
			<article><p class="lead">A lead paragraph, set larger than the text after it.</p><p>The body text of the card.</p></article>
		</div>

		<h2>Sidebar navigation</h2>
		<p class="caption">#sidebar a against .nav .nav-link. Standard: the links are purple and bold, an id ancestor outweighing two classes. Legacy: they are blue and bold, the rule lifted from the nearer .nav coming last.</p>
		<div id="sidebar"><ul class="nav"><li><a class="nav-link" href="#one">First link</a></li><li><a class="nav-link" href="#two">Second link</a></li></ul></div>

		<h2>List group</h2>
		<p class="caption">.list-group-item.active against .list-group .list-group-item, as heavy and written later. Standard: the active item is white on blue. Legacy: it is white on light grey, the descendant rule's background coming last, so its text all but disappears.</p>
		<ul class="list-group">
			<li class="list-group-item">An item</li>
			<li class="list-group-item active">The active item</li>
			<li class="list-group-item">Another item</li>
		</ul>

		<h2>Table</h2>
		<p class="caption">td.text-end against .table td, as heavy and written later. Standard: the amounts are right-aligned. Legacy: they are left-aligned, the descendant rule coming last. "Hosting", the third row counting the header, is striped grey in both.</p>
		<table class="table table-striped">
			<tr><th>Item</th><th class="text-end">Amount</th></tr>
			<tr><td>Consulting</td><td class="text-end">1,200.00</td></tr>
			<tr><td>Hosting</td><td class="text-end">300.00</td></tr>
			<tr><td>Support</td><td class="text-end">150.00</td></tr>
		</table>

		<h2>Utilities and components</h2>
		<p class="caption">.muted, written after .title. Standard: "Muted title" is light grey and bold. Legacy: it is blue and bold, classes applying in alphabetical order.</p>
		<p class="title muted">Muted title</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
