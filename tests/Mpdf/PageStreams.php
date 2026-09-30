<?php

namespace Mpdf;

/**
 * Renders a document uncompressed and hands back its page content streams, with the fixtures and assertions the
 * page-break tests share
 */
trait PageStreams
{

	use InvoiceFixtures;

	/**
	 * An uncompressed document
	 *
	 * @param array $config
	 * @param \Mpdf\Container\ContainerInterface|null $container Services to use in place of mPDF's own
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function mpdf($config = [], $container = null)
	{
		$mpdf = new Mpdf($config + ['mode' => 'c'], $container);
		$mpdf->compress = false;

		return $mpdf;
	}

	/**
	 * A PDF/A document of the given version, in a mode that embeds its fonts as PDF/A needs
	 *
	 * @param string $version
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfA($version)
	{
		return $this->mpdf(['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
	}

	/**
	 * The EN 16931 Cross Industry Invoice fixture, naming the given guideline in place of its own
	 *
	 * @param string|null $guideline
	 *
	 * @return string
	 */
	private function invoice($guideline = null)
	{
		$xml = $this->invoiceXml();

		return $guideline === null ? $xml : str_replace('<ram:ID>urn:cen.eu:en16931:2017</ram:ID>', '<ram:ID>' . $guideline . '</ram:ID>', $xml);
	}

	/**
	 * A PDF/A-3 document, the kind that can carry a Factur-X invoice
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfA3()
	{
		return $this->pdfA('3-B');
	}

	private function render($html, $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->WriteHTML($html);

		return $this->output($mpdf);
	}

	private function output(Mpdf $mpdf)
	{
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * Draw into a document with kerning on - the combination the unguarded reads of the OTL state
	 * are behind - and assert it said nothing. PHPUnit turns a warning into a failure, but a
	 * handler names the line that raised it and catches the notice PHP 5 raises for the same miss.
	 *
	 * @param callable $draw
	 * @param array $config
	 *
	 * @return string The document
	 */
	private function assertDrawsSilently($draw, $config = [])
	{
		$raised = [];
		set_error_handler(static function ($errno, $message, $file, $line) use (&$raised) {
			$raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			return true;
		});

		try {
			$mpdf = $this->mpdf($config + ['useKerning' => true]);
			call_user_func($draw, $mpdf);
			$pdf = $this->output($mpdf);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);

		return $pdf;
	}

	/**
	 * The content stream of each page, in order
	 */
	private function pages($pdf)
	{
		preg_match_all('/\d+ 0 obj\s*<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $pdf, $matches);

		return $matches[1];
	}

	/**
	 * A page takes about twenty-nine of these, so twenty-two leave room for a few more but not for a block
	 */
	private function filler($paragraphs)
	{
		return str_repeat('<p>Filler</p>', $paragraphs);
	}

	/**
	 * A 5x5 PNG, for an image whose size comes from its style
	 */
	/**
	 * A kept block of $lines lines, with $inner ahead of them
	 */
	private function keptBlock($lines, $inner = '', $style = '')
	{
		return '<div style="page-break-inside: avoid; ' . $style . '">' . $inner . str_repeat('<p>Kept</p>', $lines) . '</div>';
	}

	/**
	 * An opaque JPEG, which a cell or block tiles as a pattern; the trait's PNG has an alpha channel and is not
	 */
	private function backgroundImage()
	{
		return __DIR__ . '/../data/img/bg.jpg';
	}

	/**
	 * The object numbers of the pages, in page order
	 */
	private function pageObjects($pdf)
	{
		preg_match_all('/(\d+) 0 obj\n<<\/Type \/Page\n/', $pdf, $matches);

		return $matches[1];
	}

	private function object($pdf, $number)
	{
		preg_match('/\n' . $number . ' 0 obj\n(.*?)endobj/s', $pdf, $match);

		return $match[1];
	}

	/**
	 * The content stream of each page, in order. Unlike pages(), no other stream is included, such as an embedded
	 * font's ToUnicode map.
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function pageContents($pdf)
	{
		$contents = [];
		foreach ($this->pageObjects($pdf) as $number) {
			preg_match('#/Contents (\d+) 0 R#', $this->object($pdf, $number), $ref);
			preg_match('/stream\n(.*?)\nendstream/s', $this->object($pdf, $ref[1]), $stream);
			$contents[] = $stream[1];
		}

		return $contents;
	}

	/**
	 * The object numbers each page lists in its /Annots array, as one array of numbers per page
	 *
	 * @param string $pdf
	 *
	 * @return string[][]
	 */
	private function annotationRefs($pdf)
	{
		$refs = [];
		foreach ($this->pageObjects($pdf) as $i => $number) {
			$refs[$i] = [];
			if (preg_match('/\/Annots \[([^\]]*)\]/', $this->object($pdf, $number), $list)) {
				preg_match_all('/(\d+) 0 R/', $list[1], $listed);
				$refs[$i] = $listed[1];
			}
		}

		return $refs;
	}

	/**
	 * The objects an array of references lists, the first in $pdf under the key given
	 *
	 * @param string $key 'Fields' or 'Kids'
	 * @param string $pdf a document or one of its objects
	 *
	 * @return string[]
	 */
	private function refs($key, $pdf)
	{
		$this->assertSame(1, preg_match('/\/' . $key . ' \[([^\]]*)\]/', $pdf, $list));
		preg_match_all('/(\d+) 0 R/', $list[1], $refs);

		return $refs[1];
	}

	/**
	 * The annotation objects listed by each page, as one string per page
	 */
	private function annotations($pdf)
	{
		$annotations = [];
		foreach ($this->annotationRefs($pdf) as $i => $refs) {
			$annotations[$i] = '';
			foreach ($refs as $ref) {
				$annotations[$i] .= $this->object($pdf, $ref);
			}
		}

		return $annotations;
	}

	private function pngImage()
	{
		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==';
	}

	private function images($stream)
	{
		return preg_match_all('/\/I\d+ Do/', $stream);
	}

	/**
	 * The clip path cut before the first picture is placed, and the placement itself, keyed w/h/x/y in points.
	 */
	private function clipAndPlacement($stream)
	{
		preg_match('/([-\d. ]+ m (?:[-\d. ]+ [lc] )+)W n ([-\d. ]+) cm \/I1 Do Q/', $stream, $matches);
		$cm = explode(' ', $matches[2]);

		return [$matches[1], ['w' => $cm[0], 'h' => $cm[3], 'x' => $cm[4], 'y' => $cm[5]]];
	}

	/**
	 * Where each image on a page is placed, keyed w/h/x in millimetres
	 *
	 * @param string $html
	 * @param int $page Counted from 0
	 * @param array $config
	 *
	 * @return array[]
	 */
	private function imagePlacements($html, $page = 0, $config = [])
	{
		$pages = $this->pages($this->render($html, $config));
		$this->assertArrayHasKey($page, $pages);
		preg_match_all('/([-\d.]+) 0 0 ([-\d.]+) ([-\d.]+) [-\d.]+ cm \/I\d+ Do/', $pages[$page], $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			return ['w' => $match[1] / Mpdf::SCALE, 'h' => $match[2] / Mpdf::SCALE, 'x' => $match[3] / Mpdf::SCALE];
		}, $matches);
	}

