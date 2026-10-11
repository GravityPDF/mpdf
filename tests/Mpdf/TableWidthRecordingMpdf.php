<?php

namespace Mpdf;

/**
 * Records the width of every table and of each of its columns once _tableWidth() has laid them out, which is where
 * the drawing code reads them from. A table's array is emptied once it is drawn, so it cannot be read afterwards.
 */
class TableWidthRecordingMpdf extends Mpdf
{

	/**
	 * Each table laid out, in the order _tableWidth() reached it: its level, `w` (the width with its margins and outer
	 * borders) and `columns` (each column's width, by index)
	 *
	 * @var array[]
	 */
	public $tableWidths = [];

	/**
	 * Lays the table's columns out, then records what they came to
	 *
	 * @param array $table
	 */
	function _tableWidth(&$table)
	{
		parent::_tableWidth($table);

		$this->tableWidths[] = ['level' => $table['level'], 'w' => $table['w'], 'columns' => $table['wc']];
	}
}
