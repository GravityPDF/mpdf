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
	 * The left and right margins of the current page: those it was made with, or the defaults while it is being made,
	 * mirrored on an even page
	 *
	 * A page keeps its margins when the defaults change after it is made, as they do when the document turns right to
	 * left.
	 *
	 * @return float[]
	 */
	public function pageSideMargins()
	{
		if (isset($this->pageDim[$this->page]['sideMargins'])) {
			list($left, $right) = $this->pageDim[$this->page]['sideMargins'];
		} else {
			$left = $this->DeflMargin;
			$right = $this->DefrMargin;
		}

		if (!$this->marginsForcedPortrait() && $this->mirrorMargins && $this->page % 2 == 0) {
			return [$right, $left];
		}

		return [$left, $right];
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
		$outerLeft = $this->outerLeftMargin();

		$this->AddPage($this->CurOrientation);

		$shift = $this->MarginCorrection + $this->outerLeftMargin() - $outerLeft;
		$this->x = $x + $shift;
		$this->SetSpacing($charspacing, $ws);

		return $shift;
	}

	/**
	 * How far the block being written sits in from the left of the page area. It changes over a page turn when the page
	 * area changes width under a block with a set width whose left margin takes up slack: a centred or right-aligned
	 * block, a right to left one, or a right float. The line carried over the turn moves with it.
	 *
	 * @return float
	 */
	public function outerLeftMargin()
	{
		return isset($this->blk[$this->blklvl]['outer_left_margin']) ? $this->blk[$this->blklvl]['outer_left_margin'] : 0;
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
	 * @return float How much wider the block being written became, less than 0 where it narrowed
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

		// A block with a set width keeps it. The growth goes into the margins that took up its slack when BlockTag opened
		// it: the left, the right, or half to each. Every block inside it moves with it.
		$slackMargin = null;
		$leftShare = 0;
		for ($bl = 1; $bl <= $this->blklvl; $bl++) {
			if (!isset($this->blk[$bl]['width'])) {
				continue;
			}

			$blk = &$this->blk[$bl];

			if (!$slackMargin && !empty($blk['css_set_width'])) {
				$slackMargin = isset($blk['slack_margin']) ? $blk['slack_margin'] : 'right';
				$leftShare = $slackMargin === 'both' ? $growth / 2 : ($slackMargin === 'left' ? $growth : 0);
				$blk['margin_left'] += $leftShare;
				$blk['margin_right'] += $growth - $leftShare;
			}

			if (!$slackMargin) {
				$blk['width'] += $growth;
				$blk['inner_width'] += $growth;
			} else {
				$blk['outer_left_margin'] += $leftShare;
				$blk['outer_right_margin'] += $growth - $leftShare;
				if (isset($blk['x0'])) {
					$blk['x0'] += $leftShare;
				}
			}
			unset($blk);
		}

		return $slackMargin ? 0 : $growth;
	}

	/**
	 * Turn to a page that already exists, forwards or back, into the page area it was made with. Text beside a float
	 * needs this: it goes back to the page the float started on, and on over the pages the float made.
	 *
	 * @param int $page
	 *
	 * @return float How much wider the block being written became, less than 0 where it narrowed
	 */
	public function turnToPage($page)
	{
		$previousArea = $this->pageArea();

		$this->page = $page;
		$this->ResetMargins();

		if ($this->pageArea() == $previousArea) {
			return 0;
		}

		$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;

		return $this->moveIntoPageArea($previousArea);
	}

	/**
	 * Widen or narrow the rest of the flowing block with the block being written on the new page
	 *
	 * @param float $growth How much wider the block became, as moveIntoPageArea() gave it
	 */
	public function resizeFlowingBlock($growth)
	{
		if ($growth && $this->divwidth && !$this->flowingBlockAttr['is_table']) {
			$this->divwidth += $growth;
			$this->flowingBlockAttr['width'] += $growth * Mpdf::SCALE;
		}
	}

}
