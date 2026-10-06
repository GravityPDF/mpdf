<?php

namespace Mpdf\Tag;

// TODO: Extend THEAD instead?

class TFoot extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->lastoptionaltag = 'TFOOT'; // Save current HTML specified optional endtag
		$this->cssManager->tbCSSlvl++;
		$this->mpdf->tabletfoot = 1;
		$this->mpdf->tablethead = 0;
		$properties = $this->cssManager->MergeCSS('TABLE', 'TFOOT', $attr);
		$this->keepLegacyRowGroupFont($properties, 'tfoot');

		if (isset($properties['VERTICAL-ALIGN'])) {
			$this->mpdf->tfoot_valign_default = $properties['VERTICAL-ALIGN'];
		}
		if (isset($properties['TEXT-ALIGN'])) {
			$this->mpdf->tfoot_textalign_default = $properties['TEXT-ALIGN'];
		}
	}

	public function close(&$ahtml, &$ihtml)
	{
		$this->mpdf->lastoptionaltag = '';
		unset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl]);
		$this->cssManager->tbCSSlvl--;
		$this->mpdf->tabletfoot = 0;
		$this->mpdf->ResetStyles();
		$this->mpdf->tfoot_font_weight = '';
		$this->mpdf->tfoot_font_style = '';
		$this->mpdf->tfoot_font_smCaps = '';

		$this->mpdf->tfoot_valign_default = '';
		$this->mpdf->tfoot_textalign_default = '';
	}
}
