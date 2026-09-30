<?php

namespace Mpdf;

/**
 * Stylesheet rules that name a positioned block reach the content inside it under the standard cascade too
 */
class PositionedBlockSelectorStandardCascadeTest extends PositionedBlockSelectorTest
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
