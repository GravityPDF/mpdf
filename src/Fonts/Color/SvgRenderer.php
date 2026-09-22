<?php

namespace Mpdf\Fonts\Color;

use DOMDocument;
use DOMElement;
use Mpdf\Color\NamedColors;
use Mpdf\Image\Svg\Path;

/**
 * Draws the glyphs of one OpenType SVG document, covering the part of SVG 1.1 the OpenType SVG table
 * requires:
 *
 * - Shapes: path, rect, circle, ellipse, line, polyline and polygon, filled and stroked
 * - Structure: g, use, and svg inside the document, drawn as a g is
 * - Paint: colours, currentColor - the colour of the text - and CPAL colours through
 *   var(--colorN, fallback); linearGradient and radialGradient, in either gradientUnits, with
 *   gradientTransform, spreadMethod and a gradient's href
 * - clip-path, from a clipPath of any number of shapes: one clips with W n, several through a soft
 *   mask of them all, since the shapes of a clip path add up while a PDF clip path's winding may not
 * - mask, as a soft mask of its content's luminosity within the mask's region, in either maskUnits and
 *   maskContentUnits. PDF takes the luminosity of the colours as they are, where SVG 1.1 takes it of
 *   them in linear RGB, which is the same for white, black and anything between drawn at an opacity.
 * - opacity, fill-opacity and stroke-opacity; an element's opacity paints it as a group, unless it is
 *   a shape with no stroke, whose fill takes the opacity itself
 * - image, a PNG or a JPEG as a data: URL
 * - Presentation attributes and the style attribute
 *
 * Not drawn, each with a warning: text and the other elements a font must not use, a style element's
 * rules, filter, pattern and marker. A gradient stroke is drawn in its middle stop's colour. A
 * stroke in currentColor is drawn in the stroking colour of the text, not its fill colour.
 *
 * The glyph element is drawn where it is in the document, as FreeType draws one through librsvg: the
 * root's viewBox, and its ancestors' transforms and inherited properties, apply. Groups and masks are
 * drawn within the font's bounding box.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/svg
 * @see https://www.w3.org/TR/SVG11/
 */
class SvgRenderer
{

	use FillsInColour;
	use FillsWithGradients;

	/**
	 * How deep elements may nest, counting each use as a level
	 */
	const MAX_DEPTH = 64;

	/**
	 * The properties a child takes from its parent, each with its initial value. A color of '' is the
	 * colour of the text.
	 */
	const INHERITED = [
		'fill' => 'black', 'fill-opacity' => '1', 'fill-rule' => 'nonzero', 'stroke' => 'none', 'stroke-width' => '1',
		'stroke-opacity' => '1', 'stroke-linecap' => 'butt', 'stroke-linejoin' => 'miter', 'stroke-miterlimit' => '4',
		'stroke-dasharray' => 'none', 'stroke-dashoffset' => '0', 'color' => '', 'clip-rule' => 'nonzero', 'visibility' => 'visible',
	];

	/**
	 * The properties that apply to one element alone, each with its initial value
	 */
	const OWN = ['opacity' => '1', 'clip-path' => 'none', 'display' => 'inline', 'mask' => 'none', 'filter' => 'none', 'stop-color' => 'black', 'stop-opacity' => '1'];

	/**
	 * Elements a font must not use, which are not drawn
	 */
	const FORBIDDEN = ['text', 'font', 'foreignObject', 'switch', 'script', 'a', 'view'];

	const SHAPES = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'];

	const XLINK = 'http://www.w3.org/1999/xlink';

	/**
	 * @var DOMElement[] Each element of the document with an id, by it
	 */
	private $ids = [];

	/**
	 * @var bool Whether the document has a style element
	 */
	private $styled = false;

	/**
	 * @var ColorFontFile
	 */
	private $file;

	/**
	 * @var float The em, in font units: the size of the initial viewport, and what a percentage of it is of
	 */
	private $em;

	/**
	 * @var float[] The font's bounding box, in glyph space, which groups and masks are drawn within
	 */
	private $box;

	/**
	 * @var GlyphResources
	 */
	private $resources;

	/**
	 * @var int The glyph being drawn, for a warning
	 */
	private $glyph = 0;

	/**
	 * @var Path[] Each path data the document's glyphs have drawn, parsed, by the data: a shape that use
	 *             draws many times, or several paths with the same data, are parsed once
	 */
	private $paths = [];

	/**
	 * @param DOMDocument   $document The SVG document
	 * @param ColorFontFile $file     The font, whose palette var() reads and whose log is warned
	 */
	public function __construct(DOMDocument $document, ColorFontFile $file)
	{
		foreach ($document->getElementsByTagName('*') as $element) {
			if ($element->hasAttribute('id') && !isset($this->ids[$element->getAttribute('id')])) {
				$this->ids[$element->getAttribute('id')] = $element;
			}
			$this->styled = $this->styled || $element->localName === 'style';
		}

		$this->file = $file;
		$this->em = $file->unitsPerEm;
		$this->box = $file->bbox();
	}

