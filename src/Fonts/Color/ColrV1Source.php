<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FontReader;

/**
 * Colour glyphs as COLR version 1 paint graphs: Noto Color Emoji's vector build, the one Google Fonts
 * serves.
 *
 * A colour glyph is a graph of paints, walked from its root as the glyph is drawn. How each is drawn:
 *
 * - PaintColrLayers, each layer in turn, bottom to top; PaintColrGlyph, another colour glyph's graph
 * - PaintGlyph, a glyph's outline as a clip, W n, with what its child paints inside it. Where the child
 *   is a solid colour the outline is filled with it, as a COLR version 0 layer is.
 * - PaintSolid, the clip filled in a colour of the palette - see ColorFontFile::colour()
 * - PaintLinearGradient, an axial shading, ShadingType 2. Version 1 gives it three points, the third
 *   turning the colour's lines off the perpendicular; they are folded into two by moving the second
 *   onto the line through the first that runs parallel to them.
 * - PaintRadialGradient, a radial shading, ShadingType 3, which is defined the same way
 * - PaintSweepGradient, which a PDF shading cannot draw short of a function of the angle; it is drawn
 *   in the colour of its middle stop, with a warning. No Noto emoji has one.
 * - The transforms - PaintTransform, Translate, Scale, Rotate, Skew and those around a centre - cm
 * - PaintComposite: a blend mode through /BM, and the Porter-Duff modes through soft masks drawn from
 *   the alpha of the source or the backdrop - see COMPOSITES. Where the mask is one glyph in an opaque
 *   colour, its alpha is its outline, so the mask is a clip instead, since Preview draws a soft mask
 *   inside a Type3 glyph wrongly or not at all. PLUS, which PDF has no way to draw, is drawn as
 *   SRC_OVER, with a warning. A mode the spec does not define draws nothing, as CLEAR does.
 *
 * A gradient's stops are colour and alpha; the alpha, where it varies, is a soft mask drawn from a grey
 * shading of the same shape - see ColorLine for how the two are interpolated. A stop in the colour of
 * the text is drawn black, since a shading cannot take its colour from the text. A radial gradient is
 * cut where its radius reaches 0, as the spec asks, since a shading cannot take a negative radius.
 *
 * The glyph is clipped to its clip box, or where it has none to the font's bounding box, which is also
 * the area a paint that fills its clip fills. Variable fonts are drawn at their default instance, each
 * Var paint read as the paint it varies. A glyph with no version 1 paint is left to the next source,
 * ColrV0Source, which draws it from its version 0 layers where the table has any.
 *
 * Every offset is checked to lie within COLR before it is read, and a graph that loops back on itself
 * or runs deeper than MAX_DEPTH draws nothing past that point.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/colr
 */
class ColrV1Source implements ColorGlyphSource
{

	use FillsInColour;
	use FillsWithGradients;

	/**
	 * How deep a paint graph may run. Noto's deepest is 9.
	 */
	const MAX_DEPTH = 64;

	/**
	 * Each paint's bytes, by format, less the varIndexBase a Var paint has after them
	 */
	const SIZES = [1 => 6, 2 => 5, 4 => 16, 6 => 16, 8 => 12, 10 => 6, 11 => 3, 12 => 7, 14 => 8, 16 => 8, 18 => 12, 20 => 6, 22 => 10, 24 => 6, 26 => 10, 28 => 8, 30 => 12, 32 => 8];

	/**
	 * The composite modes that are blend modes, each as PDF names it
	 */
	const BLEND_MODES = [
		13 => 'Screen', 14 => 'Overlay', 15 => 'Darken', 16 => 'Lighten', 17 => 'ColorDodge', 18 => 'ColorBurn', 19 => 'HardLight',
		20 => 'SoftLight', 21 => 'Difference', 22 => 'Exclusion', 23 => 'Multiply', 24 => 'Hue', 25 => 'Saturation', 26 => 'Color',
		27 => 'Luminosity',
	];

