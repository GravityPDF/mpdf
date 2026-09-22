<?php

namespace Mpdf\Ua\ImageMap;

use Mpdf\Mpdf;
use Mpdf\Ua\AnchorState;
use Mpdf\Ua\StructureTree;
use Mpdf\Ua\UaState;

/**
 * The <map> and <area> elements of a PDF/UA document, and the tagged Link annotations drawn over
 * each <img usemap> that uses one.
 *
 * A <map> may come after the image that uses it, so images are queued as they are placed and their
 * links written once WriteHTML() has read the whole document.
 */
class ImageMapRegistry
{

	/** @var Mpdf */
	private $mpdf;

	/** @var StructureTree */
	private $structureTree;

	/** @var AnchorState */
	private $anchorState;

	/**
	 * The areas of each map by lowercased name, each with its shape, coords, href, alt and target
	 *
	 * @var array<string,array<int,array<string,mixed>>>
	 */
	private $maps = [];

	/**
	 * Lowercased name of the <map> being read, or null outside one
	 *
	 * @var string|null
	 */
	private $currentMapName = null;

	/**
	 * The images waiting for their map: where each was placed, its page and page height, its size in
	 * pixels, any transform it was drawn with and its Figure element
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $deferred = [];

	/**
	 * Set after construction, as UaState is built from this class
	 *
	 * @var UaState|null
	 */
	private $uaState = null;

	/**
	 * @param Mpdf          $mpdf
	 * @param StructureTree $structureTree
	 * @param AnchorState   $anchorState   Carries each area's Link element to Mpdf::Link()
	 */
	public function __construct(Mpdf $mpdf, StructureTree $structureTree, AnchorState $anchorState)
	{
		$this->mpdf          = $mpdf;
		$this->structureTree = $structureTree;
		$this->anchorState   = $anchorState;
	}

	/**
	 * Give the registry somewhere to record warnings, once UaState has been built
	 *
	 * @param UaState $uaState
	 */
	public function setUaState(UaState $uaState)
	{
		$this->uaState = $uaState;
	}

	/**
	 * Start reading a <map>. A second map of the same name adds to the areas of the first.
	 *
	 * @param string $name Lowercased
	 */
	public function openMap($name)
	{
		if (!isset($this->maps[$name])) {
			$this->maps[$name] = [];
		}
		$this->currentMapName = $name;
	}

	/**
	 * Stop reading the current <map>
	 */
	public function closeMap()
	{
		$this->currentMapName = null;
	}

	/**
	 * @return string|null The lowercased name of the <map> being read, or null outside one
	 */
	public function getCurrentMapName()
	{
		return $this->currentMapName;
	}

	/**
	 * @return array<string,array<int,array<string,mixed>>> The areas of each map by lowercased name
	 */
	public function getMaps()
	{
		return $this->maps;
	}

	/**
	 * Add an <area> to the map being read. One outside any map is ignored here; Tag\Area warns of it.
	 *
	 * @param string      $shape  rect, circle, poly or default
	 * @param float[]     $coords
	 * @param string      $href
	 * @param string      $alt
	 * @param string|null $target
	 */
	public function addArea($shape, array $coords, $href, $alt, $target)
	{
		if ($this->currentMapName === null) {
			return;
		}
		$this->maps[$this->currentMapName][] = [
			'shape'  => $shape,
			'coords' => $coords,
			'href'   => $href,
			'alt'    => $alt,
			'target' => $target,
		];
	}

	/**
	 * Hold an <img usemap> that has been placed until its map is known
	 *
	 * @param array<string,mixed> $entry
	 */
	public function queueDeferred(array $entry)
	{
		$this->deferred[] = $entry;
	}

	/**
	 * @return bool Whether any image is waiting for its map
	 */
	public function hasDeferred()
	{
		return !empty($this->deferred);
	}

