<?php

namespace Mpdf;

/**
 * Carries text across a page break when the next page has different side margins
 *
 * Text is set in the page area, the space between the page's left and right margins or, in columns, in a column box.
 * The @page :first, :left and :right rules can give pages different side margins, so the page area can move or
 * change width from one page to the next. These methods work out the page area of each page, and at a page break
 * move the text, and any blocks it is inside, into the new one.
 *
 * They read and write Mpdf's layout state directly, which is why they are a trait of Mpdf and not a separate class.
 *
 * @internal
 */
trait PageAreas
{

	/**
	 * The left and right margins of the current page: mirrored on an even page
	 *
	 * @return float[]
	 */
	public function pageSideMargins()
	{
		if (!$this->marginsForcedPortrait() && $this->mirrorMargins && $this->page % 2 == 0) {
			return [$this->DefrMargin, $this->DeflMargin];
		}

		return [$this->DeflMargin, $this->DefrMargin];
	}

	/**
	 * Whether a landscape page in a portrait document keeps the portrait page's margins, turned with it
	 *
	 * @return bool
	 */
	public function marginsForcedPortrait()
	{
		return ($this->forcePortraitHeaders || $this->forcePortraitMargins) && $this->DefOrientation == 'P' && $this->CurOrientation == 'L';
	}

	/**
	 * Turn the page before measuring a line that will not fit, when pages of this flow have different page areas, so
	 * the line is measured against the page area it is set in. Otherwise the page turns once the line has been set.
	 *
	 * @param float $lineHeight The least height the line takes, with the top margin, border and padding of a block it opens
	 *
	 * @return float|false How far text moves across to the new page, or false if the page was not turned
	 */
	public function turnPageAheadOfLine($lineHeight)
	{
		// A line that only moves to the next column keeps its width
		if ($this->InFooter
			|| $this->y + $lineHeight <= $this->PageBreakTrigger
			|| ($this->ColActive && $this->CurrCol < $this->NbCol - 1)
			|| !$this->page_box['using']
			|| !$this->cssManager->pseudoPagesSetSideMargins($this->page_box['current'])
			|| !$this->AcceptPageBreak()
		) {
			return false;
		}

		$shift = $this->turnPageKeepingPlace();
		if ($this->ColActive) {
			$step = $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
			$shift += $step;
			$this->x += $step;
		}

		return $shift;
	}

	/**
	 * Turn to the next page, carrying on from where x stands, with the word and character spacing in use
	 *
	 * @return float How far x moved across to the page area of the new page
	 */
	public function turnPageKeepingPlace()
	{
		$x = $this->x;
		$ws = $this->ws;
		$charspacing = $this->charspacing;
		$this->ResetSpacing();

		$this->AddPage($this->CurOrientation);

		$this->x = $x + $this->MarginCorrection;
		$this->SetSpacing($charspacing, $ws);

		return $this->MarginCorrection;
	}

	/**
	 * The left edge and width of the page area of the current page: the space between its side margins or, in columns,
	 * its last column
	 *
	 * @return float[]
	 */
	public function pageArea()
	{
		if ($this->ColActive) {
			return [$this->ColL[$this->NbCol - 1], $this->ColWidth];
		}

		list($left, $right) = $this->pageSideMargins();

		return [$left, $this->w - $left - $right];
	}

	/**
	 * Move what carries on from the last page into the page area of the new one
	 *
	 * MarginCorrection becomes how far text moves across; in columns, the caller then steps from the last column to the
	 * first with ChangeColumn. Open blocks are widened or narrowed with the page area, except that a block with a set
	 * width keeps it.
	 *
	 * @param float[] $previousArea The page area of the last page, as pageArea() gave it
	 *
	 * @return float The width the block being written gained, or 0
	 */
	public function moveIntoPageArea($previousArea)
	{
		list($left, $width) = $this->pageArea();
		$shift = $left - $previousArea[0];

		// Where the page area has not moved keep ResetMargins()' value, so an unchanged layout writes the same bytes
		$unmoved = $this->mirrorMargins ? $this->MarginCorrection : 0;
		$this->MarginCorrection = abs($shift - $unmoved) < 0.0001 ? $unmoved : $shift;

		$growth = $width - $previousArea[1];
		if (abs($growth) < 0.0001) {
			return 0;
		}

		$gained = 0;
		$taken = 0; // The growth a block with a set width, and every block inside it, puts in its right margin
		for ($bl = 1; $bl <= $this->blklvl; $bl++) {
			if (!isset($this->blk[$bl]['width'])) {
				continue;
			}

			$blk = &$this->blk[$bl];
			$blk['outer_right_margin'] += $taken;

			if (!empty($blk['css_set_width']) && $taken != $growth) {
				$blk['margin_right'] += $growth - $taken;
				$blk['outer_right_margin'] += $growth - $taken;
				$taken = $growth;
			}

			$gained = $growth - $taken;
			$blk['width'] += $gained;
			$blk['inner_width'] += $gained;
			unset($blk);
		}

		return $gained;
	}

}
