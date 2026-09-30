<?php

namespace Mpdf;

/**
 * Records, besides the text TextRecordingMpdf records, the colours the borders and shadows of a document are painted
 * in, and whether each piece of text can be seen
 */
class PaintRecordingMpdf extends TextRecordingMpdf
{

	/** The colour of each side of a border that is drawn, as the PDF operator that sets it, in the order drawn. */
	public $drawnBorders = [];

	/** The colour of each box-shadow of a block that is painted, as the PDF operator that sets it. */
	public $drawnBoxShadows = [];

	/** The colours of the text-shadows of each line of text drawn, as PDF operators, in the same order as drawnText. */
	public $drawnTextShadows = [];

	/** Whether each line of text drawn can be seen, in the same order as drawnText: false for transparent text. */
	public $drawnVisible = [];

	/**
	 * Records each side of a border that is drawn, with its colour
	 *
	 * @param array|null $side
	 *
	 * @return bool
	 */
	protected function drawsBorderSide($side)
	{
		$draws = parent::drawsBorderSide($side);
		if ($draws) {
			$this->drawnBorders[] = $this->SetDColor($side['c'], true);
		}

		return $draws;
	}

	/**
	 * Records the colours of the block's box-shadows before painting it
	 *
	 * @param string $divider
	 * @param int $blockstate
	 * @param int $blvl
	 */
	function PaintDivBB($divider = '', $blockstate = 0, $blvl = 0)
	{
		$level = $blvl ? $blvl : $this->blklvl;
		if (!empty($this->blk[$level]['box_shadow'])) {
			foreach ($this->blk[$level]['box_shadow'] as $shadow) {
				$this->drawnBoxShadows[] = $this->SetFColor($shadow['col'], true);
			}
		}

		return parent::PaintDivBB($divider, $blockstate, $blvl);
	}

	/**
	 * Records the text-shadows and the visibility of each line of text, as TextRecordingMpdf records its text
	 */
	function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
	{
		if (is_string($txt) && trim($txt) !== '') {
			$shadows = [];
			foreach ($this->textshadow ? $this->textshadow : [] as $shadow) {
				$shadows[] = $this->SetTColor($shadow['col'], true);
			}
			$this->drawnTextShadows[] = $shadows;
			$this->drawnVisible[] = empty($this->textparam['transparent']);
		}

		return parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, $lcpaddingL, $lcpaddingR, $valign, $spanfill, $exactWidth, $OTLdata, $textvar, $lineBox);
	}

}