	/**
	 * Write the links of every waiting image, once the whole document has been read.
	 *
	 * Each image's page is made current again so Mpdf::Link() puts the annotation on it, and its
	 * Figure is reopened so the Link elements are its children.
	 */
	public function drain()
	{
		$savedPage = $this->mpdf->page;
		$savedHpt  = $this->mpdf->hPt;
		foreach ($this->deferred as $deferred) {
			$mapName = $deferred['mapName'];
			if (!isset($this->maps[$mapName])) {
				$this->enforce(
					'PDF/UA-1: <img usemap="#' . $mapName . '"> references unknown map "' . $mapName
					. '"; no link annotations can be emitted. Define a matching <map name="' . $mapName
					. '"> or enable PDFUAauto to skip the image map.'
				);
				continue;
			}
			$figureElem = $deferred['figure'];
			$this->mpdf->page = $deferred['page'];
			// Mpdf::Link() flips y with hPt, which by now is the last page's height; a document
			// mixing page sizes needs the height of the image's own page
			$this->mpdf->hPt = $deferred['pageHpt'];
			if ($figureElem !== null) {
				$this->structureTree->pushExisting($figureElem);
			}
			$this->emitForImage(
				$this->maps[$mapName],
				$deferred['imgX'],
				$deferred['imgY'],
				$deferred['imgW'],
				$deferred['imgH'],
				$deferred['origW'],
				$deferred['origH'],
				$this->buildHotspotMatrix($deferred)
			);
			if ($figureElem !== null) {
				$this->structureTree->close();
			}
		}
		$this->deferred = [];
		$this->mpdf->page = $savedPage;
		$this->mpdf->hPt  = $savedHpt;
	}

	/**
	 * Write a Link annotation and Link element for each area of an image's map
	 *
	 * @param array<int,array<string,mixed>> $areas
	 * @param float        $imgX   Placed image, in user units from the top left
	 * @param float        $imgY
	 * @param float        $imgW
	 * @param float        $imgH
	 * @param float        $origW  Image width in pixels
	 * @param float        $origH  Image height in pixels
	 * @param float[]|null $matrix Pixel to device space for a rotated or transformed image, null otherwise
	 */
	private function emitForImage(array $areas, $imgX, $imgY, $imgW, $imgH, $origW, $origH, $matrix = null)
	{
		if ($origW <= 0 || $origH <= 0 || $imgW <= 0 || $imgH <= 0) {
			return;
		}
		foreach ($areas as $area) {
			$rect = $this->shapeToRect($area['shape'], $area['coords'], $origW, $origH);
			if ($rect === null) {
				$this->enforce(
					'PDF/UA-1: <area shape="' . $area['shape'] . '"> coords malformed; the hotspot '
					. 'cannot be placed. Fix the coords or enable PDFUAauto to skip the area.'
				);
				continue;
			}
			list($x1, $y1, $x2, $y2) = $rect;

			// A polygon is tiled with /QuadPoints so only its interior is clickable, not its bounding box
			$poly = $this->polygonPoints($area['shape'], $area['coords']);
			if ($poly !== null) {
				$devMatrix = $matrix !== null
					? $matrix
					: $this->buildAxisAlignedMatrix($imgX, $imgY, $imgW, $imgH, $origW, $origH);
				$quads = $this->polygonQuads($poly, $devMatrix);
				if ($quads !== null) {
					$this->emitQuads($area, $quads);
					continue;
				}
				// A self-intersecting or flat polygon keeps its bounding box
			}

			if ($matrix === null) {
				$sx = $imgW / $origW;
				$sy = $imgH / $origH;
				$rx = $imgX + $x1 * $sx;
				$ry = $imgY + $y1 * $sy;
				$rw = ($x2 - $x1) * $sx;
				$rh = ($y2 - $y1) * $sy;
				if ($rw <= 0 || $rh <= 0) {
					continue;
				}
				$this->emitAreaLink($area, $rx, $ry, $rw, $rh, null);
				continue;
			}

			// On a rotated or transformed image the area's corners go through the matrix it was drawn with
			$c1 = $this->applyMatrix($matrix, $x1, $y1);
			$c2 = $this->applyMatrix($matrix, $x2, $y1);
			$c3 = $this->applyMatrix($matrix, $x2, $y2);
			$c4 = $this->applyMatrix($matrix, $x1, $y2);
			$this->emitQuads($area, [$c1[0], $c1[1], $c2[0], $c2[1], $c3[0], $c3[1], $c4[0], $c4[1]]);
		}
	}