	/**
	 * @param int            $glyph     The glyph
	 * @param GlyphResources $resources What the glyph registers its images, shadings, groups and masks with
	 *
	 * @return string|null Content drawing the glyph in glyph space, or null where the document has no
	 *                     element for it or it draws nothing
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		if (!isset($this->ids['glyph' . $glyph])) {
			return null;
		}

		$element = $this->ids['glyph' . $glyph];
		$this->resources = $resources;
		$this->glyph = $glyph;

		if ($this->styled) {
			$this->file->warn($glyph, 'a <style> element, whose rules are not applied');
		}

		$root = $element->ownerDocument->documentElement;
		$viewBox = $this->viewBox($root);
		if ($viewBox === null) {
			return null;
		}

		// SVG's y runs down from the baseline, and glyph space's up
		$content = "1 0 0 -1 0 0 cm\n" . self::cm($viewBox);
		$matrix = Geometry::multiply($viewBox, [1, 0, 0, -1, 0, 0]);

		// The ancestors' transforms and inherited properties, the root's first
		$ancestors = [];
		for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
			array_unshift($ancestors, $node);
		}
		$style = self::INHERITED + self::OWN;
		foreach ($ancestors as $ancestor) {
			$style = $this->cascade($style, $ancestor);
			if ($ancestor !== $root) {
				$transform = self::transform($ancestor->getAttribute('transform'));
				$content .= self::cm($transform);
				$matrix = Geometry::multiply($transform, $matrix);
			}
		}

		$drawn = $this->element($element, $style, $matrix, 0);

		return $drawn === '' ? null : "q\n" . $content . $drawn . "Q\n";
	}

	/**
	 * @param DOMElement $root The document's svg element
	 *
	 * @return float[]|null What takes the root's user space to the em square, where it has a viewBox;
	 *                      the identity where it has none; null where its viewBox is empty
	 */
	private function viewBox(DOMElement $root)
	{
		$values = self::values($root->getAttribute('viewBox'));
		if (count($values) !== 4) {
			return Geometry::IDENTITY;
		}
		if ($values[2] <= 0 || $values[3] <= 0) {
			return null;
		}

		$width = $root->hasAttribute('width') ? $this->length($root->getAttribute('width'), $this->em) : $this->em;
		$height = $root->hasAttribute('height') ? $this->length($root->getAttribute('height'), $this->em) : $this->em;

		return self::fit($values, [0, 0, $width, $height], $root->getAttribute('preserveAspectRatio'));
	}

	/**
	 * @param DOMElement $element
	 * @param string[]   $parent  The parent's properties
	 * @param float[]    $matrix  What takes the parent's user space to glyph space
	 * @param int        $depth   How deep the element is
	 *
	 * @return string Content drawing the element in its parent's user space
	 */
	private function element(DOMElement $element, array $parent, array $matrix, $depth)
	{
		$name = $element->localName;
		$shape = in_array($name, self::SHAPES, true);

		if (in_array($name, self::FORBIDDEN, true)) {
			$this->file->warn($this->glyph, sprintf('a <%s> element, which a font must not use', $name));

			return '';
		}
		if (!$shape && !in_array($name, ['g', 'svg', 'use', 'image'], true)) {
			return '';
		}
		if ($depth >= self::MAX_DEPTH) {
			$this->file->warn($this->glyph, 'elements that use each other or nest too deep, which are drawn only so far');

			return '';
		}

		$style = $this->cascade($parent, $element);
		if ($style['display'] === 'none') {
			return '';
		}
		if ($style['filter'] !== 'none') {
			$this->file->warn($this->glyph, 'a filter, which is not drawn');
		}

		$transform = self::transform($element->getAttribute('transform'));
		if ($name === 'use') {
			$transform = Geometry::multiply($this->position($element), $transform);
		}
		$inner = Geometry::multiply($transform, $matrix);

		$opacity = self::opacity($style['opacity']);
		if ($opacity <= 0) {
			return '';
		}

		// A shape that only fills takes its opacity into the fill
		$folded = $shape && $style['stroke'] === 'none';

		if ($shape) {
			$content = $this->shape($element, $style, $inner, $folded ? $opacity : 1);
		} elseif ($name === 'image') {
			$content = $this->image($element, $style);
		} elseif ($name === 'use') {
			$target = $this->target($element);
			$content = $target === null ? '' : $this->element($target, $style, $inner, $depth + 1);
		} else {
			$content = '';
			foreach ($element->childNodes as $child) {
				if ($child instanceof DOMElement) {
					$content .= $this->element($child, $style, $inner, $depth + 1);
				}
			}
		}

		if ($content === '') {
			return '';
		}

		if ($opacity < 1 && !$folded) {
			$box = Geometry::boxIn($inner, $this->box);
			if ($box !== null) {
				$content = sprintf("q %s\n%s Do\nQ\n", $this->resources->alpha($opacity), $this->resources->group($content, $box));
			}
		}

		$masked = false;
		foreach (['mask' => 'mask', 'clip-path' => 'clip'] as $property => $method) {
			if ($style[$property] === 'none') {
				continue;
			}
			$limit = $this->$method($style[$property], $element, $inner, $depth);
			if ($limit === null) {
				return '';
			}
			$content = $limit . $content;
			$masked = $masked || $limit !== '';
		}

		$cm = self::cm($transform);

		return $cm !== '' || $masked ? "q\n" . $cm . $content . "Q\n" : $content;
	}

