<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * :last-child, :nth-last-child(), :only-child, :last-of-type, :nth-last-of-type(), :only-of-type and :empty depend on
 * what follows an element. WriteHTML() reads ahead through its tokens to find it, and in the standard CSS mode these
 * match in every context an element is written in. Where what follows is not known, they do not match. In the legacy
 * mode they are dropped, as they were before.
 */
class LookAheadSelectorTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	/** @var string[] The contexts document() writes the cases in */
	private static $contexts = ['block', 'inline', 'table cell', 'list item', 'header', 'footer', 'positioned block', 'kept block', 'forced page break', 'written in parts'];

	/**
	 * Each piece of text is drawn in the colour the rules leave it in, in every context
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {O} for another tag
	 * @param array[] $groups As cases() gives them
	 */
	public function testMatchesInEveryContext($context, $css, array $groups)
	{
		$mpdf = $this->writeParts($this->document($context, $css, $groups), ['cssMode' => CssMode::STANDARD]);

		$expected = [];
		foreach ($groups as $group) {
			foreach ($group as $child) {
				if ($child[1] !== '') {
					$expected[$child[1]] = $child[2];
				}
			}
		}

		$this->assertDrawnInColours($expected, $this->keyedByText($mpdf, $mpdf->drawnColours));
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
				$data[$context . ': ' . $name] = [$context, $case[0], $case[1]];
			}
		}

		return $data;
	}

	/**
	 * Rules, with the elements they are matched against: those that match, and near misses that must not
	 *
	 * @return array[] Each [css, groups]. A group is the children of one parent, each [T or O for its tag, its text or
	 *                 '' for an empty element, the colour it should be drawn in, its attributes]
	 */
	private function cases()
	{
		$r = self::RED;
		$g = self::GREEN;
		$k = self::BLACK;

		return [
			'last-child' => [
				'{T}:last-child { color: #f00; }',
				[
					[['T', 'first', $k], ['T', 'middle', $k], ['T', 'last', $r]],
					[['T', 'before another tag', $k], ['O', 'another tag last', $k]],
				],
			],
			'nth-last-child with a number' => [
				'{T}:nth-last-child(2) { color: #f00; }',
				[[['T', 'third from the end', $k], ['T', 'second from the end', $r], ['T', 'at the end', $k]]],
			],
			'nth-last-child with a formula' => [
				'{T}:nth-last-child(2n + 1) { color: #f00; }',
				[[['T', 'fourth from the end', $k], ['T', 'third from the end', $r], ['T', 'second from the end', $k], ['T', 'at the end', $r]]],
			],
			'nth-last-child counts siblings of another tag' => [
				'{T}:nth-last-child(2) { color: #f00; }',
				[[['T', 'before another tag', $r], ['O', 'another tag last', $k]]],
			],
			'only-child' => [
				'{T}:only-child { color: #f00; }',
				[
					[['T', 'alone', $r]],
					[['T', 'first of two', $k], ['T', 'second of two', $k]],
					[['T', 'beside another tag', $k], ['O', 'another tag', $k]],
				],
			],
			'last-of-type' => [
				'{T}:last-of-type { color: #f00; }',
				[[['T', 'first of its tag', $k], ['T', 'last of its tag', $r], ['O', 'another tag after it', $k]]],
			],
			'nth-last-of-type' => [
				'{T}:nth-last-of-type(2) { color: #f00; }',
				[[['T', 'second last of its tag', $r], ['O', 'another tag between', $k], ['T', 'last of its tag', $k]]],
			],
			'only-of-type' => [
				'{T}:only-of-type { color: #f00; }',
				[
					[['O', 'another tag', $k], ['T', 'only one of its tag', $r], ['O', 'another tag again', $k]],
					[['T', 'one of two of its tag', $k], ['T', 'two of two of its tag', $k]],
				],
			],
			'empty' => [
				'{T}:empty + {T} { color: #f00; }',
				[[['T', '', null], ['T', 'after the empty one', $r], ['T', 'after one with text', $k]]],
			],
			'not empty' => [
				'{T}:not(:empty) { color: #f00; }',
				[[['T', '', null], ['T', 'with text', $r]]],
			],
			'not last-child' => [
				'{T}:not(:last-child) { color: #008000; }',
				[[['T', 'not last', $g], ['T', 'nor this', $g], ['T', 'the last', $k]]],
			],
			'last-child with a class' => [
				'{T}.c:last-child { color: #f00; }',
				[
					[['T', 'with the class, not last', $k, 'class="c"'], ['T', 'last, without the class', $k]],
					[['T', 'before', $k], ['T', 'last, with the class', $r, 'class="c"']],
				],
			],
			'first-child and last-child compete' => [
				'{T}:first-child { color: #008000; } {T}:last-child { color: #f00; }',
				[
					[['T', 'first and last', $r]],
					[['T', 'first only', $g], ['T', 'last only', $r]],
				],
			],
		];
	}

	/**
	 * @param string $context
	 *
	 * @return string[] The tag of the subjects in a context, another tag for their siblings, and the start and end of
	 *                  the element each group is written in
	 */
	private function tags($context)
	{
		switch ($context) {
			case 'inline':
				return ['em', 'b', '<p>', '</p>'];
			case 'table cell':
				return ['td', 'th', '<table><tr>', '</tr></table>'];
			case 'list item':
				return ['li', 'div', '<ul>', '</ul>'];
			default:
				return ['p', 'h5', '<div>', '</div>'];
		}
	}

	/**
	 * A document with each group in its own parent, in a context, under a stylesheet naming the tags the context
	 * gives them
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag and {O} for the other tag
	 * @param array[] $groups As cases() gives them
	 *
	 * @return array[] The parts to write it in, each [html, whether it starts the document, whether it closes it]
	 */
	private function document($context, $css, array $groups)
	{
		list($subject, $other, $open, $close) = $this->tags($context);

		$style = '<style>' . str_replace(['{T}', '{O}'], [$subject, $other], $css) . '</style>';
		$html = '';
		foreach ($groups as $group) {
			$html .= $open;
			foreach ($group as $child) {
				$tag = $child[0] === 'T' ? $subject : $other;
				$html .= '<' . $tag . (isset($child[3]) ? ' ' . $child[3] : '') . '>' . $child[1] . '</' . $tag . '> ';
			}
			$html .= $close;
		}

		switch ($context) {
			case 'header':
				$html = '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';
				break;

			case 'footer':
				$html = '<htmlpagefooter name="f">' . $html . '</htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';
				break;

			case 'positioned block':
				$html = '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div><p>after</p>';
				break;

			case 'kept block':
				// The block starts on the first page, runs over, and is laid out again on the second
				$html = str_repeat('<p>filler</p>', 24) . '<div style="page-break-inside: avoid">' . str_repeat('<p>kept</p>', 4) . $html . '</div>';
				break;

			case 'forced page break':
				$html = '<div class="w"><p>before the break</p><pagebreak />' . $html . '</div>';
				break;

			case 'written in parts':
				return [[$style . '<div class="w">', true, false], [$html, false, false], ['</div>', false, true]];
		}

		return [[$style . $html, true, true]];
	}

	/**
	 * The kept-block context does lay its block out twice for every case: it starts on the first page, runs over, and
	 * is put back and laid out again from the top of the second
	 */
	public function testTheKeptBlockUnwindsForEveryCase()
	{
		foreach ($this->cases() as $name => $case) {
			$mpdf = new UnwindCountingMpdf(['mode' => 'c']);
			$mpdf->WriteHTML($this->document('kept block', $case[0], $case[1])[0][0]);

			$this->assertSame(1, $mpdf->unwinds, $name);
			$this->assertCount(2, $mpdf->pages, $name);
		}
	}

	/**
	 * :empty matches an element with no child elements and no text, as browsers have it: white space is text, and a
	 * comment is not, so an element holding only a comment is empty and one holding comments and white space is not.
	 * The white space at the end of a table cell is stripped before the HTML is read, so a cell holding only that is
	 * empty
	 *
	 * @dataProvider emptyElements
	 *
	 * @param string $element The element tested, followed by a paragraph that is red if it is empty
	 * @param bool $empty
	 */
	public function testWhatCountsAsEmpty($element, $empty)
	{
		$colours = $this->drawnColours('<style>:empty + p.probe { color: #f00; }</style><div>' . $element . '<p class="probe">probe</p></div>', ['cssMode' => CssMode::STANDARD]);

		$this->assertSame($empty ? self::RED : self::BLACK, $colours['probe']);
	}

	/**
	 * HTML written in the body mode drops a comment too, so the element that held only the comment is empty, as in a
	 * browser
	 */
	public function testAnElementHoldingOnlyACommentInTheBodyMode()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML('p:empty + p.probe { color: #f00; }', HTMLParserMode::HEADER_CSS);
		$mpdf->WriteHTML('<div><p><!-- nothing --></p><p class="probe">probe</p></div>', HTMLParserMode::HTML_BODY);

		$this->assertSame(self::RED, $this->keyedByText($mpdf, $mpdf->drawnColours)['probe']);
	}

	/**
	 * An element, and whether it is empty
	 *
	 * @return array[]
	 */
	public function emptyElements()
	{
		return [
			'nothing in it' => ['<p></p>', true],
			'a space' => ['<p> </p>', false],
			'line breaks and tabs' => ["<p>\n\t\n</p>", false],
			'a comment, which leaves nothing in its place' => ['<p><!-- nothing --></p>', true],
			'comments and white space' => ['<p> <!-- one --> <!-- two --> </p>', false],
			'a closed start tag' => ['<div />', true],
			'a void element' => ['<hr />', true],
			'text' => ['<p>text</p>', false],
			'a zero' => ['<p>0</p>', false],
			'a non-breaking space' => ['<p>&nbsp;</p>', false],
			'a non-breaking space as a character' => ["<p>\xc2\xa0</p>", false],
			'a void child' => ['<p><br /></p>', false],
			'an image child' => ['<p><img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" /></p>', false],
			'an empty child' => ['<p><span></span></p>', false],
			'a hidden child' => ['<p><span style="display: none">hidden</span></p>', false],
			'an empty list item' => ['<ul><li></li></ul>', false],
		];
	}

	/**
	 * Rows and cells are counted within their row group and row: the implied tbody, thead and tfoot each have their
	 * own last row, and an element a browser moves out of the table does not count
	 *
	 * @dataProvider tables
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected
	 */
	public function testTableRowsAndCells($css, $html, array $expected)
	{
		$this->assertDrawnInColours($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => CssMode::STANDARD]));
	}

	/**
	 * A rule for rows or cells, a table, and the colour of each cell's text
	 *
	 * @return array[]
	 */
	public function tables()
	{
		$r = self::RED;
		$b = self::BLUE;
		$k = self::BLACK;

		$tables = [
			'the last row of an implied tbody' => [
				'tr:last-child td { color: #f00; }',
				'<table><tr><td>row one</td></tr><tr><td>row two</td></tr></table>',
				['row one' => $k, 'row two' => $r],
			],
			'the last row of each row group' => [
				'tr:last-child td, tr:last-child th { color: #f00; }',
				'<table><thead><tr><th>head one</th></tr><tr><th>head two</th></tr></thead>'
				. '<tbody><tr><td>body one</td></tr><tr><td>body two</td></tr></tbody>'
				. '<tfoot><tr><td>foot one</td></tr></tfoot></table>',
				['head one' => $k, 'head two' => $r, 'body one' => $k, 'body two' => $r, 'foot one' => $r],
			],
			'the last row group' => [
				'tbody:last-child td { color: #f00; }',
				'<table><tbody><tr><td>first group</td></tr></tbody><tbody><tr><td>last group</td></tr></tbody></table>',
				['first group' => $k, 'last group' => $r],
			],
			'the last cell' => [
				'td:last-child { color: #f00; }',
				'<table><tr><td>a1</td><td>a2</td><td>a3</td></tr><tr><td>b1</td></tr></table>',
				['a1' => $k, 'a2' => $k, 'a3' => $r, 'b1' => $r],
			],
			'a cell spanning columns is one element' => [
				'td:nth-last-child(2) { color: #f00; }',
				'<table><tr><td colspan="2">wide</td><td>narrow</td></tr><tr><td>c1</td><td>c2</td><td>c3</td></tr></table>',
				['wide' => $r, 'narrow' => $k, 'c1' => $k, 'c2' => $r, 'c3' => $k],
			],
			'the only cell' => [
				'td:only-child { color: #f00; }',
				'<table><tr><td>alone</td></tr><tr><td>one of two</td><td>two of two</td></tr></table>',
				['alone' => $r, 'one of two' => $k, 'two of two' => $k],
			],
			'the last header cell of its type' => [
				'th:last-of-type { color: #f00; }',
				'<table><tr><th>th one</th><th>th two</th><td>td after</td></tr></table>',
				['th one' => $k, 'th two' => $r, 'td after' => $k],
			],
			'cells with no end tags' => [
				'td:last-child { color: #f00; } tr:last-child td { color: #00f; }',
				'<table><tr><td>x1<td>x2<tr><td>y1<td>y2</table>',
				['x1' => $k, 'x2' => $r, 'y1' => $b, 'y2' => $b],
			],
			'an element moved out of the row is not a cell' => [
				'td:last-child { color: #f00; }',
				'<table><tr><td>before the bookmark</td><bookmark content="b" /></tr><tr><td>first</td><tocentry content="t" /><td>after the entry</td></tr></table>',
				['before the bookmark' => $r, 'first' => $k, 'after the entry' => $r],
			],
			'a nested table has its own rows' => [
				'tr:last-child > td:last-child { color: #f00; }',
				'<table><tr><td>outer one</td></tr><tr><td>outer two<table><tr><td>inner one</td><td>inner two</td></tr><tr><td>inner three</td></tr></table></td><td>outer three</td></tr></table>',
				['outer one' => $k, 'outer two' => $k, 'inner one' => $k, 'inner two' => $k, 'inner three' => $r, 'outer three' => $r],
			],
			'an empty cell' => [
				'td:empty + td { color: #f00; }',
				'<table><tr><td></td><td>after the empty cell</td><td>after a full one</td></tr></table>',
				['after the empty cell' => $r, 'after a full one' => $k],
			],
			'a cell holding only a space, which mPDF strips' => [
				'td:empty + td { color: #f00; }',
				'<table><tr><td> </td><td>after the cell with a space</td></tr></table>',
				['after the cell with a space' => $r],
			],
		];

		return $tables;
	}

	/**
	 * A table header repeated at the top of each page keeps the style its last row was given
	 */
	public function testARepeatedTableHeader()
	{
		$rows = '';
		for ($i = 1; $i <= 60; $i++) {
			$rows .= '<tr><td>row ' . $i . '</td></tr>';
		}
		$mpdf = $this->drawDocument('<style>thead tr:last-child th { color: #f00; } tbody tr:last-child td { color: #00f; }</style>'
			. '<table><thead><tr><th>top heading</th></tr><tr><th>last heading</th></tr></thead><tbody>' . $rows . '</tbody></table>', ['cssMode' => CssMode::STANDARD]);

		$headings = [];
		foreach ($mpdf->drawnText as $i => $text) {
			if (in_array(trim($text), ['top heading', 'last heading'], true)) {
				$headings[] = [trim($text), $mpdf->drawnBoxes[$i][0], $mpdf->drawnColours[$i]];
			}
		}

		$this->assertSame([
			['top heading', 1, self::BLACK], ['last heading', 1, self::RED],
			['top heading', 2, self::BLACK], ['last heading', 2, self::RED],
		], $headings);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$this->assertSame(self::BLACK, $colours['row 59']);
		$this->assertSame(self::BLUE, $colours['row 60']);
	}

	/**
	 * A table in a page-break-inside: avoid block that runs over the page is read again from the block's start tag,
	 * and its last row matches both times
	 */
	public function testATableInABlockThatUnwinds()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$unwinds = new UnwindCountingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$html = '<style>tr:last-child td { color: #f00; } td:last-child { color: #00f; }</style>'
			. str_repeat('<p>filler</p>', 24)
			. '<div style="page-break-inside: avoid">' . str_repeat('<p>kept</p>', 3)
			. '<table><tr><td>one</td><td>two</td></tr><tr><td>three</td><td>four</td></tr></table><p>after</p></div>';
		$mpdf->WriteHTML($html);
		$unwinds->WriteHTML($html);

		$this->assertSame(1, $unwinds->unwinds);
		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$pages = $this->keyedByText($mpdf, array_map(function ($box) {
			return $box[0];
		}, $mpdf->drawnBoxes));
		$this->assertSame(2, $pages['one']);
		$this->assertSame(self::BLACK, $colours['one']);
		$this->assertSame(self::BLUE, $colours['two']);
		$this->assertSame(self::RED, $colours['three']);
		$this->assertSame(self::RED, $colours['four'], 'tr:last-child td is the heavier rule');
	}

	/**
	 * Elements are counted as the open elements are: a hidden element is still a sibling, end tags left out are
	 * implied, mPDF's own tags count and a span mPDF adds for a run of another script or font does not
	 *
	 * @dataProvider elementModel
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected
	 * @param array $config
	 */
	public function testCountsElementsAsTheOpenElementsDo($css, $html, array $expected, array $config = [])
	{
		$this->assertDrawnInColours($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, $config + ['cssMode' => CssMode::STANDARD]));
	}

	/**
	 * A rule, a document, the colour of each piece of text, and any other configuration it needs
	 *
	 * @return array[]
	 */
	public function elementModel()
	{
		$r = self::RED;
		$k = self::BLACK;

		$documents = [
			'a hidden element is a sibling' => [
				'li:last-child { color: #f00; }',
				'<ul><li>shown</li><li style="display: none">hidden</li></ul>',
				['shown' => $k],
			],
			'an element inside a hidden one is counted' => [
				'p:last-child { color: #f00; }',
				'<div><p>shown</p><div style="display: none"><p>hidden</p></div></div>',
				['shown' => $k],
			],
			'list items with no end tags' => [
				'li:last-child { color: #f00; } li:first-child { font-weight: bold; }',
				'<ul><li>one<li>two<li>three</ul><p>after</p>',
				['one' => $k, 'two' => $k, 'three' => $r, 'after' => $k],
			],
			'paragraphs with no end tags' => [
				'p:last-child { color: #f00; }',
				'<div><p>one<p>two</div>',
				['one' => $k, 'two' => $r],
			],
			'definitions with no end tags' => [
				'dd:last-of-type { color: #f00; }',
				'<dl><dt>term<dd>first<dt>term two<dd>second</dl>',
				['first' => $k, 'second' => $r],
			],
			'a void element is a sibling' => [
				'p:last-child { color: #f00; }',
				'<div><p>before the rule</p><hr /></div><div><p>last</p></div>',
				['before the rule' => $k, 'last' => $r],
			],
			'an element closed by its start tag is a sibling' => [
				'p:last-child { color: #f00; }',
				'<div><p>before the empty div</p><div /></div>',
				['before the empty div' => $k],
			],
			'a forced page break is a sibling' => [
				'p:last-child { color: #f00; }',
				'<div><p>before the break</p><pagebreak /></div><div><pagebreak /><p>after the break</p></div>',
				['before the break' => $k, 'after the break' => $r],
			],
			'a block reopened after a forced page break is still one element' => [
				'div > p:last-child { color: #f00; } div:only-child > p:first-child { font-weight: bold; }',
				'<section><div><p>before</p><pagebreak /><p>last</p></div></section>',
				['before' => $k, 'last' => $r],
			],
			'a positioned block and the element around it' => [
				'p:last-child, em:last-child { color: #f00; } div:nth-last-child(2) > p:first-child { color: #00f; }',
				'<span><em>before the box</em><div style="position: absolute; top: 60mm; left: 20mm; width: 100mm;"><p>in the box</p><p>last in the box</p></div><em>after the box</em></span>',
				['before the box' => $k, 'in the box' => self::BLUE, 'last in the box' => $r, 'after the box' => $r],
			],
			'a span around a run of another script is not an element' => [
				'span:last-child { color: #f00; }',
				'<p><span>latin</span> ελληνικά</p>',
				['latin' => $r],
				['mode' => '', 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font' => 'dejavusans'],
			],
			'a span around a run of a substitute font is not an element' => [
				'span:last-child { color: #f00; } span:nth-child(2) { font-weight: bold; }',
				'<p style="font-family: ccourier"><span>first</span> α <span>second</span></p>',
				['first' => $k, 'second' => $r],
				['mode' => '', 'useSubstitutions' => true],
			],
			'an ancestor that is the last child' => [
				'div:last-child > p { color: #f00; }',
				'<div><p>in the first</p></div><div><p>in the last</p></div>',
				['in the first' => $k, 'in the last' => $r],
			],
			'an earlier sibling at its place from the end' => [
				'p:nth-last-child(3) + p { color: #f00; }',
				'<div><p>one</p><p>two</p><p>three</p></div>',
				['one' => $k, 'two' => $r, 'three' => $k],
			],
		];

		return $documents;
	}

	/**
	 * The span mPDF adds around characters it moves into a substitute font is not the document's, so in the standard
	 * mode no stylesheet rule reaches it, as a browser has no such element. The legacy mode styles it as before
	 *
	 * @dataProvider substituteFontSpan
	 *
	 * @param string $mode
	 * @param string $expected The colour the substituted character is drawn in
	 */
	public function testAStylesheetRuleForSpansAndTheSpanAroundASubstituteFont($mode, $expected)
	{
		$colours = $this->drawnColours('<style>span { color: #f00; }</style><p style="font-family: ccourier">latin α latin <span>a span</span></p>', ['mode' => '', 'useSubstitutions' => true, 'cssMode' => $mode]);

		$this->assertSame($expected, $colours['α']);
		$this->assertSame(self::RED, $colours['a span']);
	}

	/**
	 * Each CSS mode, and the colour the substituted character is drawn in
	 *
	 * @return array[]
	 */
	public function substituteFontSpan()
	{
		return [
			'standard' => [CssMode::STANDARD, self::BLACK],
			'legacy' => [CssMode::LEGACY, self::RED],
		];
	}

	/**
	 * HTML written in parts: what follows an element open at the end of a call is not known in that call, so the
	 * pseudo-classes that need it do not match its children there, and :not() of them does not either. Once the
	 * element closes in a later call, its children written in that call match
	 *
	 * @dataProvider htmlInParts
	 *
	 * @param string $css
	 * @param string $first Written first, leaving elements open
	 * @param string $second Written after, closing them
	 * @param array<string, string> $expected
	 */
	public function testHtmlWrittenInParts($css, $first, $second, array $expected)
	{
		$mpdf = $this->writeParts([['<style>' . $css . '</style>' . $first, true, false], [$second, false, true]], ['cssMode' => CssMode::STANDARD]);

		$this->assertDrawnInColours($expected, $this->keyedByText($mpdf, $mpdf->drawnColours));
	}

	/**
	 * A rule, the two parts of a document, and the colour of each piece of text
	 *
	 * @return array[]
	 */
	public function htmlInParts()
	{
		$r = self::RED;
		$k = self::BLACK;

		$documents = [
			'the last child' => [
				'li:last-child { color: #f00; }',
				'<ul><li>one</li><li>two</li>',
				'<li>three</li></ul>',
				['one' => $k, 'two' => $k, 'three' => $r],
			],
			'counting from the end' => [
				'li:nth-last-child(3) { color: #f00; }',
				'<ul><li>one</li><li>two</li>',
				'<li>three</li><li>four</li><li>five</li></ul>',
				['one' => $k, 'two' => $k, 'three' => $r, 'four' => $k, 'five' => $k],
			],
			'not the last child' => [
				'li:not(:last-child) { color: #f00; }',
				'<ul><li>one</li>',
				'<li>two</li><li>three</li></ul>',
				['one' => $k, 'two' => $r, 'three' => $k],
			],
			'the body is open across the calls' => [
				'p:last-child { color: #f00; }',
				'<p>one</p><p>two</p>',
				'<p>three</p>',
				['one' => $k, 'two' => $k, 'three' => $r],
			],
			'an element open at the end of a call with nothing in it yet' => [
				'p:not(:empty) { color: #f00; }',
				'<div><p>closed</p><p>',
				'written later</p></div>',
				['closed' => $r, 'written later' => $k],
			],
			'an element open at the end of a call with text in it' => [
				'p:not(:empty) { color: #f00; }',
				'<div><p>started',
				' <b>finished</b></p></div>',
				['started' => $r, 'finished' => $r],
			],
		];

		return $documents;
	}

	/**
	 * A header written, or an index inserted, while the flow's elements are open is a document of its own, read ahead
	 * on its own. The flow carries on with what was read ahead of it
	 *
	 * @dataProvider interruptions
	 *
	 * @param string $interruption Written between the first paragraph and the list
	 * @param array $config
	 */
	public function testTheFlowCarriesOnAfterADocumentWrittenInsideIt($interruption, array $config)
	{
		$colours = $this->drawnColours('<style>li:last-child, p:last-child { color: #f00; }</style>'
			. '<htmlpageheader name="h"><div><p>head one</p><p>head two</p></div></htmlpageheader>'
			. '<div><p>one<indexentry content="Word" /></p>' . $interruption . '<ul><li>a</li><li>b</li><li>c</li></ul></div>', $config + ['cssMode' => CssMode::STANDARD]);

		$this->assertDrawnInColours(['b' => self::BLACK, 'c' => self::RED], $colours);
	}

	/**
	 * Something written as a document of its own in the middle of the flow, and the configuration it needs
	 *
	 * @return array[]
	 */
	public function interruptions()
	{
		$interruptions = [
			'a header measured for the top margin' => ['<pageheader name="ph" content-left="Left" content-right="Right" /><setpageheader name="ph" value="on" show-this-page="1" />', ['setAutoTopMargin' => 'stretch']],
			'an index' => ['<indexinsert usedivletters="off" />', []],
		];

		return $interruptions;
	}

	/**
	 * Every document written as one call is read ahead in full, so the last element of the body is known to be last
	 */
	public function testEachCallIsADocumentOfItsOwn()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML('<style>p:last-child { color: #f00; }</style><p>first call, first</p><p>first call, last</p>');
		$mpdf->WriteHTML('<p>second call, first</p><p>second call, last</p>');

		$this->assertDrawnInColours([
			'first call, first' => self::BLACK,
			'first call, last' => self::RED,
			'second call, first' => self::BLACK,
			'second call, last' => self::RED,
		], $this->keyedByText($mpdf, $mpdf->drawnColours));
	}

	/**
	 * These pseudo-classes weigh as a class, and take their place among the rules by specificity and then by source
	 * order
	 *
	 * @dataProvider precedence
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The colour of the text "subject"
	 */
	public function testPrecedence($css, $html, $expected)
	{
		$this->assertSame($expected, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => CssMode::STANDARD])['subject']);
	}

	/**
	 * Rules competing for the last of three list items, or for a table's only cell
	 *
	 * @return array[]
	 */
	public function precedence()
	{
		$list = '<ul><li>one</li><li>two</li><li class="c" id="i">subject</li></ul>';
		$cell = '<table><tr><td>subject</td></tr></table>';

		return [
			'a later first-child on a cell' => ['td:last-child { color: #f00; } td:first-child { color: #008000; }', $cell, self::GREEN],
			'an earlier first-child on a cell' => ['td:first-child { color: #008000; } td:last-child { color: #f00; }', $cell, self::RED],
			'a later first-child elsewhere' => ['li:last-child { color: #f00; } li:first-child { color: #008000; }', '<ul><li>subject</li></ul>', self::GREEN],
			'a later class of the same weight' => ['li:last-child { color: #f00; } li.c { color: #008000; }', $list, self::GREEN],
			'an earlier class of the same weight' => ['li.c { color: #008000; } li:last-child { color: #f00; }', $list, self::RED],
			'an id' => ['#i { color: #008000; } li:last-child { color: #f00; }', $list, self::GREEN],
			'a heavier compound' => ['li.c:last-child { color: #f00; } ul li.c { color: #008000; }', $list, self::RED],
			'two pseudo-classes beat one' => ['li:last-child:nth-child(3) { color: #f00; } li:last-child { color: #008000; }', $list, self::RED],
			'one pseudo-class loses to two' => ['li:last-child { color: #008000; } ul > li:only-of-type, li:nth-last-child(1):nth-child(3) { color: #f00; }', $list, self::RED],
		];
	}

	/**
	 * In the legacy CSS mode these pseudo-classes are dropped, as the legacy parser drops them: a document with rules
	 * using them is drawn as it is with those rules taken out
	 *
	 * @dataProvider legacyDocuments
	 *
	 * @param array[] $with The parts of the document, as document() gives them
	 * @param array[] $without The same with the rules that use these pseudo-classes taken out
	 * @param array $config
	 */
	public function testTheLegacyModeDropsThem(array $with, array $without, array $config)
	{
		$config += ['cssMode' => CssMode::LEGACY];
		$dropped = $this->writeParts($without, $config);
		$written = $this->writeParts($with, $config);

		$this->assertSame($dropped->drawnText, $written->drawnText);
		$this->assertSame($dropped->drawnColours, $written->drawnColours);
	}

	/**
	 * Every case in every context, every table and every document of the element model, each with its rules and
	 * without those that use these pseudo-classes
	 *
	 * @return array[]
	 */
	public function legacyDocuments()
	{
		$data = [];
		foreach (self::$contexts as $context) {
			foreach ($this->cases() as $name => $case) {
				$parts = $this->document($context, $case[0], $case[1]);
				$data[$context . ': ' . $name] = [$parts, self::withoutLookAhead($parts), []];
			}
		}

		foreach (['table' => $this->tables(), 'element model' => $this->elementModel()] as $set => $documents) {
			foreach ($documents as $name => $document) {
				$parts = [['<style>' . $document[0] . '</style>' . $document[1], true, true]];
				$data[$set . ': ' . $name] = [$parts, self::withoutLookAhead($parts), isset($document[3]) ? $document[3] : []];
			}
		}

		return $data;
	}

	/**
	 * @param array[] $parts A document's parts, as document() gives them
	 *
	 * @return array[] The same parts, with the rules whose selector uses a pseudo-class that looks at what follows
	 *                 taken out of their stylesheets
	 */
	private static function withoutLookAhead(array $parts)
	{
		foreach ($parts as $i => $part) {
			$parts[$i][0] = preg_replace_callback('/<style>(.*?)<\/style>/s', function ($style) {
				return '<style>' . preg_replace('/[^{}]*:(?:last-child|last-of-type|nth-last-|only-child|only-of-type|empty)[^{}]*\{[^}]*\}/', '', $style[1]) . '</style>';
			}, $part[0]);
		}

		return $parts;
	}

	/**
	 * Writes a document in parts, each [html, whether it starts the document, whether it closes it], in core-font mode
	 *
	 * @param array[] $parts
	 * @param array $config
	 *
	 * @return TextRecordingMpdf
	 */
	private function writeParts(array $parts, array $config)
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c']);
		foreach ($parts as $part) {
			$mpdf->WriteHTML($part[0], HTMLParserMode::DEFAULT_MODE, $part[1], $part[2]);
		}

		return $mpdf;
	}
}
