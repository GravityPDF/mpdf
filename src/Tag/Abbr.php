<?php

namespace Mpdf\Tag;

/**
 * An abbreviation. Under PDF/UA its title is the expansion a screen reader speaks, carried as
 * /E on a Span.
 */
class Abbr extends InlineTag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		parent::open($attr, $ahtml, $ihtml);

		// Counted on this tag's inline frame, so close() pops it along with any Span the parent opened
		if ($this->mpdf->PDFUA && !empty($attr['TITLE'])) {
			$this->ua->getStructureTree()->open('Span', ['E' => $attr['TITLE']]);
			$this->pushInlineUaStructDepth(1);
		}
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		parent::close($ahtml, $ihtml);
	}
}