	/**
	 * @param DOMElement $element A basic shape or a path
	 * @param string[]   $style   Its properties
	 * @param float[]    $matrix  What takes its user space to glyph space
	 * @param float      $opacity What its fill and stroke opacity are multiplied by
	 *
	 * @return string
	 */
	private function shape(DOMElement $element, array $style, array $matrix, $opacity)
	{
		$path = $this->path($element);
		$outline = $path === null ? '' : $path->content();
		if ($outline === '' || $style['visibility'] !== 'visible') {
			return '';
		}

		$content = '';
		$evenOdd = $style['fill-rule'] === 'evenodd';
		$fill = $this->paint($style['fill'], $style);
		$fillOpacity = self::opacity($style['fill-opacity']) * $opacity;

		if ($fill instanceof DOMElement) {
			$content .= $this->gradient($fill, $path, $outline, $evenOdd, $fillOpacity, $matrix);
		} elseif ($fill !== null) {
			$content .= $this->filled([$fill[0], $fill[1] * $fillOpacity], $outline . ($evenOdd ? 'f*' : 'f'), $this->resources);
		}

		return $content . $this->stroke($outline, $style, $opacity);
	}

	/**
	 * @param string   $outline The path, as content draws it
	 * @param string[] $style   The shape's properties
	 * @param float    $opacity What its stroke opacity is multiplied by
	 *
	 * @return string Content stroking the path, or nothing where it has no stroke
	 */
	private function stroke($outline, array $style, $opacity)
	{
		$paint = $this->paint($style['stroke'], $style);
		$width = $this->length($style['stroke-width'], $this->em);
		if ($paint === null || $width <= 0) {
			return '';
		}

		if ($paint instanceof DOMElement) {
			$this->file->warn($this->glyph, 'a gradient stroke, which is drawn in the colour of its middle stop');
			$stops = $this->stops($this->chain($paint), 1);
			if (!$stops) {
				return '';
			}
			$paint = (new ColorLine(ColorLine::PAD, $stops))->middle();
		}

		list($rgb, $alpha) = $paint;
		$alpha *= self::opacity($style['stroke-opacity']) * $opacity;
		if ($alpha <= 0) {
			return '';
		}

		$caps = ['butt' => 0, 'round' => 1, 'square' => 2];
		$joins = ['miter' => 0, 'round' => 1, 'bevel' => 2];
		$set = $alpha < 1 ? [$this->resources->alpha($alpha)] : [];
		$set[] = Geometry::number($width) . ' w';
		$set[] = (isset($caps[$style['stroke-linecap']]) ? $caps[$style['stroke-linecap']] : 0) . ' J';
		$set[] = (isset($joins[$style['stroke-linejoin']]) ? $joins[$style['stroke-linejoin']] : 0) . ' j';
		$set[] = Geometry::number(max(1, self::number($style['stroke-miterlimit'], 4))) . ' M';
		$dashes = $this->dashes($style['stroke-dasharray']);
		if ($dashes) {
			$set[] = sprintf('[%s] %s d', Geometry::numbers($dashes), Geometry::number($this->length($style['stroke-dashoffset'], 0)));
		}
		if ($rgb !== null) {
			$set[] = vsprintf('%.3F %.3F %.3F RG', $rgb);
		}

		return 'q ' . implode(' ', $set) . "\n" . $outline . "S\nQ\n";
	}

	/**
	 * @param string $value A stroke-dasharray
	 *
	 * @return float[] The dashes and gaps, an odd list repeated to make it even, or none where the
	 *                 line is solid: 'none', a negative length, or every length 0
	 */
	private function dashes($value)
	{
		if ($value === 'none') {
			return [];
		}

		$dashes = [];
		foreach (preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $dash) {
			$length = $this->length($dash, 0);
			if ($length < 0) {
				return [];
			}
			$dashes[] = $length;
		}

		if (!array_filter($dashes)) {
			return [];
		}

		return count($dashes) % 2 ? array_merge($dashes, $dashes) : $dashes;
	}

	/**
	 * @param DOMElement $element A basic shape or a path
	 *
	 * @return Path|null The shape, or null where it draws nothing: a width, height or radius of 0
	 */
	private function path(DOMElement $element)
	{
		$em = $this->em;

		switch ($element->localName) {
			case 'path':
				$data = $element->getAttribute('d');
				if (!isset($this->paths[$data])) {
					$this->paths[$data] = Path::parse($data);
				}

				return $this->paths[$data];

			case 'rect':
				$w = $this->length($element->getAttribute('width'), $em);
				$h = $this->length($element->getAttribute('height'), $em);
				if ($w <= 0 || $h <= 0) {
					return null;
				}
				// A radius given alone is both, and neither is more than half the side
				$rx = $element->hasAttribute('rx') ? $this->length($element->getAttribute('rx'), $em) : null;
				$ry = $element->hasAttribute('ry') ? $this->length($element->getAttribute('ry'), $em) : null;
				$rx = $rx === null ? ($ry === null ? 0 : $ry) : $rx;
				$ry = $ry === null ? $rx : $ry;

				return Path::rectangle(
					$this->length($element->getAttribute('x'), $em),
					$this->length($element->getAttribute('y'), $em),
					$w,
					$h,
					min(max(0, $rx), $w / 2),
					min(max(0, $ry), $h / 2)
				);

			case 'circle':
			case 'ellipse':
				$circle = $element->localName === 'circle';
				$rx = $this->length($element->getAttribute($circle ? 'r' : 'rx'), $em);
				$ry = $circle ? $rx : $this->length($element->getAttribute('ry'), $em);
				if ($rx <= 0 || $ry <= 0) {
					return null;
				}

				return Path::ellipse($this->length($element->getAttribute('cx'), $em), $this->length($element->getAttribute('cy'), $em), $rx, $ry);

			case 'line':
				return Path::points([
					$this->length($element->getAttribute('x1'), $em),
					$this->length($element->getAttribute('y1'), $em),
					$this->length($element->getAttribute('x2'), $em),
					$this->length($element->getAttribute('y2'), $em),
				], false);

			default:
				return Path::points(self::values($element->getAttribute('points')), $element->localName === 'polygon');
		}
	}

