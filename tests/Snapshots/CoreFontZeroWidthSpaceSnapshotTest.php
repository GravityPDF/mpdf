<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class CoreFontZeroWidthSpaceSnapshotTest extends Snapshot
{
	const ZWSP = "\xe2\x80\x8b";

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'core-font-zero-width-space';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$word = str_repeat('abcdefghij', 6);

		ob_start();
		?>
		<style>
			table { line-height: 1.2; }
			p.note { color: #606060; }
			.box { border: 0.2mm solid #b0b0b0; padding: 1mm; margin-bottom: 2mm; }
			td { border: 0.2mm solid #808080; padding: 1mm; vertical-align: top; }
			.upper { text-transform: uppercase; }
			.small-caps { font-variant: small-caps; }
		</style>

		<h1>mPDF</h1>
		<h2>A zero-width space in core fonts</h2>

		<p class="note">This document is written with the PDF core fonts, whose windows-1252 encoding has no
			code for a zero-width space (U+200B). Every sample holds at least one. None of them may draw a
			question mark or any gap of its own, and where a line has to break, it breaks at the zero-width
			space rather than in the middle of a word.</p>

		<h3>Two long words</h3>

		<p class="note">Two 60-letter words joined by a zero-width space, in a box 40mm wide. The first word
			fills three lines and the second starts on a line of its own.</p>

		<div class="box" style="width: 40mm"><?php echo $word; ?>&#8203;<?php echo $word; ?></div>

		<h3>A path with a break after every slash</h3>

		<p class="note">The same path twice, in boxes 45mm wide. The first has no zero-width spaces and is
			cut wherever the line runs out. The second has one after every slash and breaks only there.</p>

		<div class="box" style="width: 45mm">/usr/local/share/applications/documentation/reference/manual/index.html</div>
		<div class="box" style="width: 45mm">/&#8203;usr/&#8203;local/&#8203;share/&#8203;applications/&#8203;documentation/&#8203;reference/&#8203;manual/&#8203;index.html</div>

		<h3>No width of its own</h3>

		<p class="note">Each pair of lines is the same word, drawn without and then with zero-width spaces
			inside it. Both lines of a pair have to be the same length and line up letter for letter.</p>

		<div class="box" style="width: 80mm">
			Documentation<br />
			Docu&#8203;men&#8203;ta&#8203;tion
		</div>
		<div class="box" style="width: 80mm">
			<b>Bold</b><i>Italic</i><u>Underlined</u><br />
			<b>Bold&#8203;</b>&#8203;<i>&#8203;Italic</i>&#8203;<u>Under&#8203;lined</u>
		</div>
		<div class="box upper" style="width: 80mm">
			uppercase<br />
			upper&#8203;case
		</div>
		<div class="box small-caps" style="width: 80mm">
			Small Capitals<br />
			Small Cap&#8203;itals
		</div>

		<h3>Justified text</h3>

		<p class="note">A justified paragraph whose long compound words are joined by zero-width spaces. The
			spaces between words stretch, but a zero-width space never does, and the lines break at the zero-width
			spaces where a word would not fit.</p>

		<p class="box" style="width: 70mm; text-align: justify">The
			Donau&#8203;dampf&#8203;schiff&#8203;fahrts&#8203;gesellschaft ran steamers on the Danube, and its
			Kapitäns&#8203;mützen&#8203;abzeichen&#8203;hersteller made the badges on the captains' caps, while the
			Rind&#8203;fleisch&#8203;etikettierungs&#8203;überwachungs&#8203;aufgaben&#8203;übertragungs&#8203;gesetz
			governed the labelling of beef.</p>

		<h3>Beside a soft hyphen and a no-break space</h3>

		<p class="note">A soft hyphen draws a hyphen only where it breaks the line and a no-break space draws as
			a space that never breaks. Neither may be disturbed by a zero-width space next to it.</p>

		<div class="box" style="width: 30mm">extra&shy;ordinary&#8203;circum&shy;stances</div>
		<div class="box" style="width: 30mm">keep&nbsp;together&#8203;keep&nbsp;together&#8203;keep&nbsp;together</div>

		<h3>In a table</h3>

		<p class="note">The column is as wide as the widest of the words either side of each zero-width space.
			Measured as one run the text would be wider than the page, and the table would shrink it to fit.</p>

		<table>
			<tr>
				<td><?php echo $word; ?>&#8203;<?php echo $word; ?></td>
				<td>Eleven point text beside it</td>
			</tr>
		</table>

		<h3>In a list</h3>

		<ul style="width: 50mm">
			<li>alpha&#8203;beta&#8203;gamma&#8203;delta&#8203;epsilon&#8203;zeta&#8203;eta&#8203;theta</li>
			<li>iota&#8203;kappa&#8203;lambda&#8203;mu&#8203;nu&#8203;xi&#8203;omicron&#8203;pi</li>
		</ul>

		<h3>Head&#8203;ing</h3>

		<p class="note">The heading above holds a zero-width space. Its bookmark reads "Heading", with nothing
			between the two halves.</p>

		<h3>A unit separator in the source</h3>

		<p class="note">A U+001F is not the zero-width space that core-font text carries in its place: it is
			dropped, so it neither draws nor gives the line somewhere to break. The box is too narrow for the
			word, which is cut where the line runs out.</p>

		<div class="box" style="width: 12mm">aaaa<?php echo "\x1f"; ?>bbbb</div>

		<h3>In form fields</h3>

		<p class="note">Field text becomes the field's value, so a zero-width space is left out of it.</p>

		<select name="choice">
			<option value="one">First&#8203;choice</option>
			<option value="two" selected="selected">Second&#8203;choice</option>
		</select>

		<textarea name="notes" cols="20" rows="2">Zero&#8203;width</textarea>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['mode' => 'c', 'h2bookmarks' => ['H1' => 0, 'H2' => 1, 'H3' => 2]]);
		$this->mpdf->WriteHTML($html);

		$this->writeDirectly();
	}

	/**
	 * A page of text written without WriteHTML(), which converts it to the core fonts' encoding on its own
	 *
	 * @return void
	 */
	private function writeDirectly()
	{
		$mpdf = $this->mpdf;

		$mpdf->AddPage();
		$mpdf->WriteHTML('<h3>Written directly</h3><p class="note">Each line below is written straight to the page rather than through WriteHTML(). The watermark, WriteText(), WriteCell(), AutosizeText() and the shaded box draw no question mark; MultiCell() breaks at the zero-width spaces in a box 40mm wide; the SVG text and the text around the circle draw nothing between their halves.</p>');

		$mpdf->SetWatermarkText('DRA' . self::ZWSP . 'FT');
		$mpdf->showWatermarkText = true;

		$mpdf->SetFont('Helvetica', '', 11);
		$y = $mpdf->y + 5;
		$mpdf->WriteText(15, $y, 'Write' . self::ZWSP . 'Text');

		$mpdf->SetXY(15, $y + 5);
		$mpdf->WriteCell(80, 8, 'Write' . self::ZWSP . 'Cell, centred', 1, 1, 'C');

		$mpdf->SetX(15);
		$mpdf->MultiCell(40, 5, 'Multi' . self::ZWSP . 'Cell' . self::ZWSP . 'breaks' . self::ZWSP . 'at' . self::ZWSP . 'every' . self::ZWSP . 'zero' . self::ZWSP . 'width' . self::ZWSP . 'space', 1);
		$mpdf->Ln(6);

		$mpdf->SetX(15);
		$mpdf->AutosizeText('Autosize' . self::ZWSP . 'Text', 80, 'Helvetica', '', 24);
		$mpdf->Ln(12);

		$mpdf->Shaded_box('Shaded' . self::ZWSP . 'box', 'Helvetica', '', 14, '80mm');
		$mpdf->Ln(6);

		$mpdf->WriteHTML('<svg xmlns="http://www.w3.org/2000/svg" width="80mm" height="12mm" viewBox="0 0 300 45"><text x="0" y="30" font-family="Helvetica" font-size="28">SVG' . self::ZWSP . 'text</text></svg>');
		$mpdf->WriteHTML('<textcircle r="20mm" top-text="Circular' . self::ZWSP . 'Text" bottom-text="Bottom' . self::ZWSP . 'Text" style="font-size: 14pt" />');
	}
}
