<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The HTML presentational attributes under each value of cssMode. The standard cascade reads each only on the
 * elements HTML gives it to, as an author rule of no specificity: over the built-in defaults and under any stylesheet
 * rule. The legacy cascade reads them on any element, under the built-in defaults, and some tag handlers read them
 * again over the stylesheet.
 */
class PresentationalAttributeTest extends TestCase
{

	use DrawnStyles;
	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	const GREY_STROKE = '0.533 0.533 0.533 RG';
	const RED_STROKE = '1.000 0.000 0.000 RG';
	const GREEN_STROKE = '0.000 0.502 0.000 RG';
	const BLUE_STROKE = '0.000 0.000 1.000 RG';

	/**
	 * The page's content area runs from 15mm to 195mm in core-font mode
	 */
	const LEFT = 15;
	const RIGHT = 195;

	/**
	 * Each piece of text is drawn in the colour the attributes and rules leave it in
	 *
	 * @dataProvider textColours
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param array<string, string> $expected
	 */
	public function testTheColourOfText($cssMode, $html, array $expected)
	{
		$this->assertDrawnInColours($expected, $this->drawnColours($html, ['cssMode' => $cssMode]));
	}

	/**
	 * `color` on the elements HTML gives it to and on those it does not
	 *
	 * @return array[]
	 */
	public function textColours()
	{
		return $this->inBothModes([
			'font color beats the colour it inherits' => [
				'<p style="color: #00f"><font color="#008000">font</font></p>',
				['font' => self::GREEN],
				['font' => self::GREEN],
			],
			'a rule beats font color' => [
				'<style>font { color: #00f; }</style><p><font color="#008000">font</font></p>',
				['font' => self::BLUE],
				['font' => self::BLUE],
			],
			'font color inside a link' => [
				'<p><a href="#x"><font color="#008000">font</font></a></p>',
				['font' => self::GREEN],
				['font' => self::GREEN],
			],
			'a link keeps its colour over its color attribute' => [
				'<p><a href="#x" color="#f00">link</a></p>',
				['link' => self::BLUE],
				['link' => self::BLUE],
			],
			'a span has no color attribute' => [
				'<p><span color="#f00">span</span></p>',
				['span' => self::RED],
				['span' => self::BLACK],
			],
			'a paragraph has no color attribute' => [
				'<p color="#f00">para</p>',
				['para' => self::RED],
				['para' => self::BLACK],
			],
			'a div passes on no color attribute' => [
				'<div color="#f00"><p>inside</p></div>',
				['inside' => self::RED],
				['inside' => self::BLACK],
			],
			'a table cell has no color attribute' => [
				'<table><tr><td color="#f00">cell</td></tr></table>',
				['cell' => self::RED],
				['cell' => self::BLACK],
			],
			'a heading has no color attribute' => [
				'<h3 color="#f00">heading</h3>',
				['heading' => self::RED],
				['heading' => self::BLACK],
			],
		]);
	}

	/**
	 * A horizontal rule is drawn where, as long, in the colour and as thick as its attributes and rules say
	 *
	 * @dataProvider horizontalRules
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param array $expected The rule's left and right ends in millimetres, its stroke colour and its width
	 */
	public function testAHorizontalRule($cssMode, $html, array $expected)
	{
		$mpdf = $this->document($html, $cssMode);

		$this->assertCount(1, $mpdf->drawnLines);
		$line = $mpdf->drawnLines[0];
		$this->assertEqualsWithDelta($expected[0], $line['ends'][0], 0.2, 'Where the rule starts');
		$this->assertEqualsWithDelta($expected[1], $line['ends'][2], 0.2, 'Where the rule ends');
		$this->assertSame($expected[2], $line['colour']);
		$this->assertEqualsWithDelta($expected[3], $line['width'], 0.01, 'How thick the rule is');
	}

