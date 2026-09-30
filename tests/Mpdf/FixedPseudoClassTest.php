<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Pseudo-classes whose answer is fixed in a PDF, and html. In the standard CSS mode :link and :any-link match a link
 * with an href, :visited and the states a reader acts out (:hover, :focus and the like) never match, and :root and
 * html match the root, which mPDF has no element for: html is matched as the parent of body, its rules reach the text
 * as a parent's would, and its font size is the one rem is read against. The legacy mode drops them all, and reads
 * rem against body.
 */
class FixedPseudoClassTest extends TestCase
{

	use DrawnStyles {
		inContext as inDrawnContext;
	}

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	/** @var string[] The contexts each case is written in */
	private static $contexts = ['block', 'inline', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'];

	/**
	 * Each piece of text is drawn in the colour the rules leave it in, in every context, in each CSS mode
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $context
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $standard The colour of each piece of text in the standard mode
	 * @param array<string, string> $legacy The colour of each piece of text in the legacy mode
	 */
	public function testMatchesInEveryContext($context, $css, $html, array $standard, array $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$mpdf = $this->drawClosedDocument('<style>' . $css . '</style>' . $this->inContext($context, $html), $mode);

			$this->assertDrawnInColours($expected, $this->keyedByText($mpdf, $mpdf->drawnColours));
			$this->assertDrawnInContext($context, $mpdf, array_keys($expected));
		}
	}

	/**
	 * Every case in every context
	 *
	 * @return array[]
	 */
	public function casesInContexts()
	{
		$data = [];
		foreach (self::$contexts as $context) {
			foreach ($this->cases() as $name => $case) {
				$data[$context . ': ' . $name] = array_merge([$context], $case);
			}
		}

		return $data;
	}