	/**
	 * Write the link of an area drawn as /QuadPoints, with the bounding box of the quads as its /Rect
	 *
	 * @param array<string,mixed> $area
	 * @param float[]             $quads Eight device space numbers per quad
	 */
	private function emitQuads(array $area, array $quads)
	{
		$xs = [];
		$ys = [];
		$count = count($quads);
		for ($i = 0; $i < $count; $i += 2) {
			$xs[] = $quads[$i];
			$ys[] = $quads[$i + 1];
		}
		$minx = min($xs);
		$maxx = max($xs);
		$miny = min($ys);
		$maxy = max($ys);
		if ($maxx - $minx <= 0 || $maxy - $miny <= 0) {
			return;
		}
		$scale = Mpdf::SCALE;
		$this->emitAreaLink(
			$area,
			$minx / $scale,
			($this->mpdf->hPt - $maxy) / $scale,
			($maxx - $minx) / $scale,
			($maxy - $miny) / $scale,
			$quads
		);
	}

	/**
	 * Write the Link element and Link annotation of one area
	 *
	 * @param array<string,mixed> $area
	 * @param float        $rx   Annotation rectangle, in user units from the top left
	 * @param float        $ry
	 * @param float        $rw
	 * @param float        $rh
	 * @param float[]|null $quad Device space /QuadPoints, or null for a plain rectangle
	 */
	private function emitAreaLink(array $area, $rx, $ry, $rw, $rh, $quad)
	{
		// The alt text is the link's text alternative (Matterhorn 28-002), and _href lets a
		// warning about the element name where it pointed
		$this->structureTree->open('Link', ['Alt' => $area['alt']]);
		$linkElem = $this->structureTree->getCurrent();
		$linkElem->setAttribute('_href', $area['href']);
		$this->anchorState->setLinkStructElem($linkElem);

		$href = $area['href'];
		if (isset($href[0]) && $href[0] === '#') {
			$target = substr($href, 1);
			// Each pass prefixes another '#' until the key is free; the cap stops a pathological
			// $internallink from growing it without end
			$collisionGuard = 0;
			while (array_key_exists($target, $this->mpdf->internallink)) {
				$target = '#' . $target;
				if (++$collisionGuard >= 1024) {
					$this->enforce(
						'PDF/UA-1: <area href="#' . substr($area['href'], 1)
						. '"> internal-link disambiguation exceeded 1024 iterations. '
						. 'Fix the anchor collision or enable PDFUAauto to emit an external link instead.'
					);
					$target = null;
					break;
				}
			}
			if ($target === null) {
				$linkRef = $href;
			} else {
				if (!isset($this->mpdf->internallink[$target])) {
					$this->mpdf->internallink[$target] = $this->mpdf->AddLink();
				}
				$linkRef = $this->mpdf->internallink[$target];
			}
		} else {
			$linkRef = $href;
		}

		$this->mpdf->Link($rx, $ry, $rw, $rh, $linkRef, $quad);

		$this->anchorState->clearLinkStructElem();
		$this->structureTree->close();
	}

