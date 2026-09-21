<?php

namespace Mpdf\Fonts;

use Mpdf\Fonts\Table\Loca;
use Mpdf\Log\Context as LogContext;
use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

/**
 * A TrueType glyph's outline as a PDF path, in font units, for a colour font's layers and for the
 * glyphs of a colour font that have no colour of their own.
 *
 * A TrueType contour is a loop of points, each on the curve or off it. Two off-curve points in a row
 * imply an on-curve point midway between them, and each off-curve point is the control point of a
 * quadratic Bezier, which a PDF path draws as the cubic with control points two thirds of the way
 * from each end towards it. A composite glyph is other glyphs, each moved, scaled or transformed.
 *
 * The path is returned without its painting operator: TrueType fills by the non-zero winding rule,
 * which is PDF's f.
 *
 * A glyph is read whole and every field of it is checked to fit before it is read, so a glyph that
 * runs short, or claims more points than it holds, draws nothing rather than reading past its end.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/glyf
 */
class GlyphOutline
{

	/**
	 * How deep composites may nest. The spec leaves it to maxp's maxComponentDepth, which a font can
	 * state wrongly, and a cycle would otherwise never end.
	 */
	const MAX_DEPTH = 16;

	/**
	 * @var FileReader
	 */
	private $reader;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var int Where glyf starts
	 */
	private $glyf;

	/**
	 * @var int Where loca starts
	 */
	private $loca;

	/**
	 * @var int head's indexToLocFormat
	 */
	private $indexToLocFormat;

	/**
	 * @var int maxp's glyph count
	 */
	private $numGlyphs;

	/**
	 * @var string[] Each path drawn so far, by glyph id, since a layer is shared by many emoji
	 */
	private $paths = [];

	/**
	 * @param TTFontFile      $font   The font, its table directory read
	 * @param FileReader      $reader The font file
	 * @param LoggerInterface $logger Told of a component placed by matching points, which is not drawn
	 */
	public function __construct(TTFontFile $font, FileReader $reader, LoggerInterface $logger)
	{
		$this->reader = $reader;
		$this->logger = $logger;
		$this->glyf = $font->getTablePosition('glyf')[0];
		// Each glyph's place in loca is read as it is drawn, since a document draws few of a colour
		// font's tens of thousands
		$this->loca = $font->getTablePosition('loca')[0];
		$this->indexToLocFormat = $reader->uint16At($font->getTablePosition('head')[0] + 50);
		$this->numGlyphs = $reader->uint16At($font->getTablePosition('maxp')[0] + 4);
	}

	/**
	 * @param int $glyph The glyph id
	 *
	 * @return string The path, or '' where the glyph has no outline
	 */
	public function path($glyph)
	{
		if (!isset($this->paths[$glyph])) {
			$path = '';
			foreach ($this->contours($glyph, 0) as $contour) {
				$path .= $this->contourPath($contour);
			}
			$this->paths[$glyph] = $path;
		}

		return $this->paths[$glyph];
	}

	/**
	 * @param int $glyph The glyph id
	 * @param int $depth How many composites led here
	 *
	 * @return array[] Each contour as its points, each [x, y, whether it is on the curve]
	 */
	private function contours($glyph, $depth)
	{
		$range = $glyph < $this->numGlyphs && $depth <= self::MAX_DEPTH ? Loca::range($this->reader, $this->loca, $this->indexToLocFormat, $glyph) : null;
		if ($range === null || $range[1] <= $range[0]) {
			return [];
		}

		// One read of the whole glyph, parsed from memory, rather than hundreds from the file
		list($start, $end) = $range;
		$length = $end - $start;
		$bytes = $this->reader->bytesAt($this->glyf + $start, $length);
		if (strlen($bytes) < $length || $length < 10) {
			return [];
		}

		$data = new BlobReader($bytes);
		$numberOfContours = $data->readInt16();
		$data->skip(8); // the bounding box

		return $numberOfContours >= 0 ? $this->simpleContours($data, $numberOfContours) : $this->compositeContours($data, $depth);
	}

