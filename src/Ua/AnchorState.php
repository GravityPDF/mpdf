<?php

namespace Mpdf\Ua;

/**
 * What an open `<a>` leaves for the drawing code: the Link element its annotation must point
 * at, whether each anchor was stripped for a blocked scheme, and the inline element (Link,
 * Span, Ruby…) that owns the content being drawn.
 */
class AnchorState
{

	/**
	 * @var \Mpdf\Ua\StructureElement|null Null when no <a href> is open
	 */
	protected $linkStructElem = null;

	/**
	 * One [stripped, spanDepth] entry per open <a href>: whether its link was dropped for a
	 * blocked scheme, and how many Span elements were opened to keep its lang and aria
	 * attributes
	 *
	 * @var array<int,array{0:bool,1:int}>
	 */
	protected $strippedAnchorStack = [];


	/**
	 * The innermost inline element owning the content being drawn, or null when the block owns
	 * it. Kept apart from $linkStructElem, which the annotation still needs when a Span sits
	 * inside the Link.
	 *
	 * @var \Mpdf\Ua\StructureElement|null
	 */
	protected $inlineContentElem = null;

	/**
	 * @param \Mpdf\Ua\StructureElement|null $elem
	 *
	 * @return void
	 */
	public function setLinkStructElem($elem)
	{
		$this->linkStructElem = $elem;
	}

	/**
	 * @return \Mpdf\Ua\StructureElement|null Null when no <a href> is open
	 */
	public function getLinkStructElem()
	{
		return $this->linkStructElem;
	}

	/**
	 * @return void
	 */
	public function clearLinkStructElem()
	{
		$this->linkStructElem = null;
	}

	/**
	 * @param bool $stripped  Whether the link was dropped for a blocked scheme
	 * @param int  $spanDepth How many Span elements the anchor opened
	 *
	 * @return void
	 */
	public function pushStripFrame($stripped, $spanDepth)
	{
		$this->strippedAnchorStack[] = [(bool) $stripped, (int) $spanDepth];
	}

	/**
	 * @return array{0:bool,1:int}|null Null when no anchor is open
	 */
	public function popStripFrame()
	{
		if (empty($this->strippedAnchorStack)) {
			return null;
		}
		return array_pop($this->strippedAnchorStack);
	}

	/**
	 * @return bool
	 */
	public function hasStripFrames()
	{
		return !empty($this->strippedAnchorStack);
	}



	/**
	 * @param \Mpdf\Ua\StructureElement|null $elem Null when the block owns the content
	 *
	 * @return void
	 */
	public function setInlineContentElem($elem)
	{
		$this->inlineContentElem = $elem;
	}

	/**
	 * @return \Mpdf\Ua\StructureElement|null Null when the block owns the content
	 */
	public function getInlineContentElem()
	{
		return $this->inlineContentElem;
	}
}
