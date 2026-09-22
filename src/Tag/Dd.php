<?php

namespace Mpdf\Tag;

/**
 * A definition, tagged as the LBody of a list item. An LBody must sit in an LI, so a definition
 * with no term before it gets one of its own; one after a term shares the term's.
 */
class Dd extends BlockTag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if ($this->mpdf->PDFUA
			&& !$this->mpdf->tableLevel
			&& !$this->ua->isOpenedImplicitLI()
			&& $this->ua->getStructureTree()->getCurrent()->getType() === 'L'
		) {
			$this->ua->getStructureTree()->open('LI');
			$this->ua->setOpenedImplicitLI(true);
		}

		parent::open($attr, $ahtml, $ihtml);
	}

}