	/**
	 * @param DOMElement $gradient A linearGradient or a radialGradient
	 * @param Path    $path     The shape it fills
	 * @param string     $outline  The shape, as content draws it
	 * @param bool       $evenOdd  Whether the shape's fill-rule is evenodd
	 * @param float      $opacity  What the stops' alpha is multiplied by
	 * @param float[]    $matrix   What takes the shape's user space to glyph space
	 *
	 * @return string
	 */
	private function gradient(DOMElement $gradient, Path $path, $outline, $evenOdd, $opacity, array $matrix)
	{
		$chain = $this->chain($gradient);
		$stops = $this->stops($chain, $opacity);
		if (!$stops) {
			return '';
		}

		// What takes the gradient's space to the shape's: the shape's bounding box, where the gradient is
		// drawn in proportion to it, and a percentage is of the box, not the em
		$inBox = self::inherited($chain, 'gradientUnits', false) !== 'userSpaceOnUse';
		$space = Geometry::IDENTITY;
		$size = $this->em;
		if ($inBox) {
			$bounds = $path->bounds();
			if ($bounds === null || $bounds[2] <= $bounds[0] || $bounds[3] <= $bounds[1]) {
				return '';
			}
			$space = Geometry::boxMatrix($bounds);
			$size = 1;
		}
		$space = Geometry::multiply(self::transform((string) self::inherited($chain, 'gradientTransform', false)), $space);

		$coordinate = function ($name, $default) use ($chain, $inBox, $size) {
			$value = self::inherited($chain, $name, true);
			$value = $value === null ? $default : $value;

			return $inBox && substr(trim($value), -1) !== '%' ? self::number($value, 0) : $this->length($value, $size);
		};

		if ($gradient->localName === 'linearGradient') {
			$geometry = [$coordinate('x1', '0%'), $coordinate('y1', '0%'), $coordinate('x2', '100%'), $coordinate('y2', '0%')];
			$single = $geometry[0] == $geometry[2] && $geometry[1] == $geometry[3];
		} else {
			$cx = $coordinate('cx', '50%');
			$cy = $coordinate('cy', '50%');
			$r = $coordinate('r', '50%');
			$fx = $coordinate('fx', (string) $cx);
			$fy = $coordinate('fy', (string) $cy);
			// A focus outside the circle is moved onto it, as SVG 1.1 says
			$distance = hypot($fx - $cx, $fy - $cy);
			if ($r > 0 && $distance > $r) {
				$fx = $cx + ($fx - $cx) * $r * 0.999 / $distance;
				$fy = $cy + ($fy - $cy) * $r * 0.999 / $distance;
			}
			$geometry = [$fx, $fy, 0, $cx, $cy, $r];
			$single = $r <= 0;
		}

		$first = reset($stops);
		$last = end($stops);

		// One stop, or a gradient of no length, is the last stop's colour throughout
		if (count($stops) === 1 || $single) {
			return $this->filled([$last[1], $last[2]], $outline . ($evenOdd ? 'f*' : 'f'), $this->resources);
		}

		// The colours before the first stop and after the last are theirs
		if ($first[0] > 0) {
			array_unshift($stops, [0, $first[1], $first[2]]);
		}
		if ($last[0] < 1) {
			$stops[] = [1, $last[1], $last[2]];
		}

		$spreads = ['reflect' => ColorLine::REFLECT, 'repeat' => ColorLine::REPEAT];
		$spread = (string) self::inherited($chain, 'spreadMethod', false);
		$line = new ColorLine(isset($spreads[$spread]) ? $spreads[$spread] : ColorLine::PAD, $stops);

		$box = Geometry::boxIn(Geometry::multiply($space, $matrix), $this->box);
		$fill = $box === null ? '' : $this->gradientFill($line, $geometry, $box, $this->resources);

		return $fill === '' ? '' : "q\n" . $outline . ($evenOdd ? 'W*' : 'W') . " n\n" . self::cm($space) . $fill . "Q\n";
	}

	/**
	 * @param DOMElement[] $chain   A gradient and those it takes from by href - see chain()
	 * @param float        $opacity What each stop's alpha is multiplied by
	 *
	 * @return array[] The stops of the first in the chain that has any, as [offset from 0 to 1,
	 *                 [red, green, blue], alpha], each offset at least the one before it. A stop in
	 *                 currentColor is black, since a shading cannot take its colour from the text.
	 */
	private function stops(array $chain, $opacity)
	{
		$stops = [];
		$previous = 0;
		foreach ($chain as $element) {
			foreach ($element->childNodes as $stop) {
				if (!$stop instanceof DOMElement || $stop->localName !== 'stop') {
					continue;
				}

				$style = $this->cascade(self::INHERITED + self::OWN, $stop);
				$offset = trim($stop->getAttribute('offset'));
				$offset = substr($offset, -1) === '%' ? self::number(substr($offset, 0, -1), 0) / 100 : self::number($offset, 0);
				$previous = max($previous, min(1, max(0, $offset)));

				list($rgb, $alpha) = $this->colour($style['stop-color'], $style);
				$stops[] = [$previous, $rgb === null ? [0, 0, 0] : $rgb, $alpha * self::opacity($style['stop-opacity']) * $opacity];
			}

			if ($stops) {
				break;
			}
		}

		return $stops;
	}

