<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * List markers in the counter styles mPDF had no conversion for, string markers, <ol reversed>, <li value>, the
 * list-item counter's counter-reset, counter-increment and counter-set, and the quotation marks of <q>, each under a
 * caption saying what a browser draws
 *
 * @group snapshot
 */
class ListMarkersQuotesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'list-markers-quotes';
	}

	/**
	 * The cases, each with a caption saying what should be drawn, grouped under headings
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			'Counter styles' => [
				['<ol style="list-style-type: decimal-leading-zero"><li>one</li><li>two</li></ol>', 'decimal-leading-zero: 01. and 02.'],
				['<ol style="list-style-type: upper-greek"><li>one</li><li>two</li><li>three</li></ol>', 'upper-greek: Α. Β. Γ.'],
				['<ol start="1984" style="list-style-type: armenian"><li>one</li></ol>', 'armenian from 1984: ՌՋՁԴ.'],
				['<ol style="list-style-type: lower-armenian"><li>one</li><li>two</li></ol>', 'lower-armenian: ա. բ.'],
				['<ol start="19" style="list-style-type: georgian"><li>one</li></ol>', 'georgian from 19: ით.'],
				['<ol style="list-style-type: hiragana; font-family: sun-exta"><li>one</li><li>two</li></ol>', 'hiragana: <span class="ja">あ、</span> and <span class="ja">い、</span>'],
				['<ol style="list-style-type: katakana-iroha; font-family: sun-exta"><li>one</li><li>two</li></ol>', 'katakana-iroha: <span class="ja">イ、</span> and <span class="ja">ロ、</span>'],
				['<ul style="list-style-type: disclosure-closed"><li>closed</li></ul><ul style="list-style-type: disclosure-open"><li>open</li></ul>', 'disclosure-closed then disclosure-open: a triangle pointing right, then one pointing down.'],
				['<ol dir="rtl" style="list-style-type: upper-greek"><li>אחת</li><li>שתיים</li></ol>', 'Right to left, upper-greek: .Α and .Β at the right of the items.'],
			],
			'String markers' => [
				['<ul style="list-style-type: \'→ \'"><li>an arrow</li></ul>', 'An arrow and a space before the item.'],
				['<ul style="list-style: \'Note: \' inside"><li>inside the item</li></ul>', '&quot;Note: &quot; with a capital N, inside the item.'],
			],
			'Counting' => [
				['<ol reversed><li>three</li><li>two</li><li>one</li></ol>', 'reversed: 3. 2. 1.'],
				['<ol reversed start="10"><li>ten</li><li>nine</li></ol>', 'reversed start="10": 10. 9.'],
				['<ol><li value="7">seven</li><li>eight</li></ol>', 'value="7": 7. 8.'],
				['<ol style="counter-reset: list-item 4"><li>five</li></ol>', 'counter-reset: list-item 4: 5.'],
				['<ol class="by-two"><li>two</li><li>four</li></ol>', 'counter-increment: list-item 2 on the items: 2. 4.'],
				['<ol><li>one</li><li style="counter-set: list-item 20">twenty</li><li>twenty-one</li></ol>', 'counter-set: list-item 20 on the second item: 1. 20. 21.'],
				['<table border="1"><tr><td><ol reversed style="list-style-type: upper-greek"><li>beta</li><li>alpha</li></ol></td></tr></table>', 'In a table cell, reversed upper-greek: Β. then Α.'],
			],
			'Quotation marks' => [
				['<p><q>To be, or <q>not</q> to be</q></p>', 'English: “To be, or ‘not’ to be”'],
				['<p lang="de"><q>Sein oder <q>Nichtsein</q></q></p>', 'German: „Sein oder ‚Nichtsein‘“'],
				['<p lang="fr"><q>Être</q></p>', 'French: «Être»'],
				['<p style="quotes: \'[\' \']\'"><q>bracketed</q></p>', 'quotes: \'[\' \']\': [bracketed]'],
				['<p style="quotes: none"><q>no marks</q></p>', 'quotes: none: no marks'],
				['<p><q style="color: #cc0000">red</q></p>', 'A red quotation: the marks are red too.'],
			],
		];
	}

	/**
	 * Each case under its caption
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 9pt; font-family: dejavusans; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p.caption { margin: 2mm 0 0.5mm 0; color: #555555; font-style: italic; }
			ol, ul, p { margin-top: 0; margin-bottom: 0; }
			.by-two li { counter-increment: list-item 2; }
			.ja { font-family: sun-exta; }
		</style>
		<h1>List markers and quotation marks</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p class="caption">' . $case[1] . '</p>' . $case[0];
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