	/**
	 * `color`, `width`, `align` and `size` on an hr, and a rule for each
	 *
	 * @return array[]
	 */
	public function horizontalRules()
	{
		$thin = 0.2;
		$tenPixels = 10 * 25.4 / 96;

		return $this->inBothModes([
			'color' => [
				'<hr color="#f00" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[self::LEFT, self::RIGHT, self::RED_STROKE, $thin],
			],
			'a rule beats color' => [
				'<style>hr { color: #00f; }</style><hr color="#f00" />',
				[self::LEFT, self::RIGHT, self::BLUE_STROKE, $thin],
				[self::LEFT, self::RIGHT, self::BLUE_STROKE, $thin],
			],
			'width' => [
				'<hr width="50%" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[60, 150, self::GREY_STROKE, $thin],
			],
			'a rule beats width' => [
				'<style>hr { width: 80%; }</style><hr width="50%" />',
				[33, 177, self::GREY_STROKE, $thin],
				[33, 177, self::GREY_STROKE, $thin],
			],
			'align left' => [
				'<hr width="50%" align="left" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[self::LEFT, 105, self::GREY_STROKE, $thin],
			],
			'align right' => [
				'<hr width="50%" align="right" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[105, self::RIGHT, self::GREY_STROKE, $thin],
			],
			'a rule beats align' => [
				'<style>hr { margin-left: auto; margin-right: auto; }</style><hr width="50%" align="left" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[60, 150, self::GREY_STROKE, $thin],
			],
			'size' => [
				'<hr size="10" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $thin],
				[self::LEFT, self::RIGHT, self::GREY_STROKE, $tenPixels],
			],
			'a rule beats size' => [
				'<style>hr { height: 1mm; }</style><hr size="10" />',
				[self::LEFT, self::RIGHT, self::GREY_STROKE, 1],
				[self::LEFT, self::RIGHT, self::GREY_STROKE, 1],
			],
			'width in a table cell' => [
				'<table width="100%"><tr><td><hr width="50%" /></td></tr></table>',
				[60.5, 149.5, self::GREY_STROKE, $thin],
				[60.5, 149.5, self::GREY_STROKE, $thin],
			],
			'a rule beats width in a table cell' => [
				'<style>hr { width: 80%; }</style><table width="100%"><tr><td><hr width="50%" /></td></tr></table>',
				[60.5, 149.5, self::GREY_STROKE, $thin],
				[33.7, 176.3, self::GREY_STROKE, $thin],
			],
		]);
	}

	/**
	 * Each piece of text is aligned as the attributes and rules say
	 *
	 * @dataProvider textAlignments
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param array<string, string> $expected Each piece of text and its alignment across the page: left, center or
	 *                                        right
	 */
	public function testTheAlignmentOfText($cssMode, $html, array $expected)
	{
		$boxes = $this->keyedByText($mpdf = $this->document($html, $cssMode), $mpdf->drawnBoxes);

		foreach ($expected as $text => $alignment) {
			$this->assertArrayHasKey($text, $boxes, sprintf('"%s" is not drawn', $text));
			$this->assertSame($alignment, $this->alignmentBetween(self::LEFT, self::RIGHT, $boxes[$text]), sprintf('How "%s" is aligned', $text));
		}
	}

	/**
	 * `align` on the blocks and table parts HTML gives it to and on those it does not, and a rule for it
	 *
	 * @return array[]
	 */
	public function textAlignments()
	{
		return $this->inBothModes([
			'a paragraph' => [
				'<p align="right">para</p>',
				['para' => 'right'],
				['para' => 'right'],
			],
			'a rule beats a paragraph\'s align' => [
				'<style>p { text-align: left; }</style><p align="right">para</p>',
				['para' => 'right'],
				['para' => 'left'],
			],
			'a heading' => [
				'<h2 align="center">heading</h2>',
				['heading' => 'center'],
				['heading' => 'center'],
			],
			'a div passes its align on to what is in it' => [
				'<div align="center"><p>inside</p></div>',
				['inside' => 'left'],
				['inside' => 'center'],
			],
			'a rule beats the align a paragraph inherits' => [
				'<style>p { text-align: right; }</style><div align="center"><p>inside</p></div>',
				['inside' => 'right'],
				['inside' => 'right'],
			],
			'middle on a div' => [
				'<div align="middle">div</div>',
				['div' => 'left'],
				['div' => 'center'],
			],
			'an address has no align attribute' => [
				'<address align="right">address</address>',
				['address' => 'right'],
				['address' => 'left'],
			],
			'a section has no align attribute' => [
				'<section align="right">section</section>',
				['section' => 'right'],
				['section' => 'left'],
			],
			'a table cell' => [
				'<table width="100%"><tr><td align="right">cell</td></tr></table>',
				['cell' => 'right'],
				['cell' => 'right'],
			],
			'a rule beats a table cell\'s align' => [
				'<style>td { text-align: left; }</style><table width="100%"><tr><td align="right">cell</td></tr></table>',
				['cell' => 'right'],
				['cell' => 'left'],
			],
			'a header cell\'s align beats its default centring' => [
				'<table width="100%"><tr><th align="left">header</th></tr></table>',
				['header' => 'left'],
				['header' => 'left'],
			],
			'a table head passes its align on to its cells' => [
				'<table width="100%"><thead align="right"><tr><td>head</td></tr></thead><tr><td>body</td></tr></table>',
				['head' => 'left', 'body' => 'left'],
				['head' => 'right', 'body' => 'left'],
			],
		]);
	}

