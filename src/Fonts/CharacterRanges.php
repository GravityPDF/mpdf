<?php

namespace Mpdf\Fonts;

use Mpdf\Config\FontVariables;
use Mpdf\Exception\FontException;
use Mpdf\Log\Context;
use Psr\Log\LoggerInterface;

/**
 * Which characters a font has, as the codepoint ranges its widths are non-zero over, so the font
 * substitution scan can ask a backup font for a character without reading the font.
 *
 * A ranges file holds `format`, the font file's `size`, the `ranges` as [first, last] pairs and, for a
 * colour font only, `colorFormats` and `hasOutlines`. mPDF ships one as PHP for each font it ships,
 * which opcache holds; the font cache holds it as JSON, which is data and safe to read from a directory
 * anything can write to.
 */
class CharacterRanges
{

	/**
	 * The shape of the file this release reads and writes
	 */
	const FORMAT = 1;

	/**
	 * The highest codepoint a range may reach, which also caps the widths built at 2.2MB
	 */
	const LAST_CODEPOINT = 0x10FFFF;

	/**
	 * The last codepoint of the Basic Multilingual Plane, where a BMPonly font's widths stop
	 */
	const LAST_BMP_CODEPOINT = 0xFFFF;

	/**
	 * How many ranges a line of a written PHP file carries
	 */
	const PER_LINE = 4;

	/**
	 * The width given to a covered codepoint: two non-zero bytes, as _charDefined() asks
	 */
	const COVERED = "\x01\x01";

	/**
	 * What a colour format's name is made of. This branch has no colour fonts, but reads the same file
	 * format as those that do
	 */
	const COLOR_FORMAT_NAME = '/\A[A-Za-z0-9]{1,16}\z/';

	/**
	 * Where mPDF keeps the ranges file of each font it ships. This is the library's own code, so it may be
	 * included, where a font directory or the font cache may not
	 */
	const SHIPPED_RANGES_DIRECTORY = __DIR__ . '/../../data/character-ranges';

	/**
	 * Where mPDF keeps the fonts it ships, which a font fetched on demand may not be in yet
	 */
	const SHIPPED_FONT_DIRECTORY = __DIR__ . '/../../ttfonts';

	/**
	 * @var int[][]
	 */
	private $ranges;

	/**
	 * @var string The widths over every plane
	 */
	private $widths;

	/**
	 * @var int
	 */
	private $size;

	/**
	 * @var string[]
	 */
	private $colorFormats;

	/**
	 * @var bool
	 */
	private $hasOutlines;

	/**
	 * @param int[][]  $ranges       [first, last] pairs, each after the one before
	 * @param string   $widths       What rangesProblem() built from them
	 * @param int      $size         The font file's size in bytes
	 * @param string[] $colorFormats The colour tables the font has
	 * @param bool     $hasOutlines  Whether a colour font has outlines as well
	 */
	private function __construct(array $ranges, $widths, $size, array $colorFormats, $hasOutlines)
	{
		$this->ranges = $ranges;
		$this->widths = $widths;
		$this->size = $size;
		$this->colorFormats = array_values($colorFormats);
		$this->hasOutlines = $hasOutlines;
	}

	/**
	 * @return string[] The regular font file of each family mPDF ships, by family, whether or not the
	 *                  font is on disk
	 */
	public static function shippedFonts()
	{
		$config = new FontVariables();
		$defaults = $config->getDefaults();

		$fonts = [];
		foreach ($defaults['fontdata'] as $family => $fontData) {
			if (!empty($fontData['R'])) {
				$fonts[$family] = $fontData['R'];
			}
		}

		return $fonts;
	}

