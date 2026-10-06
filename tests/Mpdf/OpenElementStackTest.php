<?php

namespace Mpdf;

use Mpdf\Css\TextVars;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The stack of open elements kept while HTML is written, for selectors that look at an element's ancestors and
 * earlier siblings. It follows the tags as they are read, not the blocks mPDF opens and closes to lay them out.
 *
 * A path is written outermost first, each element as TAG#ID.CLASS:nthChild/nthOfType.
 */
class OpenElementStackTest extends TestCase
{

	use PageStreams;

	/**
	 * End tags HTML lets be left out are implied by the start tag that follows, and by the end of the parent
	 */
	public function testEndTagsLeftOutAreImplied()
	{
		$mpdf = $this->write('<ul class="list"><li>one<li>two</ul>'
			. '<p>para one<p>para two<div>div</div>'
			. '<dl><dt>term<dd>definition<dt>second term</dl>'
			. '<p>last');

		$this->assertSame('UL.LIST:1/1 > LI:1/1', $this->pathAt($mpdf, 'one'));
		$this->assertSame('UL.LIST:1/1 > LI:2/2', $this->pathAt($mpdf, 'two'));
		$this->assertSame('P:2/1', $this->pathAt($mpdf, 'para one'));
		$this->assertSame('P:3/2', $this->pathAt($mpdf, 'para two'));
		$this->assertSame('DIV:4/1', $this->pathAt($mpdf, 'div'));
		$this->assertSame('DL:5/1 > DT:1/1', $this->pathAt($mpdf, 'term'));
		$this->assertSame('DL:5/1 > DD:2/1', $this->pathAt($mpdf, 'definition'));
		$this->assertSame('DL:5/1 > DT:3/2', $this->pathAt($mpdf, 'second term'));
		$this->assertSame('P:6/3', $this->path($mpdf->getOpenElements()));

		$mpdf = $this->write('<select name="s"><option>first<option>second');
		$this->assertSame('SELECT:1/1 > OPTION:2/2', $this->path($mpdf->getOpenElements()));
	}

	/**
	 * With allow_html_optional_endtags off, mPDF lays out a paragraph opened inside another as a child of it, and
	 * the stack nests it the same way
	 */
	public function testNoEndTagIsImpliedWhenOptionalEndTagsAreOff()
	{
		$mpdf = $this->mpdfRecording(['allow_html_optional_endtags' => false]);
		$mpdf->WriteHTML('<p>one<p>two', HTMLParserMode::DEFAULT_MODE, true, false);

		$this->assertSame('P:1/1 > P:1/1', $this->path($mpdf->getOpenElements()));
	}

	/**
	 * Cells and rows with no end tag are closed by the next cell, row or table section, and a row written straight
	 * into a table sits in a tbody, as it does in a browser
	 */
	public function testTableEndTagsLeftOutAreImplied()
	{
		$mpdf = $this->write('<table><thead><tr><th>head<tbody><tr><td>a<td>b<tr><td>c</table>');

		$this->assertSame('TABLE:1/1 > THEAD:1/1 > TR:1/1 > TH:1/1', $this->pathAt($mpdf, 'head'));
		$this->assertSame('TABLE:1/1 > TBODY:2/1 > TR:1/1 > TD:1/1', $this->pathAt($mpdf, 'a'));
		$this->assertSame('TABLE:1/1 > TBODY:2/1 > TR:1/1 > TD:2/2', $this->pathAt($mpdf, 'b'));
		$this->assertSame('TABLE:1/1 > TBODY:2/1 > TR:2/2 > TD:1/1', $this->pathAt($mpdf, 'c'));
		$this->assertSame('', $this->path($mpdf->getOpenElements()));
	}

