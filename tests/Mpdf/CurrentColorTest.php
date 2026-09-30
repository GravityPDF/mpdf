<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * currentColor is the element's own colour wherever a colour property names it, and in standard mode a border or a
 * shadow that names no colour takes it (#540). A colour that converts to nothing draws nothing: a transparent or
 * hidden side of a border, transparent text and a transparent shadow.
 */
class CurrentColorTest extends TestCase
{

	use DrawnStyles;
	use PageStreams;

	/** #0a0, the colour the subjects are given */
	const GREEN = '0.000 0.667 0.000 rg';

	/** #c00 */
	const RED = '0.800 0.000 0.000 rg';

	/** A border's colour in legacy mode when it names none */
	const BLACK = '0.000 0.000 0.000 rg';

	/** A shadow's colour in legacy mode when it names none: #888888 */
	const GREY = '0.533 0.533 0.533 rg';

	/** Where a subject is put: those that pass their colour on differently, and those that are laid out apart */
	const CONTEXTS = [
		'block',
		'inline',
		'table cell',
		'collapsed table cell',
		'nested table cell',
		'list item',
		'header',
		'footer',
		'positioned block',
		'kept block',
		'forced page break',
	];

	/**
	 * A border in currentColor, or in standard mode one that names no colour, is drawn in the colour the element's
	 * text is drawn in, whether the element sets it or inherits it. A border with a colour of its own keeps it
	 *
	 * @dataProvider bordersInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $border
	 * @param bool $inherited
	 * @param string $expected The colour the sides should be drawn in
	 */
	public function testABorderIsDrawnInTheColourItNames($mode, $context, $border, $inherited, $expected)
	{
		$html = $inherited ? $this->subject($context, $border, 'color: #0a0') : $this->subject($context, 'color: #0a0; ' . $border);
		$mpdf = $this->paint($html, $mode);

		$this->assertSame(self::GREEN, $this->keyedByText($mpdf, $mpdf->drawnColours)['subject']);
		$this->assertNotEmpty($mpdf->drawnBorders, 'The border is drawn');
		$this->assertSame([strtoupper($expected)], array_values(array_unique($mpdf->drawnBorders)));
		$this->assertSubjectPlaced($context, $mpdf);
	}

	/**
	 * Each border in each context, with the colour set on the element and inherited, in both modes
	 *
	 * @return array[]
	 */
	public function bordersInContexts()
	{
		$cases = [
			'currentColor' => ['border: 0.4mm solid currentColor', self::GREEN, self::GREEN],
			'currentColor in capitals' => ['border: 0.4mm solid CURRENTCOLOR', self::GREEN, self::GREEN],
			'border-color: currentColor' => ['border: 0.4mm solid #c00; border-color: currentColor', self::GREEN, self::GREEN],
			'no colour' => ['border: 0.4mm solid', self::GREEN, self::BLACK],
			'no colour, from the longhands' => ['border-style: solid; border-width: 0.4mm', self::GREEN, self::BLACK],
			'a colour of its own' => ['border: 0.4mm solid #c00', self::RED, self::RED],
		];

		$data = [];
		foreach ($this->inModesAndContexts(self::CONTEXTS, $cases) as $name => $case) {
			$data[$name . ', colour set'] = [$case[0], $case[1], $case[2], false, $case[3]];
			$data[$name . ', colour inherited'] = [$case[0], $case[1], $case[2], true, $case[3]];
		}

		return $data;
	}

	/**
	 * An image's border in currentColor, or in standard mode with no colour, is drawn in the colour of the text around
	 * the image, which is the image's own
	 *
	 * @dataProvider imageBorders
	 *
	 * @param string $mode
	 * @param string $border
	 * @param string $expected
	 */
	public function testAnImagesBorderIsDrawnInTheColourItNames($mode, $border, $expected)
	{
		$mpdf = $this->paint('<p style="color: #0a0">before ' . $this->image($border) . ' after</p>', $mode);

		$this->assertSame([strtoupper($expected)], array_values(array_unique($mpdf->drawnBorders)));
	}

	/**
	 * An image's borders, in both modes
	 *
	 * @return array[]
	 */
	public function imageBorders()
	{
		return [
			'standard, currentColor' => [CssMode::STANDARD, 'border: 0.4mm solid currentColor', self::GREEN],
			'standard, no colour' => [CssMode::STANDARD, 'border: 0.4mm solid', self::GREEN],
			'standard, a colour of its own' => [CssMode::STANDARD, 'border: 0.4mm solid #c00', self::RED],
			'legacy, currentColor' => [CssMode::LEGACY, 'border: 0.4mm solid currentColor', self::GREEN],
			'legacy, no colour' => [CssMode::LEGACY, 'border: 0.4mm solid', self::BLACK],
			'legacy, a colour of its own' => [CssMode::LEGACY, 'border: 0.4mm solid #c00', self::RED],
		];
	}

	/**
	 * color: currentColor is the colour the element inherits. With none to inherit, the text keeps the default
	 *
	 * @dataProvider colorCurrentColorInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $parent The style of the element the subject inherits from
	 * @param string $expected
	 */
	public function testColorCurrentColorIsTheInheritedColour($mode, $context, $parent, $expected)
	{
		$mpdf = $this->paint($this->subject($context, 'color: currentColor', $parent), $mode);

		$this->assertSame($expected, $this->keyedByText($mpdf, $mpdf->drawnColours)['subject']);
	}

	/**
	 * color: currentColor in each context, under a colour and under none, in both modes
	 *
	 * @return array[]
	 */
	public function colorCurrentColorInContexts()
	{
		return $this->inModesAndContexts(self::CONTEXTS, [
			'under a colour' => ['color: #0a0', self::GREEN, self::GREEN],
			'under none' => ['', '0.000 g', '0.000 g'],
		]);
	}

	/**
	 * A transparent side of a border draws nothing, and the others are drawn, with no warning
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testATransparentSideDrawsNothing($mode, $context)
	{
		$mpdf = $this->paint($this->subject($context, 'border: 1mm solid transparent; border-bottom-color: #c00'), $mode);

		$this->assertSame([strtoupper(self::RED)], array_values(array_unique($mpdf->drawnBorders)));
		$this->assertSubjectPlaced($context, $mpdf);

		$mpdf = $this->paint($this->subject($context, 'border: 1mm solid transparent'), $mode);

		$this->assertSame([], $mpdf->drawnBorders);
	}

	/**
	 * A hidden border draws nothing, with no warning. A block drew a hairline for it
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testAHiddenBorderDrawsNothing($mode, $context)
	{
		$mpdf = $this->paint($this->subject($context, 'border: 3mm hidden #36c'), $mode);

		$this->assertSame([], $mpdf->drawnBorders);
		$this->assertSubjectPlaced($context, $mpdf);
	}

	/**
	 * An image's transparent or hidden border draws nothing, and a side with a colour is drawn
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testAnImagesTransparentOrHiddenBorderDrawsNothing($mode)
	{
		$mpdf = $this->paint('<p>' . $this->image('border: 1mm solid transparent; border-left-color: #c00') . $this->image('border: 1mm hidden #c00') . '</p>', $mode);

		$this->assertSame([strtoupper(self::RED)], $mpdf->drawnBorders);
	}

	/**
	 * Transparent text is laid out, and not seen. Text inside it that sets a colour is seen, and so is the text after
	 * it
	 *
	 * @dataProvider contextsInModes
	 *
	 * @param string $mode
	 * @param string $context
	 */
	public function testTransparentTextIsNotSeen($mode, $context)
	{
		$html = $this->subject($context, 'color: transparent', '', ' <b>inner</b> <i style="color: #c00">coloured</i>') . '<p>next</p>';
		$mpdf = $this->paint($html, $mode);

		$visible = $this->keyedByText($mpdf, $mpdf->drawnVisible);
		$this->assertFalse($visible['subject']);
		$this->assertFalse($visible['inner']);
		$this->assertTrue($visible['coloured']);
		$this->assertTrue($visible['next']);
		$this->assertSame(self::RED, $this->keyedByText($mpdf, $mpdf->drawnColours)['coloured']);
		$this->assertSubjectPlaced($context, $mpdf);
	}

	/**
	 * A block in a transparent block, and a cell of a transparent table, are not seen either
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testTransparentTextIsInherited($mode)
	{
		$mpdf = $this->paint('<div style="color: transparent"><p>in a block</p></div><p>after</p>'
			. '<table style="color: transparent"><tr><td>in a cell</td></tr></table>', $mode);

		$this->assertSame(['in a block' => false, 'after' => true, 'in a cell' => false], $this->keyedByText($mpdf, $mpdf->drawnVisible));
	}

	/**
	 * Transparent text is written in the invisible text rendering mode, and its shadow is drawn
	 */
	public function testTransparentTextIsWrittenInvisiblyAndKeepsItsShadow()
	{
		$pdf = $this->uncompressed('<p style="color: transparent; text-shadow: 0.5mm 0.5mm #c00">hidden</p>', CssMode::STANDARD);

		$this->assertMatchesRegularExpression('/q 0\.800 0\.000 0\.000 rg\s+1 0 0 1 [\d.]+ -[\d.]+ cm\s+BT [\d. ]+Td\s+\(hidden\) Tj ET Q\s+q 3 Tr BT [\d. ]+Td\s+\(hidden\) Tj ET Q/', $pdf);
	}

	/**
	 * A text-shadow is drawn in the colour it names, in currentColor when it names that, and in standard mode in
	 * currentColor when it names none. A transparent one draws nothing
	 *
	 * @dataProvider textShadowsInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $shadow
	 * @param string[] $expected The colour of each shadow drawn
	 */
	public function testATextShadowIsDrawnInTheColourItNames($mode, $context, $shadow, array $expected)
	{
		$mpdf = $this->paint($this->subject($context, 'color: #0a0; text-shadow: ' . $shadow), $mode);

		$this->assertSame($expected, $this->keyedByText($mpdf, $mpdf->drawnTextShadows)['subject']);
	}

	/**
	 * Text shadows in each context, in both modes
	 *
	 * @return array[]
	 */
	public function textShadowsInContexts()
	{
		return $this->inModesAndContexts(self::CONTEXTS, [
			'currentColor' => ['0.5mm 0.5mm currentColor', [self::GREEN], [self::GREEN]],
			'no colour' => ['0.5mm 0.5mm', [self::GREEN], [self::GREY]],
			'no colour, with a blur' => ['0.5mm 0.5mm 1mm', [self::GREEN], [self::GREY]],
			'two, one with no colour' => ['0.5mm 0.5mm #c00, 1mm 1mm', [self::GREEN, self::RED], [self::GREY, self::RED]],
			'transparent' => ['0.5mm 0.5mm transparent', [], []],
			'a colour of its own' => ['0.5mm 0.5mm #c00', [self::RED], [self::RED]],
		]);
	}

	/**
	 * A block's box-shadow is painted in the colour it names, in currentColor when it names that, and in standard mode
	 * in currentColor when it names none. A transparent one paints nothing
	 *
	 * @dataProvider boxShadows
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $shadow
	 * @param string[] $expected
	 */
	public function testABoxShadowIsPaintedInTheColourItNames($mode, $context, $shadow, array $expected)
	{
		$mpdf = $this->paint($this->subject($context, 'color: #0a0; box-shadow: ' . $shadow), $mode);

		$this->assertSame($expected, array_values(array_unique($mpdf->drawnBoxShadows)));
	}

	/**
	 * Box shadows on the blocks of each context that lays out a block, in both modes
	 *
	 * @return array[]
	 */
	public function boxShadows()
	{
		return $this->inModesAndContexts(['block', 'list item', 'positioned block', 'kept block', 'forced page break'], [
			'currentColor' => ['1mm 1mm currentColor', [self::GREEN], [self::GREEN]],
			'no colour' => ['1mm 1mm', [self::GREEN], [self::GREY]],
			'inset, no colour' => ['inset 1mm 1mm', [self::GREEN], [self::GREY]],
			'transparent' => ['1mm 1mm transparent', [], []],
			'a colour of its own' => ['1mm 1mm #c00', [self::RED], [self::RED]],
		]);
	}

	/**
	 * Every property mPDF reads a colour from resolves currentColor to the element's colour when its properties are
	 * merged, in both modes, in a value with other parts and in a gradient, and not in an image's address
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testEveryColourPropertyResolvesCurrentColor($mode)
	{
		$properties = $this->merged('INLINE', 'SPAN', 'color: #0a0; background-color: currentColor; border-top: 1mm solid currentColor;'
			. ' border-right-color: currentColor; box-shadow: 1mm 1mm currentColor, inset 1mm 1mm rgb(0 0 255);'
			. ' text-shadow: 1mm 1mm currentColor; text-outline: 0.1mm currentColor', $mode);

		$this->assertSame('#0a0', $properties['BACKGROUND-COLOR']);
		$this->assertSame('1mm solid #0a0', $properties['BORDER-TOP']);
		$this->assertSame('#0a0', $properties['BORDER-RIGHT-COLOR']);
		$this->assertStringEndsWith(' #0a0', $properties['BORDER-RIGHT']);
		$this->assertSame('1mm 1mm #0a0, inset 1mm 1mm rgb(0,0,255)', $properties['BOX-SHADOW']);
		$this->assertSame('1mm 1mm #0a0', $properties['TEXT-SHADOW']);
		$this->assertSame('#0a0', $properties['TEXT-OUTLINE-COLOR']);

		$gradient = $this->merged('BLOCK', 'DIV', 'color: #0a0; background-image: linear-gradient(to right, CurrentColor 20%, #c00)', $mode);
		$url = $this->merged('BLOCK', 'DIV', 'color: #0a0; background-image: url(currentcolor.png)', $mode);

		$this->assertSame('linear-gradient(to right, #0a0 20%, #c00)', $gradient['BACKGROUND-IMAGE']);
		$this->assertStringContainsString('currentcolor.png', $url['BACKGROUND-IMAGE']);

		$table = $this->merged('TABLE', 'TABLE', 'color: rgb(0, 170, 0); topntail: 0.5mm solid currentColor; thead-underline: 0.5mm solid currentColor', $mode);

		$this->assertSame('0.5mm solid rgb(0,170,0)', $table['TOPNTAIL']);
		$this->assertSame('0.5mm solid rgb(0,170,0)', $table['THEAD-UNDERLINE']);
	}

	/**
	 * A text outline in currentColor is drawn in the text's colour, with no warning
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testATextOutlineInCurrentColorIsDrawnInTheTextsColour($mode)
	{
		$pdf = $this->uncompressed('<p style="color: #0a0; text-outline: 0.1mm currentColor">subject</p>', $mode);

		$this->assertStringContainsString('0.000 0.667 0.000 RG  2 Tr BT', $pdf);
	}

	/**
	 * A colour that names currentColor takes the colour the element has, not what its parent has
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testCurrentColorIsTheElementsOwnColourOverItsParents($mode)
	{
		$mpdf = $this->paint('<div style="color: #c00"><div style="color: #0a0; border: 0.4mm solid currentColor">subject</div></div>'
			. '<p style="color: #c00">before <span style="color: #0a0; border: 0.4mm solid currentColor">inline</span></p>', $mode);

		$this->assertSame([strtoupper(self::GREEN)], array_values(array_unique($mpdf->drawnBorders)));
	}

	/**
	 * With no colour anywhere, currentColor is the body's colour, and with none there, black
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testCurrentColorFallsBackToTheBodysColour($mode)
	{
		$mpdf = $this->paint('<style>body { color: #0a0 }</style><table><tr><td style="border: 0.4mm solid currentColor">subject</td></tr></table>', $mode);

		$this->assertSame(self::GREEN, $this->keyedByText($mpdf, $mpdf->drawnColours)['subject']);
		$this->assertSame([strtoupper(self::GREEN)], array_values(array_unique($mpdf->drawnBorders)));

		$mpdf = $this->paint('<table><tr><td style="border: 0.4mm solid currentColor">subject</td></tr></table>', $mode);

		$this->assertSame([strtoupper(self::BLACK)], array_values(array_unique($mpdf->drawnBorders)));
	}

	/**
	 * Every context in both modes
	 *
	 * @return array[]
	 */
	public function contextsInModes()
	{
		$data = [];
		foreach ($this->inModesAndContexts(self::CONTEXTS, ['' => ['', '', '']]) as $name => $case) {
			$data[rtrim($name, ', ')] = [$case[0], $case[1]];
		}

		return $data;
	}

	/**
	 * Each case in each context, in both modes
	 *
	 * @param string[] $contexts
	 * @param array[] $cases Each [value, what standard mode should give, what legacy mode should give], keyed by name
	 *
	 * @return array[] Each [mode, context, value, what the mode should give], keyed "mode, context, name"
	 */
	private function inModesAndContexts(array $contexts, array $cases)
	{
		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach ($contexts as $context) {
				foreach ($cases as $name => $case) {
					$data[$mode . ', ' . $context . ', ' . $name] = [$mode, $context, $case[0], $mode === CssMode::STANDARD ? $case[1] : $case[2]];
				}
			}
		}

		return $data;
	}

	/**
	 * Both modes
	 *
	 * @return string[][]
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD], CssMode::LEGACY => [CssMode::LEGACY]];
	}

	/**
	 * Writes a document in core-font mode, recording what it paints, and fails on any warning or notice it raises
	 *
	 * @param string $html
	 * @param string $mode
	 *
	 * @return PaintRecordingMpdf
	 */
	private function paint($html, $mode)
	{
		$raised = [];
		set_error_handler(static function ($errno, $message, $file, $line) use (&$raised) {
			$raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			return true;
		});

		try {
			$mpdf = new PaintRecordingMpdf(['mode' => 'c', 'cssMode' => $mode]);
			$mpdf->WriteHTML($html);
			$mpdf->Close();
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);

		return $mpdf;
	}

	/**
	 * A document written uncompressed, with no warning or notice raised
	 *
	 * @param string $html
	 * @param string $mode
	 *
	 * @return string The document
	 */
	private function uncompressed($html, $mode)
	{
		return $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $mode, 'useKerning' => false]);
	}

	/**
	 * An element with the text "subject" in a context, under a parent. The parent is the element mPDF passes a colour
	 * on from in that context: a block, a paragraph, the table, the inner table, or the list
	 *
	 * @param string $context One of CONTEXTS
	 * @param string $style The subject's style
	 * @param string $parent The parent's style
	 * @param string $content What follows the text in the subject
	 *
	 * @return string
	 */
	private function subject($context, $style, $parent = '', $content = '')
	{
		$subject = 'style="' . $style . '">subject' . $content;

		switch ($context) {
			case 'inline':
				return '<p style="' . $parent . '">before <span ' . $subject . '</span> after</p>';

			case 'table cell':
				return '<table style="' . $parent . '"><tr><td ' . $subject . '</td></tr></table>';

			case 'collapsed table cell':
				return '<table style="border-collapse: collapse; ' . $parent . '"><tr><td ' . $subject . '</td></tr></table>';

			case 'nested table cell':
				return '<table><tr><td><table style="' . $parent . '"><tr><td ' . $subject . '</td></tr></table></td></tr></table>';

			case 'list item':
				return '<ul style="' . $parent . '"><li ' . $subject . '</li></ul>';
		}

		$html = '<div style="' . $parent . '"><div ' . $subject . '</div></div>';

		switch ($context) {
			case 'header':
				return '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'footer':
				return '<htmlpagefooter name="f">' . $html . '</htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';

			case 'positioned block':
				return '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div>';

			case 'kept block':
				// Too little of the first page is left for the block, which is laid out again on the second
				return str_repeat('<p>filler</p>', 44) . '<div style="page-break-inside: avoid"><p>kept</p>' . $html . '</div>';

			case 'forced page break':
				return '<div><p>before the break</p><pagebreak />' . $html . '</div>';
		}

		return $html;
	}

	/**
	 * The subject is drawn where its context puts it: on the second page after a forced page break or once a kept
	 * block has moved there, and on the first page elsewhere
	 *
	 * @param string $context
	 * @param PaintRecordingMpdf $mpdf
	 */
	private function assertSubjectPlaced($context, PaintRecordingMpdf $mpdf)
	{
		$page = in_array($context, ['kept block', 'forced page break'], true) ? 2 : 1;

		$this->assertSame($page, $this->keyedByText($mpdf, $mpdf->drawnBoxes)['subject'][0]);
	}

	/**
	 * A small image with a style
	 *
	 * @param string $style
	 *
	 * @return string
	 */
	private function image($style)
	{
		return '<img style="' . $style . '" width="10mm" height="10mm" src="' . $this->pngImage() . '">';
	}

	/**
	 * An element's merged CSS properties, from its inline style
	 *
	 * @param string $inherit The context MergeCSS() is given
	 * @param string $tag
	 * @param string $style
	 * @param string $mode
	 *
	 * @return string[]
	 */
	private function merged($inherit, $tag, $style, $mode)
	{
		$mpdf = new Mpdf(['mode' => 'c', 'cssMode' => $mode]);
		$property = new \ReflectionProperty($mpdf, 'cssManager');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property->getValue($mpdf)->MergeCSS($inherit, $tag, ['STYLE' => $style]);
	}

}