	/**
	 * @param BlobReader $data             The glyph, read to its contours
	 * @param int        $numberOfContours
	 *
	 * @return array[] As contours() gives them
	 */
	private function simpleContours(BlobReader $data, $numberOfContours)
	{
		// endPtsOfContours, then instructionLength
		if (!$numberOfContours || !$this->fits($data, 2 * $numberOfContours + 2)) {
			return [];
		}

		$ends = [];
		for ($i = 0; $i < $numberOfContours; $i++) {
			$ends[] = $data->readUInt16();
			// Each contour ends past the last
			if ($i > 0 && $ends[$i] < $ends[$i - 1]) {
				return [];
			}
		}

		$instructions = $data->readUInt16();
		if (!$this->fits($data, $instructions)) {
			return [];
		}
		$data->skip($instructions);

		$count = end($ends) + 1;
		$flags = [];
		while (count($flags) < $count) {
			if (!$this->fits($data, 1)) {
				return [];
			}
			$flag = $data->readUInt8();
			$flags[] = $flag;
			if ($flag & 0x08) {
				if (!$this->fits($data, 1)) {
					return [];
				}
				for ($repeat = $data->readUInt8(); $repeat > 0; $repeat--) {
					$flags[] = $flag;
				}
			}
		}

		$xs = $this->coordinates($data, $flags, $count, 0x02, 0x10);
		$ys = $xs === null ? null : $this->coordinates($data, $flags, $count, 0x04, 0x20);
		if ($ys === null) {
			return [];
		}

		$contours = [];
		$start = 0;
		foreach ($ends as $end) {
			$contour = [];
			for ($i = $start; $i <= $end; $i++) {
				$contour[] = [$xs[$i], $ys[$i], (bool) ($flags[$i] & 0x01)];
			}
			$contours[] = $contour;
			$start = $end + 1;
		}

		return $contours;
	}

	/**
	 * One axis of a simple glyph's points: each a delta from the last, of one byte - its sign in the
	 * flag - or of two, or the same as the last where the flag says so
	 *
	 * @param BlobReader $data   The glyph, read to this axis
	 * @param int[]      $flags  Each point's flags
	 * @param int        $count  How many points there are
	 * @param int        $short  The flag bit for a one-byte delta
	 * @param int        $same   The flag bit that, for a one-byte delta, says it is positive, and
	 *                           otherwise says the delta is 0
	 *
	 * @return int[]|null Each point's coordinate, or null where the glyph ends before they do
	 */
	private function coordinates(BlobReader $data, array $flags, $count, $short, $same)
	{
		$bytes = 0;
		for ($i = 0; $i < $count; $i++) {
			$bytes += ($flags[$i] & $short) ? 1 : (($flags[$i] & $same) ? 0 : 2);
		}
		if (!$this->fits($data, $bytes)) {
			return null;
		}

		$values = [];
		$value = 0;
		for ($i = 0; $i < $count; $i++) {
			if ($flags[$i] & $short) {
				$delta = $data->readUInt8();
				$value += ($flags[$i] & $same) ? $delta : -$delta;
			} elseif (!($flags[$i] & $same)) {
				$value += $data->readInt16();
			}
			$values[] = $value;
		}

		return $values;
	}

	/**
	 * @param BlobReader $data  The glyph, read to its components
	 * @param int        $depth How many composites led here
	 *
	 * @return array[] As contours() gives them: the components' contours, each placed
	 */
	private function compositeContours(BlobReader $data, $depth)
	{
		$contours = [];

		do {
			// The flags and glyph id, then the arguments and transformation they say are there
			if (!$this->fits($data, 4)) {
				return $contours;
			}
			$flags = $data->readUInt16();
			$glyph = $data->readUInt16();

			if (!$this->fits($data, GlyphOperator::argumentsLength($flags))) {
				return $contours;
			}

			$xy = $flags & GlyphOperator::XY_VALUES;
			if ($flags & GlyphOperator::WORDS) {
				$dx = $xy ? $data->readInt16() : $data->readUInt16();
				$dy = $xy ? $data->readInt16() : $data->readUInt16();
			} else {
				$dx = $xy ? $data->readInt8() : $data->readUInt8();
				$dy = $xy ? $data->readInt8() : $data->readUInt8();
			}

			$a = $d = 1;
			$b = $c = 0;
			if ($flags & GlyphOperator::SCALE) {
				$a = $d = $this->f2dot14($data);
			} elseif ($flags & GlyphOperator::XYSCALE) {
				$a = $this->f2dot14($data);
				$d = $this->f2dot14($data);
			} elseif ($flags & GlyphOperator::TWOBYTWO) {
				$a = $this->f2dot14($data);
				$b = $this->f2dot14($data);
				$c = $this->f2dot14($data);
				$d = $this->f2dot14($data);
			}

			if (!$xy) {
				$this->logger->warning(sprintf('Glyph %d is placed by matching points, which mPDF does not draw', $glyph), ['context' => LogContext::FONTS]);
				continue;
			}

			if ($flags & GlyphOperator::SCALED_OFFSET) {
				list($dx, $dy) = [$a * $dx + $c * $dy, $b * $dx + $d * $dy];
			}

			foreach ($this->contours($glyph, $depth + 1) as $contour) {
				foreach ($contour as $i => $point) {
					list($x, $y) = $point;
					$contour[$i][0] = $a * $x + $c * $y + $dx;
					$contour[$i][1] = $b * $x + $d * $y + $dy;
				}
				$contours[] = $contour;
			}
		} while ($flags & GlyphOperator::MORE);

		return $contours;
	}

