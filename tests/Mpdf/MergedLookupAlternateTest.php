<?php

namespace Mpdf;

/**
 * A Lookup two selected features name is applied under the alternate the first of them asked for
 * (#257).
 *
 * Otl::_applyGSUBrules() keyed its Lookup list by Lookup and stored the feature tag as the value, so a
 * Lookup two selected features named kept whichever tag was written last - which is the one the
 * language system listed last, by way of readScriptsAndFeatures(). alternateWanted() then read the
 * alternate off that tag, so `font-feature-settings: "salt" 3, "ss04" 1` drew ss04's first alternate
 * and dropped the third the document asked for. The path now goes through lookupsForStage(), as the
 * per-feature paths do (#243), which takes the Lookup once and under the tag and alternate of the
 * first feature that names it, and lookupsInLookupListOrder() sorts what comes back.
 *
 * Only a LookupType 3 Alternate Substitution can show this, nothing else reading the alternate, and
 * of the fonts in packages/ and tests/data/ttf - 130 carry a GSUB, 37 of those a LookupType 3 - not
 * one has a LookupType 3 Lookup that two features of a single language system name. The nearest is
 * Noto Sans, where salt and ss04 both name Lookup 43 under latn, grek and cyrl - the collision #245
 * taught the parser to keep rather than drop - but Lookup 43 is a Single Substitution and ignores the
 * alternate entirely.
 *
 * NotoSans-SharedAlternate-Synthetic is an eight-glyph subset of that same Noto Sans Regular 2.007
 * (Google LLC, SIL OFL 1.1 with no Reserved Font Name, fsType 0) carrying a GSUB of two alternate
 * substitutions built for this test:
 *
 *   lookup 0  salt, then ss04   I -> [I.salt Iacute.salt Idieresis.salt]
 *   lookup 1  ss06              J -> [J.salt Jcircumflex.salt]
 *
 * The forms substituted in are the donor's own .salt glyphs; only the features that name them are
 * new. It keeps the donor's copyright and version strings and carries a name of its own, so that a
 * font called Noto Sans that is not Noto Sans cannot mislead. salt and ss04 both start at Lookup 0, so
 * the language system's order is what reaches Otl, and it lists salt first: the tag that used to win
 * was always ss04, whatever the document said. Lookup 1 is the control, one feature naming it and its
 * alternate never in doubt.
 *
 * `hb-shape` 14.3.1 draws the rows where one feature is selected the same way, and the rows where two
 * are selected as neither answer:
 *
 *   $ hb-shape --font-file=NotoSans-SharedAlternate-Synthetic.ttf --features=salt=3 --unicodes=0049,004A --no-positions
 *   [Idieresis.salt=0|J=1]
 *   $ hb-shape --font-file=NotoSans-SharedAlternate-Synthetic.ttf --features=salt=1,ss04=1 --unicodes=0049,004A --no-positions
 *   [I.salt=0|J=1]
 *   $ hb-shape --font-file=NotoSans-SharedAlternate-Synthetic.ttf --features=salt=3,ss04=1 --unicodes=0049,004A --no-positions
 *   [I=0|J=1]
 *
 * HarfBuzz carries the value on the glyph and not on the Lookup: each feature is given a field of mask
 * bits wide enough for its value, the fields of the features that merged are ORed together, and the
 * alternate is read back out of the merged field. Two values in one field overflow the alternate set,
 * which is why the third run draws nothing - as does every one of the nine pairs from 1,1 to 3,3 but
 * 1,1 itself, which is the one value needing no field of its own. mPDF has no per-glyph mask on this
 * path to read a value out of, so it honours what the document asked for first.
 */
class MergedLookupAlternateTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function dataRuns()
	{
		// The .salt forms the donor gives no code point are given one in the Private Use Area, in glyph
		// order: I.salt, Iacute.salt, Idieresis.salt, J.salt, Jcircumflex.salt
		return [
			'the alternate salt asks for, with only salt selected' => [
				'"salt" 3',
				[0xE002, 0x004A],
			],
			'the alternate ss04 asks for, with only ss04 selected' => [
				'"ss04" 1',
				[0xE000, 0x004A],
			],
			'both selected, salt named first and asking for the third' => [
				'"salt" 3, "ss04" 1',
				[0xE002, 0x004A],
			],
			'both selected, ss04 named first and asking for the third' => [
				'"ss04" 3, "salt" 1',
				[0xE002, 0x004A],
			],
			'both selected, salt named first and asking for the first' => [
				'"salt" 1, "ss04" 3',
				[0xE000, 0x004A],
			],
			'both selected, ss04 named first and asking for the first' => [
				'"ss04" 1, "salt" 3',
				[0xE000, 0x004A],
			],
			'the first feature asking for an alternate the set has not got, which cancels' => [
				'"salt" 9, "ss04" 1',
				[0x0049, 0x004A],
			],
			'the Lookup ss06 names alone' => [
				'"ss06" 2',
				[0x0049, 0xE004],
			],
			'neither feature selected' => [
				'normal',
				[0x0049, 0x004A],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAMergedLookupTakesTheAlternateTheFirstFeatureAskedFor($settings, $expected)
	{
		$this->assertSame($expected, $this->drawn($settings));
	}

	/**
	 * @return int[] the code points of the line as it is handed to the drawing code
	 */
	private function drawn($settings)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['notosanssharedalternatesynthetic' => [
				'R' => 'NotoSans-SharedAlternate-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => 'notosanssharedalternatesynthetic',
		]);
		$mpdf->WriteHTML("<p style='font-feature-settings:" . $settings . "'>IJ</p>");

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