	/**
	 * The Porter-Duff composite modes that draw both the source and the backdrop, each as what is drawn,
	 * in order: the source or the backdrop, and where it is masked, what by and whether the mask is
	 * inverted. SRC_IN is the source where the backdrop is, SRC_OUT where it is not. SRC_ATOP, DEST_ATOP
	 * and XOR sum two such parts, which PDF cannot, so the second is drawn over the first: exact where
	 * each alpha is 0 or 1, slightly too opaque where both are partial, as at an antialiased edge. CLEAR,
	 * SRC and DEST draw nothing, the source alone and the backdrop alone.
	 */
	const COMPOSITES = [
		3 => [['backdrop'], ['source']],
		4 => [['source'], ['backdrop']],
		5 => [['source', 'backdrop', false]],
		6 => [['backdrop', 'source', false]],
		7 => [['source', 'backdrop', true]],
		8 => [['backdrop', 'source', true]],
		9 => [['backdrop', 'source', true], ['source', 'backdrop', false]],
		10 => [['source', 'backdrop', true], ['backdrop', 'source', false]],
		11 => [['source', 'backdrop', true], ['backdrop', 'source', true]],
	];

	/**
	 * The composite mode that adds the source to the backdrop, which PDF has no way to draw
	 */
	const PLUS = 12;

	/**
	 * @var ColorFontFile
	 */
	private $file;

	/**
	 * @var int Where COLR ends, past which nothing is read
	 */
	private $end = 0;

	/**
	 * @var int[] Base glyph id => where its root paint is
	 */
	private $baseGlyphs = [];

	/**
	 * @var int Where the LayerList starts
	 */
	private $layers = 0;

	/**
	 * @var int How many layers the LayerList has
	 */
	private $layerCount = 0;

	/**
	 * @var int[][] Each clip as [first glyph id, last glyph id, where its ClipBox is]
	 */
	private $clips = [];

	/**
	 * @var string[] Each paint's bytes, by where it is, read once however many glyphs share it: as many
	 *               as the longest paint's fields take, or as COLR has left
	 */
	private $paints = [];

	/**
	 * @var GlyphResources What the glyph being drawn registers its shadings, groups and masks with
	 */
	private $resources;

	/**
	 * @var int The glyph being drawn, for a warning
	 */
	private $glyph = 0;

	/**
	 * @var float[] The clip box of the glyph being drawn, in glyph space
	 */
	private $box = [];

	/**
	 * @param ColorFontFile $file The font
	 */
	public function __construct(ColorFontFile $file)
	{
		$this->file = $file;

		list($colr, $length) = $file->table('COLR');
		$this->end = $colr + $length;

		// version, then past the version 0 fields, baseGlyphListOffset, layerListOffset, clipListOffset
		$header = $this->fields($colr, 34, 'nversion/x12/Nlist/Nlayers/Nclips');
		if ($header === null || $header[0] < 1) {
			return;
		}

		list(, $list, $layers, $clips) = $header;

		// Each record is a glyph id and a 32-bit offset, read as three 16-bit numbers
		$count = $list ? $this->fields($colr + $list, 4, 'N') : null;
		$records = $count === null ? null : $this->fields($colr + $list + 4, $count[0] * 6, 'n*');
		for ($i = 0; $records !== null && $i < count($records); $i += 3) {
			$this->baseGlyphs[$records[$i]] = $colr + $list + ($records[$i + 1] << 16 | $records[$i + 2]);
		}

		$layerCount = $layers ? $this->fields($colr + $layers, 4, 'N') : null;
		if ($layerCount !== null) {
			$this->layers = $colr + $layers;
			$this->layerCount = $layerCount[0];
		}

		// format, of which only 1 is defined, numClips, then each clip's first and last glyph and a 24-bit
		// offset
		$clipList = $clips ? $this->fields($colr + $clips, 5, 'Cformat/Ncount') : null;
		$clipCount = $clipList !== null && $clipList[0] === 1 ? $clipList[1] : 0;
		$clipRecords = $clipCount ? $this->fields($colr + $clips + 5, $clipCount * 7, 'a*') : null;
		for ($i = 0; $clipRecords !== null && $i < $clipCount; $i++) {
			$record = unpack('nfirst/nlast/Chigh/nlow', substr($clipRecords[0], 7 * $i, 7));
			$this->clips[] = [$record['first'], $record['last'], $colr + $clips + ($record['high'] << 16 | $record['low'])];
		}
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		if (!isset($this->baseGlyphs[$glyph])) {
			return null;
		}

		$this->resources = $resources;
		$this->glyph = $glyph;
		$this->box = $this->clipBox($glyph);

		$content = $this->paint($this->baseGlyphs[$glyph], Geometry::IDENTITY, []);
		if ($content === '') {
			return null;
		}

		return 'q ' . Geometry::rectangle($this->box) . " re W n\n" . $content . "Q\n";
	}

