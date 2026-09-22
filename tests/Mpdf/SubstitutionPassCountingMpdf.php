<?php

namespace Mpdf;

/**
 * Counts the passes the substitution scan makes over the text tokens, so a test can pin that a token
 * holding several runs is split in one of them rather than one each.
 */
class SubstitutionPassCountingMpdf extends TextRecordingMpdf
{

	/** How many times WriteHTML() has offered a text token to the substitution scan. */
	public $substitutionPasses = 0;

	/**
	 * @param string[] $writehtml_a
	 * @param int      $writehtml_i
	 * @param string   $writehtml_e
	 *
	 * @return int
	 */
	function SubstituteCharsMB(&$writehtml_a, &$writehtml_i, &$writehtml_e)
	{
		$this->substitutionPasses++;

		return parent::SubstituteCharsMB($writehtml_a, $writehtml_i, $writehtml_e);
	}

}
