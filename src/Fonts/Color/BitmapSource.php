<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FileReader;
use Psr\Log\LoggerInterface;

/**
 * What the two bitmap formats share: the largest strike is drawn, scaled from its pixels into font
 * units, and each glyph is an image placed by its bottom left corner.
 *
 * Every read is checked for coming up short - see FontReader::fieldsAt() - so an offset past the end
 * of the file draws nothing rather than decoding the empty string.
 */
abstract class BitmapSource implements ColorGlyphSource
{

	/**
	 * @var FileReader
	 */
	protected $reader;

	/**
	 * @var LoggerInterface Told of a glyph the format has a bitmap for that cannot be drawn
	 */
	protected $logger;

	/**
	 * @var float Font units per pixel of the strike drawn
	 */
	protected $scale;

	/**
	 * @param FileReader      $reader The font file
	 * @param LoggerInterface $logger Told of a glyph the format has a bitmap for that cannot be drawn
	 */
	public function __construct(FileReader $reader, LoggerInterface $logger)
	{
		$this->reader = $reader;
		$this->logger = $logger;
	}

	/**
	 * @param string $name   The image, as GlyphResources::image() names it
	 * @param int    $width  In pixels
	 * @param int    $height In pixels
	 * @param int    $x      Where the image's left edge is from the glyph origin, in pixels
	 * @param int    $y      Where its bottom edge is, in pixels
	 * @param bool   $mirror Whether it is drawn mirrored left to right, over the same box
	 *
	 * @return string The content drawing it
	 */
	protected function place($name, $width, $height, $x, $y, $mirror = false)
	{
		return sprintf(
			'q %.3F 0 0 %.3F %.3F %.3F cm %s Do Q',
			($mirror ? -$width : $width) * $this->scale,
			$height * $this->scale,
			($mirror ? $x + $width : $x) * $this->scale,
			$y * $this->scale,
			$name
		);
	}
}
