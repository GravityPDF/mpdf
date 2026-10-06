<?php

namespace Mpdf;

/**
 * A cell, row or row group whose end tag is left out ends where the next one starts under the standard cascade too
 */
class OmittedTableEndTagStandardCascadeTest extends OmittedTableEndTagTest
{

	/**
	 * The standard cascade
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['cssMode' => CssMode::STANDARD];
	}
}
