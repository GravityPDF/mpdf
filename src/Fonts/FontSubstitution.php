<?php

namespace Mpdf\Fonts;

use Mpdf\Fonts\Color\ColorFormats;
use Mpdf\Mpdf;
use Mpdf\Strict;
use Mpdf\Unicode\Emoji;

/**
 * Which backup font Mpdf::SubstituteCharsMB() moves a run of text into, and whether an emoji the
 * current font can draw should move at all.
 */
class FontSubstitution
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var array[] What metrics() read of each font the document had not loaded, by family, so it is
	 *              not read again for every emoji
	 */
	private $tried = [];

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 */
	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Whether an emoji the current font could draw should still be drawn in another.
	 *
	 * An emoji the current font lacks any part of goes to the backup fonts as any other character
	 * does. One it has all of stays, unless it asks for a presentation the font cannot give and a
	 * backup font can: colour from a colour font, or text from one that is not.
	 *
	 * @param int[]  $emoji        The emoji's codepoints
	 * @param string $presentation One of the Emoji::PRESENTATION_ constants
	 *
	 * @return bool
	 */
	public function emojiWantsAnotherFont(array $emoji, $presentation)
	{
		if (!$this->fontCovers($this->drawnWidths($this->mpdf->CurrentFont), $emoji)) {
			return true;
		}

		if ($presentation === Emoji::PRESENTATION_DEFAULT || ColorFormats::drawsInColor($this->mpdf->CurrentFont, $this->mpdf) === ($presentation === Emoji::PRESENTATION_EMOJI)) {
			return false;
		}

		list(, $preferred) = $this->backupFontOrder($presentation);
		foreach ($preferred as $family) {
			if ($this->fontCovers($this->widths($family), $emoji)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $cw         A font's character widths
	 * @param int[]  $codepoints
	 *
	 * @return bool Whether the font has every one of them that draws anything
	 */
	public function fontCovers($cw, array $codepoints)
	{
		foreach ($codepoints as $char) {
			if (!Emoji::isFormatting($char) && !$this->mpdf->_charDefined($cw, $char)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The backup fonts in the order a run is offered to them.
	 *
	 * A run starting with an emoji that asks for colour is offered to the colour fonts first, and one
	 * asking for text to the others first; each keeps its configured order within its group. Any other
	 * run takes the fonts in the order they were configured.
	 *
	 * @param string $presentation What the run's first emoji asks for, one of the
	 *                             Emoji::PRESENTATION_ constants
	 *
	 * @return array [every family in order, the families moved to the front for the presentation]
	 */
	public function backupFontOrder($presentation)
	{
		$families = (array) $this->mpdf->backupSubsFont;
		if ($presentation === Emoji::PRESENTATION_DEFAULT) {
			return [$families, []];
		}

		$wantsColor = $presentation === Emoji::PRESENTATION_EMOJI;
		$preferred = [];
		$rest = [];
		foreach ($families as $family) {
			if ($family != $this->mpdf->currentfontfamily && $this->isColorFont($family) === $wantsColor) {
				$preferred[] = $family;
			} else {
				$rest[] = $family;
			}
		}

		return [array_merge($preferred, $rest), $preferred];
	}

	/**
	 * The character widths of a font the substitution scan might move text into. A font that draws
	 * nothing here has no widths - see drawnWidths().
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return string|array|null Null where SetFont() would not add a font under that name
	 */
	public function widths($family)
	{
		$font = $this->metrics($family);

		return $font === null ? null : $this->drawnWidths($font);
	}

	/**
	 * @param string $family The font's key in fontdata
	 *
	 * @return bool Whether a backup font is drawn in colour
	 */
	private function isColorFont($family)
	{
		$font = $this->metrics($family);

		return $font !== null && ColorFormats::drawsInColor($font, $this->mpdf);
	}

	/**
	 * What the scan needs to know of a font: the document's own entry for it where the document has
	 * loaded it, and otherwise its metrics, read without adding it to the document so that asking about
	 * a font the scan then passes over leaves no trace.
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return array|null The font's 'cw', and 'colorFormats' and 'hasOutlines' where it has them, or null
	 *                    where SetFont() would not add a font under that name: a core font, a fonttrans
	 *                    alias, or a family with no regular font
	 */
	private function metrics($family)
	{
		if (isset($this->mpdf->fonts[$family])) {
			unset($this->tried[$family]);

			return $this->mpdf->fonts[$family];
		}

		if (!array_key_exists($family, $this->tried)) {
			$this->tried[$family] = $this->unloadedMetrics($family);
		}

		return $this->tried[$family];
	}

	/**
	 * @param string $family The font's key in fontdata, not loaded in the document
	 *
	 * @return array|null What metrics() gives for the font
	 */
	private function unloadedMetrics($family)
	{
		if ($this->mpdf->onlyCoreFonts || !empty($this->mpdf->fonttrans[$family])) {
			return null;
		}

		if (in_array($family, $this->mpdf->available_CJK_fonts, true)) {
			$cw = $this->mpdf->cjkWidths($family);

			return $cw === null ? null : ['cw' => $cw];
		}

		if (!in_array($family, $this->mpdf->available_unifonts, true)) {
			return null;
		}

		return array_intersect_key(
			$this->mpdf->fontMetrics($family, ''),
			['cw' => true, 'colorFormats' => true, 'hasOutlines' => true]
		);
	}

	/**
	 * The widths of the characters a font draws. A font whose glyphs exist only in a colour format the
	 * document may not draw has none: to the substitution scan it lacks every character, so a backup
	 * font that has them draws them instead. Its own widths still lay out what is left in it.
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 *
	 * @return string
	 */
	public function drawnWidths(array $font)
	{
		return ColorFormats::blank($font, $this->mpdf) ? '' : $font['cw'];
	}

}