	/**
	 * @param int     $offset Where the paint is
	 * @param float[] $matrix What takes the paint's space to glyph space
	 * @param true[]  $path   The paints that led here, by where they are, so a loop is seen
	 *
	 * @return string Content drawing the paint, in its own space
	 */
	private function paint($offset, array $matrix, array $path)
	{
		if (isset($path[$offset]) || count($path) >= self::MAX_DEPTH) {
			$this->file->warn($this->glyph, 'a paint graph that loops or nests too deep, which is drawn only so far');

			return '';
		}
		$path[$offset] = true;

		$paint = $this->paintAt($offset);
		if ($paint === '') {
			return '';
		}

		// A Var paint is read as the paint it varies: the same fields, then a varIndexBase
		$format = ord($paint[0]);
		$var = $format % 2 === 1 && $format > 2 && $format < 32 && $format !== 11;
		$base = $var ? $format - 1 : $format;

		if (!array_key_exists($base, self::SIZES)) {
			$this->file->warn($this->glyph, sprintf('a paint of format %d, which is not in the spec', $format));

			return '';
		}

		if (strlen($paint) < self::SIZES[$base]) {
			return '';
		}

		switch ($base) {
			case 1:
				return $this->layers(FontReader::uint32(substr($paint, 2, 4)), ord($paint[1]), $matrix, $path);

			case 2:
				return $this->fill($this->solid($offset), $matrix);

			case 4:
			case 6:
			case 8:
				return $this->gradient($base, $paint, self::child($offset, $paint, 1), $var, $matrix);

			case 10:
				return $this->clipped(self::uint16($paint, 4), self::child($offset, $paint, 1), $matrix, $path);

			case 11:
				$glyph = self::uint16($paint, 1);

				return isset($this->baseGlyphs[$glyph]) ? $this->paint($this->baseGlyphs[$glyph], $matrix, $path) : '';

			case 32:
				return $this->composite($offset, $paint, $matrix, $path);
		}

		$transform = $this->transform($base, $paint, $offset);
		$child = self::child($offset, $paint, 1);
		if ($transform === null || $child === null) {
			return '';
		}

		$content = $this->paint($child, Geometry::multiply($transform, $matrix), $path);

		return $content === '' ? '' : sprintf("q %s cm\n", Geometry::numbers($transform)) . $content . "Q\n";
	}

	/**
	 * @param int     $first  The first layer, in the LayerList
	 * @param int     $count  How many layers
	 * @param float[] $matrix What takes the paints' space to glyph space
	 * @param true[]  $path   The paints that led here
	 *
	 * @return string Content drawing each layer, bottom to top, or nothing where they run past the list
	 */
	private function layers($first, $count, array $matrix, array $path)
	{
		$offsets = $first + $count > $this->layerCount ? null : $this->fields($this->layers + 4 + $first * 4, $count * 4, 'N*');

		$content = '';
		foreach ($offsets === null ? [] : $offsets as $offset) {
			$content .= $this->paint($this->layers + $offset, $matrix, $path);
		}

		return $content;
	}