	/**
	 * mPDF lays out a cell, row or row group opened without the end tag of the one before as the next one with
	 * allow_html_optional_endtags off too, and the stack closes the one before as it does with the option on. A
	 * paragraph is still opened inside the one before
	 */
	public function testTableEndTagsLeftOutAreImpliedWhenOptionalEndTagsAreOff()
	{
		$mpdf = $this->mpdfRecording(['allow_html_optional_endtags' => false]);
		$mpdf->WriteHTML('<table><thead><tr><th>head<tbody><tr><td>a<td><p>b<p>c<tr><td>d</table>', HTMLParserMode::DEFAULT_MODE, true, false);

		$this->assertSame('TABLE:1/1 > THEAD:1/1 > TR:1/1 > TH:1/1', $this->pathAt($mpdf, 'head'));
		$this->assertSame('TABLE:1/1 > TBODY:2/1 > TR:1/1 > TD:2/2 > P:1/1 > P:1/1', $this->pathAt($mpdf, 'c'));
		$this->assertSame('TABLE:1/1 > TBODY:2/1 > TR:2/2 > TD:1/1', $this->pathAt($mpdf, 'd'));
	}

	/**
	 * An element a browser moves out of a table, such as mPDF's own tags written between cells or rows, is not counted
	 * among the cells of the row or the rows of the row group
	 */
	public function testElementsWrittenStraightIntoATablePartAreNotItsChildren()
	{
		$mpdf = $this->write('<table><bookmark content="b" />'
			. '<tr><tocentry content="t" /><td>a</td><span>span</span><td>b</td></tr>'
			. '<indexentry content="i" /><tr><td>c</td></tr></table><p>after</p>');

		$this->assertSame('TABLE:1/1 > TBODY:1/1 > TR:1/1 > TD:2/2', $this->pathAt($mpdf, 'b'));
		$this->assertSame('TABLE:1/1 > TBODY:1/1 > TR:2/2 > TD:1/1', $this->pathAt($mpdf, 'c'));
		$this->assertSame([], $this->childTags($this->frameAt($mpdf, 'b', 1)));
		$this->assertSame(['TD'], $this->childTags($this->frameAt($mpdf, 'b', 3)));
		$this->assertSame(['TR'], $this->childTags($this->frameAt($mpdf, 'c', 2)));
		$this->assertSame('P:2/1', $this->pathAt($mpdf, 'after'));
	}

	/**
	 * An end tag with no element of its name open is ignored, and so is one inside a table cell for an element
	 * outside the table
	 */
	public function testStrayEndTagsAreIgnored()
	{
		$mpdf = $this->write('<div class="a"><p>before</span>after</p></div>'
			. '<div class="b"><table><tr><td>cell</div>still in the cell</td></tr></table>after the table');

		$this->assertSame('DIV.A:1/1 > P:1/1', $this->pathAt($mpdf, 'before'));
		$this->assertSame('DIV.A:1/1 > P:1/1', $this->pathAt($mpdf, 'after'));
		$this->assertSame('DIV.B:2/2 > TABLE:1/1 > TBODY:1/1 > TR:1/1 > TD:1/1', $this->pathAt($mpdf, 'still in the cell'));
		$this->assertSame('DIV.B:2/2', $this->pathAt($mpdf, 'after the table'));
	}

	/**
	 * Elements with no content, mPDF's own among them, count among their siblings without ever being open
	 */
	public function testEmptyElementsCountAsSiblingsButAreNeverOpen()
	{
		$mpdf = $this->write('<p>text<br><br /><span>after the breaks</span></p><pagebreak>'
			. '<pagefooter name="f" content-center="footer"><h1>heading</h1>');

		$this->assertSame('P:1/1 > SPAN:3/1', $this->pathAt($mpdf, 'after the breaks'));
		$this->assertSame('H1:4/1', $this->pathAt($mpdf, 'heading'));
		$this->assertSame(['P', 'PAGEBREAK', 'PAGEFOOTER'], $this->childTags($this->frameAt($mpdf, 'heading', 0)));
	}

	/**
	 * An empty element is never open even when a slash in one of its attribute values keeps AdjustHTML() from closing
	 * its start tag, so the element after it is its sibling, not its child
	 *
	 * @dataProvider emptyElementsWithASlashInAnAttribute
	 *
	 * @param string $element
	 * @param string $tag
	 */
	public function testAnEmptyElementWithASlashInAnAttributeIsNeverOpen($element, $tag)
	{
		$mpdf = $this->write('<div>' . $element . '<p>after</p></div>');

		$this->assertSame('DIV:1/1 > P:2/1', $this->pathAt($mpdf, 'after'));
		$this->assertSame([$tag], $this->childTags($this->frameAt($mpdf, 'after', 1)));
	}

