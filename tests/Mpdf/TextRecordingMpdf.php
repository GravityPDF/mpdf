<?php

namespace Mpdf;

/**
 * Records the text of every line as it is handed to the drawing code, which is after the
 * line-breaking pass has run.
 *
 * Cell() is the first public seam past that point. Its signature is restated in full so that the
 * override matches the parent on PHP 5.6 to 7.4.
 */
class TextRecordingMpdf extends Mpdf
{

	/** The text of each line drawn, in the order it is drawn in. */
	public $drawnText = [];

	/** The font family of each line drawn, by the same index as the text. */
	public $drawnFontFamily = [];

	/** The font size, in points, of each line drawn, by the same index as the text. */
	public $drawnFontSize = [];

	/** The operator that sets the text colour of each line drawn, by the same index as the text. */
	public $drawnColours = [];

	/**
	 * Records the text of a line before drawing it
	 *
	 * {@inheritdoc}
	 */
	function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
	{
		if (is_string($txt) && trim($txt) !== '') {
			$this->drawnText[] = $txt;
			$this->drawnFontFamily[] = $this->FontFamily;
			$this->drawnFontSize[] = $this->FontSizePt;
			$this->drawnColours[] = $this->TextColor;
		}

		return parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, $lcpaddingL, $lcpaddingR, $valign, $spanfill, $exactWidth, $OTLdata, $textvar, $lineBox);
	}

	/**
	 * @return array[] Each piece of text drawn, in order: [font family, its codepoints]
	 */
	public function drawnPieces()
	{
		$pieces = [];
		foreach ($this->drawnText as $i => $text) {
			$pieces[] = [$this->drawnFontFamily[$i], $this->UTF8StringToArray($text, false)];
		}

		return $pieces;
	}

}