	/**
	 * A PaintGlyph: what its child paints, inside the glyph's outline
	 *
	 * @param int      $glyph  The glyph whose outline clips
	 * @param int|null $child  Where the child paint is
	 * @param float[]  $matrix What takes the paint's space to glyph space
	 * @param true[]   $path   The paints that led here
	 *
	 * @return string
	 */
	private function clipped($glyph, $child, array $matrix, array $path)
	{
		$outline = $this->file->outline()->path($glyph);
		if ($outline === '' || $child === null) {
			return '';
		}

		// A solid colour fills the outline, as a version 0 layer does, rather than a box clipped to it
		$solid = $this->solid($child);
		if ($solid !== null) {
			return $this->filled($solid, $outline . 'f', $this->resources);
		}

		return $this->inside($outline, $this->paint($child, $matrix, $path));
	}

	/**
	 * @param string $outline A path
	 * @param string $content What is drawn
	 *
	 * @return string The content clipped to the path, or nothing where it draws nothing
	 */
	private function inside($outline, $content)
	{
		return $content === '' ? '' : "q\n" . $outline . "W n\n" . $content . "Q\n";
	}

	/**
	 * @param int     $offset Where the PaintComposite is
	 * @param string  $paint  Its bytes
	 * @param float[] $matrix What takes the paint's space to glyph space
	 * @param true[]  $path   The paints that led here
	 *
	 * @return string The source composited onto the backdrop
	 */
	private function composite($offset, $paint, array $matrix, array $path)
	{
		$box = Geometry::boxIn($matrix, $this->box);
		if ($box === null) {
			return '';
		}

		$mode = ord($paint[4]);
		if ($mode === self::PLUS) {
			$this->file->warn($this->glyph, 'composite mode PLUS, which is drawn as the source over the backdrop');
			$mode = 3;
		} elseif ($mode > 2 && !array_key_exists($mode, self::BLEND_MODES) && !array_key_exists($mode, self::COMPOSITES)) {
			// The spec draws a mode it does not define as CLEAR
			$this->file->warn($this->glyph, sprintf('composite mode %d, which is not in the spec and draws nothing', $mode));
			$mode = 0;
		}

		// CLEAR, SRC and DEST: nothing, the source alone, the backdrop alone
		if ($mode <= 2) {
			$child = $mode === 0 ? null : self::child($offset, $paint, $mode === 1 ? 1 : 5);

			return $child === null ? '' : $this->paint($child, $matrix, $path);
		}

		$blend = array_key_exists($mode, self::BLEND_MODES);

		$drawn = ['source' => '', 'backdrop' => ''];
		$children = [];
		foreach ([1 => 'source', 5 => 'backdrop'] as $at => $part) {
			$children[$part] = self::child($offset, $paint, $at);
			if ($children[$part] !== null) {
				$drawn[$part] = $this->paint($children[$part], $matrix, $path);
			}
		}

		// The source blended with the backdrop alone, then the two drawn together on what is below
		if ($blend) {
			$content = $drawn['backdrop'];
			if ($drawn['source'] !== '') {
				$content .= sprintf("q %s %s Do Q\n", $this->resources->blend(self::BLEND_MODES[$mode]), $this->resources->group($drawn['source'], $box));
			}

			return $content === '' ? '' : $this->resources->group($content, $box, true) . " Do\n";
		}

		$content = '';
		foreach (self::COMPOSITES[$mode] as $part) {
			$content .= isset($part[1]) ? $this->masked($drawn[$part[0]], $drawn[$part[1]], $part[2], $box, $children[$part[1]]) : $drawn[$part[0]];
		}

		return $content;
	}

