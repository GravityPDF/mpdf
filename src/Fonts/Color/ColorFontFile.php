<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\FileReader;
use Mpdf\Fonts\GlyphOutline;
use Mpdf\TTFontFile;
use Psr\Log\LoggerInterface;

/**
 * An open font, as each ColorGlyphSource drawing it reads it: its tables, its file, the log a glyph
 * that cannot be drawn is reported to, and its outlines, decoded once for every source that draws
 * with them.
 */
class ColorFontFile
{

	/**
	 * @var FileReader
	 */
	public $reader;

	/**
	 * @var LoggerInterface Told of a glyph the font has that cannot be drawn
	 */
	public $logger;

	/**
	 * @var int
	 */
	public $unitsPerEm;

	/**
	 * @var TTFontFile
	 */
	private $font;

	/**
	 * @var GlyphOutline|null
	 */
	private $outline;

	/**
	 * @param TTFontFile      $font       The font, its table directory read
	 * @param FileReader      $reader     The font file
	 * @param int             $unitsPerEm
	 * @param LoggerInterface $logger     Told of a glyph the font has that cannot be drawn
	 */
	public function __construct(TTFontFile $font, FileReader $reader, $unitsPerEm, LoggerInterface $logger)
	{
		$this->font = $font;
		$this->reader = $reader;
		$this->unitsPerEm = $unitsPerEm;
		$this->logger = $logger;
	}

	/**
	 * @param string $tag
	 *
	 * @return int[] [where the table starts, its length], each 0 where the font has none
	 */
	public function table($tag)
	{
		return $this->font->getTablePosition($tag);
	}

	/**
	 * @return GlyphOutline
	 */
	public function outline()
	{
		if ($this->outline === null) {
			$this->outline = new GlyphOutline($this->font, $this->reader, $this->logger);
		}

		return $this->outline;
	}
}
