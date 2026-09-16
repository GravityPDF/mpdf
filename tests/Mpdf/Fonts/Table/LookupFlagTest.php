<?php

namespace Mpdf\Fonts\Table;

class LookupFlagTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Marks 00300-00302, 00301 alone in mark attachment class 1 and in mark glyph set 0
	 *
	 * @var LookupFlag
	 */
	private $lookupFlag;

	public function set_up()
	{
		$this->lookupFlag = new LookupFlag('test', [
			'GlyphClassMarks' => ' 00300| 00301| 00302',
			'GlyphClassLigatures' => ' 0FB01',
			'GlyphClassBases' => ' 00041| 00042',
			'MarkAttachmentType' => [1 => ' 00300| 00302'],
			'MarkGlyphSets' => [0 => ' 00301'],
		]);
	}

	public function testAFlagThatSkipsNothingNamesNothing()
	{
		$this->assertSame([], LookupFlag::skipped(0));
		$this->assertSame('', $this->lookupFlag->glyphs(0x0001, ''));
		$this->assertFalse($this->lookupFlag->skips(0, '00300', ''));
	}

	/**
	 * The mark class comes first and the rest follow in bit order, which is the order the parser has
	 * always written them to the cached GSUB data
	 */
	public function testMarksLigaturesAndBasesAreSkippedInThatOrder()
	{
		$this->assertSame([LookupFlag::MARKS, LookupFlag::LIGATURES, LookupFlag::BASES], LookupFlag::skipped(0x000E));
		$this->assertSame(' 00300| 00301| 00302| 0FB01| 00041| 00042', $this->lookupFlag->glyphs(0x000E, ''));
		$this->assertSame(' 0FB01| 00041| 00042', $this->lookupFlag->glyphs(0x0006, ''));
	}

	/**
	 * The combination the dump used to disagree on. The parser and the shaper let IgnoreMarks give way
	 * to the attachment class and the dump did not; see GravityPDF/mpdf#175 for why the spec would
	 * rather it did not.
	 */
	public function testIgnoreMarksGivesWayToAMarkAttachmentClass()
	{
		$this->assertSame([LookupFlag::MARKS_OUTSIDE_ATTACHMENT_CLASS], LookupFlag::skipped(0x0108));
		$this->assertSame(' 00300| 00302', $this->lookupFlag->glyphs(0x0108, ''));
		$this->assertTrue($this->lookupFlag->skips(0x0108, '00300', ''));
		$this->assertFalse($this->lookupFlag->skips(0x0108, '00301', ''));
	}

	/**
	 * Carlito and NATS name a mark attachment class with no MarkAttachClassDef at all. No mark is in a
	 * class nothing defines, so every mark is outside it.
	 */
	public function testAnUndefinedMarkAttachmentClassSkipsEveryMark()
	{
		$this->assertSame(2, LookupFlag::attachmentClass(0x0218));
		$this->assertSame(' 00300| 00301| 00302', $this->lookupFlag->glyphs(0x0200, ''));
		$this->assertTrue($this->lookupFlag->skips(0x0200, '00301', ''));
	}

	/**
	 * UseMarkFilteringSet skips the marks outside the set, not the set
	 */
	public function testAMarkFilteringSetSkipsTheMarksOutsideIt()
	{
		$this->assertSame([LookupFlag::MARKS_OUTSIDE_FILTERING_SET, LookupFlag::BASES], LookupFlag::skipped(0x0012));
		$this->assertSame(' 00300| 00302| 00041| 00042', $this->lookupFlag->glyphs(0x0012, 0));
		$this->assertTrue($this->lookupFlag->skips(0x0010, '00302', 0));
		$this->assertFalse($this->lookupFlag->skips(0x0010, '00301', 0));
		$this->assertFalse($this->lookupFlag->skips(0x0010, '00041', 0));
	}

	/**
	 * A set wins over an attachment class, for the list and the per-glyph test alike. Here the two
	 * disagree about 00300, which the class skips and the set does not.
	 */
	public function testAMarkFilteringSetWinsOverAMarkAttachmentClass()
	{
		$lookupFlag = new LookupFlag('test', [
			'GlyphClassMarks' => ' 00300| 00301| 00302',
			'GlyphClassLigatures' => '',
			'GlyphClassBases' => '',
			'MarkAttachmentType' => [1 => ' 00300| 00302'],
			'MarkGlyphSets' => [0 => ' 00300| 00301'],
		]);

		$this->assertSame([LookupFlag::MARKS_OUTSIDE_FILTERING_SET], LookupFlag::skipped(0x0118));
		$this->assertSame(' 00302', $lookupFlag->glyphs(0x0110, 0));
		$this->assertFalse($lookupFlag->skips(0x0110, '00300', 0));
		$this->assertTrue($lookupFlag->skips(0x0110, '00302', 0));
	}

	/**
	 * A mark glyph set GDEF does not define is refused whenever the flag names one, even where
	 * IgnoreMarks makes it moot
	 */
	public function testAnUndefinedMarkFilteringSetIsRefused()
	{
		$this->expectException('Mpdf\Exception\FontException');
		$this->expectExceptionMessage('Font "test" uses mark filtering set 3, which GDEF does not define');

		$this->lookupFlag->skips(0x0018, '00041', 3);
	}

	/**
	 * An empty class adds no separator of its own, but one it follows still gets its "|" - which is
	 * how the ignore strings in the cached GSUB data have always read
	 */
	public function testAnEmptyClassIsJoinedAsTheCachedDataHasIt()
	{
		$gdef = [
			'GlyphClassMarks' => '',
			'GlyphClassLigatures' => '',
			'GlyphClassBases' => '',
			'MarkAttachmentType' => [],
			'MarkGlyphSets' => [],
		];

		$basesOnly = new LookupFlag('test', array_merge($gdef, ['GlyphClassBases' => ' 00041']));
		$ligaturesOnly = new LookupFlag('test', array_merge($gdef, ['GlyphClassLigatures' => ' 0FB01']));

		$this->assertSame(' 00041', $basesOnly->glyphs(0x000A, ''));
		$this->assertSame(' 0FB01|', $ligaturesOnly->glyphs(0x0006, ''));
	}

}
