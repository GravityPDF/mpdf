<?php

namespace Mpdf;

/**
 * Records the text of every line as it is handed to the drawing code, which is after the
 * line-breaking pass has run and after the characters it worked from have been taken back out.
 *
 * Cell() is the first public seam past that point. Reading the drawn text back out of the PDF
 * instead would mean unescaping and stepping through UTF-16BE operands across each of the branches
 * Cell() writes them from, and telling those apart from the same bytes occurring inside an embedded
 * font. The signature is restated in full because a variadic tail only absorbs the parameters it
 * replaces from PHP 8.0, and warns about the declaration on every version before that.
 *
 * It counts the page-break-inside: avoid blocks put back as well, for a test that needs to know a block was laid out
 * twice.
 */
class TextRecordingMpdf extends UnwindCountingMpdf
{

	public $drawnText = [];

	/** The OpenType layout data each of those lines was drawn with, in the same order. */
	public $drawnOTLdata = [];

	/** The font family each of those lines was drawn in, in the same order. */
	public $drawnFontFamily = [];

	/** The font size, in points, each of those lines was drawn at, in the same order. */
	public $drawnFontSize = [];

	/** The page each of those lines was drawn on, its left and right edges and its top, in the same order. */
	public $drawnBoxes = [];

	/** The top of each of those lines, in the same order. */
	public $drawnY = [];

	/** The text colour each of those lines was drawn in, as the PDF operator that sets it, in the same order. */
	public $drawnColours = [];

	/**
	 * Where each of those lines was drawn, in the same order: in an HTML 'header' or 'footer', in a 'positioned' block
	 * WriteFixedPosHTML() writes, or '' in the flow
	 */
	public $drawnIn = [];

	function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
	{
		if (is_string($txt) && trim($txt) !== '') {
			$this->drawnText[] = $txt;
			$this->drawnOTLdata[] = $OTLdata;
			$this->drawnFontFamily[] = $this->FontFamily;
			$this->drawnFontSize[] = $this->FontSizePt;
			$this->drawnBoxes[] = [$this->page, $this->x, $this->x + $w, $this->y];
			$this->drawnY[] = $this->y;
			$this->drawnColours[] = $this->TextColor;
			$this->drawnIn[] = $this->whereDrawing();
		}

		return parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, $lcpaddingL, $lcpaddingR, $valign, $spanfill, $exactWidth, $OTLdata, $textvar, $lineBox);
	}

	/**
	 * WriteFixedPosHTML() raises the flags for a header and a footer both, to keep page breaks out of a positioned block
	 *
	 * @return string Where the line being drawn is: 'header', 'footer', 'positioned' or '' in the flow
	 */
	private function whereDrawing()
	{
		if ($this->writingHTMLheader && $this->writingHTMLfooter) {
			return 'positioned';
		}

		return $this->writingHTMLfooter ? 'footer' : ($this->writingHTMLheader ? 'header' : '');
	}

	/**
	 * @param int $i Which recorded Cell() call, from 0
	 *
	 * @return int[] The codepoints of the $i-th line drawn
	 */
	public function drawnCodepoints($i)
	{
		return $this->UTF8StringToArray($this->drawnText[$i], false);
	}

	/**
	 * @return array[] Each piece of text drawn, in order: [font family, its codepoints]
	 */
	public function drawnPieces()
	{
		$pieces = [];
		foreach (array_keys($this->drawnText) as $i) {
			$pieces[] = [$this->drawnFontFamily[$i], $this->drawnCodepoints($i)];
		}

		return $pieces;
	}

}
