<?php

namespace Mpdf\Fonts;

use Mpdf\TTFontFile;

/**
 * Keeps the GSUB lookups as readGSUBrules() leaves them, before the parser builds the shaper's
 * tables from them.
 */
class GsubLookupRecordingTTFontFile extends TTFontFile
{

	/**
	 * @var array The font's GSUB lookups, rules read, or an empty array if it has no GSUB
	 */
	public $gsubLookups = [];

	/**
	 * Records the lookups, then does what the parser does with them.
	 *
	 * @return string See TTFontFile::useGSUBlookups()
	 */
	protected function useGSUBlookups(array $Lookup, array $gsub, array $GSLookup, $gsubOffset)
	{
		$this->gsubLookups = $Lookup;

		return parent::useGSUBlookups($Lookup, $gsub, $GSLookup, $gsubOffset);
	}

}
