<?php

namespace Mpdf;

/**
 * An annotation in an HTML header or footer is put on every page the header or footer is drawn on, and moves
 * and turns with it the way its links do (GravityPDF/mpdf#410).
 */
class HtmlHeaderAnnotationTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Three pages of body text
	 */
	const BODY = '<p>One</p><pagebreak /><p>Two</p><pagebreak /><p>Three</p>';

	/**
	 * A header or footer line with a link, which was already put on each page, and a note after it
	 */
	const LINE = '<a href="https://example.com">Link</a> <annotation content="Note" />';

	/**
	 * A header or a footer, set by its method or by the elements that name one and set it, with where the note
	 * in it is anchored on an A4 portrait page, in mm down from the top edge
	 *
	 * @return array[]
	 */
	public function places()
	{
		return [
			'SetHTMLHeader()' => ['Header', false, 0, 16],
			'htmlpageheader and sethtmlpageheader' => ['Header', true, 0, 16],
			'SetHTMLFooter()' => ['Footer', false, 281, 297],
			'htmlpagefooter and sethtmlpagefooter' => ['Footer', true, 281, 297],
		];
	}

	/**
	 * A note in a header or footer is on every page, in the header or footer, on the line of the link beside
	 * it. A footer is drawn lower than it is written, and its note has to move down with it
	 *
	 * @dataProvider places
	 *
	 * @param string $kind Header or Footer
	 * @param bool $elements Whether it is set by elements rather than by its method
	 * @param float $top
	 * @param float $bottom
	 */
	public function testAnAnnotationIsOnEveryPageBesideTheLink($kind, $elements, $top, $bottom)
	{
		$mpdf = $this->mpdf();
		if ($elements) {
			$name = 'htmlpage' . strtolower($kind);
			$mpdf->WriteHTML('<' . $name . ' name="notes">' . self::LINE . '</' . $name . '><set' . $name . ' name="notes" value="on" show-this-page="1" />' . self::BODY);
		} else {
			$mpdf->{'SetHTML' . $kind}(self::LINE);
			$mpdf->WriteHTML(self::BODY);
		}
		$annotations = $this->annotations($this->output($mpdf));
		$notes = $this->rects($annotations, 'Text');
		$links = $this->rects($annotations, 'Link');

		$this->assertCount(3, $notes);
		foreach ($notes as $i => $page) {
			$where = 'the ' . strtolower($kind) . ' of page ' . ($i + 1);
			$this->assertCount(1, $page, "The note should be in $where once");

			$x = $page[0][0] / Mpdf::SCALE;
			$y = 297 - $page[0][3] / Mpdf::SCALE;
			$this->assertGreaterThanOrEqual(15, $x, "The note should be in $where");
			$this->assertLessThanOrEqual(210 - 15, $x, "The note should be in $where");
			$this->assertGreaterThanOrEqual($top, $y, "The note should be in $where");
			$this->assertLessThan($bottom, $y, "The note should be in $where");

			// The second number of a link's /Rect is its top
			$this->assertEqualsWithDelta($links[$i][0][1], $page[0][3], 2, "The note should be on the line of the link in $where");
		}
	}

	/**
	 * Each page's note is an object of its own that names that page as its parent
	 */
	public function testEachPageHasItsOwnCopyOfTheAnnotation()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetHTMLHeader(self::LINE);
		$mpdf->WriteHTML(self::BODY);
		$pdf = $this->output($mpdf);

		$this->assertSame(3, substr_count($pdf, '/Subtype /Text'), 'The document should carry one note a page');
		$annotations = $this->annotations($pdf);
		foreach ($this->pageObjects($pdf) as $i => $page) {
			$this->assertStringContainsString('/P ' . $page . ' 0 R', $annotations[$i], 'The note on page ' . ($i + 1) . ' should belong to it');
		}
	}

	/**
	 * With mirrorMargins the odd pages take the odd header and the even pages the even one, each with its note
	 */
	public function testOddAndEvenHeadersEachCarryTheirOwnAnnotation()
	{
		$mpdf = $this->mpdf(['mirrorMargins' => true]);
		$mpdf->SetHTMLHeader('Odd <annotation content="Odd note" icon="Comment" />', 'O');
		$mpdf->SetHTMLHeader('Even <annotation content="Even note" icon="Key" />', 'E');
		$mpdf->WriteHTML(self::BODY);
		$annotations = $this->annotations($this->output($mpdf));

		foreach (['/Name /Comment', '/Name /Key', '/Name /Comment'] as $i => $name) {
			$this->assertSame(1, substr_count($annotations[$i], '/Subtype /Text'), 'Page ' . ($i + 1) . ' should carry one note');
			$this->assertStringContainsString($name, $annotations[$i], 'Page ' . ($i + 1) . ' should carry the note of its own header');
		}
	}

	/**
	 * A header and a footer, each with where forcePortraitHeaders turns it on an A4 landscape page: the strip
	 * down the right edge that the top margin leaves, or the one down the left edge that the bottom margin
	 * leaves, from and to in mm across the page
	 *
	 * @return array[]
	 */
	public function rotated()
	{
		return [
			'header' => ['SetHTMLHeader', 297 - 16, 297],
			'footer' => ['SetHTMLFooter', 0, 16],
		];
	}

	/**
	 * forcePortraitHeaders turns the header or footer of a landscape page a quarter turn, so its line runs down
	 * the page from the side margin it starts at. The note turns with it, into the strip the margin leaves and
	 * below where the line starts, rather than staying where it would be on a portrait page: for a header, on a
	 * page this short, above its top edge
	 *
	 * @dataProvider rotated
	 *
	 * @param string $method
	 * @param float $from
	 * @param float $to
	 */
	public function testAnAnnotationTurnsWithARotatedHeader($method, $from, $to)
	{
		$notes = $this->rects($this->annotations($this->rotatedDocument($method)), 'Text');

		$this->assertCount(1, $notes[0]);
		// The icon is 20pt across, wider than a footer's strip, so the note is placed by where it starts
		list($x0, $bottom, , $top) = $notes[0][0];
		$this->assertGreaterThanOrEqual($from * Mpdf::SCALE, $x0, 'The note should start in the strip the margin leaves');
		$this->assertLessThanOrEqual($to * Mpdf::SCALE, $x0, 'The note should start in the strip the margin leaves');
		$this->assertLessThan((210 - 15) * Mpdf::SCALE, $top, 'The note should be below where the line starts, at the side margin');
		$this->assertGreaterThan(0, $bottom, 'The note should be on the page');
	}

	/**
	 * A popup is an annotation of its own, written after the note it opens from, and each page lists both
	 */
	public function testAPopupOnAHeaderAnnotationIsOnEveryPage()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetHTMLHeader('Header <annotation content="Header note" popup="1" />');
		$mpdf->WriteHTML(self::BODY);
		$annotations = $this->annotations($this->output($mpdf));

		$this->assertCount(3, $annotations);
		foreach ($annotations as $i => $page) {
			$this->assertSame(1, substr_count($page, '/Subtype /Text'), 'Page ' . ($i + 1) . ' should list the note');
			$this->assertSame(1, substr_count($page, '/Subtype /Popup'), 'Page ' . ($i + 1) . ' should list its popup');
		}
	}

	/**
	 * A page reserves the object numbers of its annotations before they are written, so the header's note and
	 * its popup have to be counted along with the widget of a form field on the same page, or /Annots points at
	 * the wrong objects (see GravityPDF/mpdf#343)
	 */
	public function testEveryObjectThePageListsAsAnAnnotationIsOne()
	{
		$mpdf = $this->mpdf(['useActiveForms' => true]);
		$mpdf->SetHTMLHeader('Header <annotation content="Header note" popup="1" />');
		$mpdf->WriteHTML('<p><input type="text" name="field" value="Hello" /></p>');
		$pdf = $this->output($mpdf);

		$refs = $this->annotationRefs($pdf);
		$this->assertCount(3, $refs[0], 'The page should list the note, its popup and the widget');
		foreach ($refs[0] as $number) {
			$this->assertStringContainsString('/Type /Annot', $this->object($pdf, $number), "Object $number is listed in /Annots and should be an annotation");
		}

		$annotations = $this->annotations($pdf);
		$this->assertSame(1, substr_count($annotations[0], '/Subtype /Widget'), 'The page should list the widget');
		$this->assertSame(1, substr_count($annotations[0], '/Subtype /Text'), 'The page should list the note');
	}

	/**
	 * A fixed-position block is written through the same buffers as a header, and moves its annotations onto
	 * the page itself. Writing the headers afterwards must not put them there again
	 */
	public function testAFixedPositionAnnotationIsWrittenOnce()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetHTMLHeader('Header');
		$mpdf->WriteHTML('<div style="position: fixed; top: 100mm; left: 20mm;">Fixed <annotation content="Fixed note" /></div>' . self::BODY);
		$notes = $this->rects($this->annotations($this->output($mpdf)), 'Text');

		$this->assertSame([1, 0, 0], array_map('count', $notes), 'Only the first page should carry the fixed note, once');
	}

	/**
	 * An A4 landscape page whose header or footer, set by $method, forcePortraitHeaders turns a quarter turn
	 *
	 * @param string $method SetHTMLHeader or SetHTMLFooter
	 *
	 * @return string
	 */
	private function rotatedDocument($method)
	{
		$mpdf = $this->mpdf(['forcePortraitHeaders' => true]);
		$mpdf->$method(self::LINE);
		$mpdf->AddPage('L');
		$mpdf->WriteHTML('<p>Landscape</p>');

		return $this->output($mpdf);
	}

	/**
	 * The /Rect of every annotation of $subtype in each page's annotations, as lists of four numbers in points
	 *
	 * @param string[] $annotations The annotation objects of each page, as annotations() gives them
	 * @param string $subtype
	 *
	 * @return float[][][]
	 */
	private function rects(array $annotations, $subtype)
	{
		$rects = [];
		foreach ($annotations as $i => $page) {
			preg_match_all('/\/Subtype \/' . $subtype . ' \/Rect \[([^\]]*)\]/', $page, $matches);
			$rects[$i] = array_map(static function ($rect) {
				return array_map('floatval', explode(' ', $rect));
			}, $matches[1]);
		}

		return $rects;
	}

}
