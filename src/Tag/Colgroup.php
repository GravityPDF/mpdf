<?php

namespace Mpdf\Tag;

/**
 * A group of table columns, read under the standard CSS mode only. Its width goes to the columns in it that have no
 * width of their own or, when it holds none, to each of the columns its span attribute counts
 */
class Colgroup extends Col
{

	/**
	 * Opens the group, ending the one before it if its end tag was left out
	 *
	 * @param string[] $attr
	 * @param string[] $ahtml
	 * @param int $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if (!$this->mpdf->tableLevel) {
			return;
		}

		$table = &$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]];
		self::endColumnGroup($table);

		$table['colgroup'] = [
			'width' => $this->widthOf($this->cssManager->MergeCSS('TABLE', 'COLGROUP', $attr)),
			'span' => self::spanOf($attr),
			'cols' => false,
		];
	}

	/**
	 * Ends the group
	 *
	 * @param string[] $ahtml
	 * @param int $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->tableLevel) {
			self::endColumnGroup($this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]);
		}
	}
}
