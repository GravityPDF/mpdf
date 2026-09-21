<?php

namespace Mpdf\Fonts\Color;

use DOMDocument;

/**
 * Colour glyphs as SVG documents, from the OpenType SVG table.
 *
 * Each document record covers a range of glyphs, and several may share a document. A document is read
 * when a glyph in it is first drawn, gunzipped where it starts as gzip does, and kept, up to the last
 * MAX_DOCUMENTS read, since the glyphs are drawn in the order the text uses them and one document may
 * hold many. SvgRenderer draws the glyph's element.
 *
 * A glyph whose document cannot be read, or has no element for it, is left to the next source, as is
 * one whose element draws nothing. So is every glyph where PHP has no DOM extension, or no zlib for a
 * gzipped document, each logged once.
 *
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/svg
 */
class SvgSource implements ColorGlyphSource
{

	/**
	 * How many documents are kept read at once
	 */
	const MAX_DOCUMENTS = 8;

	/**
	 * @var ColorFontFile
	 */
	private $file;

	/**
	 * @var int[][] Each document record as [first glyph, last glyph, where its document is, its length],
	 *              in glyph order
	 */
	private $records = [];

	/**
	 * @var SvgRenderer[]|null[] The documents read, by where each is, the most recently used last; null
	 *                           for one that cannot be read
	 */
	private $documents = [];

	/**
	 * @param ColorFontFile $file The font
	 */
	public function __construct(ColorFontFile $file)
	{
		$this->file = $file;

		list($svg, $length) = $file->table('SVG ');
		$end = $svg + $length;

		// version and svgDocumentListOffset; then numEntries and the records
		$header = $length >= 10 ? $file->reader->fieldsAt($svg, 6, 'nversion/Nlist') : null;
		if ($header === null) {
			return;
		}

		$list = $svg + $header[1];
		$count = $list + 2 <= $end ? $file->reader->fieldsAt($list, 2, 'n') : null;
		$data = $count !== null && $list + 2 + 12 * $count[0] <= $end ? $file->reader->fieldsAt($list + 2, 12 * $count[0], 'a*') : null;
		if ($data === null) {
			return;
		}

		for ($i = 0; $i < $count[0]; $i++) {
			$record = unpack('nfirst/nlast/Noffset/Nlength', substr($data[0], 12 * $i, 12));
			if ($record['offset'] > 0 && $list + $record['offset'] + $record['length'] <= $end) {
				$this->records[] = [$record['first'], $record['last'], $list + $record['offset'], $record['length']];
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function draw($glyph, GlyphResources $resources)
	{
		$record = $this->record($glyph);
		$renderer = $record === null ? null : $this->document($record[2], $record[3]);

		return $renderer === null ? null : $renderer->draw($glyph, $resources);
	}

	/**
	 * @param int $glyph
	 *
	 * @return int[]|null The record covering the glyph, found by halves since the spec keeps the records
	 *                    in order and apart
	 */
	private function record($glyph)
	{
		$low = 0;
		$high = count($this->records) - 1;
		while ($low <= $high) {
			$middle = (int) (($low + $high) / 2);
			$record = $this->records[$middle];
			if ($glyph < $record[0]) {
				$high = $middle - 1;
			} elseif ($glyph > $record[1]) {
				$low = $middle + 1;
			} else {
				return $record;
			}
		}

		return null;
	}

	/**
	 * @param int $offset Where the document is
	 * @param int $length How long it is, gzipped or not
	 *
	 * @return SvgRenderer|null What draws the document's glyphs, or null where it cannot be read
	 */
	private function document($offset, $length)
	{
		if (array_key_exists($offset, $this->documents)) {
			$renderer = $this->documents[$offset];
			unset($this->documents[$offset]);

			return $this->documents[$offset] = $renderer;
		}

		if (count($this->documents) >= self::MAX_DOCUMENTS) {
			reset($this->documents);
			unset($this->documents[key($this->documents)]);
		}

		return $this->documents[$offset] = $this->read($offset, $length);
	}

	/**
	 * @param int $offset Where the document is
	 * @param int $length How long it is, gzipped or not
	 *
	 * @return SvgRenderer|null What draws the document's glyphs, or null where it cannot be read
	 */
	private function read($offset, $length)
	{
		$data = $this->file->reader->fieldsAt($offset, $length, 'a*');
		$data = $data === null ? '' : $data[0];

		if (substr($data, 0, 2) === "\x1F\x8B") {
			if (!function_exists('gzdecode')) {
				$this->file->log('SVG colour glyphs are drawn from their outlines: a gzipped SVG document needs the zlib extension');

				return null;
			}
			$data = @gzdecode($data);
		}

		if (!class_exists('DOMDocument')) {
			$this->file->log('SVG colour glyphs are drawn from their outlines: they need the DOM extension');

			return null;
		}

		$dom = new DOMDocument();
		$errors = libxml_use_internal_errors(true);
		$read = is_string($data) && $data !== '' && $dom->loadXML($data, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		if (!$read || $dom->documentElement === null) {
			$this->file->log(sprintf('An SVG document at %d cannot be read, and its glyphs are drawn from their outlines', $offset));

			return null;
		}

		return new SvgRenderer($dom, $this->file);
	}
}