	/**
	 * @param string   $content  What is drawn
	 * @param string   $mask     What it is drawn only where, by alpha
	 * @param bool     $inverted Whether it is drawn only where the mask is not, instead
	 * @param float[]  $box      The area drawn, in the space it is drawn in
	 * @param int|null $at       Where the mask's paint is
	 *
	 * @return string
	 */
	private function masked($content, $mask, $inverted, array $box, $at)
	{
		if ($content === '' || $mask === '') {
			return $inverted ? $content : '';
		}

		$glyph = $at === null ? null : $this->opaqueGlyph($at);
		if ($glyph !== null) {
			$outline = $this->file->outline();
			if (!$inverted) {
				return $this->inside($outline->path($glyph), $content);
			}

			// The box less the outline by even-odd, which fills as the outline's nonzero rule does only
			// where it is one contour
			if ($outline->contourCount($glyph) === 1) {
				return 'q ' . Geometry::rectangle($box) . " re\n" . $outline->path($glyph) . "W* n\n" . $content . "Q\n";
			}
		}

		return sprintf("q %s %s Do Q\n", $this->resources->softMask($mask, $box, false, $inverted), $this->resources->group($content, $box));
	}

	/**
	 * @param int      $format The gradient's paint format: 4, linear, 6, radial or 8, sweep
	 * @param string   $paint  Its bytes
	 * @param int|null $line   Where its ColorLine is
	 * @param bool     $var    Whether it is a Var paint, whose ColorLine is a VarColorLine
	 * @param float[]  $matrix What takes the paint's space to glyph space
	 *
	 * @return string Content filling the clip with the gradient
	 */
	private function gradient($format, $paint, $line, $var, array $matrix)
	{
		$line = $line === null ? null : $this->colorLine($line, $var);
		$box = Geometry::boxIn($matrix, $this->box);
		if ($line === null || !$line->stops || $box === null) {
			return '';
		}

		if ($format === 8) {
			$this->file->warn($this->glyph, 'a sweep gradient, which is drawn in the colour of its middle stop');
		}

		if (count($line->stops) === 1 || $format === 8) {
			return $this->fill($line->middle(), $matrix);
		}

		list($first, $last) = $line->span();

		$values = [];
		for ($i = 4; $i < 16; $i += 2) {
			$values[] = self::int16At($paint, $i);
		}

		if ($format === 4) {
			$geometry = $this->linear($values, $first, $last);
		} else {
			// The radii are unsigned. Two circles the same are ill-formed.
			$values[2] = self::uint16($paint, 8);
			$values[5] = self::uint16($paint, 14);
			$same = $values[0] === $values[3] && $values[1] === $values[4] && $values[2] === $values[5];
			$geometry = $same ? null : self::along($values, $first, $last);
		}

		if ($geometry === null) {
			return '';
		}

		return $this->gradientFill($line, $geometry, $box, $this->resources);
	}

	/**
	 * A linear gradient's three points folded into the two of an axial shading, over the span of its
	 * stops
	 *
	 * @param int[] $values x0, y0, x1, y1, x2, y2
	 * @param float $first  The first stop's offset
	 * @param float $last   The last stop's offset
	 *
	 * @return float[]|null [x0, y0, x1, y1] for offsets 0 and 1, or null where the gradient is
	 *                      ill-formed: a point on the first, or the three in a line
	 */
	private function linear(array $values, $first, $last)
	{
		list($x0, $y0, $x1, $y1, $x2, $y2) = $values;

		// The second point moved onto the line through the first perpendicular to the first and third
		$normal = [$y2 - $y0, $x0 - $x2];
		$squared = $normal[0] * $normal[0] + $normal[1] * $normal[1];
		$along = ($x1 - $x0) * $normal[0] + ($y1 - $y0) * $normal[1];
		if ($squared == 0 || $along == 0) {
			return null;
		}

		$x3 = $x0 + $normal[0] * $along / $squared;
		$y3 = $y0 + $normal[1] * $along / $squared;

		return self::along([$x0, $y0, $x3, $y3], $first, $last);
	}

