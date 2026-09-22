<?php

namespace Mpdf\Ua;

/**
 * Image maps: each area of an img usemap becomes a link annotation over its part of the image,
 * in a Link struct element named by the area's alt.
 *
 * @group pdfua
 */
class ImageMapTest extends PdfUaTestCase
{

	/**
	 * A 1x1 PNG as a data: URI
	 *
	 * @var string
	 */
	private $redPixelPng = 'data:image/png;base64,'
		. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8'
		. 'z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

	/**
	 * @param string $alt
	 * @param string $usemap
	 * @param string $extra  More attributes for the img
	 * @return string A 200x200 img using the map
	 */
	private function imgUseMap($alt = 'Floor plan', $usemap = '#rooms', $extra = '')
	{
		return '<img src="' . $this->redPixelPng . '" alt="' . $alt . '" usemap="' . $usemap
			. '" width="200" height="200"' . ($extra ? ' ' . $extra : '') . '>';
	}

	/**
	 * A map and its areas are registered even when no image uses them, as an image may come
	 * before or after its map.
	 */
	public function testMapAndAreaPopulateRegistry()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML(
			'<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="/lobby" alt="Lobby">'
			. '<area shape="circle" coords="200,200,40" href="/atrium" alt="Atrium">'
			. '</map>'
		);
		$registry = $mpdf->getPdfUaImageMapRegistry();
		$maps     = $registry->getMaps();
		$this->assertArrayHasKey('rooms', $maps);
		$this->assertCount(2, $maps['rooms']);
		$this->assertSame('rect', $maps['rooms'][0]['shape']);
		$this->assertSame('Lobby', $maps['rooms'][0]['alt']);
		$this->assertSame([10.0, 10.0, 100.0, 100.0], $maps['rooms'][0]['coords']);
		$this->assertSame('circle', $maps['rooms'][1]['shape']);
		$this->assertSame([200.0, 200.0, 40.0], $maps['rooms'][1]['coords']);
		$this->assertNull($registry->getCurrentMapName());
	}

	/**
	 * Map names are lowercased, since HTML matches them regardless of case.
	 */
	public function testMapNameIsLowercased()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML(
			'<map name="Rooms"><area shape="rect" coords="0,0,10,10" href="/x" alt="x"></map>'
		);
		$this->assertArrayHasKey('rooms', $mpdf->getPdfUaImageMapRegistry()->getMaps());
	}

	/**
	 * A rect area gives a link annotation to its href.
	 */
	public function testRectAreaProducesLinkAnnotationWithAlt()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
		$this->assertStringContainsString('/URI (https://example.com/lobby)', $pdf);
	}

	/**
	 * The area's alt is the /Alt of its Link struct element (Matterhorn 28-002).
	 */
	public function testRectAreaProducesLinkStructWithAlt()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/S /Link', $pdf);
		// "Lobby)" in UTF-16BE
		$this->assertStringContainsString('feff004c006f00620062007929', bin2hex($pdf));
	}

	/**
	 * The Link struct element points at its annotation with an OBJR.
	 */
	public function testLinkStructElementHasObjrKid()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertMatchesRegularExpression('#/Type\s*/OBJR\s*/Obj\s+\d+\s+0\s+R#', $pdf);
	}

	/**
	 * The link annotation carries the /StructParent that finds its Link element in the ParentTree.
	 */
	public function testLinkAnnotationHasStructParent()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertMatchesRegularExpression(
			'#/Subtype\s*/Link[\s\S]+?/StructParent\s+\d+#',
			$pdf
		);
	}

	/**
	 * A circle area gives a link annotation, tagged with its alt.
	 */
	public function testCircleAreaProducesLinkAnnotation()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="circle" coords="100,100,40" href="/atrium" alt="Atrium">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
		// "Atrium" in UTF-16BE
		$this->assertStringContainsString('feff00410074007200690075006d', bin2hex($pdf));
	}

	/**
	 * A poly area gives a link annotation.
	 */
	public function testPolyAreaProducesLinkAnnotation()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="poly" coords="100,50,150,150,50,150" href="/garden" alt="Garden">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
	}

	/**
	 * A default area, which has no coords, gives a link annotation over the whole image.
	 */
	public function testDefaultShapeProducesLinkAnnotation()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="default" href="/whole" alt="Whole image">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
	}

	/**
	 * An area linking to a fragment goes to a /Dest in the document, not a URI.
	 */
	public function testInternalHrefProducesGoTo()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<h1 id="section2">Section 2</h1>'
			. '<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="0,0,50,50" href="#section2" alt="Jump to section 2">'
			. '</map>'
		);
		$this->assertMatchesRegularExpression('#/Subtype\s*/Link[\s\S]+?/Dest\s*\[#', $pdf);
		$this->assertStringNotContainsString('/A <</S /URI /URI (#section2)', $pdf);
	}

	/**
	 * An area linking to a URL gets a URI action.
	 */
	public function testExternalHrefProducesUriAction()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="0,0,50,50" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/A <</S /URI /URI (https://example.com/lobby)', $pdf);
	}

	/**
	 * In strict mode an area with an href but no alt throws, citing Matterhorn 28-002.
	 */
	public function testStrictModeAreaWithoutAltThrows()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessage('28-002');
		$mpdf->WriteHTML(
			'<map name="rooms">'
			. '<area shape="rect" coords="0,0,50,50" href="https://example.com/lobby">'
			. '</map>'
		);
	}

	/**
	 * In auto mode an area with no alt is named "Link to <href>", with a warning.
	 */
	public function testAutoModeAreaWithoutAltSynthesisesAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
		// "Link to" in UTF-16BE
		$this->assertStringContainsString(
			'feff004c0069006e006b00200074006f',
			bin2hex($pdf)
		);
		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (strpos($w, 'synthesised') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected warning about synthesised alt was not recorded');
	}

	/**
	 * An area with no href links nowhere, so it gives no annotation.
	 */
	public function testAreaWithoutHrefIsSkipped()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" alt="Inert">'
			. '</map>'
		);
		$this->assertStringNotContainsString('/Subtype /Link', $pdf);
	}

	/**
	 * In auto mode an image using a map that does not exist is drawn without links, with a warning.
	 */
	public function testImgUsemapWithNoMatchingMapWarns()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap('Plan', '#missing') . '</p>'
		);
		$this->assertStringNotContainsString('/Subtype /Link', $pdf);
		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (strpos($w, 'unknown map') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected unknown-map warning was not recorded');
	}

	/**
	 * In strict mode an image using a map that does not exist throws, naming the map.
	 */
	public function testStrictModeImgUsemapUnknownMapThrows()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => false]);
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessage('unknown map "missing"');
		$this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap('Plan', '#missing') . '</p>'
		);
	}

	/**
	 * A map on a decorative image is ignored with a warning, since an image with links in it is
	 * not decorative.
	 */
	public function testImageMapOnDecorativeImageIsSkipped()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p><img src="' . $this->redPixelPng . '" alt="" usemap="#rooms" width="200" height="200"></p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringNotContainsString('/Subtype /Link', $pdf);
		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (strpos($w, 'decorative image') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected decorative-image warning was not recorded');
	}

	/**
	 * Two images using one map each get their own set of links.
	 */
	public function testMultipleImagesShareOneMap()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap('First', '#shared') . '</p>'
			. '<p>' . $this->imgUseMap('Second', '#shared') . '</p>'
			. '<map name="shared">'
			. '<area shape="rect" coords="10,10,50,50" href="https://example.com/a" alt="A">'
			. '<area shape="rect" coords="60,60,100,100" href="https://example.com/b" alt="B">'
			. '</map>'
		);
		$count = preg_match_all('#/Subtype /Link#', $pdf);
		$this->assertSame(4, $count);
	}

	/**
	 * An area outside a map is ignored with a warning.
	 */
	public function testAreaOutsideMapIsIgnored()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML(
			'<area shape="rect" coords="0,0,10,10" href="/x" alt="x">'
		);
		$this->assertSame([], $mpdf->getPdfUaImageMapRegistry()->getMaps());
		$warnings = $mpdf->getPdfUaWarnings();
		$found = false;
		foreach ($warnings as $w) {
			if (strpos($w, '<area> outside') !== false) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Expected <area> outside warning');
	}

	/**
	 * A hotspot is placed using the height of the page its image is on, not that of the last page.
	 *
	 * Links are made at the end of WriteHTML(), when $hPt is the last page's; here the image is
	 * on a portrait page and the document ends landscape.
	 */
	public function testHotspotUsesHostPageHeightAcrossOrientations()
	{
		$mpdf = $this->makeMpdf(['format' => 'A4']);
		$pdf = $this->getOutput(
			$mpdf,
			'<h1>Plan</h1>'
			. '<img src="' . $this->redPixelPng . '" usemap="#rooms" width="200" height="100" alt="Floor plan">'
			. '<map name="rooms">'
			. '<area shape="rect" coords="0,0,100,50" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
			. '<pagebreak orientation="L" />'
			. '<p>Landscape page.</p>'
		);

		$this->assertStringContainsString('/Subtype /Link', $pdf);

		$y1 = null;
		if (preg_match_all('/<<([^>]*\/Subtype \/Link[^>]*)>>/s', $pdf, $objs)) {
			foreach ($objs[1] as $obj) {
				if (preg_match('/\/Rect \[\s*[\d.\-]+\s+([\d.\-]+)/', $obj, $r)) {
					$y1 = (float) $r[1];
					break;
				}
			}
		}

		$this->assertNotNull($y1, 'expected a Link annotation with a /Rect');
		// A4 landscape is 595.28pt high; the top of a portrait page is above that
		$this->assertGreaterThan(
			595.28,
			$y1,
			'image-map hotspot /Rect yTop must be flipped with the host (portrait) page height'
		);
	}

	/**
	 * The hotspots of a rotated image are given as /QuadPoints, since a /Rect can only be upright.
	 */
	public function testRotatedImageMapEmitsQuadPoints()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap('Floor plan', '#rooms', 'rotate="90"') . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
		$this->assertStringContainsString('/QuadPoints [', $pdf);
		$this->assertStringContainsString('/S /Link', $pdf);
		$this->assertStringNotContainsString('not supported', implode(' ', $mpdf->getPdfUaWarnings()));
	}

	/**
	 * The rect area of an unrotated image needs only a /Rect, and has no /QuadPoints.
	 */
	public function testAxisAlignedImageMapHasNoQuadPoints()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="rect" coords="10,10,100,100" href="https://example.com/lobby" alt="Lobby">'
			. '</map>'
		);
		$this->assertStringContainsString('/Subtype /Link', $pdf);
		$this->assertStringNotContainsString('/QuadPoints', $pdf);
	}

	/**
	 * The /QuadPoints of a hotspot over a whole rotated image fall on the corners of the image as
	 * drawn, worked out from the cm operators in the content stream.
	 */
	public function testRotatedImageMapQuadAlignsWithRenderedImage()
	{
		$mpdf = $this->makeMpdf();
		$pdf = $this->getOutput(
			$mpdf,
			'<p><img src="' . $this->redPixelPng . '" alt="Photo" usemap="#m" width="120" height="60" rotate="90"></p>'
			. '<map name="m"><area shape="default" href="https://example.com/full" alt="Whole"></map>'
		);

		$this->assertSame(1, preg_match('/\/I(\d+) Do/', $pdf, $idm));
		$doPos = strpos($pdf, '/I' . $idm[1] . ' Do');
		$seg   = substr($pdf, strrpos(substr($pdf, 0, $doPos), 'q'), $doPos);
		preg_match_all(
			'/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+cm/',
			$seg,
			$cms,
			PREG_SET_ORDER
		);
		$ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
		foreach ($cms as $c) {
			$ctm = $this->matmul(
				[(float) $c[1], (float) $c[2], (float) $c[3], (float) $c[4], (float) $c[5], (float) $c[6]],
				$ctm
			);
		}
		// The image's pixel corners (0,0), (W,0), (W,H), (0,H) in its unit square, y flipped
		$expected = array_merge(
			$this->applyMatrix($ctm, 0.0, 1.0),
			$this->applyMatrix($ctm, 1.0, 1.0),
			$this->applyMatrix($ctm, 1.0, 0.0),
			$this->applyMatrix($ctm, 0.0, 0.0)
		);

		$this->assertSame(1, preg_match('/\/QuadPoints \[([^\]]+)\]/', $pdf, $qm));
		$quad = array_map('floatval', preg_split('/\s+/', trim($qm[1])));

		$this->assertCount(8, $quad);
		for ($i = 0; $i < 8; $i++) {
			$this->assertEqualsWithDelta($expected[$i], $quad[$i], 0.05, "quad coord $i");
		}
	}

	/**
	 * A triangular poly area is covered by /QuadPoints over the triangle only, half its bounding box.
	 */
	public function testTriangularPolyEmitsQuadPointsCoveringTriangle()
	{
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput(
			$mpdf,
			'<p>' . $this->imgUseMap() . '</p>'
			. '<map name="rooms">'
			. '<area shape="poly" coords="100,50,150,150,50,150" href="https://example.com/garden" alt="Garden">'
			. '</map>'
		);

		$this->assertStringContainsString('/Subtype /Link', $pdf);
		$this->assertSame(1, preg_match('/\/QuadPoints \[([^\]]+)\]/', $pdf, $qm));
		$quad = array_map('floatval', preg_split('/\s+/', trim($qm[1])));
		// One triangle is one quad with two corners the same
		$this->assertCount(8, $quad);

		$this->assertSame(1, preg_match('/\/Rect \[([^\]]+)\]/', $pdf, $rm));
		$rect = array_map('floatval', preg_split('/\s+/', trim($rm[1])));
		$rectArea = abs(($rect[2] - $rect[0]) * ($rect[3] - $rect[1]));
		$quadArea = $this->shoelaceArea($quad);

		$this->assertGreaterThan(0.0, $quadArea, 'the polygon hotspot must have a non-empty active region');
		$this->assertEqualsWithDelta(0.5 * $rectArea, $quadArea, 0.02 * $rectArea);
	}

	/**
	 * @param float[] $pts [x0, y0, x1, y1, ...]
	 * @return float The area of the polygon, by the shoelace formula
	 */
	private function shoelaceArea(array $pts)
	{
		$n = (int) (count($pts) / 2);
		$a = 0.0;
		for ($i = 0; $i < $n; $i++) {
			$j  = ($i + 1) % $n;
			$a += $pts[2 * $i] * $pts[2 * $j + 1] - $pts[2 * $j] * $pts[2 * $i + 1];
		}
		return abs($a) / 2.0;
	}

	/**
	 * Multiplies two PDF matrices [a b c d e f], $a applied first.
	 *
	 * @param float[] $a
	 * @param float[] $b
	 * @return float[]
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
	 * @return float[] The point transformed by the matrix
	 */
	private function applyMatrix(array $m, $x, $y)
	{
		return [$x * $m[0] + $y * $m[2] + $m[4], $x * $m[1] + $y * $m[3] + $m[5]];
	}
}
