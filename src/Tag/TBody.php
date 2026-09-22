<?php

namespace Mpdf\Tag;

class TBody extends Tag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->tablethead = 0;
		$this->mpdf->tabletfoot = 0;
		$this->mpdf->lastoptionaltag = 'TBODY'; // Save current HTML specified optional endtag
		$this->cssManager->tbCSSlvl++;
		$this->cssManager->MergeCSS('TABLE', 'TBODY', $attr);

		// Ends the TBody made for any rows written before it outside a group
		if ($this->mpdf->PDFUA) {
			$tree = $this->ua->getStructureTree();
			$tree->closeRowGroup();
			$tree->open('TBody');
		}
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->PDFUA) {
			$this->ua->getStructureTree()->closeRowGroup();
		}

		$this->mpdf->lastoptionaltag = '';
		unset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl]);
		$this->cssManager->tbCSSlvl--;
	}
}
