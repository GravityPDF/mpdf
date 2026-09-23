<?php

namespace Mpdf\Writer;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\Image\ImageProcessor;
use Mpdf\Mpdf;
use Mpdf\Pdf\Protection;
use Mpdf\TestLogger;

/**
 * An image and the soft mask its alpha is drawn from are registered together, and the image names the
 * object its own mask was written at.
 *
 * ImageWriter used to write the /SMask as the object before the image's, which was right only for as
 * long as every caller put a mask in immediately ahead of the image it masks and nothing else was
 * written in between. Nothing enforced that, and an image naming the wrong object renders wrong and
 * says nothing.
 */
class ImageMaskTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const DIR = __DIR__ . '/../../data/img/pngpixels/';

	/**
	 * @var string The font cache the colour-glyph test decodes into
	 */
	private $cacheDir;

	/**
	 * A font cache of its own for the document that decodes PNGs the way a colour font does
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->cacheDir = sys_get_temp_dir() . '/mpdf-imagemask-' . getmypid();
	}

	/**
	 * Removes the font cache, where the colour-glyph test made one
	 */
	protected function tear_down()
	{
		parent::tear_down();

		if (!is_dir($this->cacheDir)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->cacheDir);
	}

	/**
	 * The regression: an image whose mask is not the object written just before it still names the mask
	 * it was registered with, rather than whatever object the one before it turned out to be
	 */
	public function testAnImageNamesItsMaskWhereverThatWasWritten()
	{
		$mpdf = $this->document();

		$mpdf->images['mask'] = $this->image('DeviceGray', 1) + ['i' => 1];
		$mpdf->images['between'] = $this->image('DeviceRGB', 3) + ['i' => 2];
		$mpdf->images['masked'] = $this->image('DeviceRGB', 3) + ['i' => 3, 'masked' => 1];

		$pdf = $mpdf->Output('', 'S');

		$this->assertNotSame(
			$mpdf->images['masked']['n'] - 1,
			$mpdf->images['mask']['n'],
			'the mask has to be written somewhere other than just before the image for this to test anything'
		);

		$this->assertSame($mpdf->images['mask']['n'], $this->softMaskOf($pdf, $mpdf->images['masked']['n']));
	}

	/**
	 * Registering an image with a mask numbers the mask first, and gives the image its number to name
	 */
	public function testRegisteringAMaskWithAnImageNumbersTheMaskAhead()
	{
		$mpdf = $this->document();

		$info = ImageProcessor::register($mpdf, 'drawing.png', $this->image('DeviceRGB', 3), $this->image('DeviceGray', 1));

		$this->assertSame([1, 2], [$mpdf->images['drawing.png-mask']['i'], $info['i']]);
		$this->assertSame($mpdf->images['drawing.png-mask']['i'], $info['masked']);
		$this->assertSame($info, $mpdf->images['drawing.png']);
	}

	/**
	 * An image registered without a mask has neither a mask nor anything to name as one
	 */
	public function testRegisteringAnImageWithoutAMaskLeavesItUnmasked()
	{
		$mpdf = $this->document();

		$info = ImageProcessor::register($mpdf, 'drawing.png', $this->image('DeviceRGB', 3));

		$this->assertArrayNotHasKey('masked', $info);
		$this->assertSame(['drawing.png'], array_keys($mpdf->images));
	}

	/**
	 * The path that sends a PNG's alpha channel through GD registers each image with its own mask
	 */
	public function testAPngWithAlphaIsPairedWithItsOwnMask()
	{
		$mpdf = $this->document();
		$mpdf->WriteHTML('<img src="' . self::DIR . 'rgba8-None.png" /><img src="' . self::DIR . 'la8-PNG.png" />');

		$this->assertEachImageNamesItsOwnMask($mpdf, $mpdf->Output('', 'S'), 2);
	}

	/**
	 * So does the path that redraws an image into another colour space, taking its alpha out as it goes
	 */
	public function testAnImageConvertedToAnotherColourSpaceIsPairedWithItsOwnMask()
	{
		$mpdf = $this->document(['restrictColorSpace' => 1]);
		$mpdf->WriteHTML('<img src="' . self::DIR . 'rgba8-None.png" /><img src="' . self::DIR . 'rgba8-PNG.png" />');

		$this->assertEachImageNamesItsOwnMask($mpdf, $mpdf->Output('', 'S'), 2);
	}

	/**
	 * And so does the writer of a colour font, which decodes the bitmaps its glyphs draw itself rather
	 * than through GD
	 */
	public function testAColourGlyphImageIsPairedWithItsOwnMask()
	{
		$mpdf = $this->document(['tempDir' => $this->cacheDir]);
		$writer = new Type3FontWriter($mpdf, new BaseWriter($mpdf, new Protection()), new FontCache(new Cache($this->cacheDir . '/mpdf/ttfontdata', 0)), 'win', new TestLogger());

		$writer->image(file_get_contents(self::DIR . 'rgba8-None.png'));
		$writer->image(file_get_contents(self::DIR . 'p8-None.png'));

		$mpdf->WriteHTML('.');

		$this->assertEachImageNamesItsOwnMask($mpdf, $mpdf->Output('', 'S'), 2);
	}

	/**
	 * Every image that carries a mask names the object that mask was written at, and the mask is the
	 * grey image of the same size that was registered with it
	 *
	 * @param \Mpdf\Mpdf $mpdf   The document, once it has been written
	 * @param string     $pdf    What it was written as
	 * @param int        $masked How many of its images are expected to carry a mask
	 */
	private function assertEachImageNamesItsOwnMask(Mpdf $mpdf, $pdf, $masked)
	{
		$byNumber = [];
		foreach ($mpdf->images as $image) {
			$byNumber[$image['i']] = $image;
		}

		$found = 0;
		foreach ($mpdf->images as $key => $image) {
			if (!isset($image['masked'])) {
				continue;
			}

			$found++;
			$this->assertArrayHasKey($image['masked'], $byNumber, sprintf('image "%s" names a mask that was never registered', $key));

			$mask = $byNumber[$image['masked']];
			$this->assertSame($mask['n'], $this->softMaskOf($pdf, $image['n']), sprintf('image "%s" names the wrong object as its mask', $key));
			$this->assertSame('DeviceGray', $mask['cs'], sprintf('the mask of image "%s" is not grey', $key));
			$this->assertSame([$image['w'], $image['h']], [$mask['w'], $mask['h']], sprintf('the mask of image "%s" is not its size', $key));
		}

		$this->assertSame($masked, $found, 'the document did not carry the masked images this reads');
	}

	/**
	 * @param string $pdf    A document
	 * @param int    $object The object number of an image in it
	 *
	 * @return int The object its /SMask names
	 */
	private function softMaskOf($pdf, $object)
	{
		if (!preg_match('~(?:^|[\r\n])' . $object . ' 0 obj(.*?)stream~s', $pdf, $image)) {
			$this->fail(sprintf('object %d is not in the document', $object));
		}

		if (!preg_match('~/SMask (\d+) 0 R~', $image[1], $mask)) {
			$this->fail(sprintf('object %d carries no soft mask', $object));
		}

		return (int) $mask[1];
	}

	/**
	 * @param array $config What to make it with
	 *
	 * @return \Mpdf\Mpdf An uncompressed document, so that its objects can be read back
	 */
	private function document($config = [])
	{
		$mpdf = new Mpdf($config);
		$mpdf->compress = false;

		return $mpdf;
	}

	/**
	 * @param string $cs         The colour space to give it
	 * @param int    $components How many samples each of its pixels holds
	 *
	 * @return array A two-pixel image, as Mpdf::$images holds one, less its number
	 */
	private function image($cs, $components)
	{
		return [
			'w' => 2,
			'h' => 1,
			'cs' => $cs,
			'bpc' => 8,
			'type' => 'png',
			'data' => str_repeat("\x80", 2 * $components),
		];
	}

}