	/**
	 * The matrix taking an image's pixels (from the top left) to device space, built from the same
	 * cm operators the image was drawn with so the areas land on the pixels they name
	 *
	 * @param array<string,mixed> $entry A waiting image
	 *
	 * @return float[]|null [a, b, c, d, e, f], or null when the image was neither rotated nor transformed
	 */
	private function buildHotspotMatrix(array $entry)
	{
		$cm = isset($entry['transformCm']) ? $entry['transformCm'] : '';
		if ($cm === '') {
			return null;
		}
		$scale = Mpdf::SCALE;
		// The "cm /I Do" that places the image's unit square
		$imageCm = [
			$entry['imgW'] * $scale, 0.0,
			0.0, $entry['imgH'] * $scale,
			$entry['imgX'] * $scale,
			$entry['pageHpt'] - ($entry['imgY'] + $entry['imgH']) * $scale,
		];
		// Each cm left-multiplies the CTM, so fold them in the order they were written
		$pre = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
		foreach ($this->parseCmMatrices($cm) as $m) {
			$pre = $this->matmul($m, $pre);
		}
		$ctm = $this->matmul($imageCm, $pre);
		// Pixels run down from the top left, the unit square up from the bottom left
		$pixelToUnit = [1.0 / $entry['origW'], 0.0, 0.0, -1.0 / $entry['origH'], 0.0, 1.0];
		return $this->matmul($pixelToUnit, $ctm);
	}

	/**
	 * The matrix taking the pixels of an image that was neither rotated nor transformed to device
	 * space, for tiling a polygon the same way buildHotspotMatrix() allows on one that was
	 *
	 * @param float $imgX
	 * @param float $imgY
	 * @param float $imgW
	 * @param float $imgH
	 * @param float $origW
	 * @param float $origH
	 *
	 * @return float[] [a, b, c, d, e, f]
	 */
	private function buildAxisAlignedMatrix($imgX, $imgY, $imgW, $imgH, $origW, $origH)
	{
		$scale = Mpdf::SCALE;
		$sx = ($imgW * $scale) / $origW;
		$sy = ($imgH * $scale) / $origH;
		return [$sx, 0.0, 0.0, -$sy, $imgX * $scale, $this->mpdf->hPt - $imgY * $scale];
	}

	/**
	 * @param string $cm Content stream
	 *
	 * @return array<int,float[]> Each "a b c d e f cm" in it, in order
	 */
	private function parseCmMatrices($cm)
	{
		$num = '(-?\d+(?:\.\d+)?)';
		$re  = '/' . $num . '\s+' . $num . '\s+' . $num . '\s+' . $num . '\s+' . $num . '\s+' . $num . '\s+cm/';
		if (!preg_match_all($re, $cm, $matches, PREG_SET_ORDER)) {
			return [];
		}
		$out = [];
		foreach ($matches as $m) {
			$out[] = [(float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4], (float) $m[5], (float) $m[6]];
		}
		return $out;
	}

	/**
	 * Multiply two PDF matrices [a b c d e f], which act on row vectors
	 *
	 * @param float[] $a
	 * @param float[] $b
	 *
	 * @return float[] $a × $b
	 */
	private function matmul(array $a, array $b)
	{
		return [
			$a[0] * $b[0] + $a[1] * $b[2],
			$a[0] * $b[1] + $a[1] * $b[3],
			$a[2] * $b[0] + $a[3] * $b[2],
			$a[2] * $b[1] + $a[3] * $b[3],
			$a[4] * $b[0] + $a[5] * $b[2] + $b[4],
			$a[4] * $b[1] + $a[5] * $b[3] + $b[5],
		];
	}

	/**
	 * @param float[] $m
	 * @param float   $x
	 * @param float   $y
	 *
	 * @return float[] The point [x, y] taken through $m
	 */
	private function applyMatrix(array $m, $x, $y)
	{
		return [
			$x * $m[0] + $y * $m[2] + $m[4],
			$x * $m[1] + $y * $m[3] + $m[5],
		];
	}

