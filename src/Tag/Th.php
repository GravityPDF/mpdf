<?php

namespace Mpdf\Tag;

/**
 * A header cell, laid out as any cell. Under PDF/UA it is a TH with a /Scope, and an /ID that
 * the /Headers of other cells refer to.
 */
class Th extends Td
{

	/**
	 * @return string The structure type of the cell
	 */
	protected function pdfuaCellStructType()
	{
		return 'TH';
	}

	/**
	 * The attributes of any cell, and a /Scope. HTML has no scope="both", but it is taken for a
	 * header that labels both ways.
	 *
	 * @param array $attr
	 * @return array
	 */
	protected function pdfuaCellStructAttrs($attr)
	{
		$cellAttrs = parent::pdfuaCellStructAttrs($attr);

		$scope = 'Column';
		if (!empty($attr['SCOPE'])) {
			$s = strtolower($attr['SCOPE']);
			if ($s === 'row' || $s === 'rowgroup') {
				$scope = 'Row';
			} elseif ($s === 'col' || $s === 'colgroup') {
				$scope = 'Column';
			} elseif ($s === 'both') {
				$scope = 'Both';
			}
		}
		$cellAttrs['Scope'] = $scope;

		return $cellAttrs;
	}

	/**
	 * Gives the header its /ID, cleaned the way a cell's /Headers are so the two match. A header
	 * without an id is given one numbered across the document, as its place in the table alone
	 * repeats from one table to the next.
	 *
	 * @param array                     $attr
	 * @param \Mpdf\Ua\StructureElement $cellElem
	 */
	protected function pdfuaRegisterCellId($attr, $cellElem)
	{
		if (!empty($attr['ID'])) {
			$thId = \Mpdf\Ua\StructureElement::sanitiseIdForPdf($attr['ID']);
		} else {
			$counter = $this->ua->getAriaIdResolver()->nextSyntheticThCounter();
			$thId = 'th-' . $this->mpdf->tableLevel . '-' . $this->mpdf->row
				. '-' . $this->mpdf->col . '-' . $counter;
		}
		$cellElem->setId($thId);

		if (!empty($attr['ID'])) {
			$this->ua->getAriaIdResolver()->registerId($attr['ID'], $cellElem);
		}
	}

	public function close(&$ahtml, &$ihtml)
	{
		$this->mpdf->SetStyle('B', false);
		parent::close($ahtml, $ihtml);
	}
}
