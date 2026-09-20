<?php

namespace Mpdf;

/**
 * A BaseRecord states one anchor per mark class, and a Mark2Record the same, and the spec lets any of
 * them be NULL: that base or that mark offers nothing for marks of that class to attach to. Adding a
 * NULL to the start of the array it is measured from lands back on the array's own header, where the
 * record count reads as an Anchor format and the two offsets after it as coordinates, so the mark was
 * placed tens of ems away rather than left alone.
 *
 * Both fonts are already in the repository, and hb-shape is the oracle for where the mark belongs.
 */
class NullMarkAnchorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Noto Sans GPOS Lookup 3 is a mark-to-base whose BaseRecord for A leaves the class that COMBINING
	 * TILDE OVERLAY belongs to NULL. hb-shape 14.3.1 gives "[A=0+639|uni0334=0+0]": the mark keeps the
	 * position it already had.
	 */
	public function testAMarkIsLeftAloneWhereItsBaseStatesNoAnchorForItsClass()
	{
		$mpdf = new PositionRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosans' => [
				'R' => 'NotoSans-Regular.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosans',
		]);

		$mpdf->WriteHTML('<p>A&#x334;</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

	/**
	 * FreeSerif GPOS Lookup 21 is the mkmk of the Hebrew script, and the Mark2Record for PATAH leaves
	 * the class SHALSHELET belongs to NULL. Both marks still attach to the ALEF by mark-to-base, which
	 * is the placement hb-shape gives: "[shalshelethebrew=0@86,-7+0|patahhebrew=0@100,0+0|alefhebrew=0+537]".
	 */
	public function testAMarkIsLeftAloneWhereTheMarkItStacksOnStatesNoAnchorForItsClass()
	{
		$mpdf = new PositionRecordingMpdf(['default_font' => 'freeserif']);

		$mpdf->WriteHTML('<p>&#x5d0;&#x5b7;&#x593;</p>');

		$this->assertSame([[
			['BaseWidth' => 537, 'XPlacement' => 86, 'YPlacement' => -7, 'wDir' => 'RTL'],
			['BaseWidth' => 537, 'XPlacement' => 100, 'YPlacement' => 0, 'wDir' => 'RTL'],
		]], $mpdf->drawnPositions);
	}

}
