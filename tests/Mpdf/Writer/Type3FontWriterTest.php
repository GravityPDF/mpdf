<?php

namespace Mpdf\Writer;

use Mpdf\Cache;
use Mpdf\Fonts\FontCache;
use Mpdf\Image\PngPixels;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Pdf\Protection;
use Mpdf\TestLogger;

/**
 * The images a colour glyph draws, as Type3FontWriter registers them on the document.
 *
 * The PNGs are the ones PngPixelsTest reads, each beside ImageMagick's RGBA of it.
 */
class Type3FontWriterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const DIR = __DIR__ . '/../../data/img/pngpixels/';

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var string
	 */
	private $cacheDir;

	/**
	 * @var Type3FontWriter
	 */
	private $writer;

	/**
	 * @var TestLogger
	 */
	private $logger;

	/**
	 * A document, and a writer whose font cache starts empty
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->logger = new TestLogger();
		$this->cacheDir = sys_get_temp_dir() . '/mpdf-type3-' . getmypid();
		$this->mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $this->cacheDir]);
		$this->writer = $this->writer();
	}

	/**
	 * Removes the font cache set_up() made
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
	 * A palette PNG is registered as /Indexed with its image data as the file holds it: rebuilt as a
	 * PNG from nothing but what was registered, it decodes to ImageMagick's colours, and its mask to
	 * ImageMagick's alpha
	 *
	 * @dataProvider palettes
	 *
	 * @param string $name  The fixture
	 * @param int    $depth Its bits per index
	 */
	public function testAPaletteImageIsWrittenIndexedAsTheFileHoldsIt($name, $depth)
	{
		$key = $this->register($name);
		$image = $this->mpdf->images[$key];

		$this->assertSame('Indexed', $image['cs']);
		$this->assertSame($depth, $image['bpc']);
		$this->assertSame(sprintf('/DecodeParms <</Predictor 15 /Colors 1 /BitsPerComponent %d /Columns 13>>', $depth), $image['parms']);
		$this->assertSame($this->mpdf->images[$key . '-mask']['i'], $image['masked'], 'the image names its own mask');

		$rgba = file_get_contents(self::DIR . $name . '.rgba');
		$rebuilt = new PngPixels($this->png(13, 7, $depth, 3, $image['data'], $image['pal']));
		$this->assertSame($this->channels($rgba, [0, 1, 2]), $rebuilt->rgb);
		$this->assertSame($this->channels($rgba, [3]), gzuncompress($this->mpdf->images[$key . '-mask']['data']));
	}

	/**
	 * @return array[] Each palette fixture, and its bits per index
	 */
	public function palettes()
	{
		return [
			'1-bit' => ['p1-None', 1],
			'2-bit' => ['p2-None', 2],
			'4-bit' => ['p4-None', 4],
			'8-bit' => ['p8-None', 8],
		];
	}

	/**
	 * Any other PNG is decoded, and its colours written as RGB beside its alpha
	 */
	public function testAnImageThatIsNotAPaletteIsWrittenAsRgb()
	{
		$key = $this->register('rgba8-None');
		$image = $this->mpdf->images[$key];
		$rgba = file_get_contents(self::DIR . 'rgba8-None.rgba');

		$this->assertSame('DeviceRGB', $image['cs']);
		$this->assertSame(8, $image['bpc']);
		$this->assertArrayNotHasKey('parms', $image);
		$this->assertSame($this->channels($rgba, [0, 1, 2]), gzuncompress($image['data']));
		$this->assertSame($this->channels($rgba, [3]), gzuncompress($this->mpdf->images[$key . '-mask']['data']));
	}

	/**
	 * A JPEG is written as it stands, in the colour space and at the depth its header gives, and handed
	 * back with its size in pixels
	 *
	 * @dataProvider jpegs
	 *
	 * @param string $file       The fixture
	 * @param string $colorSpace What its components are written as
	 * @param int[]  $size       Its width and height in pixels
	 */
	public function testAJpegIsWrittenAsItStands($file, $colorSpace, array $size)
	{
		$jpeg = file_get_contents(__DIR__ . '/../../data/img/' . $file);

		$this->assertSame(['/I1', $size[0], $size[1]], $this->writer->image($jpeg));

		$image = $this->mpdf->images['colorglyph-' . md5($jpeg)];
		$this->assertSame($colorSpace, $image['cs']);
		$this->assertSame(8, $image['bpc']);
		$this->assertSame('DCTDecode', $image['f']);
		$this->assertSame($jpeg, $image['data']);
		$this->assertTrue($image['interpolation']);
		$this->assertCount(1, $this->mpdf->images, 'and no mask');
	}

	/**
	 * @return array[] Each JPEG fixture, its colour space and its size
	 */
	public function jpegs()
	{
		return [
			'RGB' => ['exif-orientation-none.jpg', 'DeviceRGB', [40, 20]],
			'greyscale' => ['exif-orientation-6-gray.jpg', 'DeviceGray', [20, 40]],
		];
	}

	/**
	 * An opaque image has no mask, and says it has none
	 */
	public function testAnOpaqueImageHasNoMask()
	{
		$key = $this->register('rgb8-None');

		$this->assertArrayNotHasKey($key . '-mask', $this->mpdf->images);
		$this->assertArrayNotHasKey('masked', $this->mpdf->images[$key]);
	}

	/**
	 * The same bitmap drawn by two glyphs is one image, named the same by both
	 */
	public function testAnImageDrawnTwiceIsRegisteredOnce()
	{
		$png = file_get_contents(self::DIR . 'p8-None.png');

		$this->assertSame($this->writer->image($png), $this->writer->image($png));
		$this->assertCount(2, $this->mpdf->images, 'the image and its mask');
	}

	/**
	 * What a PNG was decoded to is read back from the font cache by the next document, rather than
	 * decoded again
	 */
	public function testTheNextDocumentReadsTheDecodedImageFromTheCache()
	{
		$png = file_get_contents(self::DIR . 'rgba8-None.png');
		$this->writer->image($png);

		$files = glob($this->cacheDir . '/mpdf/ttfontdata/colorglyph-*.image.dat');
		$this->assertCount(1, $files);

		// A width no decoding of this PNG would give
		$cached = file_get_contents($files[0]);
		file_put_contents($files[0], pack('N', 2) . substr($cached, 4));

		$this->mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $this->cacheDir]);
		$this->writer = $this->writer();
		$this->writer->image($png);

		$this->assertSame(2, $this->mpdf->images['colorglyph-' . md5($png)]['w']);
	}

	/**
	 * An image that cannot be decoded is logged once, however many glyphs draw it, and registers
	 * nothing: the glyphs drawing it are left blank and the document goes on
	 *
	 * @dataProvider undecodable
	 *
	 * @param string $data What the font holds as the image
	 */
	public function testAnImageThatCannotBeDecodedIsLoggedOnceAndDrawsNothing($data)
	{
		$this->assertNull($this->writer->image($data));
		$this->assertNull($this->writer->image($data));

		$this->assertSame([], $this->mpdf->images);
		$this->assertCount(1, $this->logger->records);
		$this->assertTrue($this->logger->hasWarningThatContains('is left blank'));
	}

	/**
	 * @return array[] Images that cannot be decoded
	 */
	public function undecodable()
	{
		return [
			'not a PNG' => ['not a PNG'],
			'a JPEG with no frame header' => ["\xFF\xD8\xFF\xD9"],
		];
	}

	/**
	 * showImageErrors and debug each ask for a broken image to stop the document, as they do for an
	 * image in the text
	 *
	 * @dataProvider strictSettings
	 *
	 * @param string $setting The document property that asks for it
	 */
	public function testAnImageThatCannotBeDecodedStopsTheDocumentWhereAskedTo($setting)
	{
		$this->mpdf->{$setting} = true;

		$this->expectException(MpdfException::class);

		$this->writer->image('not a PNG');
	}

	/**
	 * @return array[] Each setting that stops a document at a broken image
	 */
	public function strictSettings()
	{
		return [
			'showImageErrors' => ['showImageErrors'],
			'debug' => ['debug'],
		];
	}

	/**
	 * @return Type3FontWriter Writing to $this->mpdf, with the font cache under $this->cacheDir
	 */
	private function writer()
	{
		$fontCache = new FontCache(new Cache($this->cacheDir . '/mpdf/ttfontdata', 0));

		return new Type3FontWriter($this->mpdf, new BaseWriter($this->mpdf, new Protection()), $fontCache, 'win', $this->logger);
	}

	/**
	 * @param string $name A fixture
	 *
	 * @return string The key its image was registered under in Mpdf::$images
	 */
	private function register($name)
	{
		$png = file_get_contents(self::DIR . $name . '.png');
		$this->writer->image($png);

		return 'colorglyph-' . md5($png);
	}

	/**
	 * @param string $rgba     ImageMagick's RGBA, four bytes a pixel
	 * @param int[]  $channels Which of the four to keep
	 *
	 * @return string Those bytes of each pixel, in order
	 */
	private function channels($rgba, array $channels)
	{
		$kept = '';
		for ($i = 0, $length = strlen($rgba); $i < $length; $i += 4) {
			foreach ($channels as $channel) {
				$kept .= $rgba[$i + $channel];
			}
		}

		return $kept;
	}

	/**
	 * @param int    $width     In pixels
	 * @param int    $height    In pixels
	 * @param int    $depth     Bits per sample
	 * @param int    $colorType PNG's colour type
	 * @param string $data      The IDAT stream
	 * @param string $palette   PLTE, or ''
	 *
	 * @return string A PNG of them, with no transparency
	 */
	private function png($width, $height, $depth, $colorType, $data, $palette)
	{
		$chunk = function ($type, $payload) {
			return pack('N', strlen($payload)) . $type . $payload . pack('N', crc32($type . $payload));
		};

		return "\x89PNG\r\n\x1a\n"
			. $chunk('IHDR', pack('NNCCCCC', $width, $height, $depth, $colorType, 0, 0, 0))
			. ($palette === '' ? '' : $chunk('PLTE', $palette))
			. $chunk('IDAT', $data)
			. $chunk('IEND', '');
	}

}
