<?php

namespace Mpdf\Tag;

class THead extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->lastoptionaltag = 'THEAD'; // Save current HTML specified optional endtag
		$this->cssManager->tbCSSlvl++;
		$this->mpdf->tablethead = 1;
		$this->mpdf->tabletfoot = 0;
		$properties = $this->cssManager->MergeCSS('TABLE', 'THEAD', $attr);
		$this->keepLegacyRowGroupFont($properties, 'thead');

		if (isset($properties['VERTICAL-ALIGN'])) {
			$this->mpdf->thead_valign_default = $properties['VERTICAL-ALIGN'];
		}
		if (isset($properties['TEXT-ALIGN'])) {
			$this->mpdf->thead_textalign_default = $properties['TEXT-ALIGN'];
		}
	}

	public function close(&$ahtml, &$ihtml)
	{
		$this->mpdf->lastoptionaltag = '';
		unset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl]);
		$this->cssManager->tbCSSlvl--;
		$this->mpdf->tablethead = 0;
		$this->mpdf->tabletheadjustfinished = true;
		$this->mpdf->ResetStyles();
		$this->mpdf->thead_font_weight = '';
		$this->mpdf->thead_font_style = '';
		$this->mpdf->thead_font_smCaps = '';

		$this->mpdf->thead_valign_default = '';
		$this->mpdf->thead_textalign_default = '';
	}
}