	/**
	 * Empty elements whose start tag has no slash, with a slash in an attribute value
	 *
	 * @return array[]
	 */
	public function emptyElementsWithASlashInAnAttribute()
	{
		return [
			'an image' => ['<img src="images/a.png">', 'IMG'],
			'an annotation' => ['<annotation content="http://x/y">', 'ANNOTATION'],
			'a page header' => ['<pageheader name="h" content-left="{DATE d/m/Y}">', 'PAGEHEADER'],
		];
	}

	/**
	 * A frame carries the element's id, classes, attributes and language, which it inherits from an ancestor when
	 * it has none of its own
	 */
	public function testAFrameDescribesItsElement()
	{
		$mpdf = $this->write('<div id="main" class="a  b" lang="de" title="Box"><p class="c">text</p></div>');

		$div = $this->frameAt($mpdf, 'text', 1);
		$this->assertSame('DIV', $div['tag']);
		$this->assertSame('MAIN', $div['id']);
		$this->assertSame(['A', 'B'], $div['classes']);
		$this->assertSame('de', $div['lang']);
		$this->assertSame('Box', $div['attr']['TITLE']);

		$p = $this->frameAt($mpdf, 'text', 2);
		$this->assertSame('de', $p['lang']);
		$this->assertSame('', $p['id']);
		$this->assertSame(['C'], $p['classes']);
	}

	/**
	 * In the standard CSS mode a frame carries its element's computed values, with the CSS-wide keywords resolved, and
	 * the document's frame carries those of body. A font size and an em length are absolute, so that a descendant
	 * taking them, by inheriting them or through inherit, gets the length. A tbody the HTML leaves out carries what it
	 * inherits from its table. The legacy mode keeps none
	 */
	public function testAFrameCarriesItsElementsComputedValues()
	{
		$html = '<style>body { background-color: #ff0; } div { font-size: 20pt; padding: 1em; letter-spacing: 0.1em; }'
			. ' p { font-size: 50%; padding: inherit; } table { color: #00f; padding: 3mm; }</style>'
			. '<div><p>text</p><table><tr><td>cell</td></tr></table></div>';

		$mpdf = $this->write($html);
		$frames = $this->framesAt($mpdf, 'text');
		$this->assertSame('#ff0', $frames[0]['computed']['BACKGROUND-COLOR']);
		$this->assertSame('20pt', $frames[1]['computed']['FONT-SIZE']);
		$this->assertStringEndsWith('mm', $frames[1]['computed']['PADDING-LEFT']);
		$this->assertEqualsWithDelta(20 / Mpdf::SCALE, (float) $frames[1]['computed']['PADDING-LEFT'], 1e-9);
		$this->assertStringEndsWith('pt', $frames[2]['computed']['FONT-SIZE']);
		$this->assertEqualsWithDelta(10, (float) $frames[2]['computed']['FONT-SIZE'], 1e-9);
		$this->assertSame($frames[1]['computed']['PADDING-LEFT'], $frames[2]['computed']['PADDING-LEFT']);
		$this->assertSame($frames[1]['computed']['LETTER-SPACING'], $frames[2]['computed']['LETTER-SPACING']);

		$tbody = $this->frameAt($mpdf, 'cell', 3);
		$this->assertSame('TBODY', $tbody['tag']);
		$this->assertSame('#00f', $tbody['computed']['COLOR']);
		$this->assertSame('20pt', $tbody['computed']['FONT-SIZE']);
		$this->assertArrayNotHasKey('PADDING-LEFT', $tbody['computed']);

		$mpdf = $this->mpdfRecording(['cssMode' => CssMode::LEGACY]);
		$mpdf->WriteHTML($html, HTMLParserMode::DEFAULT_MODE, true, false);
		foreach (array_merge($this->framesAt($mpdf, 'text'), $this->framesAt($mpdf, 'cell')) as $frame) {
			$this->assertNull($frame['computed']);
		}
	}

