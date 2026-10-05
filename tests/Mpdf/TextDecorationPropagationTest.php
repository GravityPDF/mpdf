<?php

namespace Mpdf;

use Mpdf\Css\TextDecorations;
use Mpdf\Css\TextVars;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Under standard an element's underline, line-through and overline propagate to its in-flow descendants: they are drawn
 * over the descendants' text whatever the descendants set, in the colour and at the size of the element that set them.
 * text-decoration: none on a descendant removes only its own decoration. Floats, positioned blocks, inline blocks and
 * tables take none of the decorations they are in, and a child block takes no vertical-align. Under legacy
 * text-decoration and vertical-align are inherited as in mPDF v7.
 */
class TextDecorationPropagationTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 RG';

	const BLUE = '0.000 0.000 1.000 RG';

	const BLACK = '0.000 G';

	/**
	 * The font size, in millimetres, of the default 11pt and of the descendant's 16pt
	 */
	const ANCESTOR_SIZE = 3.88;

	const DESCENDANT_SIZE = 5.64;

	/**
	 * Documents with {A} for the ancestor's declarations and {D} for those of the descendant, whose text is qq. Each
	 * names the channel legacy carried the decoration through: the block's text state read back as CSS for its child
	 * block, which loses the overline and takes the child's colour and size, or the text state an inline element or
	 * the content of a cell carries on
	 */
	const CONTEXTS = [
		'block to child block' => ['block', '<div style="{A}"><p style="{D}">qq</p></div>'],
		'block to inline element' => ['inline', '<div style="{A}">zz <span style="{D}">qq</span></div>'],
		'inline element to inline element' => ['inline', '<p>zz <span style="{A}">yy <b style="{D}">qq</b></span></p>'],
		'list to item' => ['block', '<ul style="{A}"><li style="{D}">qq</li></ul>'],
		'list item to child block' => ['block', '<ul><li style="{A}"><div style="{D}">qq</div></li></ul>'],
		'body to block' => ['block', '<body style="{A}"><p style="{D}">qq</p></body>'],
		'header block to child block' => ['block', '<htmlpageheader name="h"><div style="{A}"><p style="{D}">qq</p></div></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>'],
		'footer block to child block' => ['block', '<htmlpagefooter name="f"><div style="{A}"><p style="{D}">qq</p></div></htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>'],
		'kept block to child block' => ['block', '{FILLER}<div style="page-break-inside: avoid; {A}"><p>zz</p><p style="{D}">qq</p></div>'],
		'block to child block after a forced page break' => ['block', '<div style="{A}"><p>zz</p><pagebreak /><p style="{D}">qq</p></div>'],
		'positioned block to child block' => ['block', '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; {A}"><p style="{D}">qq</p></div>'],
		'cell to child block' => ['inline', '<table><tr><td style="{A}"><div style="{D}">qq</div></td></tr></table>'],
		'cell to inline element' => ['inline', '<table><tr><td style="{A}">zz <span style="{D}">qq</span></td></tr></table>'],
	];

	/**
	 * Each decoration the ancestor sets reaches the descendant's text. Under standard it is drawn as the ancestor draws
	 * it; under legacy a child block draws it in its own colour and size, and loses an overline
	 *
	 * @dataProvider decorationsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $decoration
	 */
	public function testTheAncestorsDecorationIsDrawnOverTheDescendant($mode, $context, $decoration)
	{
		$drawn = $this->decorationsOfQq($mode, $context, 'color: #f00; text-decoration: ' . $decoration, 'color: #00f; font-size: 16pt');

		if ($mode === CssMode::STANDARD || self::CONTEXTS[$context][0] === 'inline') {
			$expected = [$decoration => [self::RED, self::ANCESTOR_SIZE]];
		} else {
			$expected = $decoration === 'overline' ? [] : [$decoration => [self::BLUE, self::DESCENDANT_SIZE]];
		}

		$this->assertSame($expected, $drawn);
	}

	/**
	 * text-decoration: none on the descendant leaves the ancestor's decoration drawn over it under standard, and
	 * removes it under legacy
	 *
	 * @dataProvider decorationsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $decoration
	 */
	public function testNoneOnTheDescendantRemovesOnlyItsOwn($mode, $context, $decoration)
	{
		$drawn = $this->decorationsOfQq($mode, $context, 'color: #f00; text-decoration: ' . $decoration, 'text-decoration: none');

		$this->assertSame($mode === CssMode::STANDARD ? [$decoration => [self::RED, self::ANCESTOR_SIZE]] : [], $drawn);
	}

	/**
	 * The descendant's own decoration is drawn with the ancestor's under standard, each in the colour of the element
	 * that set it. Under legacy a child block's own decoration replaces the one it inherits
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testTwoDecorationsStack($mode, $context)
	{
		$drawn = $this->decorationsOfQq($mode, $context, 'color: #f00; text-decoration: underline', 'color: #00f; text-decoration: line-through');

		$expected = [
			'underline' => [self::RED, self::ANCESTOR_SIZE],
			'line-through' => [self::BLUE, self::ANCESTOR_SIZE],
		];
		if ($mode === CssMode::LEGACY && self::CONTEXTS[$context][0] === 'block') {
			unset($expected['underline']);
		}

		$this->assertSame($expected, $drawn);
	}

	/**
	 * Where the descendant sets the same decoration as its ancestor, its own is drawn: mPDF draws one line of each kind
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testTheDescendantsOwnDecorationOfTheSameKindIsDrawn($mode, $context)
	{
		$drawn = $this->decorationsOfQq($mode, $context, 'color: #f00; text-decoration: underline', 'color: #00f; text-decoration: underline');

		$this->assertSame(['underline' => [self::BLUE, self::ANCESTOR_SIZE]], $drawn);
	}

	/**
	 * A descendant with no decoration anywhere around it is drawn with none
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testNoDecorationIsDrawnWhereNoneIsSet($mode, $context)
	{
		$this->assertSame([], $this->decorationsOfQq($mode, $context, 'color: #f00', 'color: #00f'));
	}

	/**
	 * Every decoration in every context, under both modes
	 *
	 * @return array[]
	 */
	public function decorationsInContexts()
	{
		$data = [];
		foreach ($this->contextsInModes() as $name => $case) {
			foreach (['underline', 'line-through', 'overline'] as $decoration) {
				$data[$name . ': ' . $decoration] = [$case[0], $case[1], $decoration];
			}
		}

		return $data;
	}

	/**
	 * Every context, under both modes
	 *
	 * @return array[]
	 */
	public function contextsInModes()
	{
		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach (array_keys(self::CONTEXTS) as $context) {
				$data[$mode . ': ' . $context] = [$mode, $context];
			}
		}

		return $data;
	}

	/**
	 * The decorations drawn over qq, which is inside the underlined element in each document, but out of its flow or
	 * in a table
	 *
	 * @dataProvider nearMisses
	 *
	 * @param string $html
	 * @param array $standard What is drawn over qq under standard
	 * @param array $legacy What is drawn over qq under legacy
	 */
	public function testDecorationsDoNotPropagateOutOfTheFlow($html, array $standard, array $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$this->assertSame($expected, $this->drawnDecorations($html, $mode)['qq'], $mode);
		}
	}

	/**
	 * Floats, positioned blocks, inline blocks and tables inside an underlined element, each with a case that sets a
	 * decoration of its own and one in the flow beside it
	 *
	 * @return array[]
	 */
	public function nearMisses()
	{
		$underline = ['underline' => [self::RED, self::ANCESTOR_SIZE]];
		$ownUnderline = ['underline' => [self::BLUE, self::ANCESTOR_SIZE]];
		$ancestor = 'color: #f00; text-decoration: underline';

		return [
			'a float' => ['<div style="' . $ancestor . '"><div style="float: left; width: 50mm; color: #00f">qq</div>zz</div>', [], $ownUnderline],
			'a right float' => ['<div style="' . $ancestor . '"><div style="float: right; width: 50mm; color: #00f">qq</div>zz</div>', [], $ownUnderline],
			'a block inside a float' => ['<div style="' . $ancestor . '"><div style="float: left; width: 50mm; color: #00f"><p>qq</p></div>zz</div>', [], $ownUnderline],
			'a float with its own underline' => ['<div style="' . $ancestor . '"><div style="float: left; width: 50mm; color: #00f; text-decoration: underline">qq</div>zz</div>', $ownUnderline, $ownUnderline],
			'a block beside a float' => ['<div style="' . $ancestor . '"><div style="float: left; width: 50mm">zz</div><p style="float: none">qq</p></div>', $underline, $underline],
			'a positioned block inside an underlined body' => ['<body style="' . $ancestor . '"><div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; color: #00f">qq</div></body>', [], []],
			'a positioned block inside an underlined block' => ['<div style="' . $ancestor . '"><div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; color: #00f">qq</div></div>', [], $ownUnderline],
			'a relatively positioned block' => ['<div style="' . $ancestor . '"><div style="position: relative">qq</div></div>', $underline, $underline],
			'an inline block' => ['<p style="' . $ancestor . '">zz <span style="display: inline-block">qq</span></p>', [], $underline],
			'an inline block with its own underline' => ['<p style="' . $ancestor . '">zz <span style="display: inline-block; color: #00f; text-decoration: underline">qq</span></p>', $ownUnderline, $ownUnderline],
			'the text after an inline block' => ['<p style="' . $ancestor . '"><span style="display: inline-block">zz</span> qq</p>', $underline, $underline],
			'a block set to display as an inline block' => ['<div style="' . $ancestor . '"><div style="display: inline-block; color: #00f">qq</div></div>', [], $ownUnderline],
			'a float in a cell' => ['<table><tr><td style="' . $ancestor . '">zz<div style="float: left; width: 30mm">qq</div></td></tr></table>', [], $underline],
			'an inline block in a cell' => ['<table><tr><td style="' . $ancestor . '">zz <span style="display: inline-block">qq</span></td></tr></table>', [], $underline],
			'a table' => ['<div style="' . $ancestor . '"><table><tr><td>qq</td></tr></table></div>', [], []],
			'a table nested in a cell' => ['<table><tr><td style="' . $ancestor . '">zz<table><tr><td>qq</td></tr></table></td></tr></table>', [], []],
			'a cell with its own underline' => ['<div style="' . $ancestor . '"><table><tr><td style="color: #00f; text-decoration: underline">qq</td></tr></table></div>', $ownUnderline, $ownUnderline],
		];
	}

	/**
	 * A link set to no decoration inside an underlined paragraph still has the paragraph's underline drawn under it,
	 * as in a browser. A link on its own has its underline in its own colour
	 */
	public function testALinkWithNoDecorationKeepsTheParagraphsUnderline()
	{
		$html = '<style>a { text-decoration: none; color: #00f }</style>'
			. '<p style="color: #f00; text-decoration: underline">zz <a href="https://example.com/">qq</a></p>'
			. '<p>yy <a href="https://example.com/" style="text-decoration: underline">xx</a></p>';

		$standard = $this->drawnDecorations($html, CssMode::STANDARD);
		$this->assertSame(['underline' => [self::RED, self::ANCESTOR_SIZE]], $standard['qq']);
		$this->assertSame(['underline' => [self::BLUE, self::ANCESTOR_SIZE]], $standard['xx']);

		$legacy = $this->drawnDecorations($html, CssMode::LEGACY);
		$this->assertSame([], $legacy['qq']);
		$this->assertSame(['underline' => [self::BLUE, self::ANCESTOR_SIZE]], $legacy['xx']);
	}

	/**
	 * A block inside an underlined inline element takes its underline under standard, as it takes the element's other
	 * text properties (#541), and the text after the block is underlined again. Under legacy the block starts from an
	 * empty text state, so neither is underlined
	 */
	public function testABlockInsideAnInlineElementTakesItsDecorations()
	{
		$html = '<div><span style="color: #f00; text-decoration: underline">zz<div style="color: #00f">qq</div>yy</span> xx</div>';

		$standard = $this->drawnDecorations($html, CssMode::STANDARD);
		$this->assertSame(['underline' => [self::RED, self::ANCESTOR_SIZE]], $standard['qq']);
		$this->assertSame(['underline' => [self::RED, self::ANCESTOR_SIZE]], $standard['yy']);
		$this->assertSame([], $standard['xx']);

		$legacy = $this->drawnDecorations($html, CssMode::LEGACY);
		$this->assertSame([], $legacy['qq']);
		$this->assertSame([], $legacy['yy']);
	}

	/**
	 * Under standard an element whose colour is transparent draws its own decoration unseen, so none is added for it,
	 * and the decorations of the elements it is in are drawn over its text in their colour. Under legacy the element's
	 * own decoration is drawn in the colour its text had before
	 *
	 * @dataProvider transparentElements
	 *
	 * @param string $html
	 * @param array $standard What is drawn over qq under standard
	 * @param array $legacy What is drawn over qq under legacy
	 */
	public function testATransparentElementsOwnDecorationIsNotSeen($html, array $standard, array $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$this->assertSame($expected, $this->drawnDecorations($html, $mode)['qq'], $mode);
		}
	}

	/**
	 * An inline element and a block, each transparent, setting a decoration of their own or none
	 *
	 * @return array[]
	 */
	public function transparentElements()
	{
		$struck = ['line-through' => [self::RED, self::ANCESTOR_SIZE]];
		$underlined = ['underline' => [self::RED, self::ANCESTOR_SIZE]];
		// Legacy reads transparent as no colour, and the block keeps the black it was reset to
		$blackUnderline = ['underline' => [self::BLACK, self::ANCESTOR_SIZE]];

		return [
			'an inline element with its own underline' => [
				'<p style="color: #f00; text-decoration: line-through">zz <span style="color: transparent; text-decoration: underline">qq</span></p>',
				$struck,
				$underlined + $struck,
			],
			'an inline element under an underline' => [
				'<p style="color: #f00; text-decoration: underline">zz <span style="color: transparent">qq</span></p>',
				$underlined,
				$underlined,
			],
			'a block with its own underline' => [
				'<div style="color: #f00; text-decoration: line-through"><p style="color: transparent; text-decoration: underline">qq</p></div>',
				$struck,
				$blackUnderline,
			],
			'a block under an underline' => [
				'<div style="color: #f00; text-decoration: underline"><p style="color: transparent">qq</p></div>',
				$underlined,
				$blackUnderline,
			],
			'an inline element inside a transparent one' => [
				'<p style="color: #f00; text-decoration: line-through"><span style="color: transparent">zz <b style="text-decoration: underline">qq</b></span></p>',
				$struck,
				$underlined + $struck,
			],
			'a block inside a transparent one' => [
				'<div style="color: #f00; text-decoration: line-through"><div style="color: transparent"><p style="text-decoration: underline">qq</p></div></div>',
				$struck,
				$blackUnderline,
			],
		];
	}

	/**
	 * The decoration's thickness and position come from the font of the element that set it, and the text after a
	 * descendant set to none is still decorated
	 */
	public function testThePropagatedDecorationKeepsTheAncestorsFont()
	{
		$html = '<div style="font-size: 20pt; text-decoration: underline"><p style="font-size: 10pt; text-decoration: none">qq</p><p>zz <span style="font-family: courier">yy</span></p></div>';

		$standard = $this->drawnDecorations($html, CssMode::STANDARD);
		$this->assertSame(['underline' => [self::BLACK, 7.06]], $standard['qq']);
		$this->assertSame(['underline' => [self::BLACK, 7.06]], $standard['yy']);

		$legacy = $this->drawnDecorations($html, CssMode::LEGACY);
		$this->assertSame([], $legacy['qq']);
		$this->assertSame(['underline' => [self::BLACK, 7.06]], $legacy['yy']);
	}

	/**
	 * A block's vertical-align does not reach its child blocks under standard, as vertical-align is not inherited. The
	 * block's own text is still raised, and an inline element inside it still raises its text
	 */
	public function testAChildBlockTakesNoVerticalAlign()
	{
		$html = '<div style="vertical-align: super">zz<p>qq</p><p>yy <sup>xx</sup></p></div>';

		foreach ([CssMode::STANDARD => 0, CssMode::LEGACY => TextVars::FA_SUPERSCRIPT] as $mode => $raised) {
			$textVars = $this->keyedByText($mpdf = $this->drawDocument($html, ['cssMode' => $mode]), $mpdf->drawnTextVars);

			$this->assertSame(TextVars::FA_SUPERSCRIPT, $textVars['zz'] & TextVars::FA_SUPERSCRIPT, $mode);
			$this->assertSame($raised, $textVars['qq'] & TextVars::FA_SUPERSCRIPT, $mode);
			$this->assertSame(TextVars::FA_SUPERSCRIPT, $textVars['xx'] & TextVars::FA_SUPERSCRIPT, $mode);
		}
	}

	/**
	 * enter() replaces the decorations of the text state with those that are on in the parent's, and leaves the rest of
	 * the state alone. An element out of the flow starts with none
	 */
	public function testEnterStartsFromTheParentsDecorations()
	{
		$underline = ['color' => self::RED, 'fontkey' => 'ptsans', 'fontsize' => 3.88, 'baseline' => 0];
		$overline = ['color' => self::BLUE, 'fontkey' => 'ptsans', 'fontsize' => 3.88, 'baseline' => 0];
		$parent = [
			'textvar' => TextVars::FD_UNDERLINE | TextVars::FA_SUPERSCRIPT,
			'textparam' => ['u-decoration' => $underline, 'o-decoration' => $overline, 'text-baseline' => 1],
		];

		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->textvar = TextVars::FD_LINETHROUGH | TextVars::FC_SMALLCAPS;
		$mpdf->textparam = ['s-decoration' => $overline, 'hyphens' => 1];

		TextDecorations::enter($mpdf, [], $parent);

		$this->assertSame(TextVars::FD_UNDERLINE | TextVars::FC_SMALLCAPS, $mpdf->textvar);
		$this->assertSame(['hyphens' => 1, 'u-decoration' => $underline], $mpdf->textparam);

		TextDecorations::enter($mpdf, ['FLOAT' => 'Left'], $parent);

		$this->assertSame(TextVars::FC_SMALLCAPS, $mpdf->textvar);
		$this->assertSame(['hyphens' => 1], $mpdf->textparam);
	}

	/**
	 * With no parent state given, the current one is the parent's: an element in the flow keeps its decorations and one
	 * out of it starts with none
	 */
	public function testEnterWithoutAParentStateStartsFromTheCurrentOne()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->textvar = TextVars::FD_LINETHROUGH | TextVars::FC_SMALLCAPS;
		$mpdf->textparam = ['s-decoration' => ['color' => self::RED], 'hyphens' => 1];

		TextDecorations::enter($mpdf, ['DISPLAY' => 'inline']);

		$this->assertSame(TextVars::FD_LINETHROUGH | TextVars::FC_SMALLCAPS, $mpdf->textvar);
		$this->assertSame(['s-decoration' => ['color' => self::RED], 'hyphens' => 1], $mpdf->textparam);

		TextDecorations::enter($mpdf, ['DISPLAY' => 'inline-block']);

		$this->assertSame(TextVars::FC_SMALLCAPS, $mpdf->textvar);
		$this->assertSame(['hyphens' => 1], $mpdf->textparam);
	}

	/**
	 * Under legacy enter() leaves the text state as it is
	 */
	public function testEnterLeavesTheStateAloneUnderLegacy()
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => CssMode::LEGACY]);
		$mpdf->textvar = TextVars::FD_LINETHROUGH;
		$mpdf->textparam = ['s-decoration' => ['color' => self::RED]];

		TextDecorations::enter($mpdf, ['FLOAT' => 'left'], ['textvar' => 0, 'textparam' => []]);

		$this->assertSame(TextVars::FD_LINETHROUGH, $mpdf->textvar);
		$this->assertSame(['s-decoration' => ['color' => self::RED]], $mpdf->textparam);
	}

	/**
	 * The decorations drawn over qq in a context
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $ancestor The ancestor's declarations
	 * @param string $descendant The descendant's declarations
	 *
	 * @return array[]
	 */
	private function decorationsOfQq($mode, $context, $ancestor, $descendant)
	{
		$html = strtr(self::CONTEXTS[$context][1], [
			'{A}' => $ancestor,
			'{D}' => $descendant,
			// Too little of the first page is left for the kept block, which is laid out again on the second
			'{FILLER}' => str_repeat('<p>filler</p>', 44),
		]);

		$decorations = $this->drawnDecorations($html, $mode);
		$this->assertArrayHasKey('qq', $decorations);

		return $decorations['qq'];
	}

	/**
	 * The decorations drawn over each piece of text in a document, in core-font mode
	 *
	 * @param string $html
	 * @param string $mode
	 *
	 * @return array[] For each piece of text, trimmed, each decoration drawn over it: [colour operator, font size in
	 *                 millimetres to two places]
	 */
	private function drawnDecorations($html, $mode)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$mpdf->Close();

		return $this->keyedByText($mpdf, array_map(function (array $decorations) {
			return array_map(function (array $decoration) {
				return [$decoration['color'], round($decoration['fontsize'], 2)];
			}, $decorations);
		}, $mpdf->drawnDecorations));
	}
}
