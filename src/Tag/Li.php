<?php

namespace Mpdf\Tag;

/**
 * A list item, tagged under PDF/UA as an LI holding the Lbl of its marker and the LBody of its
 * content (Matterhorn 21-001).
 */
class Li extends BlockTag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		parent::open($attr, $ahtml, $ihtml);

		if ($this->mpdf->PDFUA
			&& !$this->mpdf->tableLevel
			&& isset($this->mpdf->blk[$this->mpdf->blklvl]['pdfua_type'])
			&& $this->mpdf->blk[$this->mpdf->blklvl]['pdfua_type'] === 'LI'
			&& empty($this->mpdf->blk[$this->mpdf->blklvl]['pdfua_artifact'])
		) {
			$blklvl = $this->mpdf->blklvl;
			$structureTree = $this->ua->getStructureTree();

			// Only an outside marker is drawn apart from the text, and only it gets a Lbl. The Lbl
			// is closed at once and filled when the marker is drawn, so LBody can follow it.
			if (is_array($this->mpdf->listitem) && !empty($this->mpdf->listitem)) {
				$structureTree->open('Lbl');
				$this->mpdf->blk[$blklvl]['pdfua_li_lbl_elem'] = $structureTree->getCurrent();
				$structureTree->close();
			}

			// The block's content is marked as LBody, which BlockTag closes; the LI is left to close()
			$structureTree->open('LBody');
			$this->mpdf->blk[$blklvl]['pdfua_type'] = 'LBody';
			$this->mpdf->blk[$blklvl]['pdfua_struct_elem'] = $structureTree->getCurrent();
			$this->mpdf->blk[$blklvl]['pdfua_li_lbody'] = true;
		}
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		// Read before the block is gone
		$hasLbody = $this->mpdf->PDFUA
			&& !$this->mpdf->tableLevel
			&& isset($this->mpdf->blk[$this->mpdf->blklvl]['pdfua_li_lbody'])
			&& $this->mpdf->blk[$this->mpdf->blklvl]['pdfua_li_lbody'];

		parent::close($ahtml, $ihtml);

		if ($hasLbody) {
			$this->ua->getStructureTree()->close();
		}
	}
}