	/**
	 * `nowrap` keeps a cell's text on one line, under any rule that wraps it
	 *
	 * @dataProvider unwrappedCells
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param int $expected How many lines the cell's text is drawn on
	 */
	public function testNowrapOnATableCell($cssMode, $html, $expected)
	{
		$this->assertCount($expected, $this->document($html, $cssMode)->drawnText);
	}

	/**
	 * A narrow cell with and without a rule against its nowrap
	 *
	 * @return array[]
	 */
	public function unwrappedCells()
	{
		$table = '<table width="30mm"><tr><td nowrap="nowrap">aaaa bbbb cccc dddd eeee</td></tr></table>';

		return $this->inBothModes([
			'nowrap' => [$table, 1, 1],
			'a rule beats nowrap' => ['<style>td { white-space: normal; }</style>' . $table, 1, 2],
		]);
	}

	/**
	 * `valign` places a cell's text, and a rule beats it, under both cascades
	 *
	 * @dataProvider verticallyAlignedCells
	 *
	 * @param string $cssMode
	 * @param string $css
	 * @param bool $lower Whether the cell's text is drawn below that of the cell beside it, which is aligned to the top
	 */
	public function testValignOnATableCell($cssMode, $css, $lower)
	{
		$html = '<style>' . $css . '</style><table><tr><td height="30mm" valign="bottom">bottom</td><td style="vertical-align: top">top</td></tr></table>';
		$y = $this->keyedByText($mpdf = $this->document($html, $cssMode), $mpdf->drawnY);

		$this->assertSame($lower, $y['bottom'] > $y['top'] + 10);
	}

	/**
	 * A cell aligned to the bottom by its attribute, with and without a rule
	 *
	 * @return array[]
	 */
	public function verticallyAlignedCells()
	{
		return $this->inBothModes([
			'valign' => ['', true, true],
			'a rule beats valign' => ['td { vertical-align: top; }', false, false],
		]);
	}

	/**
	 * `type` sets the marker of a list and of a list item, which beats what the item inherits from its list under the
	 * standard cascade, and a rule beats it
	 *
	 * @dataProvider listTypes
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param string[] $expected The type of each marker, in order
	 */
	public function testTypeOnAListAndAListItem($cssMode, $html, array $expected)
	{
		$mpdf = new ListMarkerRecordingMpdf(['mode' => 'c', 'cssMode' => $cssMode]);
		$mpdf->WriteHTML($html);

		$this->assertSame($expected, $mpdf->markerTypes);
	}

	/**
	 * Lists and items with a type, with and without a rule
	 *
	 * @return array[]
	 */
	public function listTypes()
	{
		return $this->inBothModes([
			'on an ordered list' => ['<ol type="a"><li>x</li></ol>', ['lower-latin'], ['lower-latin']],
			'a rule beats it on an ordered list' => ['<style>ol { list-style-type: decimal; }</style><ol type="a"><li>x</li></ol>', ['decimal'], ['decimal']],
			'on a list' => ['<ul type="square"><li>x</li></ul>', ['square'], ['square']],
			'on a list item' => ['<ul><li type="square">x</li><li>y</li></ul>', ['disc', 'disc'], ['square', 'disc']],
			'a rule beats it on a list item' => ['<style>li { list-style-type: circle; }</style><ul><li type="square">x</li></ul>', ['circle'], ['circle']],
		]);
	}

	/**
	 * `bgcolor` on a table, a row and a cell fills it, under any rule for its background, under both cascades
	 *
	 * @dataProvider backgrounds
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param string[] $fills The colour filled, and a colour not filled
	 */
	public function testBgcolor($cssMode, $html, array $fills)
	{
		list($expected, $unexpected) = $fills;
		$page = $this->pages($this->render($html, ['cssMode' => $cssMode]))[0];

		$this->assertMatchesRegularExpression('/' . $expected . '\s+[-\d. ]+ re f/', $page);
		$this->assertStringNotContainsString($unexpected, $page);
	}

