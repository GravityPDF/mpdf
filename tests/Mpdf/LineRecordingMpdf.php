<?php

namespace Mpdf;

/**
 * Records every straight line Line() draws, as a horizontal rule and the borders of a table or an image are drawn,
 * with the colour and width it is drawn in, alongside the text TextRecordingMpdf records
 */
class LineRecordingMpdf extends TextRecordingMpdf
{

	/**
	 * Each line drawn, in order: its page, its ends [x1, y1, x2, y2] in millimetres, the PDF operator that set its
	 * colour and its width in millimetres
	 *
	 * @var array[]
	 */
	public $drawnLines = [];

	/**
	 * @param float $x1
	 * @param float $y1
	 * @param float $x2
	 * @param float $y2
	 *
	 * @return void
	 */
	function Line($x1, $y1, $x2, $y2)
	{
		$this->drawnLines[] = ['page' => $this->page, 'ends' => [$x1, $y1, $x2, $y2], 'colour' => $this->DrawColor, 'width' => $this->LineWidth];

		parent::Line($x1, $y1, $x2, $y2);
	}
}
