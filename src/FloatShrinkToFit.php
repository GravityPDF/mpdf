<?php

namespace Mpdf;

/**
 * Sizes a float that has no width to its content, as CSS shrink-to-fit does
 *
 * The content's width is only known once it has been laid out, so the float is laid out twice. BlockTag::open()
 * takes a snapshot of the document and lays the float out at the width it would have taken, with the block marked
 * as measuring; every line, table and block of set width written inside records how far it reaches from the float's
 * content edge. BlockTag::close() puts the snapshot back, writes the widest reach into the start tag and lays the
 * float out again from that token.
 *
 * The result is min(max-content, available): a line that wrapped means the content wants more than there was, so
 * the float keeps the width it had, and the measuring pass stands as its layout. The min-content lower bound is not
 * applied, as mPDF has no notion of it for a block and would break a long word the same either way.
 */
class FloatShrinkToFit
{

	/**
	 * The attribute the measured width is written into the start tag as, in millimetres, as WriteHTML() upper-cases it
	 */
	const ATTRIBUTE = 'MEASUREDFLOATWIDTH';

	/**
	 * Added to the measured width so the same lines fit again whatever floating point leaves between the two passes
	 */
	const SLACK = 0.05;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * How many floats are being measured, so that a document with none pays nothing per line
	 *
	 * @var int
	 */
	private $measuring = 0;

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Marks the float at $blklvl as measuring
	 *
	 * @param int $blklvl
	 * @param array $snapshot The document as it was before the float opened
	 * @param int $token The index of the float's start tag among the parser's tokens
	 *
	 * @return void
	 */
	public function start($blklvl, array $snapshot, $token)
	{
		$this->mpdf->blk[$blklvl]['float_measure'] = ['state' => $snapshot, 'token' => $token, 'width' => 0, 'wrapped' => false];
		$this->measuring++;
	}

	/**
	 * Records a line written in the current block
	 *
	 * @param float $width Its width in millimetres, from the block's content edge
	 * @param bool $wrapped Whether the line was ended for want of room
	 *
	 * @return void
	 */
	public function recordLine($width, $wrapped = false)
	{
		if ($this->measuring) {
			$this->record($this->mpdf->blklvl, $width, $wrapped);
		}
	}

	/**
	 * Records a block of set width that opens at $blklvl, before its margins take up the slack
	 *
	 * @param int $blklvl
	 *
	 * @return void
	 */
	public function recordBlock($blklvl)
	{
		if (!$this->measuring) {
			return;
		}

		$blk = $this->mpdf->blk[$blklvl];
		if ($blk['float']) {
			// Its margin on the other side has been set to what is left over
			$box = $blk['float_width'];
		} else {
			$box = $blk['margin_left'] + $blk['border_left']['w'] + $blk['padding_left'] + $blk['css_set_width']
				+ $blk['padding_right'] + $blk['border_right']['w'] + $blk['margin_right'];
		}

		$this->record($blklvl - 1, $box, false);
	}

	/**
	 * Ends the measuring pass of the float at $blklvl, if it is one. When its content wants less than the width it
	 * was laid out at and its start tag is among $tokens, the document is put back as it was before the float opened,
	 * for the float to be laid out again from that tag. Otherwise the pass stands as the float's layout: a forced page
	 * break closes a block with no tokens to come back to, and a float whose text wrapped has its width already
	 *
	 * @param int $blklvl
	 * @param array $tokens The parser's tokens, as handed to BlockTag::close()
	 *
	 * @return array|null The snapshot, the index of the float's start tag and the width to lay it out at, or null
	 */
	public function finish($blklvl, array $tokens)
	{
		if (!isset($this->mpdf->blk[$blklvl]['float_measure'])) {
			return null;
		}

		$measure = $this->mpdf->blk[$blklvl]['float_measure'];
		$available = $this->mpdf->blk[$blklvl]['css_set_width'];
		$this->measuring--;

		$width = $measure['wrapped'] || $measure['width'] <= 0 ? $available : min($measure['width'] + self::SLACK, $available);
		if ($width >= $available || !isset($tokens[$measure['token']])) {
			unset($this->mpdf->blk[$blklvl]['float_measure']);
			$this->recordBlock($blklvl);

			return null;
		}

		return [$measure['state'], $measure['token'], $width];
	}

	/**
	 * Records how far something written in the block at $level reaches, on the nearest float being measured
	 *
	 * @param int $level
	 * @param float $width Its width, from that block's content edge
	 * @param bool $wrapped
	 *
	 * @return void
	 */
	private function record($level, $width, $wrapped)
	{
		for ($f = $level; $f > 0; $f--) {
			if (!isset($this->mpdf->blk[$f]['float_measure'])) {
				continue;
			}

			$measure = & $this->mpdf->blk[$f]['float_measure'];
			$measure['width'] = max($measure['width'], $this->contentLeft($level) - $this->contentLeft($f) + $width);
			$measure['wrapped'] = $measure['wrapped'] || $wrapped;

			return;
		}
	}

	/**
	 * @param int $blklvl
	 *
	 * @return float Where the content of the block at $blklvl starts, from the left page margin
	 */
	private function contentLeft($blklvl)
	{
		$blk = $this->mpdf->blk[$blklvl];

		return $blk['outer_left_margin'] + $blk['border_left']['w'] + $blk['padding_left'];
	}
}
