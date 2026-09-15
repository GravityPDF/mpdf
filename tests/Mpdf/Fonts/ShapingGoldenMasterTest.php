<?php

namespace Mpdf\Fonts;

/**
 * What applyOTL() makes of every run, for every font in tests/data/ttf.
 *
 * The gate for splitting applyOTL: whatever shape the 1,070 lines end up in, the substitutions, the
 * groups and the positioning they produce have to be the ones they produce now. The tests that cover
 * shaping otherwise name one rule in one font each, which is thinner than what guarded the
 * dispatcher split in #81, and this is what makes the difference up.
 */
class ShapingGoldenMasterTest extends GoldenMasterTestCase
{

	/**
	 * @return GoldenMaster The master this test asserts against
	 */
	protected function newMaster()
	{
		return new ShapingGoldenMaster();
	}

	/**
	 * @return string The composer script that rewrites its fixtures
	 */
	protected function updateCommand()
	{
		return 'shaping:update';
	}
}
