<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Under standard, a table inherits from the block around it, and a nested table from its cell, as in a browser: its
 * cells lay their lines out by the block's text-align, line-height and direction, its font size is relative to the
 * block's, and rem stays the document's size inside it. Under legacy a table starts from the document's defaults.
 * InheritedPropertiesTest covers each inherited text property from each kind of block.
 */
class TableInheritsFromBlockTest extends TestCase
{

	use DrawnStyles;

	/**
	 * Documents with {A} for the declarations of what the table is in and {D} for those of the cell looked at, which
	 * holds qq then, on a line of its own, ww
	 */
	const CONTEXTS = [
		'block' => '<div style="{A}"><table><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></div><p>zz</p>',
		'list item' => '<ul><li style="{A}"><table><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></li></ul><p>zz</p>',
		'positioned block' => '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; {A}"><table><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></div><p>zz</p>',
		'kept block' => '{FILLER}<div style="page-break-inside: avoid; {A}"><table><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></div><p>zz</p>',
		'cell' => '<table><tr><td style="{A}"><table><tr><td style="width: 80mm; {D}">qq<br>ww</td></tr></table></td></tr></table><p>zz</p>',
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
	 * What legacy hands a table's cells from what the table is in: the direction of a block in the flow, and the
	 * alignment and direction of a cell
	 */
	const LEGACY_CARRIED = [
		'block' => ['DIRECTION'],
		'list item' => ['DIRECTION'],
		'kept block' => ['DIRECTION'],
		'cell' => ['TEXT-ALIGN', 'DIRECTION'],
	];

	/**
	 * The cell's lines are laid out as when it sets the value itself, or under legacy as when nothing sets it, but for
	 * what legacy hands on too
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

		$legacyCarries = array_key_exists($context, self::LEGACY_CARRIED) && in_array($property, self::LEGACY_CARRIED[$context], true);
		if ($mode === CssMode::STANDARD || $legacyCarries) {
			$this->assertEquals($own, $inherited);
		} else {
			$this->assertEquals($initial, $inherited);
		}
	}

	/**
	 * Each of text-align, line-height and direction from each kind of block, under both modes
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
					if ($context === 'cell' && $property === 'DIRECTION') {
						continue;
					}
					$data[$mode . ': ' . $context . ': ' . $property] = [$mode, $context, $property];
				}
			}
		}

		return $data;
	}

	/**
	 * A table in a block that sets line-height: normal is laid out as one in a block that sets none, with the table's
	 * line-height of 1.2
	 */
	public function testALineHeightOfNormalKeepsTheTablesOwn()
	{
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach (['block', 'list item', 'positioned block', 'cell'] as $context) {
				$this->assertEquals(
					$this->drawnBoxes($mode, $context, '', 'line-height: 1.2'),
					$this->drawnBoxes($mode, $context, 'line-height: normal', ''),
					$mode . ': ' . $context
				);
			}
		}
	}

