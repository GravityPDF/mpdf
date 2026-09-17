<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Utils\UtfString;

/**
 * A glyph is in a GDEF class where the class names it, and not where its hex turns up inside another
 * glyph's. GlyphString::of() writes a plane 16 character six digits wide, so U+100300 holds both
 * U+10030 and U+0300.
 *
 * NotoSans-PlaneSixteenMark-Synthetic is Noto Sans 2.007 (OFL 1.1) cut down in fontTools 4.59.2 to
 * space, A, grave, asciitilde, gravecomb and acutecomb, with two glyphs added: u10030, drawn as A and
 * mapped to U+10030, and u100300, drawn as gravecomb and mapped to U+100300. Its layout tables are
 * replaced, and name IDs 1, 4 and 6 renamed. GDEF classes gravecomb and u10030 as bases and acutecomb
 * and u100300 as marks. A 'ccmp' lookup that sets IgnoreMarks substitutes grave for gravecomb, and a
 * 'mark' lookup attaches acutecomb to u10030. `hb-shape` 14.3.1 draws A U+0300 as `A grave`, and
 * attaches the mark in U+10030 U+0301 at x -339, which is the 300 these tests expect less the base's
 * advance of 639.
 *
 * TTFontFile reads no character past U+2FFFF out of cmap, and maps a glyph it would have reached into
 * the Private Use Area instead, so no font it parses puts a six-digit glyph in a class: this one's
 * u100300 is cached as U+E001. The shaper is handed the classes as the font states them.
 */
class GdefClassMembershipTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONTKEY = 'planesixteenmark';

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var Otl
	 */
	private $otl;

	protected function set_up()
	{
		parent::set_up();

		// A fresh directory each time: the font cache is keyed by the family name and rewritten only
		// when the file's size changes, so a cache one run left behind is read by the next
		$tempDir = sys_get_temp_dir() . '/mpdf-gdef-class-membership-' . uniqid('', true);

		$this->mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $tempDir,
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [self::FONTKEY => [
				'R' => 'NotoSans-PlaneSixteenMark-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => self::FONTKEY,
		]);

		$this->otl = new Otl($this->mpdf, new FontCache(new Cache($tempDir . '/mpdf/ttfontdata')));
	}

	protected function tear_down()
	{
		$this->mpdf->cleanup();

		parent::tear_down();
	}

	public function testTheParserClassesThePlaneSixteenMarkAsThePrivateUseCharacterItMapsItTo()
	{
		$this->shape([0x41]);

		$this->assertSame(' 00301| 0E001', $this->otl->GDEFdata[self::FONTKEY]['GlyphClassMarks']);
	}

	public function dataRuns()
	{
		return [
			'IgnoreMarks does not skip U+0300, whose hex U+100300 ends with' => [
				[0x41, 0x0300],
				[[0x41, 'C', null], [0x60, 'C', null]],
			],
			'a mark attaches to U+10030, whose hex U+100300 begins with' => [
				[0x10030, 0x0301],
				[[0x10030, 'C', null], [0x0301, 'M', ['BaseWidth' => 639, 'XPlacement' => 300, 'YPlacement' => 0]]],
			],
		];
	}

	/**
	 * @dataProvider dataRuns
	 */
	public function testAGlyphIsNotTakenForTheMarkItsHexIsPartOf($codepoints, $expected)
	{
		// Before the shaper first reads GDEF, which it then keeps for the font
		$this->otl->GDEFdata[self::FONTKEY] = [
			'GlyphClassBases' => ' 00020| 00041| 00060| 0007E| 00300| 10030',
			'GlyphClassMarks' => ' 00301| 100300',
			'GlyphClassLigatures' => '',
			'GlyphClassComponents' => '',
			'MarkGlyphSets' => [],
			'MarkAttachmentType' => [],
		];

		$this->assertSame($expected, $this->shape($codepoints));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return array[] The codepoint, the group and the positioning of each glyph of the run
	 */
	private function shape($codepoints)
	{
		$text = '';
		foreach ($codepoints as $codepoint) {
			$text .= UtfString::code2utf($codepoint);
		}
		$this->otl->applyOTL($text, 0xFF);

		$run = [];
		foreach ($this->otl->OTLdata['char_data'] as $i => $character) {
			$run[] = [
				$character['uni'],
				$this->otl->OTLdata['group'][$i],
				isset($this->otl->OTLdata['GPOSinfo'][$i]) ? $this->otl->OTLdata['GPOSinfo'][$i] : null,
			];
		}

		return $run;
	}

}