	/**
	 * @param string     $url     A clip-path, url(#id)
	 * @param DOMElement $element The element it clips
	 * @param float[]    $matrix  What takes the element's user space to glyph space
	 * @param int        $depth   How deep the element is
	 *
	 * @return string|null Content clipping to the clip path in the element's user space, nothing where
	 *                     it names no clipPath, or null where it clips everything away
	 */
	private function clip($url, DOMElement $element, array $matrix, $depth)
	{
		list($clipPath) = $this->url($url);
		if ($clipPath === null || $clipPath->localName !== 'clipPath') {
			return '';
		}
		if ($clipPath->hasAttribute('clip-path')) {
			$this->file->warn($this->glyph, 'a clip path clipped in turn, which is clipped by the first alone');
		}

		// What takes the clip path's space to the element's
		$space = self::transform($clipPath->getAttribute('transform'));
		if ($clipPath->getAttribute('clipPathUnits') === 'objectBoundingBox') {
			$bounds = $this->extent($element, $depth);
			if ($bounds === null) {
				return null;
			}
			$space = Geometry::multiply($space, Geometry::boxMatrix($bounds));
		}

		$pieces = [];
		foreach ($clipPath->childNodes as $child) {
			if ($child instanceof DOMElement) {
				$pieces = array_merge($pieces, $this->clipPieces($child, $space, $depth));
			}
		}

		if (!$pieces) {
			return null;
		}

		if (count($pieces) === 1) {
			return $pieces[0][0] . ($pieces[0][1] ? 'W*' : 'W') . " n\n";
		}

		$box = Geometry::boxIn($matrix, $this->box);
		if ($box === null) {
			return null;
		}

		$mask = "0 g\n";
		foreach ($pieces as $piece) {
			$mask .= $piece[0] . ($piece[1] ? 'f*' : 'f') . "\n";
		}

		return $this->resources->softMask($mask, $box) . "\n";
	}

	/**
	 * @param string     $url     A mask, url(#id)
	 * @param DOMElement $element The element it masks
	 * @param float[]    $matrix  What takes the element's user space to glyph space
	 * @param int        $depth   How deep the element is
	 *
	 * @return string|null Content masking what follows by the mask's luminosity, in the element's user
	 *                     space; nothing where it names no mask; null where it masks everything away
	 */
	private function mask($url, DOMElement $element, array $matrix, $depth)
	{
		list($mask) = $this->url($url);
		if ($mask === null || $mask->localName !== 'mask') {
			return '';
		}

		$box = Geometry::boxIn($matrix, $this->box);
		$bounds = $this->extent($element, $depth);
		$byBox = $mask->getAttribute('maskUnits') !== 'userSpaceOnUse';
		if ($box === null || ($bounds === null && ($byBox || $mask->getAttribute('maskContentUnits') === 'objectBoundingBox'))) {
			return null;
		}

		// The region the mask covers, by default a tenth of the bounding box beyond it each way
		$region = [];
		foreach (['x' => '-10%', 'y' => '-10%', 'width' => '120%', 'height' => '120%'] as $name => $initial) {
			$value = $mask->hasAttribute($name) ? $mask->getAttribute($name) : $initial;
			$region[] = $this->length($value, $byBox ? 1 : $this->em);
		}
		if ($region[2] <= 0 || $region[3] <= 0) {
			return null;
		}
		$region = [$region[0], $region[1], $region[0] + $region[2], $region[1] + $region[3]];
		if ($byBox) {
			$region = Geometry::bounds(Geometry::boxMatrix($bounds), $region);
		}

		$space = $mask->getAttribute('maskContentUnits') === 'objectBoundingBox' ? Geometry::boxMatrix($bounds) : Geometry::IDENTITY;
		$style = $this->cascade(self::INHERITED + self::OWN, $mask);
		$content = '';
		foreach ($mask->childNodes as $child) {
			if ($child instanceof DOMElement) {
				$content .= $this->element($child, $style, Geometry::multiply($space, $matrix), $depth + 1);
			}
		}
		if ($content === '') {
			return null;
		}

		$drawn = 'q ' . Geometry::rectangle($region) . " re W n\n" . self::cm($space) . $content . "Q\n";

		return $this->resources->softMask($drawn, $box, true) . "\n";
	}

	/**
	 * @param DOMElement $child  A child of a clipPath
	 * @param float[]    $matrix What takes the clip path's space to the clipped element's
	 * @param int        $depth  How deep the clipped element is
	 *
	 * @return array[] The shapes it adds to the clip, each [its outline in the clipped element's space,
	 *                 whether its clip-rule is evenodd]
	 */
	private function clipPieces(DOMElement $child, array $matrix, $depth)
	{
		$style = $this->cascade(self::INHERITED + self::OWN, $child);
		if ($style['display'] === 'none' || $style['visibility'] !== 'visible' || $depth >= self::MAX_DEPTH) {
			return [];
		}

		$transform = Geometry::multiply(self::transform($child->getAttribute('transform')), $matrix);

		if ($child->localName === 'use') {
			$target = $this->target($child);

			return $target === null ? [] : $this->clipPieces($target, Geometry::multiply($this->position($child), $transform), $depth + 1);
		}

		$path = in_array($child->localName, self::SHAPES, true) ? $this->path($child) : null;
		$outline = $path === null ? '' : $path->transformed($transform)->content();

		return $outline === '' ? [] : [[$outline, $style['clip-rule'] === 'evenodd']];
	}

