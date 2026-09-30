<?php

namespace Snapshots;

/**
 * Attribute selectors with each operator and the i flag, and :lang() matching an inherited language, one selector to
 * a rule and one case to a caption.
 *
 * @group snapshot
 */
class AttributeSelectorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'attribute-selectors';
	}

	/**
	 * Cases each styled by one rule, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$png = __DIR__ . '/../data/img/List-Bullet.png';
		$jpeg = __DIR__ . '/../data/img/bg.jpg';

		ob_start();
		?>
		<html lang="en-GB">
		<head>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 3mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; }
			div.case p { margin: 0.5mm 0; }
			table { border-collapse: collapse; }
			td { border: 0.2mm solid #999999; padding: 0.5mm 2mm; }

			div.presence [data-flag] { color: #c00000; font-weight: bold; }
			div.equals p[data-status="paid"] { color: #0a7d32; font-weight: bold; }
			div.flag p[data-status="paid" i] { color: #0a7d32; font-weight: bold; }
			div.words p[data-tags~="urgent"] { color: #c00000; font-weight: bold; }
			div.hyphen p[lang|="en"] { color: #1f3a93; font-weight: bold; }
			div.links a[href^="https://"] { color: #0a7d32; }
			div.links a[href$=".pdf"] { color: #c00000; font-weight: bold; }
			div.links a[href*="example"] { background-color: #f9e79f; }
			div.html-case ol[type="a"] li { color: #c00000; }
			table.status td[data-status="overdue"] { background-color: #f5b7b1; }
			div.inherit p:lang(fr) { color: #1f3a93; font-style: italic; }
			div.document p:lang(en-GB) { color: #0a7d32; font-style: italic; }
			div.images img[src$=".png"] { border: 0.8mm solid #c00000; }
			div.images img[src$=".png"] + b { color: #c00000; }
			div.hidden [data-hidden="yes"] + p { color: #c00000; font-weight: bold; }
		</style>
		</head>
		<body>
		<h1>Attribute selectors and :lang()</h1>

		<h2>[data-flag]</h2>
		<p class="caption">The paragraph and the span carrying the attribute are red and bold, even with no value. The others stay black.</p>
		<div class="case presence">
			<p data-flag="yes">Paragraph with the attribute</p>
			<p data-flag>Paragraph with the attribute and no value</p>
			<p>Paragraph without it, but <span data-flag="1">a span with it</span></p>
		</div>

		<h2>p[data-status="paid"]</h2>
		<p class="caption">Only the paragraph whose status is exactly "paid" is green and bold. "Paid" with a capital and "paid in full" stay black.</p>
		<div class="case equals">
			<p data-status="paid">Status paid</p>
			<p data-status="Paid">Status Paid, with a capital</p>
			<p data-status="paid in full">Status paid in full</p>
		</div>

		<h2>p[data-status="paid" i]</h2>
		<p class="caption">With the i flag, "paid" and "PAID" are both green and bold. "unpaid" stays black.</p>
		<div class="case flag">
			<p data-status="paid">Status paid</p>
			<p data-status="PAID">Status PAID</p>
			<p data-status="unpaid">Status unpaid</p>
		</div>

		<h2>p[data-tags~="urgent"]</h2>
		<p class="caption">The paragraph with urgent as one of its tags is red and bold. The one tagged "urgently" stays black.</p>
		<div class="case words">
			<p data-tags="new urgent billing">Tags: new urgent billing</p>
			<p data-tags="urgently">Tags: urgently</p>
		</div>

		<h2>p[lang|="en"]</h2>
		<p class="caption">English and British English are blue and bold. "eng" stays black.</p>
		<div class="case hyphen">
			<p lang="en">lang en</p>
			<p lang="en-GB">lang en-GB</p>
			<p lang="eng">lang eng</p>
		</div>

		<h2>a[href^="https://"], a[href$=".pdf"] and a[href*="example"]</h2>
		<p class="caption">The secure link is green. The PDF link is red and bold. Both example.com links have a yellow background. The plain link keeps the default blue.</p>
		<div class="case links">
			<p><a href="https://example.com/">Secure example link</a></p>
			<p><a href="http://example.com/report.pdf">Example PDF</a></p>
			<p><a href="http://other.org/">Plain link</a></p>
		</div>

		<h2>ol[type="a"] li, an attribute HTML compares in any case</h2>
		<p class="caption">The items of the list with type="A" are red. The numbered list stays black.</p>
		<div class="case html-case">
			<ol type="A"><li>Lettered one</li><li>Lettered two</li></ol>
			<ol type="1"><li>Numbered one</li></ol>
		</div>

		<h2>td[data-status="overdue"]</h2>
		<p class="caption">Only the overdue cell is pink.</p>
		<table class="status">
			<tr><td data-status="paid">INV-1 paid</td><td data-status="overdue">INV-2 overdue</td><td data-status="draft">INV-3 draft</td></tr>
		</table>

		<h2>p:lang(fr), inherited from a block</h2>
		<p class="caption">The paragraphs inside the French block are blue and italic, one of them through a nested div. The German one stays black.</p>
		<div class="case inherit">
			<div lang="fr-CA"><p>Un paragraphe</p><div><p>Un autre paragraphe</p></div></div>
			<div lang="de"><p>Ein Absatz</p></div>
		</div>

		<h2>p:lang(en-GB), from the html tag</h2>
		<p class="caption">The document is British English: the first paragraph is green and italic. The one marked French stays black.</p>
		<div class="case document">
			<p>A paragraph in the document's language</p>
			<p lang="fr">Un paragraphe en français</p>
		</div>

		<h2>img[src$=".png"] and img[src$=".png"] + b</h2>
		<p class="caption">The PNG image, whose path has slashes in it, has a red border, and the bold text after it is red. The JPEG image has no border, and the bold text after it stays black.</p>
		<div class="case images">
			<p><img src="<?php echo $png ?>" style="width: 6mm; height: 6mm"> <b>After the PNG</b></p>
			<p><img src="<?php echo $jpeg ?>" style="width: 6mm; height: 6mm"> <b>After the JPEG</b></p>
		</div>

		<h2>[data-hidden="yes"] + p, after an element hidden with display: none</h2>
		<p class="caption">The hidden blocks show nothing. The paragraph after the one marked "yes" is red and bold; the paragraph after the one marked "no" stays black.</p>
		<div class="case hidden">
			<div data-hidden="yes" style="display: none"><p>Hidden</p></div>
			<p>After the block marked yes</p>
			<div data-hidden="no" style="display: none"><p>Hidden</p></div>
			<p>After the block marked no</p>
		</div>

		</body>
		</html>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