	/**
	 * bgcolor on each part of a table, with and without a rule for its background
	 *
	 * @return array[]
	 */
	public function backgrounds()
	{
		$cases = [];
		foreach (['table' => '<table%s><tr><td>x</td></tr></table>', 'tr' => '<table><tr%s><td>x</td></tr></table>', 'td' => '<table><tr><td%s>x</td></tr></table>'] as $tag => $html) {
			$withAttribute = sprintf($html, ' bgcolor="#008000"');
			$cases['on a ' . $tag] = [$withAttribute, [self::GREEN, self::BLUE], [self::GREEN, self::BLUE]];
			$cases['a rule beats it on a ' . $tag] = ['<style>' . $tag . ' { background-color: #00f; }</style>' . $withAttribute, [self::BLUE, self::GREEN], [self::BLUE, self::GREEN]];
		}

		return $this->inBothModes($cases);
	}

	/**
	 * A table's `cellpadding` pads its cells, under any rule for their padding, under both cascades
	 *
	 * @dataProvider paddedCells
	 *
	 * @param string $cssMode
	 * @param string $css
	 * @param float $expected How far in from the page's left edge the cell's text starts, in millimetres
	 */
	public function testCellpadding($cssMode, $css, $expected)
	{
		$html = '<style>' . $css . '</style><table cellpadding="20" style="border-spacing: 0"><tr><td>padded</td></tr></table>';
		$boxes = $this->keyedByText($mpdf = $this->document($html, $cssMode), $mpdf->drawnBoxes);

		$this->assertEqualsWithDelta(self::LEFT + $expected, $boxes['padded'][1], 0.1);
	}

	/**
	 * A padded table, with and without a rule
	 *
	 * @return array[]
	 */
	public function paddedCells()
	{
		return $this->inBothModes([
			'cellpadding' => ['', 20 * 25.4 / 96, 20 * 25.4 / 96],
			'a rule beats cellpadding' => ['td { padding: 0; }', 0, 0],
		]);
	}

	/**
	 * A table's `border` draws a border around it, which a rule for the table's border beats under the standard
	 * cascade, and one around each of its cells, which a rule for the table's border does not reach
	 *
	 * @dataProvider tableBorders
	 *
	 * @param string $cssMode
	 * @param string $html
	 * @param array $expected The widths of the lines drawn, in millimetres, keyed by the number of lines drawn that wide
	 */
	public function testTheBorderOfATable($cssMode, $html, array $expected)
	{
		$this->assertEquals($expected, $this->lineWidths($this->document($html, $cssMode)));
	}

	/**
	 * Tables of one cell with a border attribute, with and without a rule
	 *
	 * @return array[]
	 */
	public function tableBorders()
	{
		$px = function ($pixels) {
			return (string) round($pixels * 25.4 / 96, 2);
		};
		$cell = '<tr><td>cell</td></tr></table>';

		return $this->inBothModes([
			'border' => ['<table border="1">' . $cell, [$px(1) => 8], [$px(1) => 8]],
			'no border' => ['<table border="0">' . $cell, [], []],
			'a wider border' => ['<table border="3">' . $cell, [], [$px(3) => 4, $px(1) => 4]],
			'a rule beats the table\'s border' => ['<table border="1" style="border: none">' . $cell, [$px(1) => 8], [$px(1) => 4]],
			'a rule beats the table\'s border with its own' => ['<style>table { border: 2px solid #00f; }</style><table border="1">' . $cell, [$px(1) => 8], [$px(2) => 4, $px(1) => 4]],
			'a rule beats the border of the cells' => ['<style>td { border: 2px solid #00f; }</style><table border="1">' . $cell, [$px(1) => 4, $px(2) => 4], [$px(1) => 4, $px(2) => 4]],
			'a wider border wins the outer edges of collapsed cells' => [
				'<style>td { border: 1px solid #00f; }</style><table border="3" style="border-collapse: collapse"><tr><td>a</td><td>b</td></tr></table>',
				[$px(1) => 8],
				[$px(3) => 6, $px(1) => 2],
			],
		]);
	}

