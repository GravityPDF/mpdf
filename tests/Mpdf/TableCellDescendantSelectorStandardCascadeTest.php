<?php

namespace Mpdf;

/**
 * A descendant rule naming a table, row or cell reaches the content of the cell under the standard cascade too
 */
class TableCellDescendantSelectorStandardCascadeTest extends TableCellDescendantSelectorTest
{

	/**
	 * The standard cascade
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['cssMode' => 'standard'];
	}
}
