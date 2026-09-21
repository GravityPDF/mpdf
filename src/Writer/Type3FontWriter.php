<?php

namespace Mpdf\Writer;

use Mpdf\Fonts\Color\ColorFontFile;
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
 * colour is drawn in the colour of the text. A glyph is drawn by the first source that has it: the
 * colour format, then the glyph's outline, so a glyph with no colour of its own - a digit, say - is
 * its outline in the colour of the text.
 *
 * Each subset has a resource dictionary of its own, listing the images, graphics states, shadings,
 * groups and soft masks its glyphs draw with. It cannot be the page's: that lists the font itself,
 * which Acrobat refuses to load. The images are written after the fonts, so the dictionary's object
 * number is set aside as the subset is written and the dictionary is written once the images have
 * numbers - see writeResources(). A group or soft mask draws with its subset's dictionary too.
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
	 * @var array[] What the glyphs of the subset being written draw with, each once: 'images', keys in
	 *              Mpdf::$images; 'states', numbers in Mpdf::$extgstates; 'shadings', keys in $shadings;
	 *              'forms', each group by a key of its content, as ['name', 'content', 'box', 'group' =>
	 *              what its /Group dictionary adds]; 'masks', each soft mask by a key, as ['name', 'form'
	 *              => the name of the group it is drawn from, 'luminosity', 'inverted']
	 */
	private $drawn = [];

	/**
	 * @var array[][] Each resource dictionary still to be written, by the object number set aside for
	 *                it: what its subset's glyphs draw with, as $drawn holds it
	 */
	private $resources = [];

	/**
	 * @var array[] Each shading the glyphs draw, by a key of it, written once for every subset: ['name',
	 *              'shading' as GlyphResources::shading() takes it, 'n' once written]
	 */
	private $shadings = [];

	/**
	 * @var int How many shadings, groups and soft masks have been named, so each is named apart
	 */
	private $named = 0;

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
	 * Where the document may not draw colour, each glyph is drawn from its outline, and where the font
	 * has none as a procedure that draws nothing - see ColorFormats::blank() - so the text keeps its
	 * place and still copies out.
	 *
	 * @param string $k    The font's key in Mpdf::$fonts
	 * @param array  $font The font
	 */
	public function writeFont($k, array $font)
	{
		$this->fontkey = $font['fontkey'];
		$charToGlyph = $this->fontCache->jsonLoad($font['fontkey'] . '.ctg.json');
		$text = $this->ligatureText($font);
		$classes = ColorFormats::sources($font, $this->mpdf);

		if (!$classes) {
			$this->writeSubsets($k, $font, [], $charToGlyph, $text);

			return;
		}

		$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
		$reader = $ttf->openFont($font['ttffile'], $font['TTCfontID']);

		try {
			$file = new ColorFontFile($ttf, $reader, $font['unitsPerEm'], $this->logger);
			$sources = [];
			foreach ($classes as $class) {
				$sources[] = new $class($file);
			}

			$this->writeSubsets($k, $font, $sources, $charToGlyph, $text);
		} finally {
			$reader->close();
		}
	}

	/**
	 * Writes each subset of a font, and records the object number of each on the font
	 *
	 * @param string             $k            The font's key in Mpdf::$fonts
	 * @param array              $font         The font
	 * @param ColorGlyphSource[] $sources      What draws a glyph, the first that has it; none where
	 *                                         every glyph is drawn blank
	 * @param int[]              $charToGlyph  Character => glyph, Private Use codes included
	 * @param int[][]            $ligatureText Ligature character => the characters it was formed from
	 */
	private function writeSubsets($k, array $font, array $sources, array $charToGlyph, array $ligatureText)
	{
		foreach ($font['subsetfontids'] as $sfid => $fid) {
			$this->mpdf->fonts[$k]['n'][$sfid] = $this->writeSubset($font, $font['subsets'][$sfid], $sources, $charToGlyph, $ligatureText);
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

		$this->drawn['images'][$key] = $key;
		$image = $this->mpdf->images[$key];

		return ['/I' . $image['i'], $image['w'], $image['h']];
	}

	/**
	 * Registers a graphics state painting at an opacity, once however many glyphs paint at it
	 *
	 * @param float $opacity From 0, transparent, to 1
	 *
	 * @return string Content setting fills and strokes to that opacity, e.g. '/GS2 gs'
	 */
	public function alpha($opacity)
	{
		return $this->state(['BM' => '/Normal', 'ca' => $opacity, 'CA' => $opacity]);
	}

	/**
	 * Registers a graphics state blending what is painted with what is under it, once however many
	 * glyphs blend so
	 *
	 * @param string $mode A PDF blend mode, e.g. 'SoftLight'
	 *
	 * @return string Content setting it, e.g. '/GS3 gs'
	 */
	public function blend($mode)
	{
		return $this->state(['BM' => '/' . $mode]);
	}

	/**
	 * @param array $parameters The graphics state's entries, as Mpdf::AddExtGState() takes them
	 *
	 * @return string Content setting it, e.g. '/GS3 gs'
	 */
	private function state(array $parameters)
	{
		$state = $this->mpdf->AddExtGState($parameters);
		$this->drawn['states'][$state] = $state;

		return sprintf('/GS%d gs', $state);
	}

	/**
	 * @inheritdoc
	 */
	public function shading(array $shading)
	{
		$key = md5(serialize($shading));
		if (!isset($this->shadings[$key])) {
			$this->shadings[$key] = ['name' => 'Sh' . ++$this->named, 'shading' => $shading];
		}
		$this->drawn['shadings'][$key] = $key;

		return '/' . $this->shadings[$key]['name'];
	}

	/**
	 * @inheritdoc
	 */
	public function group($content, array $box, $isolated = false)
	{
		return '/' . $this->form($content, $box, $isolated ? ' /I true' : '');
	}

	/**
	 * @inheritdoc
	 */
	public function softMask($content, array $box, $luminosity = false, $inverted = false)
	{
		// A mask of brightness is drawn in grey
		$form = $this->form($content, $box, $luminosity ? ' /CS /DeviceGray' : '');
		$key = $form . ($luminosity ? '-luminosity' : '-alpha') . ($inverted ? '-inverted' : '');
		if (!isset($this->drawn['masks'][$key])) {
			$this->drawn['masks'][$key] = ['name' => 'SM' . ++$this->named, 'form' => $form, 'luminosity' => $luminosity, 'inverted' => $inverted];
		}

		return '/' . $this->drawn['masks'][$key]['name'] . ' gs';
	}

	/**
	 * Registers a transparency group on the subset being written, once however many of its glyphs draw
	 * the same
	 *
	 * @param string  $content What the group draws
	 * @param float[] $box     [xMin, yMin, xMax, yMax] it is drawn within
	 * @param string  $group   What its /Group dictionary adds to /S /Transparency
	 *
	 * @return string Its name, e.g. 'Fx4'
	 */
	private function form($content, array $box, $group)
	{
		$key = md5($content . serialize($box) . $group);
		if (!isset($this->drawn['forms'][$key])) {
			$this->drawn['forms'][$key] = ['name' => 'Fx' . ++$this->named, 'content' => $content, 'box' => $box, 'group' => $group];
		}

		return $this->drawn['forms'][$key]['name'];
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
	 * it, with the shadings, groups and soft masks it names. Called once the images are written, which
	 * is when they have object numbers to be named by, and writes the graphics states the glyphs
	 * registered after the document's were written.
	 */
	public function writeResources()
	{
		if (!$this->resources) {
			return;
		}

		$this->mpdf->_putextgstates();

		foreach ($this->shadings as $key => $shading) {
			if (!isset($shading['n'])) {
				$this->shadings[$key]['n'] = $this->writeObject($this->shadingDictionary($shading['shading']));
			}
		}

		foreach ($this->resources as $object => $drawn) {
			$objects = ['XObject' => [], 'ExtGState' => [], 'Shading' => []];
			foreach ($drawn['images'] as $key) {
				$objects['XObject']['I' . $this->mpdf->images[$key]['i']] = $this->mpdf->images[$key]['n'];
			}
			foreach ($drawn['states'] as $state) {
				$objects['ExtGState']['GS' . $state] = $this->mpdf->extgstates[$state]['n'];
			}
			foreach ($drawn['shadings'] as $key) {
				$objects['Shading'][$this->shadings[$key]['name']] = $this->shadings[$key]['n'];
			}

			// Each group draws with this dictionary, and each mask from a group
			foreach ($drawn['forms'] as $form) {
				list($xMin, $yMin, $xMax, $yMax) = $form['box'];
				$this->writeStream(sprintf('/Type /XObject /Subtype /Form /BBox [%.3F %.3F %.3F %.3F] /Group <</S /Transparency%s>> /Resources %d 0 R', $xMin, $yMin, $xMax, $yMax, $form['group'], $object), $form['content']);
				$objects['XObject'][$form['name']] = $this->mpdf->n;
			}
			foreach ($drawn['masks'] as $mask) {
				// An inverted mask lets through what its group does not cover
				$objects['ExtGState'][$mask['name']] = $this->writeObject(sprintf('<</Type /ExtGState /SMask <</Type /Mask /S /%s /G %d 0 R%s>>>>', $mask['luminosity'] ? 'Luminosity' : 'Alpha', $objects['XObject'][$mask['form']], $mask['inverted'] ? ' /TR <</FunctionType 2 /Domain [0 1] /C0 [1] /C1 [0] /N 1>>' : ''));
			}

			$this->writer->object($object);
			$dictionaries = '';
			foreach ($objects as $type => $named) {
				$dictionaries .= $this->dictionary($type, $named);
			}
			$this->writer->write('<<' . $dictionaries . '>>');
			$this->writer->write('endobj');
		}

		$this->resources = [];
	}

	/**
	 * @param array $shading As GlyphResources::shading() takes it
	 *
	 * @return string The shading's dictionary: axial from two points, or radial from two circles, its
	 *                colours stitched from one stop to the next, and extended past both ends
	 */
	private function shadingDictionary(array $shading)
	{
		$colour = function ($colour) {
			return implode(' ', array_map(function ($value) {
				return sprintf('%.3F', $value);
			}, $colour));
		};

		$stops = $shading['stops'];
		$functions = [];
		$bounds = [];
		for ($i = 0; $i < count($stops) - 1; $i++) {
			$functions[] = sprintf('<</FunctionType 2 /Domain [0 1] /C0 [%s] /C1 [%s] /N 1>>', $colour($stops[$i][1]), $colour($stops[$i + 1][1]));
			if ($i > 0) {
				$bounds[] = sprintf('%.4F', $stops[$i][0]);
			}
		}

		$function = $functions[0];
		if (count($functions) > 1) {
			$function = sprintf('<</FunctionType 3 /Domain [0 1] /Functions [%s] /Bounds [%s] /Encode [%s]>>', implode(' ', $functions), implode(' ', $bounds), trim(str_repeat('0 1 ', count($functions))));
		}

		return sprintf(
			'<</ShadingType %d /ColorSpace /Device%s /Coords [%s] /Function %s /Extend [true true]>>',
			count($shading['coords']) === 4 ? 2 : 3,
			count($stops[0][1]) === 1 ? 'Gray' : 'RGB',
			$colour($shading['coords']),
			$function
		);
	}

	/**
	 * @param string $type    A kind of resource, e.g. 'XObject'
	 * @param int[]  $objects Name => the object number it names
	 *
	 * @return string The entry of a resource dictionary naming them, or nothing where there are none
	 */
	private function dictionary($type, array $objects)
	{
		if (!$objects) {
			return '';
		}

		$entries = '';
		foreach ($objects as $name => $object) {
			$entries .= '/' . $name . ' ' . $object . ' 0 R ';
		}

		return '/' . $type . ' <<' . $entries . '>>';
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
	 * @param array              $font         The font, as Mpdf::$fonts holds it
	 * @param int[]              $subset       Byte => the character it is written for
	 * @param ColorGlyphSource[] $sources      What draws a glyph, the first that has it
	 * @param int[]              $charToGlyph  Character => glyph, Private Use codes included
	 * @param int[][]            $ligatureText Ligature character => the characters it was formed from
	 *
	 * @return int The object number of the font
	 */
	private function writeSubset(array $font, array $subset, array $sources, array $charToGlyph, array $ligatureText)
	{
		// Glyph space is font units, so widths laid out in thousandths of an em are scaled into it
		$scale = $font['unitsPerEm'] / 1000;

		$codes = [];
		$widths = [];
		$procedures = [];
		$differences = '';
		$this->drawn = ['images' => [], 'states' => [], 'shadings' => [], 'forms' => [], 'masks' => []];
		foreach ($subset as $code => $char) {
			$width = '0.000';
			if ($char && isset($charToGlyph[$char])) {
				$glyph = $charToGlyph[$char];
				$codes[] = $code;
				$width = sprintf('%.3F', $this->mpdf->_getCharWidth($font['cw'], $char, false) * $scale);
				$differences .= $code . ' /g' . $glyph . ' ';
				if (!isset($procedures[$glyph])) {
					$procedures[$glyph] = $width . " 0 d0\n" . $this->draw($glyph, $sources);
				}
			}
			$widths[] = $width;
		}

		// A subset whose glyphs draw with nothing but paths and colours - one drawn blank, say - names no
		// resource
		$resources = '<<>>';
		if (array_filter($this->drawn)) {
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

		$entries = '';
		foreach (array_keys($procedures) as $i => $glyph) {
			$entries .= '/g' . $glyph . ' ' . ($procedureObject + $i) . ' 0 R ';
		}
		$this->writeObject('<<' . $entries . '>>');

		$this->writeToUnicode($codes, $subset, $ligatureText);

		foreach ($procedures as $content) {
			$this->writeStream('', $content);
		}

		return $fontObject;
	}

	/**
	 * @param string $body The object, e.g. a dictionary
	 *
	 * @return int Its object number
	 */
	private function writeObject($body)
	{
		$this->writer->object();
		$this->writer->write($body);
		$this->writer->write('endobj');

		return $this->mpdf->n;
	}

	/**
	 * Writes a stream object, deflated where the document is compressed
	 *
	 * @param string $dictionary What its dictionary holds besides its length and filter
	 * @param string $content
	 */
	private function writeStream($dictionary, $content)
	{
		$this->writer->object();
		if ($this->mpdf->compress) {
			$content = gzcompress($content);
			$dictionary .= ' /Filter /FlateDecode';
		}
		$this->writer->write('<<' . ltrim($dictionary . ' /Length ' . strlen($content)) . '>>');
		$this->writer->stream($content);
		$this->writer->write('endobj');
	}

	/**
	 * @param int                $glyph   The glyph id
	 * @param ColorGlyphSource[] $sources What draws a glyph, the first that has it
	 *
	 * @return string The glyph as the first source that has it draws it, or nothing
	 */
	private function draw($glyph, array $sources)
	{
		foreach ($sources as $source) {
			$content = $source->draw($glyph, $this);
			if ($content !== null) {
				return $content;
			}
		}

		return '';
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