	/**
	 * In the standard CSS mode a frame carries the link and the text decorations its element's content is drawn in:
	 * those of the element itself, and none for a positioned block, which is out of the flow. The legacy mode keeps
	 * none
	 */
	public function testAFrameCarriesTheLinkAndTheDecorationsOfItsContent()
	{
		$html = '<body style="text-decoration: underline"><div><a href="https://example.com/">link<span>text</span></a>'
			. '</div><a href="https://example.com/"><div style="position: absolute; top: 50mm; left: 20mm; width: 50mm">'
			. '<p>positioned</p></div></a></body>';

		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML($html);
		$frames = $this->framesAt($mpdf, 'text');
		$this->assertSame(TextVars::FD_UNDERLINE, $frames[0]['decorations']['textvar']);
		$this->assertSame('', $frames[1]['href']);
		$this->assertSame(TextVars::FD_UNDERLINE, $frames[1]['decorations']['textvar']);
		$this->assertSame('https://example.com/', $frames[2]['href']);
		$this->assertSame('https://example.com/', $frames[3]['href']);
		$this->assertSame(TextVars::FD_UNDERLINE, $frames[3]['decorations']['textvar'] & TextVars::FD_UNDERLINE);

		$positioned = $this->framesAt($mpdf, 'positioned');
		$this->assertSame('DIV', $positioned[2]['tag']);
		$this->assertSame('', $positioned[2]['href']);
		$this->assertSame(0, $positioned[2]['decorations']['textvar']);
		$this->assertSame('', $positioned[3]['href']);

		$mpdf = $this->mpdfRecording(['cssMode' => CssMode::LEGACY]);
		$mpdf->WriteHTML($html);
		foreach (array_merge($this->framesAt($mpdf, 'text'), $this->framesAt($mpdf, 'positioned')) as $frame) {
			$this->assertSame('', $frame['href']);
			$this->assertSame([], $frame['decorations']);
		}
	}

	/**
	 * Tables nested in cells keep their own rows and cells, and the outer cell is back on top after them
	 */
	public function testNestedTables()
	{
		$mpdf = $this->write('<table class="outer"><tr><td>outer'
			. '<table class="inner"><tr><td>inner a</td><td>inner b</td></tr></table>'
			. 'after the inner table</td><td>second outer cell</td></tr></table>');

		$outer = 'TABLE.OUTER:1/1 > TBODY:1/1 > TR:1/1';
		$this->assertSame($outer . ' > TD:1/1 > TABLE.INNER:1/1 > TBODY:1/1 > TR:1/1 > TD:2/2', $this->pathAt($mpdf, 'inner b'));
		$this->assertSame($outer . ' > TD:1/1', $this->pathAt($mpdf, 'after the inner table'));
		$this->assertSame($outer . ' > TD:2/2', $this->pathAt($mpdf, 'second outer cell'));
	}

	/**
	 * A forced page break closes and reopens the blocks around it to lay them out on the next page. That does not
	 * close or reopen the elements, which count the break among their children
	 */
	public function testAForcedPageBreakInsideNestedBlocks()
	{
		$mpdf = $this->write('<div class="a"><div class="b"><p>one</p><pagebreak /><p>two</p>'
			. '<p style="page-break-before: always">three</p></div></div><p>four</p>');

		$this->assertSame(3, count($mpdf->pages));
		$this->assertSame('DIV.A:1/1 > DIV.B:1/1 > P:3/2', $this->pathAt($mpdf, 'two'));
		$this->assertSame('DIV.A:1/1 > DIV.B:1/1 > P:4/3', $this->pathAt($mpdf, 'three'));
		$this->assertSame('P:2/1', $this->pathAt($mpdf, 'four'));
	}

	/**
	 * A page-break-inside: avoid block that runs onto another page is put back and read again from its start tag. It
	 * is opened once, and the elements after it count it once
	 */
	public function testAKeptBlockThatUnwinds()
	{
		$mpdf = $this->write($this->filler(22) . '<div class="kept" style="page-break-inside: avoid">'
			. str_repeat('<p>Kept</p>', 11) . '<p>last kept</p></div><p>after</p>');

		$this->assertSame(1, $mpdf->unwinds);
		$this->assertSame('DIV.KEPT:23/1 > P:12/12', $this->pathAt($mpdf, 'last kept'));
		$this->assertSame('P:24/23', $this->pathAt($mpdf, 'after'));
		$this->assertArrayNotHasKey('PAGEBREAKAVOIDCHECKED', $this->frameAt($mpdf, 'last kept', 1)['attr']);
	}

