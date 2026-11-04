<?php

namespace Mpdf\Fonts;

use Mpdf\Fonts\Color\ColorFormats;
use Mpdf\Log\Context;
use Mpdf\Mpdf;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Mpdf\Strict;
use Mpdf\Unicode\Emoji;
use Psr\Log\LoggerInterface;

/**
 * Which backup font Mpdf::SubstituteCharsMB() moves a run of text into, and whether an emoji the
 * current font can draw should move at all.
 */
class FontSubstitution implements \Psr\Log\LoggerAwareInterface
{

	use Strict;
	use PsrLogAwareTrait;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Fonts\FontCache
	 */
	private $fontCache;

	/**
	 * @var array What metrics() read of each font the document has not loaded, by family, so it is not
	 *            read again for every emoji
	 */
	private $tried = [];

	/**
	 * @var array[] The 'path' of the package ranges file each unloaded font was read from, and the 'size'
	 *              of font it was written for, by family, to compare with the font once it loads
	 */
	private $rangesFiles = [];

	/**
	 * @param \Mpdf\Mpdf               $mpdf
	 * @param \Mpdf\Fonts\FontCache    $fontCache
	 * @param \Psr\Log\LoggerInterface $logger
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache, LoggerInterface $logger)
	{
		$this->mpdf = $mpdf;
		$this->fontCache = $fontCache;
		$this->logger = $logger;
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
	 * What the scan needs to know of a font: the document's entry for it where the document has loaded
	 * it, and otherwise its metrics, read without adding it to the document.
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
			if (isset($this->rangesFiles[$family])) {
				$this->checkRangesFileSize($family, $this->rangesFiles[$family]);
			}
			unset($this->tried[$family], $this->rangesFiles[$family]);

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

		$ranges = $this->rangesMetrics($family);
		if ($ranges !== null) {
			return $ranges;
		}

		return array_intersect_key(
			$this->mpdf->fontMetrics($family, ''),
			['cw' => true, 'colorFormats' => true, 'hasOutlines' => true]
		);
	}

	/**
	 * The font's widths from a ranges file, so the font itself is neither found nor read. The first of
	 * these that holds a valid file wins:
	 *
	 * 1. <font file>.ranges.php in the directory of the package the family is registered from
	 * 2. <family>.ranges.json in the font cache, written from the font installed here
	 *
	 * PHP is included only from the family's own package, which is code: never from another package, a
	 * font directory, which can be where downloads land, or the writable font cache.
	 *
	 * @param string $family The font's key in fontdata, not loaded in the document
	 *
	 * @return array|null What metrics() gives for the font
	 */
	private function rangesMetrics($family)
	{
		$bmpOnly = in_array($family, (array) $this->mpdf->BMPonly, true);
		$fontdata = $this->mpdf->fontdata[$family];

		$ranges = $this->packageRanges($family, $fontdata);
		if ($ranges === null) {
			$ranges = MetricsGenerator::cachedRanges($this->fontCache, $family, $fontdata, $bmpOnly, $this->logger);
		}

		return $ranges === null ? null : $ranges->metrics($bmpOnly);
	}

	/**
	 * The ranges file the family's package ships for its regular font, where it was written for a font
	 * of the size the package's own font file is. A font the package fetches on demand may not be there
	 * yet, and then the file is trusted until the font loads. The sizes are compared before the file is
	 * validated, so a file of another font builds no widths.
	 *
	 * @param string $family
	 * @param array  $fontdata The family's entry in fontdata
	 *
	 * @return CharacterRanges|null Null also for a family registered from no package, or from a
	 *                              collection, whose font file is more than one font
	 */
	private function packageRanges($family, array $fontdata)
	{
		$packages = $this->mpdf->getFontPackageDirectories();
		if (!isset($packages[$family]) || !empty($fontdata['TTCfontID']['R'])) {
			return null;
		}

		$font = $packages[$family] . '/' . $fontdata['R'];
		$path = $font . '.ranges.php';
		if (!is_file($path)) {
			return null;
		}

		$data = include $path;
		$size = @filesize($font);
		if ($size !== false && isset($data['size']) && is_int($data['size']) && $data['size'] !== $size) {
			$this->warnOfFontSize($path, $data['size'], sprintf('"%s"', $font), $size, 'is ignored');

			return null;
		}

		$ranges = CharacterRanges::fromData($data, $path, $this->logger);
		if ($ranges !== null) {
			$this->rangesFiles[$family] = ['path' => $path, 'size' => $ranges->size()];
		}

		return $ranges;
	}

	/**
	 * Warns where the font loaded is not the size the shipped ranges file it was chosen by was written
	 * for, as when the finder found the font outside the package: decisions already made from that file
	 * in this document may be wrong.
	 *
	 * @param string $family
	 * @param array  $rangesFile What packageRanges() recorded of the file
	 */
	private function checkRangesFileSize($family, array $rangesFile)
	{
		$size = isset($this->mpdf->FontFiles[$family]['length1']) ? $this->mpdf->FontFiles[$family]['length1'] : null;
		if ($size === null || (int) $size === $rangesFile['size']) {
			return;
		}

		$this->warnOfFontSize($rangesFile['path'], $rangesFile['size'], sprintf('the "%s" font loaded', $family), (int) $size, 'may have moved text into the wrong font');
	}

	/**
	 * Warns that a package's ranges file was written for a font of another size than the one it is used for
	 *
	 * @param string $path    The ranges file
	 * @param int    $written The size of font it was written for
	 * @param string $font    The font, as the warning names it
	 * @param int    $size    The size of that font
	 * @param string $outcome What came of the file
	 */
	private function warnOfFontSize($path, $written, $font, $size, $outcome)
	{
		$this->logger->warning(sprintf(
			'Character ranges file "%s" %s: it was written for a font of %d bytes, but %s is %d bytes. Write the file again with vendor/bin/mpdf-font-ranges.',
			$path,
			$outcome,
			$written,
			$font,
			$size
		), ['context' => Context::FONTS]);
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