	/**
	 * A table's font size is relative to the block's or the cell's it is in, and rem to the document's, whatever
	 * size the table sets
	 *
	 * @dataProvider fontSizes
	 *
	 * @param string $html
	 * @param float $standard The size qq is drawn at under standard, in points
	 * @param float $legacy The size under legacy
	 */
	public function testATablesFontSizeIsRelativeToWhatItIsIn($html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

			$this->assertEqualsWithDelta($expected, $this->keyedByText($mpdf, $mpdf->drawnFontSize)['qq'], 0.001, $mode);
		}
	}

	/**
	 * Percentages, ems and rems on tables and cells, in blocks and cells, with the near misses
	 *
	 * @return array[]
	 */
	public function fontSizes()
	{
		return [
			'a percentage of the block' => ['<div style="font-size: 20pt"><table style="font-size: 80%"><tr><td>qq</td></tr></table></div>', 16.0, 8.8],
			'ems of the block' => ['<div style="font-size: 20pt"><table style="font-size: 1.5em"><tr><td>qq</td></tr></table></div>', 30.0, 16.5],
			'a percentage of the list item' => ['<ul><li style="font-size: 20pt"><table style="font-size: 50%"><tr><td>qq</td></tr></table></li></ul>', 10.0, 5.5],
			'a percentage of the cell' => ['<table><tr><td style="font-size: 20pt"><table style="font-size: 50%"><tr><td>qq</td></tr></table></td></tr></table>', 10.0, 5.5],
			'a percentage of a span' => ['<span style="font-size: 20pt">zz<table style="font-size: 50%"><tr><td>qq</td></tr></table></span>', 10.0, 5.5],
			'the block\'s size, inherited' => ['<div style="font-size: 20pt"><table><tr><td>qq</td></tr></table></div>', 20.0, 11.0],
			'rem in a cell of a sized table' => ['<table style="font-size: 20pt"><tr><td style="font-size: 1.5rem">qq</td></tr></table>', 16.5, 30.0],
			'rem in a table in a sized block' => ['<div style="font-size: 20pt"><table><tr><td style="font-size: 1rem">qq</td></tr></table></div>', 11.0, 11.0],
			'rem on a table' => ['<div style="font-size: 20pt"><table style="font-size: 2rem"><tr><td>qq</td></tr></table></div>', 22.0, 22.0],
			'rem in a nested table' => ['<table style="font-size: 20pt"><tr><td><table><tr><td style="font-size: 1rem">qq</td></tr></table></td></tr></table>', 11.0, 20.0],
			'rem in a cell follows html' => ['<style>html { font-size: 16pt; }</style><table style="font-size: 8pt"><tr><td style="font-size: 1rem">qq</td></tr></table>', 16.0, 8.0],
			'near miss: a size in points on the table' => ['<div style="font-size: 20pt"><table style="font-size: 8pt"><tr><td>qq</td></tr></table></div>', 8.0, 8.0],
			'near miss: a block after the one that sets the size' => ['<div style="font-size: 20pt">zz</div><div><table style="font-size: 50%"><tr><td>qq</td></tr></table></div>', 5.5, 5.5],
		];
	}

	/**
	 * A th in a table in a right-aligned block or cell is right-aligned, and centred where nothing sets an alignment
	 *
	 * @dataProvider headerCells
	 *
	 * @param string $html
	 * @param string $standard Where qq is drawn under standard: L, C or R
	 * @param string $legacy Where it is drawn under legacy
	 */
	public function testAHeaderCellTakesTheAlignmentOfTheBlock($html, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
			$qq = $this->keyedByText($mpdf, $mpdf->drawnBoxes)['qq'];
			$reference = $this->keyedByText($mpdf, $mpdf->drawnBoxes)['zz'];

			$this->assertSame($expected, $this->alignment($qq, $reference), $mode);
		}
	}

	/**
	 * Header cells in right-aligned blocks and cells, with the near misses. Each table has a row of three cells, 20mm
	 * wide, whose middle cell holds zz centred, above the th holding qq that spans them
	 *
	 * @return array[]
	 */
	public function headerCells()
	{
		$table = '<table><tr><td style="width: 20mm">&nbsp;</td><td style="width: 20mm; text-align: center">zz</td><td style="width: 20mm">&nbsp;</td></tr>'
			. '<tr><th colspan="3" {TH}>qq</th></tr></table>';

		return [
			'a th in a right-aligned block' => ['<div style="text-align: right">' . strtr($table, ['{TH}' => '']) . '</div>', 'R', 'C'],
			'a th in a right-aligned cell' => ['<table><tr><td style="text-align: right">' . strtr($table, ['{TH}' => '']) . '</td></tr></table>', 'R', 'C'],
			'near miss: a th in a block that sets no alignment' => ['<div>' . strtr($table, ['{TH}' => '']) . '</div>', 'C', 'C'],
			'near miss: a th that sets its own alignment' => ['<div style="text-align: right">' . strtr($table, ['{TH}' => 'style="text-align: left"']) . '</div>', 'L', 'L'],
		];
	}

	/**
	 * Where the lines of the cell's text are drawn, and where the paragraph after it is
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $block The declarations of what the table is in
	 * @param string $cell The declarations of the cell
	 *
	 * @return array[] The page, left and right edges and top of qq, ww and zz, rounded
	 */
	private function drawnBoxes($mode, $context, $block, $cell)
	{
		$html = strtr(self::CONTEXTS[$context], [
			'{A}' => $block,
			'{D}' => $cell,
			// Too little of the first page is left for the kept block, which is laid out again on the second
			'{FILLER}' => str_repeat('<p>filler</p>', 44),
		]);
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);

		return array_map(function ($box) {
			return array_map(function ($value) {
				return round($value, 3);
			}, $box);
		}, array_intersect_key($boxes, array_flip(['qq', 'ww', 'zz'])));
	}

	/**
	 * Where a piece of text sits in a cell as wide as the row above it, whose middle cell is centred on the row
	 *
	 * @param array $box What the piece of text was drawn in: page, left, right, top
	 * @param array $reference The box of the text in the middle cell of the row above
	 *
	 * @return string L, C or R
	 */
	private function alignment(array $box, array $reference)
	{
		$centre = ($box[1] + $box[2]) / 2;
		$referenceCentre = ($reference[1] + $reference[2]) / 2;
		if (abs($centre - $referenceCentre) < 1) {
			return 'C';
		}

		return $centre < $referenceCentre ? 'L' : 'R';
	}
}
