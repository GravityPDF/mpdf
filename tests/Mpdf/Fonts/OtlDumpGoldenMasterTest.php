<?php

namespace Mpdf\Fonts;

/**
 * Everything OtlDump reports about every font in tests/data/ttf, plus every diagnostic PHP raised
 * while it read one.
 *
 * The witness for collapsing the dump onto the parser it extends: what it reports has to survive
 * losing its own copy of the reading.
 */
class OtlDumpGoldenMasterTest extends GoldenMasterTestCase
{

	/**
	 * @return GoldenMaster The master this test asserts against
	 */
	protected function newMaster()
	{
		return new OtlDumpGoldenMaster();
	}

	/**
	 * @return string The composer script that rewrites its fixtures
	 */
	protected function updateCommand()
	{
		return 'otldump:update';
	}
}