	/**
	 * @param BlobReader $data  A glyph
	 * @param int        $bytes How many bytes are to be read next
	 *
	 * @return bool Whether the glyph holds them
	 */
	private function fits(BlobReader $data, $bytes)
	{
		return $data->tell() + $bytes <= $data->length();
	}

	/**
	 * @param BlobReader $data A glyph, read to the number
	 *
	 * @return float F2DOT14: a signed 2.14 fixed point number
	 */
	private function f2dot14(BlobReader $data)
	{
		return $data->readInt16() / 16384;
	}

	/**
	 * One contour as a closed subpath.
	 *
	 * It starts at an on-curve point: the first there is, or, where every point is off the curve, the
	 * point midway between the last and the first.
	 *
	 * @return string
	 */
	private function contourPath(array $points)
	{
		$count = count($points);
		if ($count < 2) {
			return '';
		}

		$first = 0;
		while ($first < $count && !$points[$first][2]) {
			$first++;
		}

		if ($first === $count) {
			// Start between the last point and the first, where FreeType and fontTools start
			array_unshift($points, $this->midpoint($points[$count - 1], $points[0]));
			$count++;
		} else {
			$points = array_merge(array_slice($points, $first), array_slice($points, 0, $first));
		}

		$current = $points[0];
		$path = $this->number($current[0]) . ' ' . $this->number($current[1]) . " m\n";

		$control = null;
		for ($i = 1; $i <= $count; $i++) {
			$point = $points[$i % $count];

			if ($point[2]) {
				// A straight edge back to the start is drawn by h
				if ($control === null && $i === $count) {
					break;
				}
				$path .= $control === null
					? $this->number($point[0]) . ' ' . $this->number($point[1]) . " l\n"
					: $this->curve($current, $control, $point);
				$current = $point;
				$control = null;
			} elseif ($control === null) {
				$control = $point;
			} else {
				$implied = $this->midpoint($control, $point);
				$path .= $this->curve($current, $control, $implied);
				$current = $implied;
				$control = $point;
			}
		}

		return $path . "h\n";
	}

	/**
	 * The quadratic Bezier from $from through $control to $to, as the cubic that draws it
	 *
	 * @return string
	 */
	private function curve(array $from, array $control, array $to)
	{
		return $this->number($from[0] + 2 / 3 * ($control[0] - $from[0])) . ' '
			. $this->number($from[1] + 2 / 3 * ($control[1] - $from[1])) . ' '
			. $this->number($to[0] + 2 / 3 * ($control[0] - $to[0])) . ' '
			. $this->number($to[1] + 2 / 3 * ($control[1] - $to[1])) . ' '
			. $this->number($to[0]) . ' ' . $this->number($to[1]) . " c\n";
	}

	/**
	 * @return array An on-curve point midway between two others
	 */
	private function midpoint(array $p, array $q)
	{
		return [($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2, true];
	}

	/**
	 * @return string A coordinate as a path writes it, to three places and no more than it needs
	 */
	private function number($value)
	{
		// A simple glyph's points are whole font units
		if (is_int($value)) {
			return (string) $value;
		}

		$number = rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');

		return $number === '-0' ? '0' : $number;
	}
}
