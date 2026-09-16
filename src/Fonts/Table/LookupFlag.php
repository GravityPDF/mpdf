<?php

namespace Mpdf\Fonts\Table;

use Mpdf\Exception\FontException;

/**
 * The glyphs a lookup passes over, from its LookupFlag, its markFilteringSet and GDEF.
 *
 * A lookup's rules are matched as though the glyphs its flag names were not in the run. What those
 * glyphs are is GDEF's to say, so this holds a font's GDEF classes as the parser caches them: runs of
 * space-prefixed, "|"-separated hex, " 00641| 00642".
 *
 * Which classes a flag names is decided in skipped() and nowhere else. The parser, the shaper and the
 * dump each want the answer in a different shape - a pattern for the cached GSUB data, a set to match
 * against as text is shaped, and names for a person to read - and each projects it from there.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#lookup-table
 */
class LookupFlag
{

	const IGNORE_BASE_GLYPHS = 0x0002;
	const IGNORE_LIGATURES = 0x0004;
	const IGNORE_MARKS = 0x0008;
	const USE_MARK_FILTERING_SET = 0x0010;
	const MARK_ATTACHMENT_CLASS_FILTER = 0xFF00;

	const MARKS = 'marks';
	const MARKS_OUTSIDE_FILTERING_SET = 'marksOutsideFilteringSet';
	const MARKS_OUTSIDE_ATTACHMENT_CLASS = 'marksOutsideAttachmentClass';
	const LIGATURES = 'ligatures';
	const BASES = 'bases';

	/**
	 * @var string Names the font in an exception
	 */
	private $fontkey;

	/**
	 * @var array GlyphClassMarks, GlyphClassLigatures, GlyphClassBases, MarkAttachmentType and
	 *            MarkGlyphSets, as the parser writes them to the GDEF cache
	 */
	private $gdef;

	/**
	 * @param string $fontkey
	 * @param array  $gdef
	 */
	public function __construct($fontkey, array $gdef)
	{
		$this->fontkey = $fontkey;
		$this->gdef = $gdef;
	}

	/**
	 * Which classes of glyph a lookup flag says to skip.
	 *
	 * At most one of the three mark classes, since each is a subset of the marks and they would
	 * otherwise have to be reconciled:
	 *
	 * - IgnoreMarks gives way to a mark attachment class. The spec reads the bits as independent, which
	 *   skips every mark, but mPDF has always let the class win (see #175); no font in tests/data/ttf
	 *   sets both.
	 * - A mark filtering set wins over a mark attachment class, as HarfBuzz has it.
	 *
	 * @param int $flag The lookup's LookupFlag
	 *
	 * @return string[] Any of the class constants, the mark class first where there is one
	 */
	public static function skipped($flag)
	{
		$skipped = [];

		if (($flag & self::IGNORE_MARKS) && !($flag & self::MARK_ATTACHMENT_CLASS_FILTER)) {
			$skipped[] = self::MARKS;
		} elseif ($flag & self::USE_MARK_FILTERING_SET) {
			$skipped[] = self::MARKS_OUTSIDE_FILTERING_SET;
		} elseif ($flag & self::MARK_ATTACHMENT_CLASS_FILTER) {
			$skipped[] = self::MARKS_OUTSIDE_ATTACHMENT_CLASS;
		}

		if ($flag & self::IGNORE_LIGATURES) {
			$skipped[] = self::LIGATURES;
		}

		if ($flag & self::IGNORE_BASE_GLYPHS) {
			$skipped[] = self::BASES;
		}

		return $skipped;
	}

	/**
	 * Every glyph a lookup skips, in GDEF's own format.
	 *
	 * @param int        $flag             The lookup's LookupFlag
	 * @param int|string $markFilteringSet The mark glyph set it names, or '' where it names none
	 *
	 * @return string Empty where the flag skips nothing
	 */
	public function glyphs($flag, $markFilteringSet)
	{
		$this->checkMarkFilteringSet($flag, $markFilteringSet);

		$glyphs = '';
		foreach (self::skipped($flag) as $class) {
			if ($glyphs) {
				$glyphs .= '|';
			}
			$glyphs .= $this->glyphsOf($class, $flag, $markFilteringSet);
		}

		return $glyphs;
	}