	/**
	 * A kept paragraph closed by the start tag after it is put back while that start tag is being opened, and the
	 * element it opens is not left open over the paragraph read again
	 */
	public function testAKeptBlockClosedByTheNextStartTag()
	{
		$mpdf = $this->write($this->filler(26) . '<p class="kept" style="page-break-inside: avoid">'
			. str_repeat('Kept text. ', 80) . '<div>after</div>');

		$this->assertSame(1, $mpdf->unwinds);
		$this->assertSame('P.KEPT:27/27', $this->pathAt($mpdf, trim(str_repeat('Kept text. ', 80))));
		$this->assertSame('DIV:28/1', $this->pathAt($mpdf, 'after'));
	}

	/**
	 * A header is written to measure it when it is set, which can be in the middle of the flow, and again for each
	 * page it is on once the document is done. Each time it starts from nothing open, and the flow's open elements
	 * are as they were after it
	 */
	public function testAHeaderWrittenWhileElementsAreOpen()
	{
		$mpdf = $this->mpdfRecording(['setAutoTopMargin' => 'stretch']);
		$mpdf->WriteHTML('<div class="flow"><p>one</p>', HTMLParserMode::DEFAULT_MODE, true, false);
		$mpdf->SetHTMLHeader('<div class="head"><span>header</span></div>');
		$mpdf->WriteHTML('<p>two</p><pagebreak /><p>three</p></div>', HTMLParserMode::DEFAULT_MODE, false, true);
		$this->output($mpdf);

		$headers = array_keys(array_filter($mpdf->openElementsAtText, function ($read) {
			return $read[0] === 'header';
		}));
		$this->assertCount(2, $headers, 'Measured when it is set, then written on the second page');
		foreach ($headers as $i) {
			$this->assertSame('DIV.HEAD:1/1 > SPAN:1/1', $this->path($mpdf->openElementsAtText[$i][1]));
		}
		$this->assertSame('DIV.FLOW:1/1 > P:2/2', $this->pathAt($mpdf, 'two'));
		$this->assertSame('DIV.FLOW:1/1 > P:4/3', $this->pathAt($mpdf, 'three'));
	}

