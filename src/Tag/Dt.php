<?php

namespace Mpdf\Tag;

/**
 * A term of a definition list, tagged as the Lbl of a list item. Each term begins an LI of its
 * own, which the definitions after it share.
 */
class Dt extends BlockTag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if ($this->mpdf->PDFUA && $this->ua->isOpenedImplicitLI()) {
			$this->ua->getStructureTree()->close();
			$this->ua->setOpenedImplicitLI(false);
		}

		if ($this->mpdf->PDFUA
			&& !$this->mpdf->tableLevel
			&& $this->ua->getStructureTree()->getCurrent()->getType() === 'L'
		) {
			$this->ua->getStructureTree()->open('LI');
			$this->ua->setOpenedImplicitLI(true);
		}

		parent::open($attr, $ahtml, $ihtml);
	}

}