	/**
	 * The width of the only image on the first page, in millimetres
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return float
	 */
	private function drawnWidth($html, $config = [])
	{
		$placements = $this->imagePlacements($html, 0, $config);
		$this->assertCount(1, $placements);

		return $placements[0]['w'];
	}

	/**
	 * The fill colour each piece of text on the first page is drawn in, keyed by that text
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string[]
	 */
	private function textColours($html, $config = [])
	{
		$pages = $this->pages($this->render($html, $config));
		preg_match_all('/q ([\d. ]+ (?:rg|g)) .*?\((.*?)\) Tj/', $pages[0], $drawn, PREG_SET_ORDER);

		$colours = [];
		foreach ($drawn as $text) {
			$colours[$text[2]] = $text[1];
		}

		return $colours;
	}

	/**
	 * $needle appears $count times in the string for page $page and not at all in the others
	 */
	private function assertOnlyOnPage($page, $count, $needle, array $strings, $what)
	{
		foreach ($strings as $i => $string) {
			$expected = $i === $page ? $count : 0;
			$this->assertSame($expected, substr_count($string, $needle), 'Page ' . ($i + 1) . " should carry $what $expected time(s)");
		}
	}

	/**
	 * The text drawn on a page, joined up: a linked index list is written a piece at a time
	 */
	private function drawnText($stream)
	{
		preg_match_all('/\((.*?)\)\s*Tj/', $stream, $chunks);

		return implode('', $chunks[1]);
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return string The content streams of the document's pages, one after another
	 */
	private function joinedPageContents(Mpdf $mpdf)
	{
		return implode("\n", $this->pageContents($this->output($mpdf)));
	}

	/**
	 * The bytes each Tj and TJ in $stream shows, unescaped, one string per operator: a TJ's pieces are joined and its
	 * adjustments left out
	 *
	 * @param string $stream
	 *
	 * @return string[]
	 */
	private function shownBytes($stream)
	{
		$literal = '\(((?:\\\\.|[^\\\\)])*)\)';
		preg_match_all('/' . $literal . '\s*Tj|\[((?:\\\\.|[^\\\\\]])*)\]\s*TJ/s', $stream, $operators, PREG_SET_ORDER);

		$shown = [];
		foreach ($operators as $operator) {
			$pieces = [$operator[1]];
			if (isset($operator[2]) && $operator[2] !== '') {
				preg_match_all('/' . $literal . '/s', $operator[2], $matches);
				$pieces = $matches[1];
			}
			$shown[] = implode('', array_map('setasign\Fpdi\PdfParser\Type\PdfString::unescape', $pieces));
		}

		return $shown;
	}

	/**
	 * The index in $stream lists $term once, against $pages: a page number, or a list such as "1-3, 5"
	 */
	private function assertIndexLists($pages, $term, $stream)
	{
		$this->assertSame(1, preg_match_all('/' . preg_quote($term, '/') . '\s+([\d, -]+)/', $this->drawnText($stream), $listed), "The index should list '$term' once");
		$this->assertSame((string) $pages, trim($listed[1][0]), "The index should list '$term' against $pages");
	}

	private function assertTextCount($expected, $text, $stream, $message = '')
	{
		$this->assertSame($expected, substr_count($stream, '(' . $text . ')'), $message ?: "'$text' should appear $expected time(s)");
	}
}
