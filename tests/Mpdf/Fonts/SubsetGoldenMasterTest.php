<?php

namespace Mpdf\Fonts;

/**
 * Every byte of every font program the subsetter emits, for every font in tests/data/ttf.
 *
 * The gate for moving the subsetter off the parser: taking those three methods and their
 * byte-writing primitives out of TTFontFile must leave all of this untouched. Nothing else in the
 * suite reads the emitted program - the snapshots compare rendered pages, which a subtly wrong font
 * table survives - so this is the only test that can tell.
 */
class SubsetGoldenMasterTest extends GoldenMasterTestCase
{

	/**
	 * @return GoldenMaster The master this test asserts against
	 */
	protected function newMaster()
	{
		return new SubsetGoldenMaster();
	}

	/**
	 * @return string The composer script that rewrites its fixtures
	 */
	protected function updateCommand()
	{
		return 'subset:update';
	}
}
