<?php

namespace Mpdf\Tag;

/**
 * Ruby text. Tagged Ruby under PDF/UA whether or not it carries a language or label, which is all
 * InlineTag would tag it for.
 */
class Ruby extends InlineTag
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
			$this->ua->getStructureTree()->open('Ruby');
			$this->pushInlineUaStructDepth(1);
		}
	}
}
