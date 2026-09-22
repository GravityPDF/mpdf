<?php

namespace Mpdf\Ua;

/**
 * How many structure elements each open inline tag opened, so its close() ends the same number.
 *
 * A tag such as <abbr> or <rt> that opens more after InlineTag::open() adds them to the frame
 * with addToTopFrame().
 */
class InlineStructStack
{

	/**
	 * @var array<string,int[]> Keyed by the uppercase tag name
	 */
	protected $stacks = [];

	/**
	 * @param string $tag   Uppercase tag name
	 * @param int    $depth How many elements the tag opened
	 *
	 * @return void
	 */
	public function pushFrame($tag, $depth)
	{
		if (!isset($this->stacks[$tag])) {
			$this->stacks[$tag] = [];
		}
		$this->stacks[$tag][] = (int) $depth;
	}

	/**
	 * Does nothing when the tag has no frame, as when PDF/UA is off
	 *
	 * @param string $tag
	 * @param int    $count
	 *
	 * @return void
	 */
	public function addToTopFrame($tag, $count)
	{
		if (empty($this->stacks[$tag])) {
			return;
		}
		$idx = count($this->stacks[$tag]) - 1;
		$this->stacks[$tag][$idx] += (int) $count;
	}

	/**
	 * @param string $tag
	 *
	 * @return int How many elements to close; 0 when the tag has no frame
	 */
	public function popFrame($tag)
	{
		if (empty($this->stacks[$tag])) {
			return 0;
		}
		return (int) array_pop($this->stacks[$tag]);
	}
}