	/**
	 * @param string  $shape
	 * @param float[] $coords
	 * @param float   $origW
	 * @param float   $origH
	 *
	 * @return float[]|null The bounding box [x1, y1, x2, y2] of an area in pixels, or null when its coords are malformed
	 */
	private function shapeToRect($shape, $coords, $origW, $origH)
	{
		switch ($shape) {
			case 'rect':
			case 'rectangle':
				if (count($coords) < 4) {
					return null;
				}
				return [
					min($coords[0], $coords[2]), min($coords[1], $coords[3]),
					max($coords[0], $coords[2]), max($coords[1], $coords[3]),
				];
			case 'default':
				return [0.0, 0.0, (float) $origW, (float) $origH];
			case 'circle':
			case 'circ':
				if (count($coords) < 3) {
					return null;
				}
				$cx = $coords[0];
				$cy = $coords[1];
				$r  = $coords[2];
				if ($r <= 0) {
					return null;
				}
				return [$cx - $r, $cy - $r, $cx + $r, $cy + $r];
			case 'poly':
			case 'polygon':
				if (count($coords) < 6 || count($coords) % 2 !== 0) {
					return null;
				}
				$xs = [];
				$ys = [];
				$n  = count($coords);
				for ($i = 0; $i < $n; $i += 2) {
					$xs[] = $coords[$i];
					$ys[] = $coords[$i + 1];
				}
				return [min($xs), min($ys), max($xs), max($ys)];
			default:
				return null;
		}
	}

	/**
	 * @param string  $shape
	 * @param float[] $coords
	 *
	 * @return array<int,float[]>|null The [x, y] vertices of a polygon in pixels, or null for any other shape or malformed coords
	 */
	private function polygonPoints($shape, array $coords)
	{
		if ($shape !== 'poly' && $shape !== 'polygon') {
			return null;
		}
		$n = count($coords);
		if ($n < 6 || $n % 2 !== 0) {
			return null;
		}
		$pts = [];
		for ($i = 0; $i < $n; $i += 2) {
			$pts[] = [(float) $coords[$i], (float) $coords[$i + 1]];
		}
		return $pts;
	}

	/**
	 * Tile a polygon with /QuadPoints, one quad per triangle it is cut into, the last corner repeated
	 *
	 * @param array<int,float[]> $poly   Vertices in pixels
	 * @param float[]            $matrix Pixel to device space
	 *
	 * @return float[]|null Eight device space numbers per quad, or null when the polygon cannot be cut into triangles
	 */
	private function polygonQuads(array $poly, array $matrix)
	{
		$tris = $this->triangulatePolygon($poly);
		if (empty($tris)) {
			return null;
		}
		$dev = [];
		foreach ($poly as $i => $pt) {
			$dev[$i] = $this->applyMatrix($matrix, $pt[0], $pt[1]);
		}
		$quads = [];
		foreach ($tris as $t) {
			$a = $dev[$t[0]];
			$b = $dev[$t[1]];
			$c = $dev[$t[2]];
			array_push($quads, $a[0], $a[1], $b[0], $b[1], $c[0], $c[1], $c[0], $c[1]);
		}
		return $quads;
	}

	/**
	 * Cut a polygon into triangles by ear clipping, after John W. Ratcliff
	 *
	 * @param array<int,float[]> $pts [x, y] vertices
	 *
	 * @return array<int,int[]> The vertex indexes of each triangle, or none for a self-intersecting or flat polygon
	 */
	private function triangulatePolygon(array $pts)
	{
		$n = count($pts);
		if ($n < 3) {
			return [];
		}
		// Walk the vertices counter-clockwise whichever way they were given, as the ear test expects
		$V = [];
		if ($this->polygonArea($pts) > 0.0) {
			for ($i = 0; $i < $n; $i++) {
				$V[$i] = $i;
			}
		} else {
			for ($i = 0; $i < $n; $i++) {
				$V[$i] = ($n - 1) - $i;
			}
		}
		$tris = [];
		$nv    = $n;
		$count = 2 * $nv; // a self-intersecting polygon runs out of ears and would loop forever
		$v = $nv - 1;
		while ($nv > 2) {
			if (($count--) <= 0) {
				return [];
			}
			$u = $v >= $nv ? 0 : $v;
			$v = $u + 1 >= $nv ? 0 : $u + 1;
			$w = $v + 1 >= $nv ? 0 : $v + 1;
			if ($this->polygonSnip($pts, $V[$u], $V[$v], $V[$w], $nv, $V)) {
				$tris[] = [$V[$u], $V[$v], $V[$w]];
				for ($s = $v, $t = $v + 1; $t < $nv; $s++, $t++) {
					$V[$s] = $V[$t];
				}
				$nv--;
				$count = 2 * $nv;
			}
		}
		return $tris;
	}

