<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;
use Mpdf\Strict;

/**
 * What the substitution scan in Mpdf::SubstituteCharsMB() and Mpdf::SubstituteCharsNonCore() knows of
 * a backup font before it moves a run of text into it.
 */
class FontSubstitution
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var array What metrics() read of each font the document has not loaded, by family, so it is not
	 *            read again for every run
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

		return array_intersect_key($this->mpdf->fontMetrics($family, ''), ['cw' => true]);
	}

}
