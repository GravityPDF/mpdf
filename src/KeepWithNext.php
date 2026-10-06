<?php

namespace Mpdf;

/**
 * Keeps a block with page-break-after: avoid on the page of what follows it, in standard CSS mode
 *
 * The block and the first line of the next block in the flow are laid out as one unit, the way page-break-inside: avoid
 * lays out a block: the document state is captured as the block opens and, if that first line lands on the page after
 * the block's, the state is put back and the block laid out again at the top of a fresh page. A block or table kept
 * together counts whole in place of its first line. Blocks with page-break-after: avoid in a row form one unit and move
 * together; where the unit would not fit a fresh page either, the tail of it that does moves, and if none does the
 * break stays where it fell. Legacy mode asks for room for one more line, as mPDF v7 did
 *
 * @internal
 */
class KeepWithNext
{

	use StateSnapshot;

	/**
	 * The attribute marking a block laid out a second time, so it is not made a unit again
	 */
	const CHECKED = 'KEEPWITHNEXTCHECKED';

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * The unit waiting for the first line after it, or null. 'links' has one entry per block, in order, each with the
	 * document state captured as the block opened and the index of its token; 'page' and 'y1' are where the last block
	 * closed and 'blklvl' its level, which the block resolving the unit has to be at or below
	 *
	 * @var array|null
	 */
	private $pending;

	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Called as a block opens, before it is put on the block stack. A block with page-break-after: avoid takes over the
	 * unit waiting for it, if any, and adds itself to it with the document as it is here, so a break between it and what
	 * follows can be unwound to it
	 *
	 * @param array $properties The block's properties, as PreviewBlockCSS() reads them
	 * @param array $attr
	 * @param int $ihtml The index of the block's token
	 *
	 * @return array|null The unit for the block to carry until it closes: its 'links', whether the block's 'own' link is
	 * the last of them, and the 'page' and 'y' the block opened at. Null when the block is in no unit
	 */
	public function opens(array $properties, array $attr, $ihtml)
	{
		if (!isset($properties['PAGE-BREAK-AFTER']) || strtoupper($properties['PAGE-BREAK-AFTER']) !== 'AVOID'
			|| !$this->applies() || ($this->mpdf->use_kwt && isset($attr['KEEP-WITH-TABLE']))) {
			return null;
		}

		$links = [];
		if ($this->pending !== null) {
			if ($this->pending['page'] == $this->mpdf->page) {
				$links = $this->pending['links'];
			}
			$this->pending = null;
		}
		$own = !isset($attr[self::CHECKED]);
		if ($own) {
			$links[] = ['state' => $this->mpdf->getStateSnapshot(), 'i' => $ihtml];
		}
		if (!$links) {
			return null;
		}

		return ['links' => $links, 'own' => $own, 'page' => $this->mpdf->page, 'y' => $this->mpdf->y];
	}

	/**
	 * Called as a block carrying a unit closes, once its lines are drawn. When its first line left the page the blocks
	 * before it in the unit closed on, those are settled by it as the block after them. Otherwise the unit waits for the
	 * first line after this block, unless the block was itself split over two pages or forces a page break after itself
	 *
	 * @param array $blk The block, carrying in 'keepWithNext' what opens() handed it
	 * @param bool $forcedBreak Whether the block has page-break-after: always, left or right
	 * @param int $level The block's level
	 * @param string[] $ahtml The tokens being parsed
	 * @param int $ihtml The index of the token being parsed
	 *
	 * @return bool Whether the document was put back and the parser rewound, as settle() says
	 */
	public function closes(array $blk, $forcedBreak, $level, array &$ahtml, &$ihtml)
	{
		$unit = $blk['keepWithNext'];
		$startpage = isset($blk['startpage']) ? $blk['startpage'] : null;
		$links = $unit['links'];
		$before = $unit['own'] ? array_slice($links, 0, -1) : $links;
		if ($before && $startpage !== null && $startpage != $unit['page']) {
			$this->pending = ['links' => $before, 'page' => $unit['page'], 'y1' => $unit['y'], 'blklvl' => $level];
			$height = $this->mpdf->page == $startpage ? $this->mpdf->y - $this->mpdf->tMargin : $this->firstLine($blk);
			if ($this->settle($startpage, $height, $level, $ahtml, $ihtml)) {
				return true;
			}
			// The blocks before it stay; this block alone can still be kept with what follows it
			$links = $unit['own'] ? [end($links)] : [];
		}

		$landed = $startpage !== null ? $startpage : $unit['page'];
		if (!$links || $forcedBreak || $this->mpdf->page != $landed) {
			return false;
		}

		$this->pending = [
			'links' => $links,
			'page' => $this->mpdf->page,
			'y1' => $this->mpdf->y,
			'blklvl' => $level,
		];

		return false;
	}

