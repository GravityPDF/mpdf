<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The inheritance matrix of #547: each inherited property set on an ancestor, read from what the descendant's text
 * is drawn with, for each relationship between two elements mPDF hands values on through. Under the standard CSS mode
 * every descendant takes every value, as in a browser. Under legacy each relationship hands on what mPDF v7 did.
 */
class InheritanceMatrixTest extends TestCase
{

	use DrawnStyles;

	/**
	 * A declaration of each property in the matrix, which draws the descendant's text apart from its initial state
	 */
	const VALUES = [
		'color' => 'color: #ff0000',
		'font-size' => 'font-size: 16pt',
		'font-family' => 'font-family: monospace',
		'font-weight' => 'font-weight: bold',
		'font-style' => 'font-style: italic',
		'letter-spacing' => 'letter-spacing: 1mm',
		'word-spacing' => 'word-spacing: 5mm',
		'text-transform' => 'text-transform: uppercase',
		'text-shadow' => 'text-shadow: 1px 1px #0000ff',
		'text-align' => 'text-align: right',
		'line-height' => 'line-height: 3',
	];

	/**
	 * Each relationship: the document with {A} for the ancestor's declaration, and the same document with {D} where the
	 * descendant sets it itself. The descendant's text is two lines, "qq qq" and "ww ww". A property that lays out
	 * lines is set, as {B}, on what the descendant's lines are laid out in where that is not the descendant: the block
	 * around an inline element, and the cell around a block, which mPDF lays out as the cell's own content
	 */
	const RELATIONSHIPS = [
		'block to block' => [
			'<div style="{A}"><p>qq qq<br>ww ww</p></div>',
			'<div><p style="{D}">qq qq<br>ww ww</p></div>',
		],
		'block to inline' => [
			'<div style="{A}">zz <span>qq qq<br>ww ww</span></div>',
			'<div style="{B}">zz <span style="{D}">qq qq<br>ww ww</span></div>',
		],
		'inline to inline' => [
			'<div>zz <span style="{A}">yy <span>qq qq<br>ww ww</span></span></div>',
			'<div>zz <span>yy <span style="{D}">qq qq<br>ww ww</span></span></div>',
		],
		'inline to block' => [
			'<span style="{A}">zz<div>qq qq<br>ww ww</div></span>',
			'<span>zz<div style="{D}">qq qq<br>ww ww</div></span>',
		],
		'table to cell' => [
			'<table style="{A}"><tr><td style="width: 100mm">qq qq<br>ww ww</td></tr></table>',
			'<table><tr><td style="width: 100mm; {D}">qq qq<br>ww ww</td></tr></table>',
		],
		'tbody to cell' => [
			'<table><tbody style="{A}"><tr><td style="width: 100mm">qq qq<br>ww ww</td></tr></tbody></table>',
			'<table><tbody><tr><td style="width: 100mm; {D}">qq qq<br>ww ww</td></tr></tbody></table>',
		],
		'thead to th' => [
			'<table><thead style="{A}"><tr><th style="width: 100mm">qq qq<br>ww ww</th></tr></thead><tbody><tr><td>zz</td></tr></tbody></table>',
			'<table><thead><tr><th style="width: 100mm; {D}">qq qq<br>ww ww</th></tr></thead><tbody><tr><td>zz</td></tr></tbody></table>',
		],
		'tr to cell' => [
			'<table><tr style="{A}"><td style="width: 100mm">qq qq<br>ww ww</td></tr></table>',
			'<table><tr><td style="width: 100mm; {D}">qq qq<br>ww ww</td></tr></table>',
		],
		'cell to block' => [
			'<table><tr><td style="width: 100mm; {A}"><div>qq qq<br>ww ww</div></td></tr></table>',
			'<table><tr><td style="width: 100mm; {B}"><div style="{D}">qq qq<br>ww ww</div></td></tr></table>',
		],
		'cell to inline' => [
			'<table><tr><td style="width: 100mm; {A}">zz <span>qq qq<br>ww ww</span></td></tr></table>',
			'<table><tr><td style="width: 100mm; {B}">zz <span style="{D}">qq qq<br>ww ww</span></td></tr></table>',
		],
		'outer cell to nested table cell' => [
			'<table><tr><td style="{A}"><table><tr><td style="width: 100mm">qq qq<br>ww ww</td></tr></table></td></tr></table>',
			'<table><tr><td><table><tr><td style="width: 100mm; {D}">qq qq<br>ww ww</td></tr></table></td></tr></table>',
		],
		'block to table cell' => [
			'<div style="{A}"><table><tr><td style="width: 100mm">qq qq<br>ww ww</td></tr></table></div>',
			'<div><table><tr><td style="width: 100mm; {D}">qq qq<br>ww ww</td></tr></table></div>',
		],
		'list to item' => [
			'<ul style="{A}"><li>qq qq<br>ww ww</li></ul>',
			'<ul><li style="{D}">qq qq<br>ww ww</li></ul>',
		],
		'item to block' => [
			'<ul><li style="{A}"><div>qq qq<br>ww ww</div></li></ul>',
			'<ul><li><div style="{D}">qq qq<br>ww ww</div></li></ul>',
		],
		'positioned block to block' => [
			'<div style="position: absolute; top: 50mm; left: 20mm; width: 120mm; {A}"><p>qq qq<br>ww ww</p></div>',
			'<div style="position: absolute; top: 50mm; left: 20mm; width: 120mm"><p style="{D}">qq qq<br>ww ww</p></div>',
		],
		'body to block' => [
			'<body style="{A}"><p>qq qq<br>ww ww</p></body>',
			'<body><p style="{D}">qq qq<br>ww ww</p></body>',
		],
	];

	/**
	 * The properties laid out by the block, which an inline element cannot set itself
	 */
	const BLOCK_PROPERTIES = ['text-align', 'line-height'];