	/**
	 * Rules, the inline content they are matched against, and the colour of each piece of text in each mode
	 *
	 * @return array[] Each [css, html, standard colours, legacy colours]
	 */
	private function cases()
	{
		$r = self::RED;
		$g = self::GREEN;
		$b = self::BLUE;
		$k = self::BLACK;

		// mPDF styles no a without an href, so a rule on what one holds is the near miss for :link
		$links = '<a href="#t">linked <b>bold in link</b></a> <a name="t"><b>bold in anchor</b></a> <b>plain</b>';

		return [
			'link matches a link with an href' => [
				'a:link { color: #f00; } a:link b { color: #008000; }',
				$links,
				['linked' => $r, 'bold in link' => $g, 'bold in anchor' => $k, 'plain' => $k],
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'any-link matches a link with an href' => [
				':any-link b { color: #f00; }',
				$links,
				['bold in link' => $r, 'bold in anchor' => $k, 'plain' => $k],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'not link matches an a with no href' => [
				'a:not(:link) b { color: #f00; }',
				$links,
				['bold in link' => $b, 'bold in anchor' => $r, 'plain' => $k],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'visited never matches' => [
				'a:visited, a:visited b, :visited { color: #f00; }',
				$links,
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'hover and the other states never match' => [
				'a:hover, a:link:hover, a:focus b, a:active, b:focus-within, a:focus-visible, a:target b, :hover b { color: #f00; }',
				$links,
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'not hover and not visited always match' => [
				'a:not(:hover), b:not(:visited) { color: #f00; }',
				$links,
				['linked' => $r, 'bold in link' => $r, 'bold in anchor' => $r, 'plain' => $r],
				['linked' => $b, 'bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'link counts as a class, so a later rule of the same weight wins' => [
				'a:link { color: #f00; } a.c { color: #008000; }',
				'<a class="c" href="#t">classed link</a> <a href="#t">linked</a>',
				['classed link' => $g, 'linked' => $r],
				['classed link' => $g, 'linked' => $b],
			],
			'link beats a type selector written after it' => [
				'a:link { color: #f00; } a { color: #008000; }',
				$links,
				['linked' => $r, 'plain' => $k],
				['linked' => $g, 'plain' => $k],
			],
			'root' => [
				':root { color: #f00; }',
				$links,
				['linked' => $b, 'bold in anchor' => $r, 'plain' => $r],
				['linked' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'html' => [
				'html { color: #f00; }',
				$links,
				['linked' => $b, 'bold in anchor' => $r, 'plain' => $r],
				['linked' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'body wins over html and root, whatever the order' => [
				'body { color: #008000; } html { color: #f00; } :root { color: #f00; }',
				$links,
				['bold in anchor' => $g, 'plain' => $g],
				['bold in anchor' => $g, 'plain' => $g],
			],
			'root as an ancestor' => [
				':root b { color: #f00; }',
				$links,
				['bold in link' => $r, 'bold in anchor' => $r, 'plain' => $r],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'html and body as ancestors' => [
				'html > body b { color: #f00; }',
				$links,
				['bold in link' => $r, 'bold in anchor' => $r, 'plain' => $r],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'root is not the parent of what body holds' => [
				':root > b, html > b { color: #f00; }',
				$links,
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'body is not the root' => [
				'body:root b, b:root { color: #f00; }',
				$links,
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
			'not root matches every element' => [
				'b:not(:root) { color: #f00; }',
				$links,
				['bold in link' => $r, 'bold in anchor' => $r, 'plain' => $r],
				['bold in link' => $b, 'bold in anchor' => $k, 'plain' => $k],
			],
		];
	}

	/**
	 * HTML in a context. The inline context puts it in an inline element in a paragraph; the others are as
	 * DrawnStyles::inContext() writes them
	 *
	 * @param string $context
	 * @param string $html
	 *
	 * @return string
	 */
	private function inContext($context, $html)
	{
		if ($context === 'inline') {
			return '<p><em>' . $html . '</em></p>';
		}

		return $this->inDrawnContext($context, $html);
	}

	/**
	 * Writes a document in core-font mode and closes it, so its header and footer are drawn on the page
	 *
	 * @param string $html
	 * @param string $mode The CSS mode
	 *
	 * @return TextRecordingMpdf
	 */
	private function drawClosedDocument($html, $mode)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$mpdf->OutputBinaryData();

		return $mpdf;
	}

	/**
	 * html's colour reaches text after mPDF puts its styles back to body's, as it does after a table and at a page
	 * break
	 */
	public function testHtmlColourOutlastsAReset()
	{
		$colours = $this->drawnColours(
			'<style>html { color: #f00; }</style>text before<table><tr><td>in the cell</td></tr></table>text after the table'
			. '<pagebreak />text after the page break',
			['cssMode' => CssMode::STANDARD]
		);

		$this->assertDrawnInColours([
			'text before' => self::RED,
			'in the cell' => self::RED,
			'text after the table' => self::RED,
			'text after the page break' => self::RED,
		], $colours);
	}

	/**
	 * :root and html match the language of the <html> tag
	 *
	 * @dataProvider rootLanguages
	 *
	 * @param string $css
	 * @param string $expected The colour of the paragraph in the standard mode
	 */
	public function testRootHasTheDocumentLanguage($css, $expected)
	{
		$html = '<html lang="fr-CA"><head><style>' . $css . '</style></head><body><p>text</p></body></html>';

		$this->assertSame($expected, $this->drawnColours($html, ['cssMode' => CssMode::STANDARD])['text']);
		$this->assertSame(self::BLACK, $this->drawnColours($html, ['cssMode' => CssMode::LEGACY])['text']);
	}

	/**
	 * A rule naming the root's language, and the paragraph's colour
	 *
	 * @return array[]
	 */
	public function rootLanguages()
	{
		return [
			'the language' => [':root:lang(fr) p { color: #f00; }', self::RED],
			'html in the language' => ['html:lang(fr) { color: #f00; }', self::RED],
			'another language' => [':root:lang(de) p { color: #f00; }', self::BLACK],
		];
	}

	/**
	 * rem is read against html's font size in the standard mode, which is read against the default font size, and
	 * against body's in the legacy mode. A relative font size on body is read against html's
	 *
	 * @dataProvider remSizes
	 *
	 * @param string $css
	 * @param string $html Holding one piece of text, probe
	 * @param float $standard The probe's font size in points in the standard mode
	 * @param float $legacy Its size in the legacy mode
	 */
	public function testReadsRemAgainstHtml($css, $html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$mpdf = $this->drawDocument('<style>' . $css . '</style>' . $html, ['cssMode' => $mode]);

			$this->assertEqualsWithDelta($expected, $this->keyedByText($mpdf, $mpdf->drawnFontSize)['probe'], 0.001, $mode);
		}
	}

	/**
	 * Rules sizing html, body and the probe, and the probe's size in each mode. The default font size is 11pt
	 *
	 * @return array[]
	 */
	public function remSizes()
	{
		$p = '<p>probe</p>';

		return [
			'html in percent' => ['html { font-size: 62.5%; } p { font-size: 1.6rem; }', $p, 11, 17.6],
			'html in pixels' => ['html { font-size: 20px; } p { font-size: 1rem; }', $p, 15, 11],
			'html as a keyword' => ['html { font-size: large; } p { font-size: 1rem; }', $p, 13.2, 11],
			'html in rem, which reads the default' => ['html { font-size: 2rem; } p { font-size: 1rem; }', $p, 22, 11],
			'html in em, which reads the default' => ['html { font-size: 1.5em; } p { font-size: 1rem; }', $p, 16.5, 11],
			'root' => [':root { font-size: 62.5%; } p { font-size: 2rem; }', $p, 13.75, 22],
			'the last rule for html' => ['html { font-size: 50%; } html { font-size: 62.5%; } p { font-size: 2rem; }', $p, 13.75, 22],
			'the more specific rule for html' => [':root { font-size: 62.5%; } html { font-size: 50%; } p { font-size: 2rem; }', $p, 13.75, 22],
			'body without a size of its own takes html\'s' => ['html { font-size: 62.5%; }', $p, 6.875, 11],
			'body in rem' => ['html { font-size: 62.5%; } body { font-size: 1.6rem; }', $p, 11, 17.6],
			'body in em reads html' => ['html { font-size: 62.5%; } body { font-size: 1.6em; }', $p, 11, 17.6],
			'body in percent reads html' => ['html { font-size: 62.5%; } body { font-size: 160%; }', $p, 11, 17.6],
			'body as a keyword reads the default' => ['html { font-size: 62.5%; } body { font-size: large; }', $p, 13.2, 13.2],
			'body\'s style attribute in em reads html' => ['html { font-size: 62.5%; }', '<body style="font-size: 1.6em"><p>probe</p></body>', 11, 17.6],
			'rem does not follow body' => ['body { font-size: 20pt; } p { font-size: 1rem; }', $p, 11, 20],
			'em follows body' => ['html { font-size: 62.5%; } body { font-size: 1.6rem; } p { font-size: 1.5em; }', $p, 16.5, 26.4],
			'rem in an inline element' => ['html { font-size: 62.5%; } span { font-size: 2rem; }', '<p>text <span>probe</span></p>', 13.75, 22],
			'rem in a cell reads html, not the table' => [
				'html { font-size: 62.5%; } table { font-size: 20pt; } td p { font-size: 2rem; }',
				'<table><tr><td><p>probe</p></td></tr></table>',
				13.75,
				40,
			],
			'rem in a cell with no html rule' => [
				'table { font-size: 8pt; } td { font-size: 1rem; }',
				'<table><tr><td>probe</td></tr></table>',
				11,
				8,
			],
			'a cell takes body\'s size from html' => ['html { font-size: 62.5%; }', '<table><tr><td>probe</td></tr></table>', 6.875, 11],
		];
	}

	/**
	 * rem reads html's font size in every context, and text with no size of its own takes it through body
	 *
	 * @dataProvider remContexts
	 *
	 * @param string $context
	 */
	public function testReadsRemAgainstHtmlInEveryContext($context)
	{
		$css = '<style>html { font-size: 62.5%; } b { font-size: 1.6rem; } u { font-size: 2em; }</style>';
		$html = $this->inContext($context, '<b>remsized</b> <i>inherited</i> <u>emsized</u>');

		$expected = [CssMode::STANDARD => [11, 6.875, 13.75], CssMode::LEGACY => [17.6, 11, 22]];
		foreach ($expected as $mode => $sizes) {
			$mpdf = $this->drawClosedDocument($css . $html, $mode);
			$drawn = $this->keyedByText($mpdf, $mpdf->drawnFontSize);

			$this->assertEqualsWithDelta($sizes, [$drawn['remsized'], $drawn['inherited'], $drawn['emsized']], 0.001, $mode);
			$this->assertDrawnInContext($context, $mpdf, ['remsized', 'inherited', 'emsized']);
		}
	}

	/**
	 * Each context
	 *
	 * @return array[]
	 */
	public function remContexts()
	{
		$data = [];
		foreach (self::$contexts as $context) {
			$data[$context] = [$context];
		}

		return $data;
	}

	/**
	 * rem sizes lengths other than font sizes against html's font size too
	 */
	public function testReadsRemInALengthAgainstHtml()
	{
		$html = '<style>html { font-size: 62.5%; } p.indented { margin-left: 4rem; }</style><p>flush</p><p class="indented">indented</p>';

		$expected = [CssMode::STANDARD => 4 * 6.875, CssMode::LEGACY => 4 * 11];
		foreach ($expected as $mode => $points) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
			$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);

			$this->assertEqualsWithDelta($points / Mpdf::SCALE, $boxes['indented'][1] - $boxes['flush'][1], 0.001, $mode);
		}
	}

	/**
	 * The default font size, from the configuration or SetDefaultFontSize(), is the one html starts from, and rem reads
	 * where no rule sizes html. A stylesheet sizing body leaves it as it is
	 */
	public function testHtmlStartsFromTheDefaultFontSize()
	{
		$mpdf = $this->drawDocument('<style>body { font-size: 20pt; } p { font-size: 1rem; } p.half { font-size: 0.5rem; }</style><p>probe</p>', ['default_font_size' => 14]);
		$this->assertEqualsWithDelta(14, $this->keyedByText($mpdf, $mpdf->drawnFontSize)['probe'], 0.001);

		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->SetDefaultFontSize(16);
		$mpdf->WriteHTML('<style>html { font-size: 50%; } p { font-size: 1rem; }</style><p>probe</p>');
		$this->assertEqualsWithDelta(8, $this->keyedByText($mpdf, $mpdf->drawnFontSize)['probe'], 0.001);
	}

	/**
	 * html's rules apply the same way in each part of a document written in several calls, and do not compound: body's
	 * size is read against html's afresh each time
	 *
	 * @dataProvider documentsWrittenInParts
	 *
	 * @param string $css
	 * @param float $size The size of every piece of text
	 */
	public function testWritingInPartsKeepsTheSameSizes($css, $size)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
		$mpdf->WriteHTML('<p>first part</p>', HTMLParserMode::HTML_BODY);
		$mpdf->WriteHTML('<p>second part</p>', HTMLParserMode::HTML_BODY);
		$mpdf->WriteHTML('<p>third part</p>');

		$sizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);
		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		foreach (['first part', 'second part', 'third part'] as $text) {
			$this->assertEqualsWithDelta($size, $sizes[$text], 0.001, $text);
			$this->assertSame(self::RED, $colours[$text], $text);
		}
	}

	/**
	 * A stylesheet, and the size of the text it leaves
	 *
	 * @return array[]
	 */
	public function documentsWrittenInParts()
	{
		return [
			'body takes html\'s size' => ['html { font-size: 62.5%; color: #f00; }', 6.875],
			'body in em' => ['html { font-size: 62.5%; color: #f00; } body { font-size: 1.6em; }', 11],
			'body in percent, with no html rule' => ['body { font-size: 80%; color: #f00; }', 8.8],
			'body in em, with no html rule' => ['body { font-size: 1.2em; color: #f00; }', 13.2],
			'body as a keyword' => ['html { color: #f00; } body { font-size: large; }', 13.2],
		];
	}

	/**
	 * html's background is painted as body's is: on the page
	 */
	public function testHtmlBackgroundIsThePages()
	{
		foreach ([CssMode::STANDARD => [255, 255, 0], CssMode::LEGACY => false] as $mode => $expected) {
			$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $mode]);
			$mpdf->WriteHTML('<style>html { background-color: #ff0; }</style><p>text</p>');

			$this->assertSame($expected, $mpdf->bodyBackgroundColor ? array_values(unpack('C*', substr($mpdf->bodyBackgroundColor, 1, 3))) : false, $mode);
		}
	}
}