	/**
	 * The content of a positioned block is written once the page is done, inside the block and the elements open
	 * around it, and cannot close them
	 */
	public function testAPositionedBlock()
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<p>before</p><span class="around">'
			. '<div id="box" class="c" style="position: absolute; top: 100mm; left: 20mm; width: 100mm;">'
			. '<p>first</p></span><div><p>nested</p></div></div>'
			. '<em>beside</em></span><p>after</p>');

		$box = 'SPAN.AROUND:2/1 > DIV#BOX.C:1/1';
		$this->assertSame($box . ' > P:1/1', $this->pathAt($mpdf, 'first'));
		$this->assertSame($box . ' > DIV:2/1 > P:1/1', $this->pathAt($mpdf, 'nested'));
		$this->assertSame('SPAN.AROUND:2/1 > EM:2/1', $this->pathAt($mpdf, 'beside'));
		$this->assertSame('P:3/2', $this->pathAt($mpdf, 'after'));
	}

	/**
	 * An index is written as a document of its own where it is inserted, and the flow carries on around it
	 */
	public function testAnIndexInsertedInTheFlow()
	{
		$mpdf = $this->write('<div class="a"><p>one<indexentry content="Word" /></p><indexinsert usedivletters="off" /><p>after</p>');

		$this->assertSame('DIV.A:1/1 > P:3/2', $this->pathAt($mpdf, 'after'));
	}

	/**
	 * HTML written in parts keeps its open elements from one call to the next, until a call starts a new document
	 */
	public function testHtmlWrittenInParts()
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<div class="a"><p>one</p>', HTMLParserMode::DEFAULT_MODE, true, false);
		$mpdf->WriteHTML('<p>two</p></div><p>three</p>', HTMLParserMode::DEFAULT_MODE, false, true);
		$mpdf->WriteHTML('<p>fresh</p>');

		$this->assertSame('DIV.A:1/1 > P:2/2', $this->pathAt($mpdf, 'two'));
		$this->assertSame('P:2/1', $this->pathAt($mpdf, 'three'));
		$this->assertSame('P:1/1', $this->pathAt($mpdf, 'fresh'));
	}

	/**
	 * Elements hidden with display: none are elements all the same, and the elements after them count them
	 */
	public function testHiddenElements()
	{
		$mpdf = $this->write('<div style="display: none"><p>hidden</p><p>hidden too<br></p></div><p>shown</p>');

		$this->assertSame('P:2/1', $this->pathAt($mpdf, 'shown'));
	}

	/**
	 * An element inside a hidden one keeps its attributes, for the selectors that look at its siblings
	 */
	public function testAnElementInsideAHiddenOneKeepsItsAttributes()
	{
		$mpdf = $this->write('<div style="display: none"><p class="x">hidden</p><span id="y">');

		$open = $mpdf->getOpenElements();
		$this->assertSame('DIV:1/1 > SPAN#Y:2/1', $this->path($open));
		$this->assertSame(['X'], $open[1]['children'][0]['classes']);
	}

	/**
	 * Each frame carries what was read ahead of its element: how many children it has in all, of each tag, and
	 * whether it is empty. The record of a closed child keeps whether it is empty
	 */
	public function testAFrameKnowsWhatItsElementHolds()
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<div id="a"><p>x</p><p></p><h2>y</h2></div><p>after</p>');

		$div = $this->frameAt($mpdf, 'x', 1);
		$this->assertSame('.1', $div['key']);
		$this->assertSame(3, $div['childTotal']);
		$this->assertSame(['P' => 2, 'H2' => 1], $div['childTypeTotals']);
		$this->assertFalse($div['empty']);

		$p = $this->frameAt($mpdf, 'x', 2);
		$this->assertSame('.1.1', $p['key']);
		$this->assertSame(0, $p['childTotal']);
		$this->assertSame([], $p['childTypeTotals']);
		$this->assertFalse($p['empty']);

		$this->assertSame([false, true], array_column($this->frameAt($mpdf, 'y', 1)['children'], 'empty'));
		$this->assertSame(2, $this->frameAt($mpdf, 'after', 0)['childTotal']);
	}

	/**
	 * Rows and cells are counted in the tbody the stack puts a row written straight into a table in, with their end
	 * tags implied
	 */
	public function testWhatARowGroupHoldsIsCountedWithItsImpliedTags()
	{
		$mpdf = $this->write('<table><tr><td>a<td>b<tr><td>c</table>');

		$this->assertSame(1, $this->frameAt($mpdf, 'a', 1)['childTotal']);
		$this->assertSame(2, $this->frameAt($mpdf, 'a', 2)['childTotal']);
		$this->assertSame(2, $this->frameAt($mpdf, 'a', 3)['childTotal']);
		$this->assertSame(1, $this->frameAt($mpdf, 'c', 3)['childTotal']);
	}

	/**
	 * An element the call leaves open is not known in full: its totals are null, and it is known not to be empty
	 * only once something is in it. A later call that closes it knows it, and tells its frame
	 */
	public function testWhatAnElementHoldsIsKnownOnceItsEndIsRead()
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<div class="a"><p>one</p><p>', HTMLParserMode::DEFAULT_MODE, true, false);

		$open = $mpdf->getOpenElements();
		$this->assertNull($open[0]['childTotal']);
		$this->assertNull($open[1]['childTotal']);
		$this->assertNull($open[1]['childTypeTotals']);
		$this->assertFalse($open[1]['empty'], 'it holds a paragraph');
		$this->assertNull($open[2]['empty'], 'nothing is in it yet');
		$this->assertSame(0, $this->frameAt($mpdf, 'one', 2)['childTotal']);

		$mpdf->WriteHTML('two</p><p>three</p></div>', HTMLParserMode::DEFAULT_MODE, false, true);

		$this->assertSame(3, $this->frameAt($mpdf, 'three', 1)['childTotal']);
		$this->assertSame(1, $this->frameAt($mpdf, 'three', 0)['childTotal']);
		$this->assertFalse($this->frameAt($mpdf, 'two', 2)['empty']);
	}

	/**
	 * A kept block laid out again, a header written as a document of its own, and a positioned block's content
	 * written after the page all see what was read ahead of their elements
	 */
	public function testWhatWasReadAheadHoldsInEveryContext()
	{
		$mpdf = $this->write($this->filler(22) . '<div class="kept" style="page-break-inside: avoid">'
			. str_repeat('<p>Kept</p>', 11) . '<p>last kept</p></div><p>after</p>');
		$this->assertSame(1, $mpdf->unwinds);
		$this->assertSame(12, $this->frameAt($mpdf, 'last kept', 1)['childTotal']);

		$mpdf = $this->mpdfRecording();
		$mpdf->SetHTMLHeader('<div class="head"><span>header</span><b>bold</b></div>');
		$mpdf->WriteHTML('<p>body</p>');
		$this->output($mpdf);
		$this->assertSame(2, $this->frameAt($mpdf, 'header', 1)['childTotal']);
		$this->assertSame(1, $this->frameAt($mpdf, 'header', 0)['childTotal']);

		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<span class="around"><div id="box" style="position: absolute; top: 100mm; left: 20mm; width: 100mm;">'
			. '<p>first</p><div><p>nested</p></div></div><em>beside</em></span>');
		$this->assertSame(2, $this->frameAt($mpdf, 'nested', 1)['childTotal']);
		$this->assertSame(2, $this->frameAt($mpdf, 'nested', 2)['childTotal']);
		$this->assertSame(1, $this->frameAt($mpdf, 'nested', 3)['childTotal']);
	}

	/**
	 * Closing the document closes every element still open, into the record of the document's children
	 */
	public function testClosingTheDocumentClosesEveryElement()
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML('<div><p>open</div><section><p>still open');

		$this->assertCount(1, $mpdf->getOpenElements());
		$this->assertSame(['DIV', 'SECTION'], $this->childTags($mpdf->getOpenElements()[0]));
	}

	/**
	 * @param array $config
	 *
	 * @return OpenElementsRecordingMpdf
	 */
	private function mpdfRecording(array $config = [])
	{
		return new OpenElementsRecordingMpdf($config + ['mode' => 'c']);
	}

	/**
	 * Writes the HTML, leaving open whatever it leaves open
	 *
	 * @param string $html
	 *
	 * @return OpenElementsRecordingMpdf
	 */
	private function write($html)
	{
		$mpdf = $this->mpdfRecording();
		$mpdf->WriteHTML($html, HTMLParserMode::DEFAULT_MODE, true, false);

		return $mpdf;
	}

	/**
	 * @param OpenElementsRecordingMpdf $mpdf
	 * @param string $text
	 *
	 * @return array[] The open elements when the text was last read
	 */
	private function framesAt(OpenElementsRecordingMpdf $mpdf, $text)
	{
		$found = null;
		foreach ($mpdf->openElementsAtText as $read) {
			if ($read[0] === $text) {
				$found = $read[1];
			}
		}
		$this->assertNotNull($found, 'No text "' . $text . '" was read');

		return $found;
	}

	/**
	 * @param OpenElementsRecordingMpdf $mpdf
	 * @param string $text
	 * @param int $depth 0 for the document's frame, 1 for the outermost element
	 *
	 * @return array
	 */
	private function frameAt(OpenElementsRecordingMpdf $mpdf, $text, $depth)
	{
		return $this->framesAt($mpdf, $text)[$depth];
	}

	/**
	 * @param OpenElementsRecordingMpdf $mpdf
	 * @param string $text
	 *
	 * @return string The path of open elements when the text was last read
	 */
	private function pathAt(OpenElementsRecordingMpdf $mpdf, $text)
	{
		return $this->path($this->framesAt($mpdf, $text));
	}

	/**
	 * @param array[] $frames
	 *
	 * @return string The elements, outermost first, leaving out the document's frame
	 */
	private function path(array $frames)
	{
		$path = [];
		foreach (array_slice($frames, 1) as $frame) {
			$path[] = $frame['tag']
				. ($frame['id'] !== '' ? '#' . $frame['id'] : '')
				. ($frame['classes'] ? '.' . implode('.', $frame['classes']) : '')
				. ':' . $frame['nthChild'] . '/' . $frame['nthOfType'];
		}

		return implode(' > ', $path);
	}

	/**
	 * @param array $frame
	 *
	 * @return string[] The tags of the children the frame has closed so far
	 */
	private function childTags(array $frame)
	{
		return array_column($frame['children'], 'tag');
	}
}