	/**
	 * `align="bottom"` puts a caption below its table, under any rule for its side
	 *
	 * @dataProvider captions
	 *
	 * @param string $cssMode
	 * @param string $css
	 * @param bool $below
	 */
	public function testAlignOnACaption($cssMode, $css, $below)
	{
		$html = '<style>' . $css . '</style><table><caption align="bottom">caption</caption><tr><td>cell</td></tr></table>';
		$y = $this->keyedByText($mpdf = $this->document($html, $cssMode), $mpdf->drawnY);

		$this->assertSame($below, $y['caption'] > $y['cell']);
	}

	/**
	 * A caption at the bottom by its attribute, with and without a rule
	 *
	 * @return array[]
	 */
	public function captions()
	{
		return $this->inBothModes([
			'align bottom' => ['', true, true],
			'a rule beats align' => ['caption { caption-side: top; }', true, false],
		]);
	}

	/**
	 * An image is drawn where, as large as and inside the border its attributes and rules say
	 *
	 * @dataProvider images
	 *
	 * @param string $cssMode
	 * @param string $attributes Of the image
	 * @param string $css
	 * @param array $expected Its left edge, width and height in millimetres, and the number of lines drawn around it
	 */
	public function testAnImage($cssMode, $attributes, $css, array $expected)
	{
		$html = '<style>' . $css . '</style><p><img src="' . __DIR__ . '/../data/img/tiger.jpg" ' . $attributes . ' /> text</p>';
		$mpdf = $this->document($html, $cssMode, $pdf);
		$placements = $this->placementsIn($this->pages($pdf)[0]);

		$this->assertCount(1, $placements);
		$this->assertEqualsWithDelta($expected[0], $placements[0]['x'], 0.1, 'Where the image starts');
		$this->assertEqualsWithDelta($expected[1], $placements[0]['w'], 0.1, 'How wide the image is');
		$this->assertEqualsWithDelta($expected[2], $placements[0]['h'], 0.1, 'How tall the image is');
		$this->assertCount($expected[3], $mpdf->drawnLines, 'How many sides of a border are drawn');
	}