	/**
	 * @param DOMElement $element
	 * @param int        $depth How deep it is
	 *
	 * @return float[]|null The box around what it draws, in its own user space, or null where it draws
	 *                      nothing
	 */
	private function extent(DOMElement $element, $depth)
	{
		if ($depth >= self::MAX_DEPTH) {
			return null;
		}

		if (in_array($element->localName, self::SHAPES, true)) {
			$path = $this->path($element);

			return $path === null ? null : $path->bounds();
		}

		$children = [];
		$shift = Geometry::IDENTITY;
		if ($element->localName === 'use') {
			$target = $this->target($element);
			$children = $target === null ? [] : [$target];
			$shift = $this->position($element);
		} elseif ($element->localName === 'g' || $element->localName === 'svg') {
			foreach ($element->childNodes as $child) {
				if ($child instanceof DOMElement) {
					$children[] = $child;
				}
			}
		}

		$box = null;
		foreach ($children as $child) {
			$extent = $this->extent($child, $depth + 1);
			if ($extent !== null) {
				$extent = Geometry::bounds(Geometry::multiply(self::transform($child->getAttribute('transform')), $shift), $extent);
				$box = $box === null ? $extent : [min($box[0], $extent[0]), min($box[1], $extent[1]), max($box[2], $extent[2]), max($box[3], $extent[3])];
			}
		}

		return $box;
	}

	/**
	 * @param DOMElement $element An image
	 * @param string[]   $style   Its properties
	 *
	 * @return string Content drawing it, or nothing where it is not a PNG or a JPEG in the document
	 */
	private function image(DOMElement $element, array $style)
	{
		if (!preg_match('/^data:image\/(?:png|jpe?g);base64,(.*)$/s', trim($this->href($element)), $match)) {
			$this->file->warn($this->glyph, 'an image that is not a PNG or a JPEG held in the document, which is not drawn');

			return '';
		}

		$width = $this->length($element->getAttribute('width'), $this->em);
		$height = $this->length($element->getAttribute('height'), $this->em);
		$data = base64_decode(preg_replace('/\s+/', '', $match[1]), true);
		$image = $width > 0 && $height > 0 && $data !== false && $style['visibility'] === 'visible' ? $this->resources->image($data) : null;
		if ($image === null) {
			return '';
		}

		list($name, $pixelsWide, $pixelsHigh) = $image;
		$x = $this->length($element->getAttribute('x'), $this->em);
		$y = $this->length($element->getAttribute('y'), $this->em);
		$fit = self::fit([0, 0, $pixelsWide, $pixelsHigh], [$x, $y, $width, $height], $element->getAttribute('preserveAspectRatio'));

		// An image is drawn over the unit square, its top row at the top: here, at its y
		$placed = Geometry::multiply([$pixelsWide, 0, 0, -$pixelsHigh, 0, $pixelsHigh], $fit);
		$content = "q\n" . self::cm($placed) . $name . " Do\nQ\n";

		// Sliced, the image is cut to its box
		return strpos($element->getAttribute('preserveAspectRatio'), 'slice') === false ? $content : "q\n" . Geometry::rectangle([$x, $y, $x + $width, $y + $height]) . " re W n\n" . $content . "Q\n";
	}

	/**
	 * @param string   $value A fill or a stroke
	 * @param string[] $style The properties of the element it paints
	 *
	 * @return array|DOMElement|null The colour, as ColorFontFile::colour() gives it, the gradient, or
	 *                               null for none
	 */
	private function paint($value, array $style)
	{
		$value = trim($value);
		if ($value === 'none') {
			return null;
		}

		$url = $this->url($value);
		if ($url === null) {
			return $this->colour($value, $style);
		}

		list($server, $fallback) = $url;
		if ($server !== null && in_array($server->localName, ['linearGradient', 'radialGradient'], true)) {
			return $server;
		}
		if ($server !== null) {
			$this->file->warn($this->glyph, sprintf('a <%s> paint, which is drawn as its fallback', $server->localName));
		}

		return $fallback === '' || $fallback === 'none' ? null : $this->colour($fallback, $style);
	}

