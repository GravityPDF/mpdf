<?php

namespace Mpdf;

/**
 * Renders a document uncompressed and hands back its page content streams, with the assertions the table of contents
 * and index tests share
 */
trait PageStreams
{

	/**
	 * A document in core fonts that writes its streams uncompressed
	 */
	private function mpdf($config = [])
	{
		$mpdf = new Mpdf($config + ['mode' => 'c']);
		$mpdf->compress = false;

		return $mpdf;
	}

	/**
	 * The finished PDF of $html
	 */
	private function render($html, $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->WriteHTML($html);

		return $this->output($mpdf);
	}

	/**
	 * The finished PDF of $mpdf, leaving nothing of it behind
	 */
	private function output(Mpdf $mpdf)
	{
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

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
	 * The object numbers of the pages, in page order
	 */
	private function pageObjects($pdf)
	{
		preg_match_all('/(\d+) 0 obj\n<<\/Type \/Page\n/', $pdf, $matches);

		return $matches[1];
	}

	/**
	 * The body of PDF object $number
	 */
	private function object($pdf, $number)
	{
		preg_match('/\n' . $number . ' 0 obj\n(.*?)endobj/s', $pdf, $match);

		return $match[1];
	}

	/**
	 * The annotation objects listed by each page, as one string per page
	 */
	private function annotations($pdf)
	{
		$annotations = [];
		foreach ($this->pageObjects($pdf) as $i => $number) {
			$annotations[$i] = '';
			if (preg_match('/\/Annots \[([^\]]*)\]/', $this->object($pdf, $number), $list)) {
				preg_match_all('/(\d+) 0 R/', $list[1], $refs);
				foreach ($refs[1] as $ref) {
					$annotations[$i] .= $this->object($pdf, $ref);
				}
			}
		}

		return $annotations;
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

	/**
	 * $text is drawn $expected times in $stream
	 */
	private function assertTextCount($expected, $text, $stream, $message = '')
	{
		$this->assertSame($expected, substr_count($stream, '(' . $text . ')'), $message ?: "'$text' should appear $expected time(s)");
	}
}
