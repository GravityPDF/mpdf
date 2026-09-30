<?php

namespace Mpdf;

/**
 * Records, beside the text TextRecordingMpdf records, the style, text variants and line height each line is drawn with.
 */
class FontStateRecordingMpdf extends TextRecordingMpdf
{

	/** The font style ('', 'B', 'I' or 'BI') each line was drawn in, in the same order. */
	public $drawnFontStyle = [];

	/** The TextVars flags each line was drawn with, in the same order. */
	public $drawnTextvar = [];

	/** The height of each line, in mm, in the same order. */
	public $drawnLineHeight = [];

	/**
	 * Records the state a line is drawn with before drawing it
	 *
	 * @see TextRecordingMpdf::Cell() for why the signature is restated in full
	 */
	function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
	{
		if (is_string($txt) && trim($txt) !== '') {
			$this->drawnFontStyle[] = $this->FontStyle;
			$this->drawnTextvar[] = $textvar;
			$this->drawnLineHeight[] = $h;
		}

		return parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, $lcpaddingL, $lcpaddingR, $valign, $spanfill, $exactWidth, $OTLdata, $textvar, $lineBox);
	}

}
