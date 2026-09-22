<?php

namespace Mpdf\Tag;

/**
 * A ruby base. Tagged RB under PDF/UA; a base that is bare text is content of the Ruby element itself.
 */
class Rb extends InlineTag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		parent::open($attr, $ahtml, $ihtml);

		if ($this->mpdf->PDFUA) {
			$this->ua->getStructureTree()->open('RB');
			$this->pushInlineUaStructDepth(1);
		}
	}
}
