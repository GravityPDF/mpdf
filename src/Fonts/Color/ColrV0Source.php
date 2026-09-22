<?php

namespace Mpdf\Fonts\Color;

use Mpdf\Fonts\GlyphOutline;

/**
 * Colour glyphs as layers of outlines, in COLR version 0 with CPAL for their colours: Twemoji's
 * format, and what a version 1 font carries for renderers that know only this.
 *
 * A colour glyph is a list of layers, each another glyph's outline filled in one colour of a
 * palette, drawn bottom to top. A layer whose palette index is 0xFFFF is filled in the colour of the
 * text, which is what a layer that sets no colour inside a Type3 glyph is drawn in, as is a layer
 * naming a colour the palette does not have. The first palette is drawn.
 *
 * Every read is checked for coming up short - see FontReader::fieldsAt() - and a glyph whose layers
 * run past the ones COLR counts draws nothing.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/colr
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/cpal
 */
class ColrV0Source implements ColorGlyphSource
{

	const FOREGROUND = 0xFFFF;

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
	 * @var int[][] The first palette, each colour as [red, green, blue, alpha] from 0 to 255
	 */
	private $palette = [];

	/**
	 * @param ColorFontFile $file The font
	 */
	public function __construct(ColorFontFile $file)
	{
		$this->reader = $file->reader;
		$this->outline = $file->outline();

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

		$this->palette = $this->firstPalette($file->table('CPAL')[0]);
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

			if ($index === self::FOREGROUND || !isset($this->palette[$index])) {
				$content .= $path . "f\n";
				continue;
			}

			list($red, $green, $blue, $alpha) = $this->palette[$index];
			$content .= sprintf("q %s%.3F %.3F %.3F rg\n%sf\nQ\n", $alpha < 255 ? $resources->alpha($alpha / 255) . ' ' : '', $red / 255, $green / 255, $blue / 255, $path);
		}

		return $content === '' ? null : $content;
	}

	/**
	 * @param int $cpal Where CPAL starts
	 *
	 * @return int[][] The first palette's colours, or none where CPAL has no palette or its first runs
	 *                 past the colour records
	 */
	private function firstPalette($cpal)
	{
		// numPaletteEntries, numPalettes, numColorRecords, colorRecordsArrayOffset, colorRecordIndices[0]
		$header = $this->reader->fieldsAt($cpal + 2, 12, 'nentries/npalettes/nrecords/Ncolors/nfirst');
		if ($header === null || $header[1] === 0 || $header[4] + $header[0] > $header[2]) {
			return [];
		}

		list($entries, , , $colors, $first) = $header;

		// Each colour is stored blue, green, red, alpha
		$bgra = $this->reader->fieldsAt($cpal + $colors + $first * 4, $entries * 4, 'C*');
		$palette = [];
		for ($i = 0; $bgra !== null && $i < 4 * $entries; $i += 4) {
			$palette[] = [$bgra[$i + 2], $bgra[$i + 1], $bgra[$i], $bgra[$i + 3]];
		}

		return $palette;
	}
}