	/**
	 * Called as the block or table after a unit is placed, with the page its first line landed on, or the page the whole
	 * of a block or table kept together landed on. A unit that stayed on that page is done with. One left behind by a
	 * page break is unwound: the parser is rewound to the first of its blocks that fits a fresh page together with
	 * $height, the state put back to that block's opening and a page added, so the caller has to return at once
	 *
	 * @param int $landed
	 * @param float $height What has to follow the unit on the fresh page: the first line, or the whole kept block or table
	 * @param int $level The block level of what was placed; an ancestor of the unit closing does not resolve it
	 * @param string[] $ahtml The tokens being parsed
	 * @param int $ihtml The index of the token being parsed
	 *
	 * @return bool Whether the document was put back and the parser rewound
	 */
	public function settle($landed, $height, $level, array &$ahtml, &$ihtml)
	{
		if ($this->pending === null || $this->mpdf->bufferoutput) {
			return false;
		}
		if ($this->mpdf->ColActive) {
			$this->pending = null;

			return false;
		}
		if ($level < $this->pending['blklvl']) {
			return false;
		}

		$unit = $this->pending;
		$this->pending = null;
		if ($landed != $unit['page'] + 1) {
			return false;
		}

		foreach ($unit['links'] as $link) {
			if ($unit['y1'] - $link['state']['y'] + $height <= $this->mpdf->PageBreakTrigger - $this->mpdf->tMargin) {
				$this->rewind($link, $ahtml, $ihtml);

				return true;
			}
		}

		return false;
	}

	/**
	 * Called as a block without a unit of its own closes: its first line is what a unit before it is kept with
	 *
	 * @param array $blk The block, with the 'startpage' its first line landed on; one with no line settles nothing
	 * @param int $level The block's level
	 * @param string[] $ahtml The tokens being parsed
	 * @param int $ihtml The index of the token being parsed
	 *
	 * @return bool Whether the document was put back and the parser rewound, as settle() says
	 */
	public function settleAfter(array $blk, $level, array &$ahtml, &$ihtml)
	{
		if (!isset($blk['startpage'])) {
			return false;
		}

		return $this->settle($blk['startpage'], $this->firstLine($blk), $level, $ahtml, $ihtml);
	}

	/**
	 * What a block's first line takes up on a fresh page, its top margin, padding and border included. The margin is
	 * counted though a fresh page may drop it: a unit this close to a page's height is better left split than moved to a
	 * page it then overruns (mpdf/mpdf#1801)
	 *
	 * @param array $blk
	 *
	 * @return float
	 */
	private function firstLine(array $blk)
	{
		return $this->mpdf->lineheight + $blk['margin_top'] + $blk['padding_top'] + $blk['border_top']['w'];
	}

	/**
	 * Forgets the unit waiting for its next line: a forced page break parts them, and tokens do not outlive the
	 * WriteHTML() call that read them
	 *
	 * @return void
	 */
	public function drop()
	{
		if (!$this->mpdf->bufferoutput) {
			$this->pending = null;
		}
	}

	/**
	 * Whether a block opening here can be kept with its next: not in legacy mode, in columns, in a table, while output is
	 * buffered for a header or footer, or while a kept block is being measured, which moves whole in any case
	 *
	 * @return bool
	 */
	private function applies()
	{
		return $this->mpdf->cssMode === CssMode::STANDARD && !$this->mpdf->ColActive && !$this->mpdf->tableLevel
			&& !$this->mpdf->bufferoutput && !$this->mpdf->keep_block_together;
	}

	/**
	 * Puts the document back to how it was as $link's block opened, marks that block's token so it is laid out as an
	 * ordinary block, rewinds the parser to it and starts the fresh page for it
	 *
	 * @param array $link
	 * @param string[] $ahtml
	 * @param int $ihtml
	 *
	 * @return void
	 */
	private function rewind(array $link, array &$ahtml, &$ihtml)
	{
		$i = $link['i'];
		$this->mpdf->restoreStateSnapshot($link['state']);
		$ahtml[$i] .= ' ' . strtolower(self::CHECKED) . '="true";';
		// The kept blocks laid out again between the unit and what follows it are measured afresh on the fresh page
		$markers = [' pagebreakavoidchecked="true";', ' ' . strtolower(self::CHECKED) . '="true";'];
		for ($j = $i + 1; $j <= $ihtml; $j++) {
			$ahtml[$j] = str_replace($markers, '', $ahtml[$j]);
		}
		$ihtml = $i - 1;
		$this->mpdf->AddPage();
	}
}