	/**
	 * @param string   $cw           Character widths as TTFontFile::$charWidths holds them: two bytes
	 *                               per codepoint, the first pair being the count of widths
	 * @param int      $size         The font file's size in bytes
	 * @param string[] $colorFormats
	 * @param bool     $hasOutlines
	 *
	 * @return self The codepoints from U+0001 with a non-zero width
	 *
	 * @throws FontException Where the size, colour formats or outlines are not what a ranges file holds
	 */
	public static function fromWidths($cw, $size, array $colorFormats = [], $hasOutlines = false)
	{
		$length = strlen($cw) - strlen($cw) % 2;
		$ranges = [];

		// Zero bytes are skipped a run at a time; a range ends at the next zero width, which is a "\0\0"
		// that starts on an even offset
		$offset = 2;
		while ($offset < $length) {
			$offset += strspn($cw, "\0", $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$first = $offset >> 1;
			$end = ($first + 1) * 2;
			while (true) {
				$end = strpos($cw, "\0\0", $end);
				if ($end === false || $end >= $length) {
					$end = $length;
					break;
				}

				if ($end % 2 === 0) {
					break;
				}

				$end++;
			}

			$last = ($end >> 1) - 1;
			$ranges[] = [$first, $last];
			$offset = ($last + 1) * 2;
		}

		// Refused here as well as when written, so the error comes from the font that gave the values
		$widths = self::checkedWidths(self::fileData($size, array_values($colorFormats), $hasOutlines, $ranges));

		return new self($ranges, $widths, $size, $colorFormats, $hasOutlines);
	}

	/**
	 * Validates what a ranges file decoded to, in the same pass that builds its widths, stopping at the
	 * first thing that is wrong.
	 *
	 * @param mixed           $data   What the file decoded to
	 * @param string          $path   Where it was read from, for the warning
	 * @param LoggerInterface $logger
	 *
	 * @return self|null Null, with a warning, where the data is not a valid ranges file
	 */
	public static function fromData($data, $path, LoggerInterface $logger)
	{
		$problem = self::problem($data, $widths);
		if ($problem !== null) {
			$logger->warning(sprintf('Character ranges file "%s" is ignored: %s', $path, $problem), ['context' => Context::FONTS]);

			return null;
		}

		return new self(
			$data['ranges'],
			$widths,
			$data['size'],
			isset($data['colorFormats']) ? $data['colorFormats'] : [],
			!empty($data['hasOutlines'])
		);
	}

	/**
	 * @param mixed       $data
	 * @param string|null $widths Set to what the ranges make, where they are valid
	 *
	 * @return string|null What is wrong with the data as a ranges file, if anything
	 */
	private static function problem($data, &$widths)
	{
		if (!is_array($data)) {
			return 'it does not decode to an array';
		}

		if (!isset($data['format']) || $data['format'] !== self::FORMAT) {
			return sprintf('its format is not %d', self::FORMAT);
		}

		if (!isset($data['size']) || !is_int($data['size']) || $data['size'] < 1) {
			return 'its size is not a positive integer';
		}

		if (!isset($data['ranges']) || !is_array($data['ranges']) || !self::isList($data['ranges'])) {
			return 'its ranges are not a list';
		}

		if (isset($data['colorFormats']) && (!is_array($data['colorFormats']) || !self::isList($data['colorFormats']) || !self::areNames($data['colorFormats']))) {
			return 'its colorFormats are not a list of names';
		}

		if (isset($data['hasOutlines']) && !is_bool($data['hasOutlines'])) {
			return 'its hasOutlines is not a boolean';
		}

		return self::rangesProblem($data['ranges'], $widths);
	}

	/**
	 * @param array $formats
	 *
	 * @return bool Whether each is a string of letters and digits, as a colour format's name is
	 */
	private static function areNames(array $formats)
	{
		foreach ($formats as $format) {
			if (!is_string($format) || !preg_match(self::COLOR_FORMAT_NAME, $format)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array $array
	 *
	 * @return bool Whether the keys are 0, 1, 2… in order
	 */
	private static function isList(array $array)
	{
		return $array === [] || array_keys($array) === range(0, count($array) - 1);
	}

	/**
	 * Checks each range and appends its gap and run to the widths, in one pass because a file is read
	 * on the way to the substitution scan.
	 *
	 * @param array       $ranges
	 * @param string|null $widths Set to what the ranges make, as far as they are valid
	 *
	 * @return string|null What is wrong with the first range that is not valid, if any is not
	 */
	private static function rangesProblem(array $ranges, &$widths)
	{
		$widths = '';
		$next = 0;
		foreach ($ranges as $range) {
			if (!is_array($range) || count($range) !== 2 || !isset($range[0], $range[1]) || !is_int($range[0]) || !is_int($range[1])) {
				return 'a range is not a pair of integers';
			}

			list($first, $last) = $range;
			if ($first < 0 || $last > self::LAST_CODEPOINT) {
				return sprintf('range [%d, %d] is outside 0 to %d', $first, $last, self::LAST_CODEPOINT);
			}

			if ($first > $last) {
				return sprintf('range [%d, %d] ends before it starts', $first, $last);
			}

			if ($first < $next) {
				return sprintf('range [%d, %d] does not start after the one before it', $first, $last);
			}

			$widths .= str_repeat("\0\0", $first - $next);
			$widths .= str_repeat(self::COVERED, $last - $first + 1);
			$next = $last + 1;
		}

		return null;
	}

	/**
	 * @return int The size in bytes of the font file the ranges were written for
	 */
	public function size()
	{
		return $this->size;
	}

	/**
	 * What Mpdf::fontMetrics() gives the substitution scan for the font.
	 *
	 * @param bool $bmpOnly Whether the family is in BMPonly, so has no widths above U+FFFF
	 *
	 * @return array 'cw', and 'colorFormats' and 'hasOutlines' for a colour font
	 */
	public function metrics($bmpOnly)
	{
		$metrics = ['cw' => $this->widths($bmpOnly)];
		if ($this->colorFormats) {
			$metrics['colorFormats'] = $this->colorFormats;
			$metrics['hasOutlines'] = $this->hasOutlines;
		}

		return $metrics;
	}

	/**
	 * @param bool $bmpOnly Whether to stop at U+FFFF
	 *
	 * @return string Character widths, two bytes per codepoint, non-zero for each codepoint covered
	 */
	private function widths($bmpOnly)
	{
		return $bmpOnly ? substr($this->widths, 0, (self::LAST_BMP_CODEPOINT + 1) * 2) : $this->widths;
	}

	/**
	 * A shipped ranges file is included as code, so nothing that fromData() would not read back is
	 * written, and what is written is cast to the literal it should be whatever the rules allow.
	 *
	 * @return string The file as PHP, holding nothing but the integers and strings it was made from
	 *
	 * @throws FontException Where the ranges are not a valid ranges file
	 */
	public function toPhp()
	{
		self::checkedWidths($this->toArray());

		$php = "<?php\n\nreturn [\n"
			. "\t'format' => " . self::FORMAT . ",\n"
			. "\t'size' => " . (int) $this->size . ",\n";

		if ($this->colorFormats) {
			$php .= "\t'colorFormats' => [" . implode(', ', array_map(function ($format) {
				return var_export((string) $format, true);
			}, $this->colorFormats)) . "],\n"
				. "\t'hasOutlines' => " . ($this->hasOutlines ? 'true' : 'false') . ",\n";
		}

		return $php . "\t'ranges' => [\n" . $this->entries() . "\t],\n];\n";
	}

	/**
	 * @return string The ranges as `[0x0020, 0x007E]` PHP, PER_LINE to a line, each line indented by two
	 *                tabs and ending in a comma and a newline
	 */
	private function entries()
	{
		$entries = [];
		foreach ($this->ranges as $range) {
			$entries[] = sprintf('[0x%04X, 0x%04X]', $range[0], $range[1]);
		}

		$lines = '';
		foreach (array_chunk($entries, self::PER_LINE) as $chunk) {
			$lines .= "\t\t" . implode(', ', $chunk) . ",\n";
		}

		return $lines;
	}

	/**
	 * @return array The file as the array it decodes to
	 */
	public function toArray()
	{
		return self::fileData($this->size, $this->colorFormats, $this->hasOutlines, $this->ranges);
	}

	/**
	 * @param int      $size
	 * @param string[] $colorFormats
	 * @param bool     $hasOutlines
	 * @param int[][]  $ranges
	 *
	 * @return array A ranges file as the array it decodes to
	 */
	private static function fileData($size, array $colorFormats, $hasOutlines, array $ranges)
	{
		$data = ['format' => self::FORMAT, 'size' => $size];
		if ($colorFormats) {
			$data['colorFormats'] = $colorFormats;
			$data['hasOutlines'] = $hasOutlines;
		}
		$data['ranges'] = $ranges;

		return $data;
	}

	/**
	 * @param array $data A ranges file as the array it decodes to
	 *
	 * @return string What its ranges make
	 *
	 * @throws FontException Where it is not a valid ranges file
	 */
	private static function checkedWidths(array $data)
	{
		$problem = self::problem($data, $widths);
		if ($problem !== null) {
			throw new FontException(sprintf('Character ranges are not valid: %s', $problem));
		}

		return $widths;
	}

}
