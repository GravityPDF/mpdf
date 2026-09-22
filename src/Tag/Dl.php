<?php

namespace Mpdf\Tag;

/**
 * A definition list, tagged as an L. Each list keeps its own record of the LI its terms and
 * definitions opened, so a list nested in a definition does not close the outer one's.
 */
class Dl extends BlockTag
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
			$this->ua->pushImplicitLIFrame();
		}
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->PDFUA) {
			if ($this->ua->isOpenedImplicitLI()) {
				$this->ua->getStructureTree()->close();
				$this->ua->setOpenedImplicitLI(false);
			}
			$this->ua->popImplicitLIFrame();
		}
		parent::close($ahtml, $ihtml);
	}
}
