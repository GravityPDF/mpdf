<?php

namespace Mpdf\Tag;

/**
 * A ruby annotation, which mPDF draws inline after its base. Tagged RT under PDF/UA.
 */
class Rt extends InlineTag
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
			$this->ua->getStructureTree()->open('RT');
			$this->pushInlineUaStructDepth(1);
		}
	}
}
