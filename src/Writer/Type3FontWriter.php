<?php

namespace Mpdf\Writer;

use Mpdf\Fonts\Color\ColorFormats;
use Mpdf\Fonts\Color\ColorGlyphSource;
use Mpdf\Fonts\Color\GlyphResources;
use Mpdf\Fonts\FontCache;
use Mpdf\Image\ImageTypeGuesser;
use Mpdf\Image\PngPixels;
use Mpdf\Log\Context as LogContext;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

/**
 * Writes a colour font as Type3 fonts, each glyph a content stream drawing it in colour.
 *
 * A colour font is encoded as a font of the Supplementary Multilingual Plane is: in subsets of up to
 * 255 characters, each written as a font of its own, with the byte a character is written as being
 * its place in the subset. The first subset holds the ASCII range at its own codes, so byte 32 is
 * the space word spacing stretches.
 *
 * Each glyph procedure starts d0 rather than d1, which is what lets it set colours. One that sets no
 * colour is drawn in the colour of the text.
 *
 * Each subset has a resource dictionary of its own, listing the images its glyphs draw. It cannot be
 * the page's: that lists the font itself, which Acrobat refuses to load. The images are written after
 * the fonts, so the dictionary's object number is set aside as the subset is written and the
 * dictionary is written once the images have numbers - see writeResources().
 *
 * @see https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/PDF32000_2008.pdf 9.6.5
 */
class Type3FontWriter implements GlyphResources
{

	use Strict;

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var BaseWriter
	 */
	private $writer;

	/**
	 * @var FontCache
	 */
	private $fontCache;

	/**
	 * @var string
	 */
	private $fontDescriptor;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string The key of the font being written, which a glyph that cannot be drawn is logged under
	 */
	private $fontkey = '';

	/**
	 * @var true[] The images that could not be decoded, by key, so each is tried once
	 */
	private $undrawable = [];

	/**
	 * @var string[] The keys in Mpdf::$images of the images drawn by the glyphs of the subset being
	 *               written, each once
	 */
	private $drawn = [];

	/**
	 * @var string[][] Each resource dictionary still to be written, by the object number set aside for
	 *                 it: the images its subset's glyphs draw
	 */
	private $resources = [];