	/**
	 * Whether a lookup skips one glyph.
	 *
	 * The same answer as looking for the glyph in glyphs(), without building the list: the shaper asks
	 * once per glyph a subtable is offered, and a font's marks can run to tens of kilobytes of text.
	 *
	 * @param int        $flag             The lookup's LookupFlag
	 * @param string     $glyph            The glyph, as hex
	 * @param int|string $markFilteringSet The mark glyph set it names, or '' where it names none
	 *
	 * @return bool
	 */
	public function skips($flag, $glyph, $markFilteringSet)
	{
		$this->checkMarkFilteringSet($flag, $markFilteringSet);

		foreach (self::skipped($flag) as $class) {
			if ($class === self::MARKS_OUTSIDE_FILTERING_SET) {
				$skips = strpos($this->gdef['GlyphClassMarks'], $glyph) && !strpos($this->gdef['MarkGlyphSets'][$markFilteringSet], $glyph);
			} else {
				$skips = strpos($this->glyphsOf($class, $flag, $markFilteringSet), $glyph);
			}

			if ($skips) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A font naming a mark glyph set GDEF does not define is malformed, and guessing which marks it
	 * meant would shape silently wrong. Checked whenever the flag names a set, even where IgnoreMarks
	 * makes the set moot.
	 *
	 * @throws \Mpdf\Exception\FontException
	 */
	public function checkMarkFilteringSet($flag, $markFilteringSet)
	{
		if (($flag & self::USE_MARK_FILTERING_SET) && !isset($this->gdef['MarkGlyphSets'][$markFilteringSet])) {
			throw new FontException(sprintf('Font "%s" uses mark filtering set %s, which GDEF does not define', $this->fontkey, $markFilteringSet));
		}
	}

	private function glyphsOf($class, $flag, $markFilteringSet)
	{
		switch ($class) {
			case self::MARKS:
				return $this->gdef['GlyphClassMarks'];
			case self::MARKS_OUTSIDE_FILTERING_SET:
				return $this->marksOutsideFilteringSet($this->gdef['MarkGlyphSets'][$markFilteringSet]);
			case self::MARKS_OUTSIDE_ATTACHMENT_CLASS:
				return $this->marksOutsideAttachmentClass($flag >> 8);
			case self::LIGATURES:
				return $this->gdef['GlyphClassLigatures'];
			default:
				return $this->gdef['GlyphClassBases'];
		}
	}

	/**
	 * UseMarkFilteringSet means "skip every mark except those in the given mark glyph set", so the
	 * glyphs to skip are the marks minus that set - not the set itself.
	 */
	private function marksOutsideFilteringSet($set)
	{
		$keep = [];
		$inSet = [];
		foreach (explode('|', $set) as $glyph) {
			$inSet[trim($glyph)] = true;
		}

		foreach (explode('|', $this->gdef['GlyphClassMarks']) as $glyph) {
			$glyph = trim($glyph);
			if ($glyph !== '' && !isset($inSet[$glyph])) {
				$keep[] = $glyph;
			}
		}

		return $keep ? ' ' . implode('| ', $keep) : '';
	}

	/**
	 * The parser keeps MarkAttachmentType as the marks outside each class already.
	 *
	 * A font may name a class GDEF does not define - Carlito and NATS set the flag without a
	 * MarkAttachClassDef table at all - and then no mark is in the class, so the lookup skips every
	 * one of them.
	 */
	private function marksOutsideAttachmentClass($class)
	{
		return isset($this->gdef['MarkAttachmentType'][$class]) ? $this->gdef['MarkAttachmentType'][$class] : $this->gdef['GlyphClassMarks'];
	}
}
