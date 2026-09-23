<?php

namespace Mpdf;

/**
 * Renders a document uncompressed and hands back its page content streams, with the fixtures and assertions the
 * page-break tests share
 */
trait PageStreams
{

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
			$this->output($mpdf);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
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
