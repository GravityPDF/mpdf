<?php

namespace Mpdf\Fonts;

/**
 * Every byte the parser hands the shaper, for every font in tests/data/ttf.
 *
 * The gate for the #81 refactor: moving the reader, collapsing the second parser or splitting the
 * dispatchers must leave this output untouched. The two phases that change a persisted shape on
 * purpose - table-relative offsets, and one cache file per table - are the only ones allowed to
 * rewrite these fixtures, and they raise MetricsGenerator::CACHE_FORMAT when they do.
 */
class ParserGoldenMasterTest extends GoldenMasterTestCase
{

	/**
	 * @return GoldenMaster The master this test asserts against
	 */
	protected function newMaster()
	{
		return new ParserGoldenMaster();
	}

	/**
	 * @return string The composer script that rewrites its fixtures
	 */
	protected function updateCommand()
	{
		return 'fontcache:update';
	}
}