	/**
	 * What legacy hands on, as #547 measured it at d660a410: the properties each relationship drops
	 */
	const LEGACY_DROPPED = [
		'block to block' => ['text-shadow'],
		'block to inline' => [],
		'inline to inline' => [],
		'inline to block' => [
			'color', 'font-size', 'font-family', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing',
			'text-transform', 'text-shadow', 'text-align', 'line-height',
		],
		'table to cell' => ['text-transform', 'text-shadow'],
		'tbody to cell' => [
			'color', 'font-size', 'font-family', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing',
			'text-transform', 'text-shadow', 'text-align', 'line-height',
		],
		'thead to th' => [
			'color', 'font-size', 'font-family', 'font-style', 'letter-spacing', 'word-spacing', 'text-transform',
			'text-shadow', 'text-align', 'line-height',
		],
		'tr to cell' => [
			'color', 'font-size', 'font-family', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing',
			'text-transform', 'text-shadow', 'text-align', 'line-height',
		],
		'cell to block' => [],
		'cell to inline' => [],
		'outer cell to nested table cell' => [
			'font-size', 'font-family', 'font-weight', 'font-style', 'text-transform', 'line-height',
		],
		'block to table cell' => [
			'color', 'font-size', 'font-family', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing',
			'text-transform', 'text-shadow', 'text-align', 'line-height',
		],
		'list to item' => ['text-shadow'],
		'item to block' => ['text-shadow'],
		'positioned block to block' => ['word-spacing', 'text-shadow'],
		'body to block' => ['text-shadow'],
	];

	/**
	 * The descendant's text is drawn with the value the ancestor sets as it is with the value set on the descendant,
	 * except, under legacy, where the relationship drops it
	 *
	 * @dataProvider cells
	 *
	 * @param string $mode
	 * @param string $relationship
	 * @param string $property
	 */
	public function testTheDescendantIsDrawnWithTheAncestorsValue($mode, $relationship, $property)
	{
		list($ancestorHtml, $ownHtml) = self::RELATIONSHIPS[$relationship];
		$value = self::VALUES[$property];
		$onBlock = in_array($property, self::BLOCK_PROPERTIES, true);

		$inherited = $this->drawn($mode, strtr($ancestorHtml, ['{A}' => $value]), $property);
		$none = $this->drawn($mode, strtr($ancestorHtml, ['{A}' => '']), $property);
		// Set on the block the descendant's lines are laid out in, where that is not the descendant
		$onBlock = $onBlock && strpos($ownHtml, '{B}') !== false;
		$own = $this->drawn($mode, strtr($ownHtml, ['{D}' => $onBlock ? '' : $value, '{B}' => $onBlock ? $value : '']), $property);

		// A th is bold whatever its row group sets
		if ($relationship !== 'thead to th' || $property !== 'font-weight') {
			$this->assertNotEquals($none, $own, 'The value does not change how the descendant is drawn');
		}

		if ($mode === CssMode::LEGACY && in_array($property, self::LEGACY_DROPPED[$relationship], true)) {
			$this->assertNotEquals($own, $inherited);
		} else {
			$this->assertEquals($own, $inherited);
		}
	}

	/**
	 * Every relationship and property, under both modes. An inline element's own text is not laid out by its
	 * alignment or line height, so those are left out of inline to inline
	 *
	 * @return array[]
	 */
	public function cells()
	{
		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach (array_keys(self::RELATIONSHIPS) as $relationship) {
				foreach (array_keys(self::VALUES) as $property) {
					if ($relationship === 'inline to inline' && in_array($property, self::BLOCK_PROPERTIES, true)) {
						continue;
					}
					$data[$mode . ': ' . $relationship . ': ' . $property] = [$mode, $relationship, $property];
				}
			}
		}

		return $data;
	}

	/**
	 * What of the descendant's text a property changes: the first line's colour, font, size, style, shadow, drawn text
	 * or width, the left edge of the second line, or the distance between the two lines
	 *
	 * @param string $mode
	 * @param string $html
	 * @param string $property
	 *
	 * @return mixed
	 */
	private function drawn($mode, $html, $property)
	{
		// In core-font mode a nested table's cells lose a font family that is the table's default (#648)
		$mpdf = $this->drawDocument($html, ['mode' => 'utf-8', 'cssMode' => $mode]);
		$mpdf->Close();

		// Lowercased, as text-transform draws the lines in capitals
		$lines = array_change_key_case($this->keyedByText($mpdf, array_keys($mpdf->drawnText)));
		$this->assertArrayHasKey('qq qq', $lines, 'The first line is drawn');
		$this->assertArrayHasKey('ww ww', $lines, 'The second line is drawn');
		$first = $lines['qq qq'];
		$second = $lines['ww ww'];

		switch ($property) {
			case 'color':
				return $mpdf->drawnColours[$first];
			case 'font-size':
				return round($mpdf->drawnFontSize[$first], 3);
			case 'font-family':
				return $mpdf->drawnFontFamily[$first];
			case 'font-weight':
				return strpos($mpdf->drawnFontStyles[$first], 'B') !== false;
			case 'font-style':
				return strpos($mpdf->drawnFontStyles[$first], 'I') !== false;
			case 'letter-spacing':
			case 'word-spacing':
				return round($mpdf->drawnBoxes[$first][2] - $mpdf->drawnBoxes[$first][1], 3);
			case 'text-transform':
				return $mpdf->drawnText[$first];
			case 'text-shadow':
				return $mpdf->drawnShadows[$first];
			case 'text-align':
				return round($mpdf->drawnBoxes[$second][1], 3);
			default:
				return round($mpdf->drawnY[$second] - $mpdf->drawnY[$first], 3);
		}
	}
}