	/**
	 * @param Mpdf            $mpdf           The document, whose fonts, images and resources are written to
	 * @param BaseWriter      $writer         Writes the objects
	 * @param FontCache       $fontCache      Holds each font's glyph map and the images decoded from it
	 * @param string          $fontDescriptor 'win' or 'mac': which platform's metrics a font is read with
	 * @param LoggerInterface $logger         Told of a glyph that cannot be drawn
	 */
	public function __construct(Mpdf $mpdf, BaseWriter $writer, FontCache $fontCache, $fontDescriptor, LoggerInterface $logger)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->fontCache = $fontCache;
		$this->fontDescriptor = $fontDescriptor;
		$this->logger = $logger;
	}

	/**
	 * Writes each subset of one colour font, and records the object number of each on the font.
	 *
	 * Where the document may not draw colour, each glyph is written as a procedure that draws nothing -
	 * see ColorFormats::blank() - so the text keeps its place and still copies out.
	 *
	 * @param string $k    The font's key in Mpdf::$fonts
	 * @param array  $font The font
	 */
	public function writeFont($k, array $font)
	{
		$this->fontkey = $font['fontkey'];
		$charToGlyph = $this->fontCache->jsonLoad($font['fontkey'] . '.ctg.json');
		$text = $this->ligatureText($font);
		$format = ColorFormats::drawn($font, $this->mpdf);

		if ($format === '') {
			$this->writeSubsets($k, $font, null, $charToGlyph, $text);

			return;
		}

		$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
		$reader = $ttf->openFont($font['ttffile'], $font['TTCfontID']);

		try {
			$this->writeSubsets($k, $font, ColorFormats::source($format, $ttf, $reader, $font['unitsPerEm'], $this->logger), $charToGlyph, $text);
		} finally {
			$reader->close();
		}
	}

	/**
	 * Writes each subset of a font, and records the object number of each on the font
	 *
	 * @param string                $k            The font's key in Mpdf::$fonts
	 * @param array                 $font         The font
	 * @param ColorGlyphSource|null $source       What draws each glyph, or null where none is drawn
	 * @param int[]                 $charToGlyph  Character => glyph, Private Use codes included
	 * @param int[][]               $ligatureText Ligature character => the characters it was formed from
	 */
	private function writeSubsets($k, array $font, $source, array $charToGlyph, array $ligatureText)
	{
		foreach ($font['subsetfontids'] as $sfid => $fid) {
			$this->mpdf->fonts[$k]['n'][$sfid] = $this->writeSubset($font, $font['subsets'][$sfid], $source, $charToGlyph, $ligatureText);
		}
	}

	/**
	 * Registers an image a glyph draws, once however many glyphs draw it. Each is interpolated, since a
	 * glyph's bitmap is drawn larger than its strike more often than not.
	 *
	 * A JPEG is written as it stands, with DCTDecode. A PNG with transparency is two images, the second
	 * its /SMask, which ImageWriter expects to find written just before it.
	 *
	 * A palette PNG - every one in Noto Color Emoji - is written as /Indexed with its image data as the
	 * file holds it, which is half the size of the same pixels expanded to RGB and deflated again. Any
	 * other PNG is decoded to RGB. Either way what was decoded is kept in the font cache, since the same
	 * emoji are drawn document after document.
	 *
	 * An image that cannot be decoded is treated as ImageProcessor treats a broken image in the text: it
	 * is logged and the glyph draws nothing, unless showImageErrors or debug asks for the document to
	 * stop.
	 *
	 * @throws \Mpdf\MpdfException Where the image cannot be decoded, under showImageErrors or debug
	 *
	 * @param string $data A PNG or a JPEG, as the font stores it
	 *
	 * @return array|null [the name a glyph's content stream draws it by, e.g. /I3, its width in pixels,
	 *                    its height in pixels], or null where the image cannot be decoded
	 */
	public function image($data)
	{
		$key = 'colorglyph-' . md5($data);

		if (isset($this->undrawable[$key])) {
			return null;
		}

		if (!isset($this->mpdf->images[$key])) {
			try {
				$image = (new ImageTypeGuesser())->guess($data) === 'jpeg' ? $this->jpeg($data) : $this->png($data, $key);
			} catch (MpdfException $e) {
				if ($this->mpdf->showImageErrors || $this->mpdf->debug) {
					throw $e;
				}

				$this->undrawable[$key] = true;
				$this->logger->warning(sprintf('A glyph of colour font "%s" is left blank: %s', $this->fontkey, $e->getMessage()), ['context' => LogContext::IMAGES]);

				return null;
			}

			$this->mpdf->images[$key] = $image + ['i' => count($this->mpdf->images) + 1];
		}

		$this->drawn[$key] = $key;
		$image = $this->mpdf->images[$key];

		return ['/I' . $image['i'], $image['w'], $image['h']];
	}

	/**
	 * @throws \Mpdf\MpdfException Where the JPEG's size and colour space cannot be read
	 *
	 * @param string $data A JPEG
	 *
	 * @return array The image, as Mpdf::$images holds it, less its number
	 */
	private function jpeg($data)
	{
		$size = @getimagesizefromstring($data);
		$spaces = [1 => 'DeviceGray', 3 => 'DeviceRGB', 4 => 'DeviceCMYK'];

		if (!$size || !isset($size['channels'], $spaces[$size['channels']])) {
			throw new MpdfException('Error parsing JPG header');
		}

		return ['w' => $size[0], 'h' => $size[1], 'bpc' => $size['bits'], 'cs' => $spaces[$size['channels']], 'f' => 'DCTDecode', 'type' => 'jpg', 'interpolation' => true, 'data' => $data];
	}

	/**
	 * Decodes a PNG, and registers its /SMask where it has transparency
	 *
	 * @throws \Mpdf\MpdfException Where the PNG cannot be decoded
	 *
	 * @param string $data A PNG
	 * @param string $key  Its key in Mpdf::$images
	 *
	 * @return array The image, as Mpdf::$images holds it, less its number
	 */
	private function png($data, $key)
	{
		$decoded = $this->decoded($data, $key);
		$image = ['w' => $decoded['width'], 'h' => $decoded['height'], 'bpc' => 8, 'f' => 'FlateDecode', 'type' => 'png', 'interpolation' => true];

		if ($decoded['alpha'] !== '') {
			$this->mpdf->images[$key . '-mask'] = $image + ['cs' => 'DeviceGray', 'data' => $decoded['alpha'], 'i' => count($this->mpdf->images) + 1];
			$image['masked'] = true;
		}

		if ($decoded['palette'] === '') {
			return $image + ['cs' => 'DeviceRGB', 'data' => $decoded['colour']];
		}

		return [
			'cs' => 'Indexed',
			'pal' => $decoded['palette'],
			'bpc' => $decoded['depth'],
			'parms' => sprintf('/DecodeParms <</Predictor 15 /Colors 1 /BitsPerComponent %d /Columns %d>>', $decoded['depth'], $decoded['width']),
			'data' => $decoded['colour'],
		] + $image;
	}

	/**
	 * Writes the resource dictionary of each subset written here, under the object number set aside for
	 * it. Called once the images are written, which is when they have object numbers to be named by.
	 */
	public function writeResources()
	{
		foreach ($this->resources as $object => $keys) {
			$images = '';
			foreach ($keys as $key) {
				$images .= '/I' . $this->mpdf->images[$key]['i'] . ' ' . $this->mpdf->images[$key]['n'] . ' 0 R ';
			}

			$this->writer->object($object);
			$this->writer->write('<</XObject <<' . $images . '>>>>');
			$this->writer->write('endobj');
		}

		$this->resources = [];
	}

	/**
	 * @param string $data A PNG
	 * @param string $key  What to keep the decoded image under in the font cache
	 *
	 * @return array ['width', 'height', 'depth' => bits per component, 'palette' => PLTE or '' for
	 *               RGB, 'colour' => the colour image data deflated, 'alpha' => the alpha deflated or '']
	 */
	private function decoded($data, $key)
	{
		// Named apart from the cache entries of RGB alone that an earlier release wrote
		$file = $key . '.image.dat';
		$cached = $this->fontCache->loadIfPresent($file);

		if ($cached === null) {
			$png = new PngPixels($data);
			if ($png->indexed) {
				list($depth, $palette, $colour) = [$png->indexed['depth'], $png->indexed['palette'], $png->indexed['data']];
			} else {
				list($depth, $palette, $colour) = [8, '', gzcompress($png->rgb)];
			}

			$cached = pack('NNCNN', $png->width, $png->height, $depth, strlen($palette), strlen($colour))
				. $palette . $colour . ($png->alpha === null ? '' : gzcompress($png->alpha));
			$this->fontCache->binaryWrite($file, $cached);
		}

		$decoded = unpack('Nwidth/Nheight/Cdepth/Npalette/Ncolour', substr($cached, 0, 17));
		$lengths = [$decoded['palette'], $decoded['colour']];

		$decoded['palette'] = (string) substr($cached, 17, $lengths[0]);
		$decoded['colour'] = substr($cached, 17 + $lengths[0], $lengths[1]);
		$decoded['alpha'] = (string) substr($cached, 17 + $lengths[0] + $lengths[1]);

		return $decoded;
	}

	/**
	 * What each ligature the document formed is copied out as, with every tag the shaper was handed a
	 * Private Use code for given back as the tag
	 *
	 * @param array $font The font, as Mpdf::$fonts holds it
	 *
	 * @return int[][] Ligature character => characters
	 */
	private function ligatureText(array $font)
	{
		$tags = array_flip($font['tagChars']);
		$text = [];
		foreach ($font['ligatureText'] as $ligature => $chars) {
			foreach ($chars as $i => $char) {
				if (isset($tags[$char])) {
					$chars[$i] = $tags[$char];
				}
			}
			$text[$ligature] = $chars;
		}

		return $text;
	}

	/**
	 * @param array                 $font         The font, as Mpdf::$fonts holds it
	 * @param int[]                 $subset       Byte => the character it is written for
	 * @param ColorGlyphSource|null $source       What draws each glyph, or null where none is drawn
	 * @param int[]                 $charToGlyph  Character => glyph, Private Use codes included
	 * @param int[][]               $ligatureText Ligature character => the characters it was formed from
	 *
	 * @return int The object number of the font
	 */
	private function writeSubset(array $font, array $subset, $source, array $charToGlyph, array $ligatureText)
	{
		// Glyph space is font units, so widths laid out in thousandths of an em are scaled into it
		$scale = $font['unitsPerEm'] / 1000;

		$codes = [];
		$widths = [];
		$procedures = [];
		$differences = '';
		$this->drawn = [];
		foreach ($subset as $code => $char) {
			$width = '0.000';
			if ($char && isset($charToGlyph[$char])) {
				$glyph = $charToGlyph[$char];
				$codes[] = $code;
				$width = sprintf('%.3F', $this->mpdf->_getCharWidth($font['cw'], $char, false) * $scale);
				$differences .= $code . ' /g' . $glyph . ' ';
				if (!isset($procedures[$glyph])) {
					$procedures[$glyph] = $width . " 0 d0\n" . ($source ? $source->draw($glyph, $this) : '');
				}
			}
			$widths[] = $width;
		}

		// A subset whose glyphs draw no image - one drawn blank, say - names no resource
		$resources = '<<>>';
		if ($this->drawn) {
			$this->writer->object(false, true);
			$this->resources[$this->mpdf->n] = $this->drawn;
			$resources = $this->mpdf->n . ' 0 R';
		}

		$fontObject = $this->mpdf->n + 1;
		$charProcsObject = $fontObject + 1;
		$toUnicodeObject = $fontObject + 2;
		$procedureObject = $fontObject + 3;

		$bbox = preg_split('/\s+/', trim($font['desc']['FontBBox'], '[] '));

		$this->writer->object();
		$this->writer->write('<</Type /Font /Subtype /Type3');
		$this->writer->write(vsprintf('/FontBBox [%.3F %.3F %.3F %.3F]', array_map(function ($value) use ($scale) {
			return (float) $value * $scale;
		}, $bbox)));
		$this->writer->write(sprintf('/FontMatrix [%.10F 0 0 %.10F 0 0]', 1 / $font['unitsPerEm'], 1 / $font['unitsPerEm']));
		$this->writer->write('/CharProcs ' . $charProcsObject . ' 0 R');
		$this->writer->write('/Encoding <</Type /Encoding /Differences [' . $differences . ']>>');
		$this->writer->write('/FirstChar 0 /LastChar ' . (count($subset) - 1));
		$this->writer->write('/Widths [' . implode(' ', $widths) . ']');
		$this->writer->write('/Resources ' . $resources);
		$this->writer->write('/ToUnicode ' . $toUnicodeObject . ' 0 R');
		$this->writer->write('>>');
		$this->writer->write('endobj');

		$this->writer->object();
		$entries = '';
		foreach (array_keys($procedures) as $i => $glyph) {
			$entries .= '/g' . $glyph . ' ' . ($procedureObject + $i) . ' 0 R ';
		}
		$this->writer->write('<<' . $entries . '>>');
		$this->writer->write('endobj');

		$this->writeToUnicode($codes, $subset, $ligatureText);

		foreach ($procedures as $content) {
			$this->writer->object();
			if ($this->mpdf->compress) {
				$content = gzcompress($content);
				$this->writer->write('<</Filter /FlateDecode /Length ' . strlen($content) . '>>');
			} else {
				$this->writer->write('<</Length ' . strlen($content) . '>>');
			}
			$this->writer->stream($content);
			$this->writer->write('endobj');
		}

		return $fontObject;
	}

	/**
	 * What a reader copies out for each byte: the character it was written for, and for a ligature the
	 * characters the ligature was formed from, so a copied family is the ZWJ sequence it was typed as.
	 *
	 * @param int[]   $codes        The bytes that draw a glyph
	 * @param int[]   $subset       Byte => character
	 * @param int[][] $ligatureText Ligature character => the characters it stands for
	 */
	private function writeToUnicode(array $codes, array $subset, array $ligatureText)
	{
		$entries = [];
		foreach ($codes as $code) {
			$char = $subset[$code];
			$text = isset($ligatureText[$char]) ? $ligatureText[$char] : [$char];
			$utf8 = implode('', array_map('Mpdf\Utils\UtfString::code2utf', $text));
			$entries[] = sprintf('<%02X> <%s>', $code, strtoupper(bin2hex(mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8'))));
		}

		$cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
			. "/CIDSystemInfo <</Registry (Adobe) /Ordering (UCS) /Supplement 0>> def\n"
			. "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
			. "1 begincodespacerange\n<00> <FF>\nendcodespacerange\n";

		// A bfchar block holds 100 entries at most
		foreach (array_chunk($entries, 100) as $block) {
			$cmap .= count($block) . " beginbfchar\n" . implode("\n", $block) . "\nendbfchar\n";
		}

		$cmap .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";

		$this->writer->object();
		$this->writer->write('<</Length ' . strlen($cmap) . '>>');
		$this->writer->stream($cmap);
		$this->writer->write('endobj');
	}
}