	/**
	 * @param string   $value A colour
	 * @param string[] $style The properties of the element it colours, whose color currentColor is
	 *
	 * @return array [[red, green, blue] from 0 to 1, or null for the colour of the text, alpha]. A
	 *               colour that cannot be read is black, the initial fill.
	 */
	private function colour($value, array $style)
	{
		$value = trim($value);
		$keyword = strtolower($value);

		if ($keyword === 'currentcolor' || $keyword === 'context-fill' || $keyword === 'context-stroke') {
			return $style['color'] === '' || strtolower($style['color']) === 'currentcolor' ? [null, 1] : $this->colour($style['color'], ['color' => ''] + $style);
		}
		if ($keyword === 'transparent') {
			return [[0, 0, 0], 0];
		}

		// A CPAL colour, or the fallback where the palette has no such entry
		if (preg_match('/^var\(\s*--color(\d+)\s*(?:,\s*(.*))?\)$/s', $value, $match)) {
			if ((int) $match[1] < count($this->file->palette())) {
				return $this->file->colour((int) $match[1], 1);
			}

			return isset($match[2]) && trim($match[2]) !== '' ? $this->colour($match[2], $style) : [[0, 0, 0], 1];
		}

		if (isset(NamedColors::$colors[$keyword])) {
			$keyword = NamedColors::$colors[$keyword];
		}

		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $keyword, $match)) {
			$hex = strlen($match[1]) === 3 ? preg_replace('/(.)/', '$1$1', $match[1]) : $match[1];

			return [[hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255], 1];
		}

		if (preg_match('/^rgb\(\s*([^,\s]+)\s*,?\s*([^,\s]+)\s*,?\s*([^,\s)]+)\s*\)$/', $keyword, $match)) {
			$rgb = [];
			foreach (array_slice($match, 1) as $component) {
				$rgb[] = max(0, min(1, substr($component, -1) === '%' ? (float) $component / 100 : (float) $component / 255));
			}

			return [$rgb, 1];
		}

		return [[0, 0, 0], 1];
	}

	/**
	 * @param string[]   $parent  The parent's properties
	 * @param DOMElement $element
	 *
	 * @return string[] The element's properties: what it sets, as a presentation attribute or in its
	 *                  style attribute, which wins, and otherwise what it inherits or starts with
	 */
	private function cascade(array $parent, DOMElement $element)
	{
		$properties = self::INHERITED + self::OWN;
		$declared = [];
		foreach ($element->attributes as $attribute) {
			if (isset($properties[$attribute->name])) {
				$declared[$attribute->name] = trim($attribute->value);
			}
		}
		foreach (explode(';', $element->getAttribute('style')) as $declaration) {
			$parts = explode(':', $declaration, 2);
			$property = strtolower(trim($parts[0]));
			if (count($parts) === 2 && isset($properties[$property])) {
				$declared[$property] = trim(preg_replace('/\s*!important\s*$/i', '', $parts[1]));
			}
		}

		$style = [];
		foreach (self::INHERITED as $property => $initial) {
			$style[$property] = isset($declared[$property]) && $declared[$property] !== 'inherit' ? $declared[$property] : $parent[$property];
		}
		foreach (self::OWN as $property => $initial) {
			$value = isset($declared[$property]) ? $declared[$property] : $initial;
			$style[$property] = $value === 'inherit' ? $parent[$property] : $value;
		}

		return $style;
	}

	/**
	 * @param DOMElement $gradient
	 *
	 * @return DOMElement[] The gradient, then each gradient it takes from by href in turn, up to one
	 *                      met before
	 */
	private function chain(DOMElement $gradient)
	{
		$chain = [];
		for ($element = $gradient; $element !== null && !in_array($element, $chain, true); $element = $this->target($element)) {
			if (!in_array($element->localName, ['linearGradient', 'radialGradient'], true)) {
				break;
			}
			$chain[] = $element;
		}

		return $chain;
	}

	/**
	 * @param DOMElement[] $chain   A gradient and those it takes from by href - see chain()
	 * @param string       $name    An attribute
	 * @param bool         $ownKind Whether only a gradient of the same kind lends it: true for its
	 *                              geometry, false for its units, transform and spread
	 *
	 * @return string|null The attribute, from the gradient or the first it takes it from
	 */
	private static function inherited(array $chain, $name, $ownKind)
	{
		foreach ($chain as $element) {
			if ($ownKind && $element->localName !== $chain[0]->localName) {
				continue;
			}
			if ($element->hasAttribute($name)) {
				return $element->getAttribute($name);
			}
		}

		return null;
	}

	/**
	 * @param DOMElement $element A use or a gradient
	 *
	 * @return DOMElement|null What its href names, where the document has it
	 */
	private function target(DOMElement $element)
	{
		$href = trim($this->href($element));

		return $href !== '' && $href[0] === '#' && isset($this->ids[substr($href, 1)]) ? $this->ids[substr($href, 1)] : null;
	}

	/**
	 * @param DOMElement $use
	 *
	 * @return float[] The use's x and y, as a translation
	 */
	private function position(DOMElement $use)
	{
		return [1, 0, 0, 1, $this->length($use->getAttribute('x'), $this->em), $this->length($use->getAttribute('y'), $this->em)];
	}

	/**
	 * @param string $value A url(#id), and what may follow it
	 *
	 * @return array|null [what it names, or null where the document has no such element, what follows
	 *                    it], or null where it is not a url()
	 */
	private function url($value)
	{
		if (!preg_match('/^url\(\s*[\'"]?#([^\'")]+)[\'"]?\s*\)\s*(.*)$/s', trim($value), $match)) {
			return null;
		}

		return [isset($this->ids[$match[1]]) ? $this->ids[$match[1]] : null, trim($match[2])];
	}

	/**
	 * @param DOMElement $element
	 *
	 * @return string Its xlink:href, or its href
	 */
	private function href(DOMElement $element)
	{
		return $element->hasAttributeNS(self::XLINK, 'href') ? $element->getAttributeNS(self::XLINK, 'href') : $element->getAttribute('href');
	}

	/**
	 * @param string $value     A length: a number, in user units or with a unit, or a percentage
	 * @param float  $reference What a percentage is of
	 *
	 * @return float The length in user units, or 0 where there is none
	 */
	private function length($value, $reference)
	{
		$units = ['' => 1, 'px' => 1, 'pt' => 1.25, 'pc' => 15, 'mm' => 3.543307, 'cm' => 35.43307, 'in' => 90, '%' => $reference / 100];
		if (!preg_match('/^\s*([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)\s*(px|pt|pc|mm|cm|in|%)?\s*$/', $value, $match)) {
			return 0;
		}

		return (float) $match[1] * $units[isset($match[2]) ? $match[2] : ''];
	}

	/**
	 * @param string $value A transform attribute
	 *
	 * @return float[] The transform it lists, the first outermost; the identity where it lists none or
	 *                 cannot be read
	 */
	private static function transform($value)
	{
		if (!preg_match_all('/(matrix|translate|scale|rotate|skewX|skewY)\s*\(([^)]*)\)/', $value, $matches, PREG_SET_ORDER)) {
			return Geometry::IDENTITY;
		}

		$matrix = Geometry::IDENTITY;
		foreach (array_reverse($matches) as $match) {
			$values = self::values($match[2]);
			$count = count($values);
			$transform = null;
			switch ($match[1]) {
				case 'matrix':
					$transform = $count === 6 ? $values : null;
					break;
				case 'translate':
					$transform = $count === 1 || $count === 2 ? [1, 0, 0, 1, $values[0], $count === 2 ? $values[1] : 0] : null;
					break;
				case 'scale':
					$transform = $count === 1 || $count === 2 ? [$values[0], 0, 0, $count === 2 ? $values[1] : $values[0], 0, 0] : null;
					break;
				case 'rotate':
					if ($count === 1 || $count === 3) {
						$angle = deg2rad($values[0]);
						$transform = [cos($angle), sin($angle), -sin($angle), cos($angle), 0, 0];
					}
					// About a centre: moved to the origin, turned, and moved back
					if ($count === 3) {
						$transform = Geometry::multiply(Geometry::multiply([1, 0, 0, 1, -$values[1], -$values[2]], $transform), [1, 0, 0, 1, $values[1], $values[2]]);
					}
					break;
				case 'skewX':
					$transform = $count === 1 ? [1, 0, tan(deg2rad($values[0])), 1, 0, 0] : null;
					break;
				default:
					$transform = $count === 1 ? [1, tan(deg2rad($values[0])), 0, 1, 0, 0] : null;
			}

			if ($transform === null) {
				return Geometry::IDENTITY;
			}
			$matrix = Geometry::multiply($matrix, $transform);
		}

		return $matrix;
	}

	/**
	 * A viewBox or an image fitted to a viewport, as preserveAspectRatio says
	 *
	 * @param float[] $box      [x, y, width, height] fitted
	 * @param float[] $viewport [x, y, width, height] fitted to
	 * @param string  $preserve A preserveAspectRatio, by default xMidYMid meet
	 *
	 * @return float[] The transform taking the box to the viewport
	 */
	private static function fit(array $box, array $viewport, $preserve)
	{
		$parts = preg_split('/\s+/', trim($preserve));
		$align = $parts[0] === '' ? 'xMidYMid' : $parts[0];
		$scaleX = $viewport[2] / $box[2];
		$scaleY = $viewport[3] / $box[3];

		// Scaled evenly, unless the alignment is none; with none, the box fills the viewport, and the
		// alignment moves it nowhere
		if ($align !== 'none') {
			$scaleX = $scaleY = isset($parts[1]) && $parts[1] === 'slice' ? max($scaleX, $scaleY) : min($scaleX, $scaleY);
		}

		$fractions = ['Min' => 0, 'Mid' => 0.5, 'Max' => 1];
		$alignX = preg_match('/^x(Min|Mid|Max)/', $align, $match) ? $fractions[$match[1]] : 0.5;
		$alignY = preg_match('/Y(Min|Mid|Max)$/', $align, $match) ? $fractions[$match[1]] : 0.5;

		return [
			$scaleX,
			0,
			0,
			$scaleY,
			$viewport[0] - $box[0] * $scaleX + $alignX * ($viewport[2] - $box[2] * $scaleX),
			$viewport[1] - $box[1] * $scaleY + $alignY * ($viewport[3] - $box[3] * $scaleY),
		];
	}

	/**
	 * @param float[] $matrix
	 *
	 * @return string Content setting it, or nothing for the identity
	 */
	private static function cm(array $matrix)
	{
		return $matrix == Geometry::IDENTITY ? '' : Geometry::numbers($matrix) . " cm\n";
	}

	/**
	 * @param string $value   A number
	 * @param float  $default What it is where it is not one
	 *
	 * @return float
	 */
	private static function number($value, $default)
	{
		return is_numeric(trim($value)) ? (float) trim($value) : $default;
	}

	/**
	 * @param string $value An opacity
	 *
	 * @return float It, from 0 to 1; 1 where it is not a number
	 */
	private static function opacity($value)
	{
		return max(0, min(1, self::number($value, 1)));
	}

	/**
	 * @param string $value Numbers, spaced or separated by commas
	 *
	 * @return float[]
	 */
	private static function values($value)
	{
		return array_map('floatval', preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY));
	}
}