	/**
	 * @param int  $offset Where the ColorLine is
	 * @param bool $var    Whether it is a VarColorLine, whose stops each end in a varIndexBase
	 *
	 * @return ColorLine|null
	 */
	private function colorLine($offset, $var)
	{
		$header = $this->fields($offset, 3, 'Cextend/ncount');
		$size = $var ? 10 : 6;
		$data = $header === null ? null : $this->fields($offset + 3, $header[1] * $size, 'a*');
		if ($data === null) {
			return null;
		}

		$stops = [];
		for ($i = 0; $i < $header[1]; $i++) {
			$stop = unpack('noffset/nindex/nalpha', substr($data[0], $i * $size, 6));
			list($colour, $alpha) = $this->file->colour($stop['index'], self::signed($stop['alpha']) / 16384);
			// A stop in the colour of the text is black: a shading cannot take its colour from the text
			$stops[] = [self::signed($stop['offset']) / 16384, $colour === null ? [0, 0, 0] : $colour, $alpha];
		}

		return new ColorLine($header[0], $stops);
	}

	/**
	 * @param int    $format A transform's paint format, from 12 to 30
	 * @param string $paint  Its bytes
	 * @param int    $offset Where it is
	 *
	 * @return float[]|null The transform as a PDF matrix, or null where it cannot be read
	 */
	private function transform($format, $paint, $offset)
	{
		switch ($format) {
			case 12:
				// An Affine2x3 of 16.16 fixed numbers, in the order a PDF matrix takes them
				$affine = self::child($offset, $paint, 4);
				$fixed = $affine === null ? null : $this->fields($affine, 24, 'N6');
				if ($fixed === null) {
					return null;
				}

				return array_map(function ($value) {
					return ($value >= 0x80000000 ? $value - 0x100000000 : $value) / 65536;
				}, $fixed);

			case 14:
				return [1, 0, 0, 1, self::int16At($paint, 4), self::int16At($paint, 6)];

			case 16:
			case 18:
				$matrix = [self::f2dot14($paint, 4), 0, 0, self::f2dot14($paint, 6), 0, 0];
				break;

			case 20:
			case 22:
				$scale = self::f2dot14($paint, 4);
				$matrix = [$scale, 0, 0, $scale, 0, 0];
				break;

			case 24:
			case 26:
				// Counter-clockwise, in half turns
				$angle = self::f2dot14($paint, 4) * M_PI;
				$matrix = [cos($angle), sin($angle), -sin($angle), cos($angle), 0, 0];
				break;

			case 28:
			case 30:
				// The x angle is clockwise and the y counter-clockwise, each in half turns
				$matrix = [1, tan(self::f2dot14($paint, 6) * M_PI), -tan(self::f2dot14($paint, 4) * M_PI), 1, 0, 0];
				break;

			default:
				return null;
		}

		// The formats about a centre end in the centre's x and y
		return in_array($format, [18, 22, 26, 30], true) ? self::around($matrix, $paint, self::SIZES[$format] - 4) : $matrix;
	}

	/**
	 * @param float[] $matrix A transform about the origin
	 * @param string  $paint  The paint's bytes
	 * @param int     $at     Where in them its centre's x and y are
	 *
	 * @return float[] The transform about the centre instead
	 */
	private static function around(array $matrix, $paint, $at)
	{
		$x = self::int16At($paint, $at);
		$y = self::int16At($paint, $at + 2);

		$matrix[4] = $x - $matrix[0] * $x - $matrix[2] * $y;
		$matrix[5] = $y - $matrix[1] * $x - $matrix[3] * $y;

		return $matrix;
	}

	/**
	 * @param array   $colour As ColorFontFile::colour() gives it
	 * @param float[] $matrix What takes the paint's space to glyph space
	 *
	 * @return string Content filling the clip in the colour
	 */
	private function fill(array $colour, array $matrix)
	{
		$box = Geometry::boxIn($matrix, $this->box);
		if ($box === null) {
			return '';
		}

		return $this->filled($colour, Geometry::rectangle($box) . ' re f', $this->resources);
	}

