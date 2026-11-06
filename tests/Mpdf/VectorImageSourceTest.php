<?php

namespace Mpdf;

use Mpdf\Container\SimpleContainer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * ImageProcessor::getImage() names a data URI or an imageVars image by the md5 of its bytes, writes that name back to
 * $file, and does not hold those bytes. Asked for the bare md5 once an unwound pass has removed the form object, it
 * would have nothing to parse and would send the md5 to the asset fetcher. These tests hold that it is never asked
 */
class VectorImageSourceTest extends TestCase
{

	use PageStreams;

	/**
	 * Each way a document reaches getImage(), through each pass that is unwound, from each source of bytes. A template
	 * has %s for the image's source
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function routes()
	{
		$routes = [
			'img' => '<img src="%s" />',
			'img in a table' => '<table><tr><td><img src="%s" /></td></tr></table>',
			'img in a nested table' => '<table><tr><td><table><tr><td><img src="%s" /></td></tr></table></td></tr></table>',
			'img in a repeating header' => '<table><thead><tr><td><img src="%s" /></td></tr></thead><tbody><tr><td>Row</td></tr></tbody></table>',
			'block background' => '<div style="height: 20mm; background-image: url(%s);">Block</div>',
			'list marker' => '<ul style="list-style-image: url(%s);"><li>Item</li></ul>',
			'watermark' => '<watermarkimage src="%s" />',
		];
		$passes = [
			'in a kept block that moves' => str_repeat('<p>Filler</p>', 27) . '<div style="page-break-inside: avoid;">%2$s<p>Kept</p><p>Kept</p><p>Kept</p></div>',
			'in the contents, then after an index' => '<tocpagebreak toc-prehtml="%1$s" /><tocentry content="One" /><indexentry content="Term" /><p>Body</p><pagebreak /><indexinsert />%2$s',
		];
		$sources = [
			'from a data URI' => 'data:image/svg+xml;base64,' . base64_encode(self::gradientSvg()),
			'from imageVars' => 'var:logo',
		];

		$cases = [];
		foreach ($routes as $routeName => $route) {
			foreach ($passes as $passName => $pass) {
				foreach ($sources as $sourceName => $source) {
					$cases[$routeName . ' ' . $passName . ' ' . $sourceName] = [sprintf($pass, htmlspecialchars($route, ENT_QUOTES), $route), $source];
				}
			}
		}

		return $cases;
	}

	/**
	 * An mPDF that shows watermarks and has the image in imageVars
	 *
	 * @param \Mpdf\RecordingAssetFetcher $fetcher
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function document(RecordingAssetFetcher $fetcher)
	{
		$mpdf = $this->mpdf(['showWatermarkImage' => true], new SimpleContainer(['assetFetcher' => $fetcher]));
		$mpdf->imageVars['logo'] = self::gradientSvg();

		return $mpdf;
	}

	/**
	 * An image that brings its bytes is parsed from them on every pass
	 *
	 * @dataProvider routes
	 *
	 * @param string $template
	 * @param string $source
	 */
	public function testAVectorImageThatBringsItsBytesIsNeverFetched($template, $source)
	{
		$fetcher = new RecordingAssetFetcher([]);
		$mpdf = $this->document($fetcher);
		$mpdf->WriteHTML(str_replace('%s', $source, $template));
		$pdf = $this->output($mpdf);

		$this->assertGreaterThan(1, count($mpdf->pages), 'the pass was not unwound');
		$this->assertSame([], $fetcher->requested);
		$this->assertCount(1, $mpdf->formobjects);
		$this->assertSame(2, substr_count($pdf, '/ShadingType'));
	}

	/**
	 * An image in noImageFile still draws for each image missing after an unwound pass, and its md5 is not fetched
	 *
	 * @dataProvider routes
	 *
	 * @param string $template
	 * @param string $source
	 */
	public function testAVectorImageInNoImageFileIsNeverFetched($template, $source)
	{
		$fetcher = new RecordingAssetFetcher([]);
		$mpdf = $this->document($fetcher);
		$mpdf->noImageFile = $source;
		$mpdf->WriteHTML(str_replace('%s', 'missing.png', $template) . '<img src="after.png" />');
		$pdf = $this->output($mpdf);

		$this->assertGreaterThan(1, count($mpdf->pages), 'the pass was not unwound');
		$this->assertSame([], preg_grep('/[0-9a-f]{32}$/', $fetcher->requested));
		$this->assertCount(1, $mpdf->formobjects);
		$this->assertSame(2, substr_count($pdf, '/ShadingType'));
		$this->assertGreaterThan(0, substr_count($pdf, '/FO1 Do'));
	}

	/**
	 * getImage() rewrites the public noImageFile to the md5 as it adds the form object. A snapshot restores both, so
	 * the property never names a form object that an unwound pass removed
	 */
	public function testNoImageFileUnwindsWithItsFormObject()
	{
		$mpdf = $this->document(new RecordingAssetFetcher([]));
		$source = 'data:image/svg+xml;base64,' . base64_encode(self::gradientSvg());
		$mpdf->noImageFile = $source;
		$mpdf->AddPage();
		$snapshot = $mpdf->getStateSnapshot();

		$mpdf->WriteHTML('<img src="missing.png" />');
		$this->assertSame(md5(self::gradientSvg()), $mpdf->noImageFile);
		$this->assertArrayHasKey($mpdf->noImageFile, $mpdf->formobjects);

		$mpdf->restoreStateSnapshot($snapshot);
		$this->assertSame($source, $mpdf->noImageFile);
		$this->assertSame([], $mpdf->formobjects);
	}

	/**
	 * Every call to getImage(), with what it passes, so that a new one has to be looked at. This sees calls only: it
	 * cannot see an existing call's $file being stored somewhere new, which routes() covers for the routes it lists
	 */
	public function testEveryCallToGetImageIsAccountedFor()
	{
		$src = realpath(__DIR__ . '/../../src');
		$calls = [];
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			foreach (file($file->getPathname()) as $line) {
				if (preg_match('/^\s*(?:\/\/|\*)/', $line) || !preg_match('/->getImage\(([^,)]+)/', $line, $match)) {
					continue;
				}

				$calls[] = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1)) . ': ' . $match[1];
			}
		}
		sort($calls);

		$this->assertSame([
			'Image/Svg.php: $srcpath',
			'Mpdf.php: $file',
			'Mpdf.php: $srcpath',
			'Mpdf.php: $this->noImageFile',
			'Tag/Img.php: $srcpath',
			'Tag/Img.php: $this->mpdf->noImageFile',
			'Tag/Input.php: $srcpath',
			'Tag/Input.php: $this->mpdf->noImageFile',
			'Tag/Meter.php: $srcpath',
			'Tag/Meter.php: $this->mpdf->noImageFile',
		], $calls, 'A call to getImage() changed. If it keeps the md5 written back to $file and passes it in a later pass, a data URI or imageVars image breaks, as those bytes are not held. Check that, add the route to routes(), then update this list');
	}

}
