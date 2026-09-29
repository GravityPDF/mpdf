<?php

namespace Mpdf;

/**
 * The border and background shorthands reset the longhands they do not name, and a later longhand still overrides
 * the shorthand
 */
class ShorthandResetsLonghandsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The stroke colour of a red side
	 */
	const RED = '0.800 0.000 0.000 RG';

	/**
	 * The stroke colour of a blue side
	 */
	const BLUE = '0.000 0.000 0.800 RG';

	/**
	 * A repeating tile of the 100 by 116 pixel background image at its natural size
	 */
	const NATURAL_TILE = "/BBox [0 0 75.000 87.000]\n/XStep 75.000";

	/**
	 * A background image that is not repeated
	 */
	const NO_REPEAT = '/XStep 99999';

	/**
	 * A later border shorthand gives the top side its own colour, blue, in place of an earlier red top colour
	 *
	 * @dataProvider borderResetProvider
	 *
	 * @param string $element The element the rules select: p, span, td or collapsed td
	 * @param string $css The rules, with {el} for the element
	 * @param string $style The element's style attribute
	 */
	public function testBorderShorthandResetsAnEarlierLonghand($element, $css, $style = '')
	{
		$pdf = $this->renderElement($element, $css, $style);

		$this->assertDrawn(self::BLUE, $pdf);
		$this->assertDrawn(self::RED, $pdf, false);
	}

	/**
	 * Each element with each way of giving a longhand before a border shorthand
	 *
	 * @return array[]
	 */
	public function borderResetProvider()
	{
		return $this->forEachElement([
			'same selector' => ['{el} { border-top-color: #c00 } {el} { border: 2mm solid #00c }'],
			'same declaration block' => ['{el} { border-top-color: #c00; border: 2mm solid #00c }'],
			'a side shorthand' => ['{el} { border-top-color: #c00 } {el} { border-top: 2mm solid #00c }'],
			'descendant rules' => ['div.wrap {el} { border-top-color: #c00 } div.wrap {el} { border: 2mm solid #00c }'],
			'a later class rule' => ['{el} { border-top-color: #c00 } .x { border: 2mm solid #00c }'],
			'the style attribute' => ['{el} { border-top-color: #c00 }', 'border: 2mm solid #00c'],
		]);
	}

	/**
	 * A top colour given after a border shorthand still colours the top side red
	 *
	 * @dataProvider borderOverrideProvider
	 *
	 * @param string $element The element the rules select: p, span, td or collapsed td
	 * @param string $css The rules, with {el} for the element
	 * @param string $style The element's style attribute
	 */
	public function testLaterLonghandOverridesTheBorderShorthand($element, $css, $style = '')
	{
		$pdf = $this->renderElement($element, $css, $style);

		$this->assertDrawn(self::BLUE, $pdf);
		$this->assertDrawn(self::RED, $pdf);
	}

	/**
	 * Each element with each way of giving a longhand after a border shorthand
	 *
	 * @return array[]
	 */
	public function borderOverrideProvider()
	{
		return $this->forEachElement([
			'same selector' => ['{el} { border: 2mm solid #00c } {el} { border-top-color: #c00 }'],
			'same declaration block' => ['{el} { border: 2mm solid #00c; border-top-color: #c00 }'],
			'a longhand repeated after the shorthand' => ['{el} { border-top-color: #0c0; border: 2mm solid #00c; border-top-color: #c00 }'],
			'descendant rules' => ['div.wrap {el} { border: 2mm solid #00c } div.wrap {el} { border-top-color: #c00 }'],
			'the style attribute' => ['{el} { border: 2mm solid #00c }', 'border-top-color: #c00'],
			'a longhand repeated in the style attribute' => ['', 'border-top-color: #0c0; border: 2mm solid #00c; border-top-color: #c00'],
		]);
	}

	/**
	 * border-radius is not one of the parts of border, so a later border shorthand keeps an earlier radius
	 */
	public function testBorderShorthandKeepsTheRadius()
	{
		$pdf = $this->renderElement('p', '{el} { border-radius: 5mm } {el} { border: 2mm solid #00c }');

		// The rounded corners are drawn with Bézier curves
		$this->assertDrawn(' c ', $pdf);
	}

	/**
	 * A later background shorthand repeats the image from the top left at its natural size, in place of an earlier
	 * rule's repeat, position and size
	 *
	 * @dataProvider backgroundResetProvider
	 *
	 * @param string $element The element the rules select: p or td
	 * @param string $css The rules, with {el} for the element and {img} for the image
	 * @param string $style The element's style attribute
	 */
	public function testBackgroundShorthandResetsEarlierLonghands($element, $css, $style = '')
	{
		$pdf = $this->renderElement($element, $css, $style);

		$this->assertDrawn(self::NATURAL_TILE, $pdf);
	}

	/**
	 * Each element that draws a background image with each way of giving longhands before a background shorthand
	 *
	 * @return array[]
	 */
	public function backgroundResetProvider()
	{
		$longhands = 'background-repeat: no-repeat; background-position: right bottom; background-size: 50%';

		return $this->forEachElement([
			'same selector' => ['{el} { ' . $longhands . ' } {el} { background: url({img}) }'],
			'same declaration block' => ['{el} { ' . $longhands . '; background: url({img}) }'],
			'descendant rules' => ['div.wrap {el} { ' . $longhands . ' } div.wrap {el} { background: url({img}) }'],
			'a later class rule' => ['{el} { ' . $longhands . ' } .x { background: url({img}) }'],
			'the style attribute' => ['{el} { ' . $longhands . ' }', 'background: url({img})'],
		], ['p', 'td']);
	}

	/**
	 * A repeat given after a background shorthand still applies
	 *
	 * @dataProvider backgroundOverrideProvider
	 *
	 * @param string $element The element the rules select: p or td
	 * @param string $css The rules, with {el} for the element and {img} for the image
	 * @param string $style The element's style attribute
	 */
	public function testLaterLonghandOverridesTheBackgroundShorthand($element, $css, $style = '')
	{
		$pdf = $this->renderElement($element, $css, $style);

		$this->assertDrawn(self::NO_REPEAT, $pdf);
	}

	/**
	 * Each element that draws a background image with each way of giving a longhand after a background shorthand
	 *
	 * @return array[]
	 */
	public function backgroundOverrideProvider()
	{
		return $this->forEachElement([
			'same selector' => ['{el} { background: url({img}) } {el} { background-repeat: no-repeat }'],
			'same declaration block' => ['{el} { background: url({img}); background-repeat: no-repeat }'],
			'a longhand repeated after the shorthand' => ['{el} { background-repeat: repeat-x; background: url({img}); background-repeat: no-repeat }'],
			'descendant rules' => ['div.wrap {el} { background: url({img}) } div.wrap {el} { background-repeat: no-repeat }'],
			'the style attribute' => ['{el} { background: url({img}) }', 'background-repeat: no-repeat'],
		], ['p', 'td']);
	}

	/**
	 * An inline element draws only a background colour: a later shorthand without one paints none, and a later
	 * colour paints over the shorthand
	 */
	public function testInlineBackgroundColour()
	{
		$this->assertDrawn('0.000 1.000 0.000 rg', $this->renderElement('span', '{el} { background-color: #0f0 } {el} { background: none }'), false);
		$this->assertDrawn('0.000 1.000 0.000 rg', $this->renderElement('span', '{el} { background: none } {el} { background-color: #0f0 }'));
	}

	/**
	 * background-image-resize, -resolution and -opacity are mPDF's own and not parts of the shorthand, so the
	 * shorthand keeps them whether they are given before or after it
	 *
	 * @dataProvider backgroundExtensionProvider
	 *
	 * @param string $css The rules, with {img} for the image
	 * @param string $expected What the mPDF property draws
	 */
	public function testBackgroundShorthandKeepsMpdfBackgroundProperties($css, $expected)
	{
		$this->assertDrawn($expected, $this->renderElement('p', $css));
	}

	/**
	 * Each mPDF background property, before the shorthand, after it, and before it in the same block
	 *
	 * @return string[][]
	 */
	public function backgroundExtensionProvider()
	{
		$properties = [
			'resize to the width' => ['background-image-resize: 4', '/BBox [0 0 510.241 591.879]'],
			'resolution' => ['background-image-resolution: 192dpi', '/BBox [0 0 37.500 43.500]'],
			'opacity' => ['background-image-opacity: 0.5', '/ca 0.5'],
		];

		$data = [];
		foreach ($properties as $name => $property) {
			list($declaration, $expected) = $property;
			$data[$name . ', earlier rule'] = ['p { ' . $declaration . ' } p { background: url({img}) no-repeat }', $expected];
			$data[$name . ', after in the block'] = ['p { background: url({img}) no-repeat; ' . $declaration . ' }', $expected];
			$data[$name . ', before in the block'] = ['p { ' . $declaration . '; background: url({img}) no-repeat }', $expected];
		}

		return $data;
	}

	/**
	 * Whether the document holds the operators or object entries given, without dumping the document on failure
	 *
	 * @param string $needle
	 * @param string $pdf
	 * @param bool   $drawn Whether they should be there
	 */
	private function assertDrawn($needle, $pdf, $drawn = true)
	{
		$this->assertSame($drawn, strpos($pdf, $needle) !== false, '"' . $needle . '" should ' . ($drawn ? '' : 'not ') . 'be drawn');
	}

	/**
	 * Every case for each element
	 *
	 * @param array    $cases The CSS, and the style attribute if any, keyed by name
	 * @param string[] $elements
	 *
	 * @return array[]
	 */
	private function forEachElement(array $cases, array $elements = ['p', 'span', 'td', 'collapsed td'])
	{
		$data = [];
		foreach ($elements as $element) {
			foreach ($cases as $name => $case) {
				$data[$element . ': ' . $name] = array_merge([$element], $case);
			}
		}

		return $data;
	}

	/**
	 * Renders the element with class x inside div.wrap, and returns the uncompressed document
	 *
	 * @param string $element p, span, td, or collapsed td for a cell of a table with collapsed borders
	 * @param string $css The rules, with {el} for the element and {img} for the image
	 * @param string $style The element's style attribute
	 *
	 * @return string
	 */
	private function renderElement($element, $css, $style = '')
	{
		$replace = ['{el}' => $element === 'collapsed td' ? 'td' : $element, '{img}' => $this->backgroundImage()];
		$attributes = 'class="x"' . ($style !== '' ? ' style="' . strtr($style, $replace) . '"' : '');

		if ($element === 'td' || $element === 'collapsed td') {
			$collapse = $element === 'td' ? 'separate' : 'collapse';
			$body = '<table style="border-collapse: ' . $collapse . '"><tr><td ' . $attributes . '>Text</td></tr></table>';
		} elseif ($element === 'span') {
			$body = '<p>A <span ' . $attributes . '>word</span> here</p>';
		} else {
			$body = '<p ' . $attributes . '>Text</p>';
		}

		return $this->assertDrawsSilently(function (Mpdf $mpdf) use ($css, $replace, $body) {
			$mpdf->WriteHTML('<style>' . strtr($css, $replace) . '</style><div class="wrap">' . $body . '</div>');
		});
	}

}
