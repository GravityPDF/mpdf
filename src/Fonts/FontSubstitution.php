<?php

namespace Mpdf\Fonts;

use Mpdf\Log\Context;
use Mpdf\Mpdf;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Mpdf\Strict;
use Psr\Log\LoggerInterface;

/**
 * What the substitution scan in Mpdf::SubstituteCharsMB() and Mpdf::SubstituteCharsNonCore() knows of
 * a backup font before it moves a run of text into it, and which SIP font a Plane 2 character moves to.
 */
class FontSubstitution implements \Psr\Log\LoggerAwareInterface
{

	use Strict;
	use PsrLogAwareTrait;

	/**
	 * The first and last codepoints of Plane 2, the Supplementary Ideographic Plane
	 */
	const SIP_FIRST = 0x20000;
	const SIP_LAST = 0x2FFFF;

	/**
	 * Matches a Plane 2 character in UTF-8
	 */
	const SIP_PATTERN = '/[\x{20000}-\x{2FFFF}]/u';

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Fonts\FontCache
	 */
	private $fontCache;

	/**
	 * @var string The directory holding the ranges file of each font mPDF ships
	 */
	private $rangesDirectory;

	/**
	 * @var string The directory holding the fonts mPDF ships
	 */
	private $fontDirectory;

	/**
	 * @var string[]|null The font file of each family mPDF ships, by family, once it has been asked for
	 */
	private $shippedFonts;

	/**
	 * @var array What metrics() read of each font the document has not loaded, by family, so it is not
	 *            read again for every run
	 */
	private $tried = [];

	/**
	 * @var array[] The 'path' of the shipped ranges file each unloaded font was read from, and the 'size'
	 *              of font it was written for, by family, to compare with the font once it loads
	 */
	private $rangesFiles = [];

	/**
	 * @param \Mpdf\Mpdf               $mpdf
	 * @param \Mpdf\Fonts\FontCache    $fontCache
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param string|null              $rangesDirectory Where shipped ranges files are read from, for a test
	 * @param string|null              $fontDirectory   Where shipped fonts are compared with, for a test
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache, LoggerInterface $logger, $rangesDirectory = null, $fontDirectory = null)
	{
		$this->mpdf = $mpdf;
		$this->fontCache = $fontCache;
		$this->logger = $logger;
		$this->rangesDirectory = $rangesDirectory === null ? CharacterRanges::SHIPPED_RANGES_DIRECTORY : $rangesDirectory;
		$this->fontDirectory = $fontDirectory === null ? CharacterRanges::SHIPPED_FONT_DIRECTORY : $fontDirectory;
	}

	/**
	 * Only Plane 2 moves to a SIP font, so Plane 3 and above never depend on checkSIP.
	 *
	 * @param int $char A codepoint
	 *
	 * @return string|null The current font's sip-ext font, else backupSIPFont, the first that has the
	 *                     character, or null
	 */
	public function sipFontFor($char)
	{
		if (!empty($this->mpdf->CurrentFont['sipext']) && $this->sipFontHas($this->mpdf->CurrentFont['sipext'], $char)) {
			return $this->mpdf->CurrentFont['sipext'];
		}

		$backup = $this->mpdf->backupSIPFont;
		if (!empty($backup) && $this->mpdf->currentfontfamily != $backup && $this->sipFontHas($backup, $char)) {
			return $backup;
		}

		return null;
	}

	/**
	 * @param string $family The font's key in fontdata
	 * @param int    $char   A codepoint
	 *
	 * @return bool Whether the character is in Plane 2 and the font's widths have it, read from its
	 *              ranges file where the document has not loaded it
	 */
	public function sipFontHas($family, $char)
	{
		if ($char < self::SIP_FIRST || $char > self::SIP_LAST) {
			return false;
		}

		$cw = $this->widths($family);

		return $cw && $this->mpdf->_charDefined($cw, $char);
	}

	/**
	 * The character widths of a font the substitution scan might move text into.
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return string|array|null Null where SetFont() would not add a font under that name
	 */
	public function widths($family)
	{
		$font = $this->metrics($family);

		return $font === null ? null : $font['cw'];
	}

	/**
	 * What the scan needs to know of a font: the document's entry for it where the document has loaded
	 * it, and otherwise its metrics, read without adding it to the document.
	 *
	 * @param string $family The font's key in fontdata
	 *
	 * @return array|null The font's 'cw', or null where SetFont() would not add a font under that name:
	 *                    a core font, a fonttrans alias, or a family with no regular font
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

		return array_intersect_key($this->mpdf->fontMetrics($family, ''), ['cw' => true]);
	}

	/**
	 * The font's widths from a ranges file, so the font itself is neither found nor read. The first of
	 * these that holds a valid file wins:
	 *
	 * 1. data/character-ranges/<font file>.ranges.php, for a family as mPDF ships it
	 * 2. <family>.ranges.json in the font cache, written from the font installed here
	 *
	 * PHP is included only from mPDF's own data directory, which is code: never from a font directory,
	 * which can be where downloads land, or the writable font cache.
	 *
	 * @param string $family The font's key in fontdata, not loaded in the document
	 *
	 * @return array|null What metrics() gives for the font
	 */
	private function rangesMetrics($family)
	{
		$bmpOnly = in_array($family, (array) $this->mpdf->BMPonly, true);
		$fontdata = $this->mpdf->fontdata[$family];

		$ranges = $this->shippedRanges($family, $fontdata);
		if ($ranges === null) {
			$ranges = MetricsGenerator::cachedRanges($this->fontCache, $family, $fontdata, $bmpOnly, $this->logger);
		}

		return $ranges === null ? null : $ranges->metrics($bmpOnly);
	}

	/**
	 * The ranges file mPDF ships for the family's regular font, where it was written for a font of the
	 * size mPDF's own font file is. A font fetched on demand may not be there yet, and then the file is
	 * trusted until the font loads. The sizes are compared before the file is validated, so a file of
	 * another font builds no widths.
	 *
	 * @param string $family
	 * @param array  $fontdata The family's entry in fontdata
	 *
	 * @return CharacterRanges|null Null also for a family mPDF does not ship under that name and font
	 *                              file, or that reads from a collection, whose file is more than one font
	 */
	private function shippedRanges($family, array $fontdata)
	{
		if ($this->shippedFonts === null) {
			$this->shippedFonts = CharacterRanges::shippedFonts();
		}

		if (!isset($this->shippedFonts[$family]) || $this->shippedFonts[$family] !== $fontdata['R'] || !empty($fontdata['TTCfontID']['R'])) {
			return null;
		}

		$font = $this->fontDirectory . '/' . $fontdata['R'];
		$path = $this->rangesDirectory . '/' . $fontdata['R'] . '.ranges.php';
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
	 * for, as when the finder found the font outside mPDF's own: decisions already made from that file
	 * in this document may be wrong.
	 *
	 * @param string $family
	 * @param array  $rangesFile What shippedRanges() recorded of the file
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
	 * Warns that a shipped ranges file was written for a font of another size than the one it is used for
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
			'Character ranges file "%s" %s: it was written for a font of %d bytes, but %s is %d bytes.',
			$path,
			$outcome,
			$written,
			$font,
			$size
		), ['context' => Context::FONTS]);
	}

}
