<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Under standard, a row and a row group hand their inherited properties to their cells, over the table's and under
 * the cell's own. A th keeps its own bold default, and is centred only when it inherits no text-align, as HTML's
 * rendering rules have it. Under legacy they hand their cells nothing, but the text-align of a thead or a tfoot.
 * InheritedPropertiesTest covers each inherited text property from each part of a table.
 */
class RowPropertiesToCellsTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';

	const GREEN = '0.000 1.000 0.000 rg';

	const BLUE = '0.000 0.000 1.000 rg';

	const BLACK = '0.000 g';

	/**
	 * Documents with {A} for the declarations of the part of a table and {D} for those of the cells in it. The cell
	 * looked at holds qq then, on a line of its own, ww
	 */
	const CONTEXTS = [
		'row' => '<table><tr style="{A}"><td style="width: 80mm; {D}">qq<br>ww</td></tr></table><p>zz</p>',
		'row rule' => '<style>tr.a { {A} }</style><table><tr class="a"><td style="width: 80mm; {D}">qq<br>ww</td></tr></table><p>zz</p>',
		'tbody' => '<table><tbody style="{A}"><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></tbody></table><p>zz</p>',
		'thead' => '<table><thead style="{A}"><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></thead></table><p>zz</p>',
		'tfoot' => '<table><tfoot style="{A}"><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></tfoot></table><p>zz</p>',
		'implied tbody, second row' => '<style>tbody { {A} }</style><table><tr><td style="{D}">aa</td></tr><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table><p>zz</p>',
		'row of a nested table' => '<table><tr><td><table><tr style="{A}"><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></td></tr></table><p>zz</p>',
	];

	/**
	 * A declaration of each inherited property a cell lays its lines out by that moves the text
	 */
	const SAMPLES = [
		'TEXT-ALIGN' => 'text-align: right',
		'LINE-HEIGHT' => 'line-height: 3',
		'DIRECTION' => 'direction: rtl',
	];

	/**
	 * The cell's lines are laid out as when it sets the value itself, or under legacy as when nothing sets it, but for
	 * the text-align of a thead or a tfoot
	 *
	 * @dataProvider blockPropertiesInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $property
	 */
	public function testTheCellLaysItsLinesOutByTheInheritedValue($mode, $context, $property)
	{
		$declaration = self::SAMPLES[$property];

		$initial = $this->drawnBoxes($mode, $context, '', '');
		$own = $this->drawnBoxes($mode, $context, '', $declaration);
		$inherited = $this->drawnBoxes($mode, $context, $declaration, '');

		$this->assertNotEquals($initial, $own, 'The sample value does not move the text');

		$legacyCarries = $property === 'TEXT-ALIGN' && in_array($context, ['thead', 'tfoot'], true);
		if ($mode === CssMode::STANDARD || $legacyCarries) {
			$this->assertEquals($own, $inherited);
		} else {
			$this->assertEquals($initial, $inherited);
		}
	}

	/**
	 * Each of text-align, line-height and direction from each part of a table, under both modes
	 *
	 * @return array[]
	 */
	public function blockPropertiesInContexts()
	{
		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach (array_keys(self::CONTEXTS) as $context) {
				foreach (array_keys(self::SAMPLES) as $property) {
					// The cell of a nested table is laid out left to right whatever its direction
					if ($context === 'row of a nested table' && $property === 'DIRECTION') {
						continue;
					}
					$data[$mode . ': ' . $context . ': ' . $property] = [$mode, $context, $property];
				}
			}
		}

		return $data;
	}

	/**
	 * What reaches the cell, and what does not: the cell's own value over the row's, the row's over its row group's
	 * and the row group's over the table's. Nothing reaches a cell from a row or row group it is not in
	 *
	 * @dataProvider cellColours
	 *
	 * @param string $html The text qq is in the cell looked at
	 * @param string $standard The colour qq is drawn in under standard
	 * @param string $legacy The colour qq is drawn in under legacy
	 */
	public function testTheNearestValueReachesTheCell($html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$this->assertDrawnInColours(['qq' => $expected], $this->drawnColours($html, ['cssMode' => $mode]));
		}
	}

	/**
	 * Cells and the colours they take, with the near misses
	 *
	 * @return array[]
	 */
	public function cellColours()
	{
		return [
			'a rule on the cell wins over the row' => ['<style>td { color: #00f }</style><table><tr style="color: #f00"><td>qq</td></tr></table>', self::BLUE, self::BLUE],
			'the cell\'s style wins over the row group' => ['<table><tbody style="color: #f00"><tr><td style="color: #00f">qq</td></tr></tbody></table>', self::BLUE, self::BLUE],
			'the row wins over its row group' => ['<table><tbody style="color: #f00"><tr style="color: #0f0"><td>qq</td></tr></tbody></table>', self::GREEN, self::BLACK],
			'the row wins over the table' => ['<table style="color: #00f"><tr style="color: #f00"><td>qq</td></tr></table>', self::RED, self::BLUE],
			'the row group wins over the table' => ['<table style="color: #00f"><thead style="color: #f00"><tr><td>qq</td></tr></thead></table>', self::RED, self::BLUE],
			'a row group rule by class' => ['<style>tbody.a { color: #f00 }</style><table><tbody class="a"><tr><td>qq</td></tr></tbody></table>', self::RED, self::BLACK],
			'a rule on the implied tbody' => ['<style>table > tbody { color: #f00 }</style><table><tr><td>qq</td></tr></table>', self::RED, self::BLACK],
			'near miss: a sibling row' => ['<table><tr style="color: #f00"><td>zz</td></tr><tr><td>qq</td></tr></table>', self::BLACK, self::BLACK],
			'near miss: a row rule for another class' => ['<style>tr.a { color: #f00 }</style><table><tr class="b"><td>qq</td></tr></table>', self::BLACK, self::BLACK],
			'near miss: a thead before the implied tbody' => ['<table><thead style="color: #f00"><tr><td>zz</td></tr></thead><tr><td>qq</td></tr></table>', self::BLACK, self::BLACK],
			'near miss: a tbody before a tbody of its own' => ['<table><tbody style="color: #f00"><tr><td>zz</td></tr></tbody><tbody><tr><td>qq</td></tr></tbody></table>', self::BLACK, self::BLACK],
			'near miss: a tbody rule and a thead cell' => ['<style>tbody { color: #f00 }</style><table><thead><tr><td>qq</td></tr></thead><tbody><tr><td>zz</td></tr></tbody></table>', self::BLACK, self::BLACK],
			'near miss: a nested table\'s row and the outer table\'s next cell' => ['<table><tr><td><table><tr style="color: #f00"><td>zz</td></tr></table></td><td>qq</td></tr></table>', self::BLACK, self::BLACK],
			'near miss: the row of the table before' => ['<table><tr style="color: #f00"><td>zz</td></tr></table><table><tr><td>qq</td></tr></table>', self::BLACK, self::BLACK],
		];
	}

	/**
	 * A th keeps its bold default whatever its row's weight, and a td takes the row's. A th is centred when it
	 * inherits no text-align, and otherwise takes the one it inherits, from its row, its row group or the table,
	 * unless a rule for it sets its own
	 *
	 * @dataProvider headerCells
	 *
	 * @param string $html The text qq is in the cell looked at
	 * @param string $standard Its font style and alignment under standard: B or '', then L, C or R
	 * @param string $legacy Its font style and alignment under legacy
	 */
	public function testAHeaderCellKeepsItsOwnDefaults($html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$this->assertSame($expected, $this->styleAndAlignment($html, $mode), $mode);
		}
	}

	/**
	 * Header and data cells in rows that set a weight or an alignment, with the near misses
	 *
	 * @return array[]
	 */
	public function headerCells()
	{
		$table = function ($part, $cell) {
			return '<table ' . $part . '><tr><td style="width: 80mm">zz</td></tr><tr ' . $cell . '</tr></table>';
		};

		return [
			'a th in a row set to normal weight' => [$table('', 'style="font-weight: normal"><th style="width: 80mm">qq</th>'), 'B C', 'B C'],
			'a td in a bold row' => [$table('', 'style="font-weight: bold"><td>qq</td>'), 'B L', ' L'],
			'a th in a right-aligned row' => [$table('', 'style="text-align: right"><th>qq</th>'), 'B R', 'B C'],
			'a th in a right-aligned thead' => ['<table><thead style="text-align: right"><tr><th style="width: 80mm">qq</th></tr></thead></table>', 'B R', 'B C'],
			'a th in a right-aligned table' => [$table('style="text-align: right"', '><th>qq</th>'), 'B R', 'B C'],
			'a th in a left-aligned row' => [$table('style="text-align: right"', 'style="text-align: left"><th>qq</th>'), 'B L', 'B C'],
			'a td in a right-aligned row' => [$table('', 'style="text-align: right"><td>qq</td>'), ' R', ' L'],
			'near miss: a th in a row that sets no alignment' => [$table('', '><th>qq</th>'), 'B C', 'B C'],
			'near miss: a th that sets its own alignment' => [$table('', 'style="text-align: right"><th style="text-align: left">qq</th>'), 'B L', 'B L'],
			'near miss: a rule that centres a th' => ['<style>th { text-align: center }</style>' . $table('', 'style="text-align: right"><th>qq</th>'), 'B C', 'B C'],
		];
	}

	/**
	 * A row group's font size is resolved against the table's, a row's against its row group's, and a cell's against
	 * its row's
	 *
	 * @dataProvider fontSizes
	 *
	 * @param string $html
	 * @param float $standard The size qq is drawn at under standard, in points
	 * @param float $legacy The size under legacy
	 */
	public function testARelativeFontSizeIsResolvedAgainstTheRow($html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

			$this->assertEqualsWithDelta($expected, $this->keyedByText($mpdf, $mpdf->drawnFontSize)['qq'], 0.001, $mode);
		}
	}

	/**
	 * Sizes as percentages, ems and keywords through a table's parts
	 *
	 * @return array[]
	 */
	public function fontSizes()
	{
		return [
			'percentages of the table, the row group and the row' => ['<table style="font-size: 20pt"><tbody style="font-size: 150%"><tr style="font-size: 50%"><td style="font-size: 200%">qq</td></tr></tbody></table>', 30.0, 40.0],
			'ems of the row group' => ['<table><thead style="font-size: 2em"><tr><td>qq</td></tr></thead></table>', 22.0, 11.0],
			'a cell\'s ems of the row' => ['<table><tr style="font-size: 20pt"><td style="font-size: 0.5em">qq</td></tr></table>', 10.0, 5.5],
			'a cell\'s percentage of the row' => ['<table><tr style="font-size: 20pt"><td style="font-size: 50%">qq</td></tr></table>', 10.0, 5.5],
			'a size in points on the cell' => ['<table><tr style="font-size: 20pt"><td style="font-size: 8pt">qq</td></tr></table>', 8.0, 8.0],
			'near miss: a sibling row\'s size' => ['<table><tr style="font-size: 20pt"><td>zz</td></tr><tr><td style="font-size: 50%">qq</td></tr></table>', 5.5, 5.5],
		];
	}

	/**
	 * The cells of a thead repeated at the top of the next page, and of the rows of a row group that runs onto it, take
	 * the row group's colour on each page
	 */
	public function testRowGroupsKeepTheirValuesOnTheNextPage()
	{
		$html = '<table><thead style="color: #f00"><tr><td>head</td></tr></thead><tbody style="color: #00f">'
			. str_repeat('<tr><td>row</td></tr>', 60) . '<tr><td>last</td></tr></tbody></table>';

		foreach ([CssMode::STANDARD => [self::RED, self::BLUE], CssMode::LEGACY => [self::BLACK, self::BLACK]] as $mode => $expected) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

			$drawn = [];
			foreach ($mpdf->drawnText as $i => $text) {
				$drawn[trim($text)][$mpdf->drawnBoxes[$i][0]][] = $mpdf->drawnColours[$i];
			}

			$this->assertSame([1, 2], array_keys($drawn['head']), $mode);
			$this->assertSame([1, 2], array_keys($drawn['row']), $mode);
			foreach ([1, 2] as $page) {
				$this->assertSame([$expected[0]], array_unique($drawn['head'][$page]), $mode);
				$this->assertSame([$expected[1]], array_unique($drawn['row'][$page]), $mode);
			}
			$this->assertSame([2 => [$expected[1]]], $drawn['last'], $mode);
		}
	}

	/**
	 * Where the lines of the cell's text are drawn, and where the paragraph after the table is
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $part The declarations of the part of the table
	 * @param string $cell The declarations of the cell
	 *
	 * @return array[] The page, left and right edges and top of qq, ww and zz, rounded
	 */
	private function drawnBoxes($mode, $context, $part, $cell)
	{
		$mpdf = $this->drawDocument(strtr(self::CONTEXTS[$context], ['{A}' => $part, '{D}' => $cell]), ['cssMode' => $mode]);
		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);

		return array_map(function ($box) {
			return array_map(function ($value) {
				return round($value, 3);
			}, $box);
		}, array_intersect_key($boxes, array_flip(['qq', 'ww', 'zz'])));
	}

	/**
	 * The font style qq is drawn in, and where it is drawn in its cell, found by comparing it with qq drawn left,
	 * centred and right in a cell of the same width
	 *
	 * @param string $html
	 * @param string $mode
	 *
	 * @return string B or '', a space, then L, C or R, or ? when it is drawn anywhere else
	 */
	private function styleAndAlignment($html, $mode)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$drawn = $this->keyedByText($mpdf, array_map(null, $mpdf->drawnFontStyles, $mpdf->drawnBoxes));

		$alignment = '?';
		foreach (['L' => 'left', 'C' => 'center', 'R' => 'right'] as $code => $align) {
			$reference = $this->drawDocument(
				'<table><tr><td style="width: 80mm; text-align: ' . $align . '"><b>qq</b></td></tr></table>',
				['cssMode' => $mode]
			);
			$box = $this->keyedByText($reference, $reference->drawnBoxes)['qq'];
			// A bold qq is a little wider, so the edge it is aligned to is compared
			$edge = $code === 'R' ? 2 : 1;
			if ($code === 'C' && abs(($box[1] + $box[2]) - ($drawn['qq'][1][1] + $drawn['qq'][1][2])) < 0.5
				|| $code !== 'C' && abs($box[$edge] - $drawn['qq'][1][$edge]) < 0.5) {
				$alignment = $code;
			}
		}

		return $drawn['qq'][0] . ' ' . $alignment;
	}
}