	/**
	 * `width`, `height`, `hspace`, `align` and `border` on an image, and a rule for each
	 *
	 * @return array[]
	 */
	public function images()
	{
		$px = function ($pixels) {
			return $pixels * 25.4 / 96;
		};
		$size = 'width="40" height="20"';

		return $this->inBothModes([
			'width and height' => [
				$size,
				'',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT, $px(40), $px(20), 0],
			],
			'a rule beats width and height' => [
				$size,
				'img { width: 20mm; height: 10mm; }',
				[self::LEFT, 20, 10, 0],
				[self::LEFT, 20, 10, 0],
			],
			'hspace' => [
				$size . ' hspace="20"',
				'',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT + $px(20), $px(40), $px(20), 0],
			],
			'a rule beats hspace' => [
				$size . ' hspace="20"',
				'img { margin: 0; }',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT, $px(40), $px(20), 0],
			],
			'align right' => [
				$size . ' align="right"',
				'',
				[self::LEFT, $px(40), $px(20), 0],
				[self::RIGHT - $px(40), $px(40), $px(20), 0],
			],
			'a rule beats align' => [
				$size . ' align="right"',
				'img { float: none; }',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT, $px(40), $px(20), 0],
			],
			'border' => [
				$size . ' border="2"',
				'',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT + $px(2), $px(40), $px(20), 4],
			],
			'no border' => [
				$size . ' border="0"',
				'',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT, $px(40), $px(20), 0],
			],
			'a rule beats border' => [
				$size . ' border="2"',
				'img { border: none; }',
				[self::LEFT, $px(40), $px(20), 0],
				[self::LEFT, $px(40), $px(20), 0],
			],
		]);
	}

	/**
	 * `<img vspace>` beats the default margin of an image under the standard cascade, and a rule beats it, which moves
	 * what follows down or not
	 */
	public function testVspaceOnAnImage()
	{
		$image = '<img src="' . __DIR__ . '/../data/img/tiger.jpg" width="20" vspace="30" /><p>after</p>';
		$y = [];
		foreach (['' => $image, 'ruled' => '<style>img { margin: 0; }</style>' . $image] as $name => $html) {
			foreach ([CssMode::LEGACY, CssMode::STANDARD] as $cssMode) {
				$y[$name][$cssMode] = $this->document($html, $cssMode)->drawnY[0];
			}
		}

		$this->assertEqualsWithDelta(2 * 30 * 25.4 / 96, $y[''][CssMode::STANDARD] - $y[''][CssMode::LEGACY], 0.1);
		$this->assertEqualsWithDelta($y[''][CssMode::LEGACY], $y['ruled'][CssMode::STANDARD], 0.01);
		$this->assertEqualsWithDelta($y[''][CssMode::LEGACY], $y['ruled'][CssMode::LEGACY], 0.01);
	}

	/**
	 * A div has no width attribute under the standard cascade: it takes the width of the page
	 *
	 * @dataProvider divWidths
	 *
	 * @param string $cssMode
	 * @param float $expected The width of its background, in millimetres
	 */
	public function testADivHasNoWidthAttribute($cssMode, $expected)
	{
		$page = $this->pages($this->render('<div width="50mm" style="background-color: #008000">div</div>', ['cssMode' => $cssMode]))[0];

		$this->assertSame(1, preg_match('/' . self::GREEN . '\s+[-\d.]+ [-\d.]+ ([-\d.]+) [-\d.]+ re f/', $page, $fill));
		$this->assertEqualsWithDelta($expected, $fill[1] / Mpdf::SCALE, 0.1);
	}

	/**
	 * Each cascade, and the width of a div with a width attribute in it
	 *
	 * @return array[]
	 */
	public function divWidths()
	{
		return $this->inBothModes(['a div' => [50, self::RIGHT - self::LEFT]]);
	}

	/**
	 * Each cascade
	 *
	 * @return array[]
	 */
	public function cssModes()
	{
		return [CssMode::LEGACY => [CssMode::LEGACY], CssMode::STANDARD => [CssMode::STANDARD]];
	}

	/**
	 * mPDF reads `align` on a text field to align its text, which a rule beats under the standard cascade
	 *
	 * @dataProvider textFields
	 *
	 * @param string $cssMode
	 * @param string $css
	 * @param string $expected The field's quadding: 1 centred, 2 to the right
	 */
	public function testAlignOnATextField($cssMode, $css, $expected)
	{
		$pdf = $this->render('<style>' . $css . '</style><form><input type="text" name="f" align="center" value="v" /></form>', ['cssMode' => $cssMode, 'useActiveForms' => true]);

		$this->assertStringContainsString('/Q ' . $expected, $pdf);
	}

	/**
	 * A centred field, with and without a rule
	 *
	 * @return array[]
	 */
	public function textFields()
	{
		return $this->inBothModes([
			'align' => ['', '1', '1'],
			'a rule beats align' => ['input { text-align: right; }', '1', '2'],
		]);
	}

	/**
	 * mPDF's own tags keep the attributes mPDF reads for them: a barcode is drawn in its color
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testABarcodeKeepsItsColour($cssMode)
	{
		$page = $this->pages($this->render('<barcode code="12345" type="C39" color="#f00" />', ['cssMode' => $cssMode]))[0];

		$this->assertMatchesRegularExpression('/' . self::RED . '\s+[-\d. ]+ re f/', $page);
	}

	/**
	 * The attributes and the rules against them apply the same wherever the element is: a block, a table cell, a
	 * header or footer, a positioned block, a block laid out again because it is kept together, and after a forced
	 * page break
	 *
	 * @dataProvider contexts
	 *
	 * @param string $cssMode
	 * @param string $context
	 * @param array $expected The colour of each piece of text, how each is aligned, the stroke colour of the rule,
	 *                        and what it spans: full, left half or middle half
	 */
	public function testInEveryContext($cssMode, $context, array $expected)
	{
		$html = '<style>.l { text-align: left; }</style>'
			. $this->inContext($context, '<p>a wide paragraph that sets the width of what it is in</p>'
				. '<p style="text-align: right">right</p><p style="text-align: center">centre</p>'
				. '<p><font color="#008000">font</font> <span color="#f00">span</span> <a href="#x" color="#f00">link</a></p>'
				. '<p color="#f00">para</p>'
				. '<p align="right">aligned</p><p class="l" align="right">ruled</p><div align="center"><p>inherited</p></div>'
				. '<hr color="#008000" width="50%" align="left" />');

		$mpdf = $this->document($html, $cssMode);

		$this->assertDrawnInColours($expected[0], $this->keyedByText($mpdf, $mpdf->drawnColours));
		$this->assertDrawnInContext($context, $mpdf, array_keys($expected[0]));

		// The wide paragraph spans a table cell, which is as wide as it, and the text aligned right reaches the edge
		// of anything wider
		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);
		$wide = $boxes['a wide paragraph that sets the width of what it is in'];
		$left = $wide[1];
		$right = max($wide[2], $boxes['right'][2]);
		foreach ($expected[1] as $text => $alignment) {
			$this->assertSame($alignment, $this->alignmentBetween($left, $right, $boxes[$text]), sprintf('How "%s" is aligned', $text));
		}

		$rule = end($mpdf->drawnLines);
		$quarter = ($right - $left) / 4;
		$spans = [
			'full' => [$left, $right],
			'left half' => [$left, $left + 2 * $quarter],
			'middle half' => [$left + $quarter, $right - $quarter],
		];
		$this->assertSame($expected[2], $rule['colour']);
		$this->assertEqualsWithDelta($spans[$expected[3]], [$rule['ends'][0], $rule['ends'][2]], 1, 'Where the rule starts and ends');
	}

	/**
	 * Every context under each cascade
	 *
	 * @return array[]
	 */
	public function contexts()
	{
		$legacy = [
			['font' => self::GREEN, 'span' => self::RED, 'link' => self::BLUE, 'para' => self::RED],
			['aligned' => 'right', 'ruled' => 'right', 'inherited' => 'left'],
			self::GREY_STROKE,
			'full',
		];
		$standard = [
			['font' => self::GREEN, 'span' => self::BLACK, 'link' => self::BLUE, 'para' => self::BLACK],
			['aligned' => 'right', 'ruled' => 'left', 'inherited' => 'center'],
			self::GREEN_STROKE,
			'left half',
		];

		$cases = [];
		foreach (['block', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
			$cases[$context] = [$context, $legacy, $standard];
		}

		// The blocks in a table cell take the cell's alignment, whatever their own, and the legacy cascade reads the
		// width of a rule in a cell from its attribute
		$cases['table cell'][1][1] = [];
		$cases['table cell'][1][3] = 'middle half';
		$cases['table cell'][2][1] = [];

		return $this->inBothModes($cases);
	}

	/**
	 * Each case once under each cascade, with what that cascade should give
	 *
	 * @param array[] $cases Each [..., what the legacy cascade gives, what the standard cascade gives], the values
	 *                       before the last two passed to the test ahead of the one for the cascade
	 *
	 * @return array[]
	 */
	private function inBothModes(array $cases)
	{
		$data = [];
		foreach ($cases as $name => $case) {
			$standard = array_pop($case);
			$legacy = array_pop($case);
			$data['legacy: ' . $name] = array_merge([CssMode::LEGACY], $case, [$legacy]);
			$data['standard: ' . $name] = array_merge([CssMode::STANDARD], $case, [$standard]);
		}

		return $data;
	}

	/**
	 * Writes a document through LineRecordingMpdf and closes it, so its headers and footers are drawn
	 *
	 * @param string $html
	 * @param string $cssMode
	 * @param string|null $pdf Set to the document, uncompressed
	 *
	 * @return LineRecordingMpdf
	 */
	private function document($html, $cssMode, &$pdf = null)
	{
		$mpdf = new LineRecordingMpdf(['mode' => 'c', 'cssMode' => $cssMode]);
		$mpdf->compress = false;
		$mpdf->WriteHTML($html);
		$pdf = $mpdf->OutputBinaryData();

		return $mpdf;
	}

	/**
	 * How a piece of text is aligned between two edges, give or take the padding of a table cell
	 *
	 * @param float $left
	 * @param float $right
	 * @param array $box As TextRecordingMpdf records it: page, left, right, top
	 *
	 * @return string left, center, right, or where it starts if none of those
	 */
	private function alignmentBetween($left, $right, array $box)
	{
		if (abs($box[1] - $left) < 1.5) {
			return 'left';
		}

		if (abs($box[2] - $right) < 1.5) {
			return 'right';
		}

		if (abs(($box[1] + $box[2]) / 2 - ($left + $right) / 2) < 1.5) {
			return 'center';
		}

		return (string) $box[1];
	}

	/**
	 * How many lines of each width a document drew
	 *
	 * @param LineRecordingMpdf $mpdf
	 *
	 * @return array<string, int> The number of lines, keyed by their width in millimetres to two places
	 */
	private function lineWidths(LineRecordingMpdf $mpdf)
	{
		return array_count_values(array_map(function ($line) {
			return (string) round($line['width'], 2);
		}, $mpdf->drawnLines));
	}

}
