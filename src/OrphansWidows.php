<?php

namespace Mpdf;

/**
 * Lays out the lines a block holds in its text buffer so that a page break leaves at least `orphans` of them at the
 * foot of a page and carries at least `widows` onto the next, in standard CSS mode.
 *
 * The lines are laid out once while the page each lands on is recorded. Where a break falls short, the document is put
 * back as it was, through the StateSnapshot seam page-break-inside: avoid uses, and the lines laid out again with a
 * fresh page started at a chosen line: the first one, which moves the block's lines whole, or the one that carries
 * `widows` lines over. Each block takes at most MAX_PASSES layouts.
 *
 * Not in legacy mode, in tables, in columns, while output is buffered for a header or footer, inside a block kept
 * together by page-break-inside: avoid while it is measured, or while a heading is kept with its table
 *
 * @internal
 */
class OrphansWidows
{

	/**
	 * What both properties are where no stylesheet sets them, as in CSS
	 */
	const INITIAL = 2;

	/**
	 * The most times one block's lines are laid out: once to measure them, once with a fresh page at the first line
	 * where too few were left at the foot of a page, and once more with a fresh page where too few were carried over
	 */
	const MAX_PASSES = 3;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var int
	 */
	private $orphans;

	/**
	 * @var int
	 */
	private $widows;

	/**
	 * @var int[] The indexes of the lines to start a fresh page at
	 */
	private $breaks = [];

	/**
	 * @var int[] The page each line laid out so far landed on, by its index
	 */
	private $pages = [];

	/**
	 * @var int The page the block's lines start from
	 */
	private $startPage;

	/**
	 * @var bool Whether anything was drawn on the page above the first line, so that moving that line to a fresh page
	 * leaves something behind
	 */
	private $belowContent;

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Lays out and draws the current block's text buffer, as Mpdf::printbuffer() does, keeping to the block's orphans
	 * and widows where they apply
	 *
	 * @param int $blockstate As printbuffer() takes it
	 *
	 * @return void
	 */
	public function printBuffer($blockstate)
	{
		$mpdf = $this->mpdf;
		$blk = $mpdf->blk[$mpdf->blklvl];
		$this->orphans = isset($blk['orphans']) ? $blk['orphans'] : self::INITIAL;
		$this->widows = isset($blk['widows']) ? $blk['widows'] : self::INITIAL;

		if (!$this->applies()) {
			$mpdf->printbuffer($mpdf->textbuffer, $blockstate);

			return;
		}

		// Set before the snapshot is taken, as a snapshot leaves objects alone and a restore keeps it in place
		$mpdf->orphansWidows = $this;
		$snapshot = $mpdf->getStateSnapshot();
		$this->startPage = $mpdf->page;
		$this->belowContent = $mpdf->y > $mpdf->tMargin + 0.001;

		do {
			$this->pages = [];
			$mpdf->printbuffer($mpdf->textbuffer, $blockstate);
			$break = count($this->breaks) < self::MAX_PASSES - 1 ? $this->nextBreak() : null;
			if ($break !== null) {
				$mpdf->restoreStateSnapshot($snapshot);
				$this->breaks[] = $break;
			}
		} while ($break !== null);

		$mpdf->orphansWidows = null;
	}

	/**
	 * Called by the line layout as it is about to place a line, before it checks whether the line fits the page
	 *
	 * @return bool Whether the line is to start a fresh page, fitting or not
	 */
	public function breaksBefore()
	{
		$line = count($this->pages);
		$previousPage = $line ? $this->pages[$line - 1] : $this->startPage;

		// Once only: the page may already have turned ahead of the line, for a page area of another width
		return $this->mpdf->page == $previousPage && in_array($line, $this->breaks, true);
	}

	/**
	 * Called by the line layout once a line is placed
	 *
	 * @param int $page The page it landed on
	 *
	 * @return void
	 */
	public function placed($page)
	{
		$this->pages[] = $page;
	}

	/**
	 * Whether the block's lines are to be kept to its orphans and widows
	 *
	 * @return bool
	 */
	private function applies()
	{
		$mpdf = $this->mpdf;

		return ($this->orphans > 1 || $this->widows > 1) && $mpdf->cssMode === CssMode::STANDARD
			&& count($mpdf->textbuffer) && $mpdf->orphansWidows === null && !$mpdf->tableLevel && !$mpdf->ColActive
			&& !$mpdf->bufferoutput && !$mpdf->keep_block_together && !$mpdf->kwt;
	}

	/**
	 * Where to start a fresh page in the next layout, from the pages the lines of the last one landed on
	 *
	 * Too few lines at the foot of the page the lines start on moves them all, unless nothing is above them there.
	 * Too few carried onto the last page takes lines from the page before it until there are `widows`, unless that
	 * leaves fewer than `orphans` there; then, where that page is the first, the lines move whole
	 *
	 * @return int|null The index of the line, or null to keep the layout
	 */
	private function nextBreak()
	{
		// The index of the first line on each page the lines landed on
		$starts = [0];
		for ($i = 1; $i < count($this->pages); $i++) {
			if ($this->pages[$i] != $this->pages[$i - 1]) {
				$starts[] = $i;
			}
		}
		$segments = count($starts);
		if ($segments < 2) {
			return null;
		}

		if ($starts[1] < $this->orphans) {
			return $this->breakAt(0);
		}

		$lastStart = $starts[$segments - 1];
		if (count($this->pages) - $lastStart >= $this->widows) {
			return null;
		}

		$previousStart = $starts[$segments - 2];
		$break = count($this->pages) - $this->widows;
		if ($break - $previousStart >= $this->orphans) {
			return $this->breakAt($break);
		}

		return $previousStart === 0 ? $this->breakAt(0) : null;
	}

	/**
	 * @param int $line
	 *
	 * @return int|null The line, or null where a break there would change nothing or leave an empty page behind
	 */
	private function breakAt($line)
	{
		if (in_array($line, $this->breaks, true) || ($line === 0 && !$this->belowContent)) {
			return null;
		}

		return $line;
	}
}
