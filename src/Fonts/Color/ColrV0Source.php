<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\GlyphOutline;

/**
 * Colour glyphs as layers of outlines, in COLR version 0 with CPAL for their colours: Twemoji's
 * format, and what a version 1 font carries for renderers that know only this.
 *
 * A colour glyph is a list of layers, each another glyph's outline filled in a colour of the
 * palette, drawn bottom to top - see ColorFontFile::colour() for which colour an index is.
 *
 * Every read is checked for coming up short - see FontReader::fieldsAt() - and a glyph whose layers
 * run past the ones COLR counts draws nothing.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/colr
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cpal
 */
class ColrV0Source implements ColorGlyphSource
{

	use FillsInColour;

	/**
	 * @var \Mpdf\Fonts\FileReader
	 */
	private $reader;

	/**
	 * @var GlyphOutline
	 */
	private $outline;

	/**
	 * @var int[][] Base glyph id => [its first layer record, how many layers]
	 */
	private $baseGlyphs = [];

	/**
	 * @var int Where the layer records start
	 */
	private $layers = 0;

	/**
	 * @var int How many layer records there are
	 */
	private $layerCount = 0;

	/**
	 * @var ColorFontFile
	 */
	private $file;

	/**
	 * @param ColorFontFile $file The font
	 */
	public function __construct(ColorFontFile $file)
	{
		$this->reader = $file->reader;
		$this->outline = $file->outline();
		$this->file = $file;

		// numBaseGlyphRecords, baseGlyphRecordsOffset, layerRecordsOffset, numLayerRecords
		$colr = $file->table('COLR')[0];
		$header = $this->reader->fieldsAt($colr + 2, 12, 'nbase/Nrecords/Nlayers/ncount');
		if ($header === null) {
			return;
		}

		list($baseCount, $records, $layers, $this->layerCount) = $header;
		$this->layers = $colr + $layers;

		// Each glyph id, its first layer and how many
		$values = $this->reader->fieldsAt($colr + $records, $baseCount * 6, 'n*');
		for ($i = 0; $values !== null && $i < 3 * $baseCount; $i += 3) {
			$this->baseGlyphs[$values[$i]] = [$values[$i + 1], $values[$i + 2]];
		}
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		if (!isset($this->baseGlyphs[$glyph])) {
			return null;
		}

		list($first, $count) = $this->baseGlyphs[$glyph];
		$records = $first + $count > $this->layerCount ? null : $this->reader->fieldsAt($this->layers + $first * 4, $count * 4, 'n*');
		if ($records === null) {
			return null;
		}

		$content = '';
		foreach (array_chunk($records, 2) as $layer) {
			list($layerGlyph, $index) = $layer;
			$path = $this->outline->path($layerGlyph);
			if ($path === '') {
				continue;
			}

			$content .= $this->filled($this->file->colour($index, 1), $path . 'f', $resources);
		}

		return $content === '' ? null : $content;
	}
}
