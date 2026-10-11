<?php

namespace Mpdf\Tag;

/**
 * A table column, read under the standard CSS mode only: the legacy mode drops the tag before it is parsed. Its width
 * is kept for each column it spans, in $table['col_widths'] by column index, as a cell keeps its own: `w` in mm, or
 * `wpercent`. A cell with no width of its own takes its column's, and a table with table-layout: fixed takes its
 * column widths from them first
 */
class Col extends Tag
{

	/**
	 * Records the width of the columns the element spans. A column with no width of its own takes its group's
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
		$width = $this->widthOf($this->cssManager->MergeCSS('TABLE', 'COL', $attr));

		if (isset($table['colgroup'])) {
			$table['colgroup']['cols'] = true;
			if ($width === null) {
				$width = $table['colgroup']['width'];
			}
		}

		self::addColumns($table, self::spanOf($attr), $width);
	}

	/**
	 * A column has no content and no end tag
	 *
	 * @param string[] $ahtml
	 * @param int $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
	}

	/**
	 * Ends the column group open in a table, whose end tag HTML lets be left out. A group with no columns in it spans
	 * as many columns as its span attribute says, each as wide as the group
	 *
	 * @param array $table
	 */
	public static function endColumnGroup(array &$table)
	{
		if (!isset($table['colgroup'])) {
			return;
		}

		if (!$table['colgroup']['cols']) {
			self::addColumns($table, $table['colgroup']['span'], $table['colgroup']['width']);
		}

		unset($table['colgroup']);
	}

	/**
	 * @param array $properties The element's merged CSS, its width attribute among them as a presentational hint
	 *
	 * @return array|null The width as a cell keeps one, or null for none or auto
	 */
	protected function widthOf(array $properties)
	{
		$width = isset($properties['WIDTH']) ? trim($properties['WIDTH']) : '';
		if ($width === '' || strtolower($width) === 'auto') {
			return null;
		}

		if (strpos($width, '%') !== false) {
			return $this->mpdf->ignore_table_percents ? null : ['wpercent' => (float) $width];
		}

		if ($this->mpdf->ignore_table_widths) {
			return null;
		}

		return ['w' => $this->sizeConverter->convert($width, $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->FontSize, false)];
	}

	/**
	 * @param string[] $attr
	 *
	 * @return int How many columns the element spans: its span attribute, from 1 to HTML's limit of 1000
	 */
	protected static function spanOf(array $attr)
	{
		if (!isset($attr['SPAN']) || !preg_match('/^\s*(\d+)/', $attr['SPAN'], $m)) {
			return 1;
		}

		return max(1, min(1000, (int) $m[1]));
	}

	/**
	 * Adds the next $span columns of a table, each with the width given
	 *
	 * @param array $table
	 * @param int $span
	 * @param array|null $width
	 */
	private static function addColumns(array &$table, $span, $width)
	{
		$first = isset($table['col_count']) ? $table['col_count'] : 0;
		$table['col_count'] = $first + $span;

		if ($width === null) {
			return;
		}

		for ($index = $first; $index < $first + $span; $index++) {
			$table['col_widths'][$index] = $width;
		}
	}
}
