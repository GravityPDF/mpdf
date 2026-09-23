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
	 * @var array The font's GSUB lookups, empty if it has no GSUB
	 */
	public $gsubLookups = [];

	/**
	 * Records the lookups, then builds the shaper's tables from them as the parser does.
	 *
	 * @return string See TTFontFile::useGSUBlookups()
	 */
	protected function useGSUBlookups(array $Lookup, array $gsub, array $GSLookup, $gsubOffset)
	{
		$this->gsubLookups = $Lookup;

		return parent::useGSUBlookups($Lookup, $gsub, $GSLookup, $gsubOffset);
	}

}