	/**
	 * @param int $offset Where a paint is
	 *
	 * @return int|null The glyph, where the paint is a PaintGlyph in an opaque colour, whose alpha is
	 *                  its outline; null otherwise
	 */
	private function opaqueGlyph($offset)
	{
		$paint = $this->paintAt($offset);
		if (strlen($paint) < self::SIZES[10] || ord($paint[0]) !== 10) {
			return null;
		}

		$child = self::child($offset, $paint, 1);
		$solid = $child === null ? null : $this->solid($child);

		return $solid !== null && $solid[1] >= 1 ? self::uint16($paint, 4) : null;
	}

	/**
	 * @param int $offset Where a paint is
	 *
	 * @return array|null Its colour, as ColorFontFile::colour() gives it, where it is a PaintSolid or a
	 *                    PaintVarSolid; null where it is anything else
	 */
	private function solid($offset)
	{
		$paint = $this->paintAt($offset);
		if (strlen($paint) < self::SIZES[2] || (ord($paint[0]) !== 2 && ord($paint[0]) !== 3)) {
			return null;
		}

		return $this->file->colour(self::uint16($paint, 1), self::f2dot14($paint, 3));
	}

	/**
	 * @param int $offset Where a paint is
	 *
	 * @return string Its bytes, as many as the longest paint's fields take or as COLR has left, or none
	 *                where it lies outside COLR
	 */
	private function paintAt($offset)
	{
		if (!isset($this->paints[$offset])) {
			$length = min(max(self::SIZES), $this->end - $offset);
			$bytes = $length > 0 ? $this->fields($offset, $length, 'a*') : null;
			$this->paints[$offset] = $bytes ? $bytes[0] : '';
		}

		return $this->paints[$offset];
	}

	/**
	 * @param int $glyph
	 *
	 * @return float[] The glyph's clip box, or the font's bounding box where it has none
	 */
	private function clipBox($glyph)
	{
		foreach ($this->clips as $clip) {
			if ($glyph >= $clip[0] && $glyph <= $clip[1]) {
				// format, xMin, yMin, xMax, yMax: format 2 is the same, with a varIndexBase after
				$box = $this->fields($clip[2], 9, 'Cformat/n4');
				if ($box !== null && ($box[0] === 1 || $box[0] === 2)) {
					return array_map([__CLASS__, 'signed'], array_slice($box, 1));
				}
			}
		}

		return $this->file->bbox();
	}

	/**
	 * @param int    $offset Where a paint is
	 * @param string $paint  Its bytes
	 * @param int    $at     Where in them an Offset24 to a subtable is
	 *
	 * @return int|null Where the subtable is, or null where the offset is 0, which is no subtable
	 */
	private static function child($offset, $paint, $at)
	{
		$relative = ord($paint[$at]) << 16 | self::uint16($paint, $at + 1);

		return $relative === 0 ? null : $offset + $relative;
	}

	/**
	 * FontReader::fieldsAt(), which also reads nothing past the end of COLR
	 *
	 * @param int    $position From the start of the font
	 * @param int    $length   The bytes the fields take
	 * @param string $format   How to unpack() them
	 *
	 * @return array|null
	 */
	private function fields($position, $length, $format)
	{
		return $position + $length > $this->end ? null : $this->file->reader->fieldsAt($position, $length, $format);
	}

	/**
	 * @param int $value A uint16
	 *
	 * @return int The same bits as an int16
	 */
	private static function signed($value)
	{
		return $value >= 0x8000 ? $value - 0x10000 : $value;
	}

	/**
	 * @return int The int16 at a place in some bytes
	 */
	private static function int16At($bytes, $at)
	{
		return FontReader::int16(substr($bytes, $at, 2));
	}

	/**
	 * @return int The uint16 at a place in some bytes
	 */
	private static function uint16($bytes, $at)
	{
		return ord($bytes[$at]) << 8 | ord($bytes[$at + 1]);
	}

	/**
	 * @return float The F2DOT14 at a place in some bytes
	 */
	private static function f2dot14($bytes, $at)
	{
		return self::int16At($bytes, $at) / 16384;
	}
}