	/**
	 * @param array<int,float[]> $pts
	 *
	 * @return float The signed area of a polygon, positive when its vertices run counter-clockwise
	 */
	private function polygonArea(array $pts)
	{
		$n    = count($pts);
		$area = 0.0;
		for ($p = $n - 1, $q = 0; $q < $n; $p = $q++) {
			$area += $pts[$p][0] * $pts[$q][1] - $pts[$q][0] * $pts[$p][1];
		}
		return $area * 0.5;
	}

	/**
	 * Whether triangle a, b, c is an ear: it turns counter-clockwise and holds no other vertex left
	 *
	 * @param array<int,float[]> $pts
	 * @param int                $a
	 * @param int                $b
	 * @param int                $c
	 * @param int                $nv How many vertices of $V are left
	 * @param int[]              $V  Indexes of the vertices left
	 *
	 * @return bool
	 */
	private function polygonSnip(array $pts, $a, $b, $c, $nv, array $V)
	{
		$eps = 1e-9;
		$ax = $pts[$a][0];
		$ay = $pts[$a][1];
		$bx = $pts[$b][0];
		$by = $pts[$b][1];
		$cx = $pts[$c][0];
		$cy = $pts[$c][1];
		if ($eps > (($bx - $ax) * ($cy - $ay) - ($by - $ay) * ($cx - $ax))) {
			return false;
		}
		for ($p = 0; $p < $nv; $p++) {
			$idx = $V[$p];
			if ($idx === $a || $idx === $b || $idx === $c) {
				continue;
			}
			if ($this->pointInTriangle($ax, $ay, $bx, $by, $cx, $cy, $pts[$idx][0], $pts[$idx][1])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether point p lies in counter-clockwise triangle a, b, c or on its edges
	 *
	 * @return bool
	 */
	private function pointInTriangle($ax, $ay, $bx, $by, $cx, $cy, $px, $py)
	{
		$ax0 = $cx - $bx;
		$ay0 = $cy - $by;
		$bx0 = $ax - $cx;
		$by0 = $ay - $cy;
		$cx0 = $bx - $ax;
		$cy0 = $by - $ay;
		$apx = $px - $ax;
		$apy = $py - $ay;
		$bpx = $px - $bx;
		$bpy = $py - $by;
		$cpx = $px - $cx;
		$cpy = $py - $cy;
		$aCrossBp = $ax0 * $bpy - $ay0 * $bpx;
		$cCrossAp = $cx0 * $apy - $cy0 * $apx;
		$bCrossCp = $bx0 * $cpy - $by0 * $cpx;
		return $aCrossBp >= 0.0 && $bCrossCp >= 0.0 && $cCrossAp >= 0.0;
	}

	/**
	 * Throw for a map or area that cannot be written, or with PDFUAauto warn and let the caller skip it
	 *
	 * @param string $msg
	 *
	 * @throws \Mpdf\MpdfException Without PDFUAauto
	 */
	private function enforce($msg)
	{
		if (empty($this->mpdf->PDFUAauto)) {
			throw new \Mpdf\MpdfException($msg);
		}
		$this->warn($msg);
	}

	/**
	 * @param string $msg
	 */
	private function warn($msg)
	{
		if ($this->uaState !== null) {
			$this->uaState->addWarning($msg);
		}
	}
}
