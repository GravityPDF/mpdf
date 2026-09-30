<?php

namespace Mpdf;

use Psr\Log\LoggerInterface;
use Mpdf\Css\RelativeFontValues;
use Mpdf\Log\Context as LogContext;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;

class SizeConverter implements \Psr\Log\LoggerAwareInterface
{

	use PsrLogAwareTrait;

	/**
	 * The units convert() reads after a number
	 *
	 * @var string[]
	 */
	private static $units = ['mm', 'cm', 'q', 'in', 'pt', 'pc', 'px', 'em', 'ex', 'ch', 'rem', 'vw', 'vh', 'vmin', 'vmax', '%'];

	private $dpi;

	private $defaultFontSize;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	public function __construct($dpi, $defaultFontSize, Mpdf $mpdf, LoggerInterface $logger)
	{
		$this->dpi = $dpi;
		$this->defaultFontSize = $defaultFontSize;
		$this->mpdf = $mpdf;
		$this->logger = $logger;
	}

	/**
	 * Depends of maxsize value to make % work properly. Usually maxsize == pagewidth
	 * For text $maxsize = $fontsize
	 * Setting e.g. margin % will use maxsize (pagewidth) and em will use fontsize
	 *
	 * @param mixed $size
	 * @param mixed $maxsize
	 * @param mixed $fontsize
	 * @param mixed $usefontsize Set false for e.g. margins - will ignore fontsize for % values
	 *
	 * @return float Final size in mm
	 */
	public function convert($size = 5, $maxsize = 0, $fontsize = false, $usefontsize = true)
	{
		$size = trim(strtolower((string) $size));
		$res = preg_match('/^(?P<size>[-+0-9.,]+([eE][-+]?[0-9]+)?)?(?P<unit>[%a-z-]+)?$/', $size, $parts);
		if (!$res) {
			// ignore definition
			$this->logger->warning(sprintf('Invalid size representation "%s"', $size), ['context' => LogContext::CSS_SIZE_CONVERSION]);
		}

		$unit = !empty($parts['unit']) ? $parts['unit'] : null;
		$size = !empty($parts['size']) ? (float) $parts['size'] : 0.0;

		switch ($unit) {
			case 'mm':
				// do nothing
				break;

			case 'cm':
				$size *= 10;
				break;

			case 'q':
				// quarter-millimetres
				$size *= 0.25;
				break;

			case 'pt':
				$size *= 1 / Mpdf::SCALE;
				break;

			case 'rem':
				// The legacy CSS mode reads rem against body, whose font size a table's replaces while it is written
				$root = $this->mpdf->cssMode === CssMode::STANDARD ? $this->mpdf->root_font_size : $this->mpdf->default_font_size;
				$size *= $root / Mpdf::SCALE;
				break;

			case '%':
			case '%%': // Issue2051
				if ($fontsize && $usefontsize) {
					$size *= $fontsize / 100;
				} else {
					$size *= $maxsize / 100;
				}
				break;

			case 'in':
				// mm in an inch
				$size *= 25.4;
				break;

			case 'pc':
				// PostScript picas
				$size *= 38.1 / 9;
				break;

			case 'ex':
			case 'ch':
				// Approximates "ex" as half of font height, and "ch" as half an em wide, as CSS does where the "0"
				// cannot be measured
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 0.5);
				break;

			case 'em':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 1);
				break;

			case 'vw':
				$size *= $this->mpdf->w / 100;
				break;

			case 'vh':
				$size *= $this->mpdf->h / 100;
				break;

			case 'vmin':
				$size *= min($this->mpdf->w, $this->mpdf->h) / 100;
				break;

			case 'vmax':
				$size *= max($this->mpdf->w, $this->mpdf->h) / 100;
				break;

			case 'thin':
				$size = 1 * (25.4 / $this->dpi);
				break;

			case 'medium':
				$size = 3 * (25.4 / $this->dpi);
				// Commented-out dead code from legacy method
				// $size *= $this->multiplyFontSize($fontsize, $maxsize, 1);
				break;

			case 'thick':
				$size = 5 * (25.4 / $this->dpi); // 5 pixel width for table borders
				break;

			case 'xx-small':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 0.7);
				break;

			case 'x-small':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 0.77);
				break;

			case 'small':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 0.86);
				break;

			case 'large':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 1.2);
				break;

			case 'x-large':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 1.5);
				break;

			case 'xx-large':
				$size *= $this->multiplyFontSize($fontsize, $maxsize, 2);
				break;

			case 'px':
			default:
				$size *= (25.4 / $this->dpi);
				break;
		}

		return $size;
	}

	/**
	 * Reads a font size: a length against the parent's font size, larger or smaller as the parent's multiplied or
	 * divided by 1.2 in the standard CSS mode, or a keyword such as small against the size medium stands for
	 *
	 * @param string $value
	 * @param float $parent The parent's font size in mm, which em, %, larger and smaller are read against
	 * @param float $medium The size in points medium stands for
	 *
	 * @return float|null In points, or null for a value it does not read
	 */
	public function convertFontSize($value, $parent, $medium)
	{
		$first = substr($value, 0, 1);
		if (is_numeric($first) || $first === '.') {
			return $this->convert($value, $parent) * Mpdf::SCALE;
		}

		if ($this->isRelativeFontSize($value)) {
			return $parent * RelativeFontValues::sizeRatio($value) * Mpdf::SCALE;
		}

		$keyword = strtoupper($value);

		return isset($this->mpdf->fontsizes[$keyword]) ? $this->mpdf->fontsizes[$keyword] * $medium : null;
	}

	/**
	 * A font-size, in mm, against the parent element's size
	 *
	 * @param string $size
	 * @param float $parentSize In mm
	 *
	 * @return float|null Null for larger or smaller in the legacy CSS mode, which ignores them
	 */
	public function convertFontSizeToMm($size, $parentSize)
	{
		$ratio = RelativeFontValues::sizeRatio($size);
		if ($ratio === null) {
			return $this->convert($size, $parentSize);
		}

		return $this->mpdf->cssMode === CssMode::STANDARD ? $parentSize * $ratio : null;
	}

	/**
	 * Whether a font-size is larger or smaller, which the standard CSS mode reads as the parent's size multiplied or
	 * divided by 1.2. The legacy mode ignores them
	 *
	 * @param string $size
	 *
	 * @return bool
	 */
	public function isRelativeFontSize($size)
	{
		return $this->mpdf->cssMode === CssMode::STANDARD && RelativeFontValues::sizeRatio($size) !== null;
	}

	/**
	 * Whether convert() reads the value as a number in a unit it knows, or as a number with no unit, which it reads
	 * as pixels. The number may have a sign and an exponent. Keywords such as "auto" or "thin" are not lengths.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function isLength($value)
	{
		return preg_match('/^[-+]?(\d+\.?\d*|\.\d+)(e[-+]?\d+)?(' . implode('|', self::$units) . ')?$/', strtolower(trim($value))) === 1;
	}

	private function multiplyFontSize($fontsize, $maxsize, $ratio)
	{
		if ($fontsize) {
			return $fontsize * $ratio;
		}

		return $maxsize * $ratio;
	}
}
