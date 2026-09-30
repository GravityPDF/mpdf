<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * text-transform: none on an inline element undoes the transform it inherits, wherever the element is: in a block, a
 * list item, a table cell, a header or footer, a positioned block, a block kept together and after a forced page
 * break. Under legacy the inherited transform stays, as it did in mPDF v7.
 */
class TextTransformNoneTest extends TestCase
{

	use DrawnStyles;

	/**
	 * Each piece of text is drawn as the transform that applies to it leaves it
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $transform The transform on the element around the pieces
	 * @param string $pieces The pieces of text, marked up
	 * @param string[] $expected How each is drawn
	 */
	public function testTheTransformThatAppliesIsDrawn($mode, $context, $transform, $pieces, array $expected)
	{
		$mpdf = $this->drawDocument($this->document($context, $transform, $pieces), ['cssMode' => $mode]);

		$drawn = array_map('trim', $mpdf->drawnText);
		foreach ($expected as $piece) {
			$this->assertContains($piece, $drawn);
		}
	}

	/**
	 * Every case in every context, under both modes
	 *
	 * @return array[]
	 */
	public function casesInContexts()
	{
		$data = [];
		foreach (['block', 'list item', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
			foreach ($this->cases() as $name => $case) {
				$data['standard: ' . $context . ': ' . $name] = [CssMode::STANDARD, $context, $case[0], $case[1], $case[2]];
				$data['legacy: ' . $context . ': ' . $name] = [CssMode::LEGACY, $context, $case[0], $case[1], $case[3]];
			}
		}

		return $data;
	}

	/**
	 * @return array[] Each [the transform around the pieces, the pieces, how standard draws them, how legacy does]
	 */
	private function cases()
	{
		return [
			'none undoes an inherited uppercase' => [
				'uppercase',
				'aa <span style="text-transform: none">bb</span>',
				['AA', 'bb'],
				['AA', 'BB'],
			],
			'none undoes an inherited lowercase' => [
				'lowercase',
				'AA <span style="text-transform: none">Bb</span>',
				['aa', 'Bb'],
				['aa', 'bb'],
			],
			'none undoes an inherited capitalize' => [
				'capitalize',
				'aa <span style="text-transform: none">bb</span>',
				['Aa', 'bb'],
				['Aa', 'Bb'],
			],
			'none in capitals is read as none' => [
				'uppercase',
				'aa <span style="text-transform: NONE">bb</span>',
				['AA', 'bb'],
				['AA', 'BB'],
			],
			'an element inside the one set to none keeps it' => [
				'uppercase',
				'aa <span style="text-transform: none">bb <b>cc</b></span>',
				['AA', 'bb', 'cc'],
				['AA', 'BB', 'CC'],
			],
			'the text after the element set to none is transformed again' => [
				'uppercase',
				'<span style="text-transform: none">bb</span> dd',
				['bb', 'DD'],
				['BB', 'DD'],
			],
			'another transform replaces the inherited one' => [
				'uppercase',
				'aa <span style="text-transform: lowercase">BB</span>',
				['AA', 'bb'],
				['AA', 'bb'],
			],
			'an element that sets no transform inherits it' => [
				'uppercase',
				'aa <span>bb</span>',
				['AA', 'BB'],
				['AA', 'BB'],
			],
			'none with nothing to undo leaves the text as written' => [
				'none',
				'aa <span style="text-transform: none">Bb</span>',
				['aa', 'Bb'],
				['aa', 'Bb'],
			],
		];
	}

	/**
	 * A document with the pieces, in an element carrying the transform, in a context
	 *
	 * @param string $context
	 * @param string $transform
	 * @param string $pieces
	 *
	 * @return string
	 */
	private function document($context, $transform, $pieces)
	{
		$style = 'text-transform: ' . $transform;

		switch ($context) {
			case 'list item':
				return '<ul><li style="' . $style . '">' . $pieces . '</li></ul>';

			case 'table cell':
				return '<table><tr><td style="' . $style . '">' . $pieces . '</td></tr></table>';

			case 'header':
				return '<htmlpageheader name="h"><p style="' . $style . '">' . $pieces . '</p></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'footer':
				return '<htmlpagefooter name="f"><p style="' . $style . '">' . $pieces . '</p></htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';

			case 'positioned block':
				return '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; ' . $style . '">' . $pieces . '</div>';

			case 'kept block':
				// Too little of the first page is left for the block, which is laid out again on the second
				return str_repeat('<p>filler</p>', 44) . '<div style="page-break-inside: avoid"><p>kept</p><p style="' . $style . '">' . $pieces . '</p></div>';

			case 'forced page break':
				return '<div style="' . $style . '"><p>before the break</p><pagebreak /><p>' . $pieces . '</p></div>';

			default:
				return '<p style="' . $style . '">' . $pieces . '</p>';
		}
	}
}
