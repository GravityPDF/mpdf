<?php

namespace Mpdf\Fonts;

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
	 * @var \Mpdf\Fonts\FontCache
	 */
	private $fontCache;

	/**
	 * @var string[] The character widths widths() read from the font cache for a backup font the
	 *               document has not loaded, by family, so they are not read again for every emoji
	 */
	private $widths = [];

	/**
	 * @param \Mpdf\Mpdf            $mpdf
	 * @param \Mpdf\Fonts\FontCache $fontCache
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache)
	{
		$this->mpdf = $mpdf;
		$this->fontCache = $fontCache;
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
		if (!$this->fontCovers($this->mpdf->CurrentFont['cw'], $emoji)) {
			return true;
		}

		if ($presentation === Emoji::PRESENTATION_DEFAULT || !empty($this->mpdf->CurrentFont['colorFormats']) === ($presentation === Emoji::PRESENTATION_EMOJI)) {
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
	 * The character widths of a font the substitution scan might move text into.
	 *
	 * Read from the font cache where the document has not loaded the font, so that trying a font does
	 * not add it to the document, and loaded where the cache does not have it.
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return string|null Null where there is no such font
	 */
	public function widths($family)
	{
		if (isset($this->mpdf->fonts[$family])) {
			return $this->mpdf->fonts[$family]['cw'];
		}

		if (!isset($this->widths[$family])) {
			$cw = $this->fontCache->loadIfPresent($family . '.cw.dat');
			if (null === $cw) {
				$this->loadFont($family);

				return isset($this->mpdf->fonts[$family]) ? $this->mpdf->fonts[$family]['cw'] : null;
			}

			$this->widths[$family] = $cw;
		}

		return $this->widths[$family];
	}

	/**
	 * Whether a backup font is a colour font. The font is loaded to ask, so AddFont() decides whether
	 * its cached metrics are still current.
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return bool
	 */
	private function isColorFont($family)
	{
		if (!isset($this->mpdf->fonts[$family])) {
			$this->loadFont($family);
		}

		return !empty($this->mpdf->fonts[$family]['colorFormats']);
	}

	/**
	 * Adds a font to the document without leaving it the current one
	 *
	 * @param string $family The font's key in fontdata
	 */
	private function loadFont($family)
	{
		$prevFontFamily = $this->mpdf->FontFamily;
		$prevFontStyle = $this->mpdf->currentfontstyle;
		$prevFontSizePt = $this->mpdf->FontSizePt;
		$this->mpdf->SetFont($family, '', '', false);
		$this->mpdf->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt, false);
	}

}
