<?php

namespace Mpdf\Tag;

/**
 * The fallback parentheses around a ruby annotation, which mPDF draws inline. Tagged RP under PDF/UA.
 */
class Rp extends InlineTag
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
			$this->ua->getStructureTree()->open('RP');
			$this->pushInlineUaStructDepth(1);
		}
	}
}
