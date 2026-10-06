<?php

namespace Mpdf\Tag;

use Mpdf\Css\Border;

class Tr extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{

		$this->mpdf->lastoptionaltag = 'TR'; // Save current HTML specified optional endtag
		$this->cssManager->tbCSSlvl++;
		$this->mpdf->row++;
		$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['nr'] ++;
		$this->mpdf->col = -1;
		$properties = $this->cssManager->MergeCSS('TABLE', 'TR', $attr);

		// write pagebreak markers into row list, so _tableWrite can respect it
		if (isset($properties['PAGE-BREAK-BEFORE']) && strtoupper($properties['PAGE-BREAK-BEFORE']) === 'AVOID'
			&& !$this->mpdf->ColActive) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['pagebreak-before'][$this->mpdf->row] = 'avoid';
		}

		if (isset($properties['PAGE-BREAK-AFTER']) && strtoupper($properties['PAGE-BREAK-AFTER']) === 'AVOID'
			&& !$this->mpdf->ColActive) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['pagebreak-before'][$this->mpdf->row + 1] = 'avoid';
		}

		if (!$this->mpdf->simpleTables && (!isset($this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['borders_separate'])
				|| !$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['borders_separate'])) {
			// Kept only for a side the row draws, or hides with hidden: a border that draws nothing, such as a
			// border-color alone, leaves the cells' borders
			foreach (['LEFT', 'RIGHT', 'TOP', 'BOTTOM'] as $side) {
				if (!empty($properties['BORDER-' . $side]) && $this->mpdf->border_details($properties['BORDER-' . $side])['s']) {
					$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['trborder-' . strtolower($side)][$this->mpdf->row] = $properties['BORDER-' . $side];
				}
			}
		}

		if (isset($properties['BACKGROUND-COLOR'])) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] = $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] ? $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] : [];
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'][$this->mpdf->row] = $properties['BACKGROUND-COLOR'];
		} elseif (isset($attr['BGCOLOR'])) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] = $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] ? $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'] : [];
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['bgcolor'][$this->mpdf->row] = $attr['BGCOLOR'];
		}

		/* -- BACKGROUNDS -- */
		if (isset($properties['BACKGROUND-GRADIENT']) && !$this->mpdf->kwt && !$this->mpdf->ColActive) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['trgradients'][$this->mpdf->row] = $properties['BACKGROUND-GRADIENT'];
		}

		// FIXME: undefined variable $currblk
		if (!empty($properties['BACKGROUND-IMAGE']) && !$this->mpdf->kwt && !$this->mpdf->ColActive) {
			$ret = $this->mpdf->SetBackground($properties, $currblk['inner_width']);
			if ($ret) {
				$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['trbackground-images'][$this->mpdf->row] = $ret;
			}
		}
		/* -- END BACKGROUNDS -- */

		if (isset($properties['TEXT-ROTATE'])) {
			$this->mpdf->trow_text_rotate = $properties['TEXT-ROTATE'];
		}
		if (isset($attr['TEXT-ROTATE'])) {
			$this->mpdf->trow_text_rotate = $attr['TEXT-ROTATE'];
		}

		if ($this->mpdf->tablethead) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['is_thead'][$this->mpdf->row] = true;
		}
		if ($this->mpdf->tabletfoot) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['is_tfoot'][$this->mpdf->row] = true;
		}
	}

	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->tableLevel) {
			// If Border set on TR - Update right border
			if (isset($this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['trborder-right'][$this->mpdf->row])) {
				$c = & $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col];
				if ($c) {
					if ($this->mpdf->packTableData) {
						$cell = $this->mpdf->_unpackCellBorder($c['borderbin']);
					} else {
						$cell = $c;
					}
					$cell['border_details']['R'] = $this->mpdf->border_details(
						$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['trborder-right'][$this->mpdf->row]
					);
					$this->mpdf->setBorder($cell['border'], Border::RIGHT, $cell['border_details']['R']['s']);
					if ($this->mpdf->packTableData) {
						$c['borderbin'] = $this->mpdf->_packCellBorder($cell);
						unset($c['border'], $c['border_details']);
					} else {
						$c = $cell;
					}
				}
			}
			$this->mpdf->lastoptionaltag = '';
			unset($this->cssManager->tablecascadeCSS[$this->cssManager->tbCSSlvl]);
			$this->cssManager->tbCSSlvl--;
			$this->mpdf->trow_text_rotate = '';
			$this->mpdf->tabletheadjustfinished = false;
		}
	}

}
