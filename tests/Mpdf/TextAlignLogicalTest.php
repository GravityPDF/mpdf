<?php

namespace Mpdf;

use Mpdf\Css\TextAlign;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * text-align: start, end and match-parent, which mPDF reads under CssMode::STANDARD only (#607)
 */
class TextAlignLogicalTest extends TestCase
{

	use DrawnStyles;

	/**
	 * A logical alignment sets the text where the physical one it stands for does, in a block and in a cell
	 *
	 * @dataProvider alignments
	 *
	 * @param string $html Draws the text qq
	 * @param string $like The same document with the physical alignment it should match
	 */
	public function testALogicalAlignmentMatchesItsPhysicalOne($html, $like)
	{
		$this->assertSame($this->leftEdge($like), $this->leftEdge($html));
	}

	/**
	 * Blocks and cells in each direction, with the physical alignment each should give
	 *
	 * @return array[]
	 */
	public function alignments()
	{
		$block = '<div style="width: 100mm; {S}">qq</div>';
		$cell = '<table style="width: 100mm"><tr><td style="{S}">qq</td></tr></table>';
		$rows = [];
		foreach (['block' => $block, 'cell' => $cell] as $name => $html) {
			$rows += [
				$name . ': start in ltr' => [strtr($html, ['{S}' => 'text-align: start']), strtr($html, ['{S}' => 'text-align: left'])],
				$name . ': end in ltr' => [strtr($html, ['{S}' => 'text-align: end']), strtr($html, ['{S}' => 'text-align: right'])],
				$name . ': start in rtl' => [strtr($html, ['{S}' => 'direction: rtl; text-align: start']), strtr($html, ['{S}' => 'direction: rtl; text-align: right'])],
				$name . ': end in rtl' => [strtr($html, ['{S}' => 'direction: rtl; text-align: end']), strtr($html, ['{S}' => 'direction: rtl; text-align: left'])],
			];
		}

		return $rows + [
			'end is taken against the direction of the element that inherits it' => [
				'<div style="width: 100mm; text-align: end"><p style="direction: rtl">qq</p></div>',
				'<div style="width: 100mm"><p style="direction: rtl; text-align: left">qq</p></div>',
			],
			'match-parent takes the parent\'s end against the parent\'s direction' => [
				'<div style="width: 100mm; text-align: end"><p style="direction: rtl; text-align: match-parent">qq</p></div>',
				'<div style="width: 100mm"><p style="direction: rtl; text-align: right">qq</p></div>',
			],
			'match-parent takes the parent\'s start in rtl' => [
				'<div style="width: 100mm; direction: rtl"><p style="direction: ltr; text-align: match-parent">qq</p></div>',
				'<div style="width: 100mm; direction: rtl"><p style="direction: ltr; text-align: right">qq</p></div>',
			],
			'match-parent takes a physical alignment as it is' => [
				'<div style="width: 100mm; text-align: center"><p style="direction: rtl; text-align: match-parent">qq</p></div>',
				'<div style="width: 100mm"><p style="direction: rtl; text-align: center">qq</p></div>',
			],
			'match-parent hands its resolved value on' => [
				'<div style="width: 100mm; text-align: end"><div style="text-align: match-parent"><p style="direction: rtl">qq</p></div></div>',
				'<div style="width: 100mm"><p style="direction: rtl; text-align: right">qq</p></div>',
			],
			'a cell takes match-parent from its row' => [
				'<table style="width: 100mm"><tr style="text-align: end"><td style="direction: rtl; text-align: match-parent">qq</td></tr></table>',
				'<table style="width: 100mm"><tr><td style="direction: rtl; text-align: right">qq</td></tr></table>',
			],
			'a cell inherits end from its table' => [
				'<table style="width: 100mm; text-align: end"><tr><td>qq</td></tr></table>',
				'<table style="width: 100mm"><tr><td style="text-align: right">qq</td></tr></table>',
			],
		];
	}

	/**
	 * Legacy mode reads none of them, and sets the text where it always did
	 */
	public function testLegacyReadsNoLogicalAlignment()
	{
		$legacy = ['cssMode' => CssMode::LEGACY];

		$this->assertSame(
			$this->leftEdge('<div style="width: 100mm">qq</div>', $legacy),
			$this->leftEdge('<div style="width: 100mm; text-align: end">qq</div>', $legacy)
		);
		$this->assertSame(
			$this->leftEdge('<table style="width: 100mm"><tr><td>qq</td></tr></table>', $legacy),
			$this->leftEdge('<table style="width: 100mm"><tr><td style="text-align: end">qq</td></tr></table>', $legacy)
		);
	}

	/**
	 * The text of an end-aligned block is set against its right edge
	 */
	public function testEndSetsTheTextAgainstTheRightEdge()
	{
		$this->assertGreaterThan(
			$this->leftEdge('<div style="width: 100mm">qq</div>') + 80,
			$this->leftEdge('<div style="width: 100mm; text-align: end">qq</div>')
		);
	}

	/**
	 * physical() gives right for end in ltr and start in rtl, and matchParent() resolves the parent's value
	 */
	public function testTheResolution()
	{
		$this->assertSame('L', TextAlign::physical('start', null));
		$this->assertSame('R', TextAlign::physical('END', 'ltr'));
		$this->assertSame('R', TextAlign::physical('start', 'rtl'));
		$this->assertSame('L', TextAlign::physical('end', 'RTL'));

		$this->assertSame('left', TextAlign::matchParent([]));
		$this->assertSame('right', TextAlign::matchParent(['TEXT-ALIGN' => 'end']));
		$this->assertSame('left', TextAlign::matchParent(['TEXT-ALIGN' => 'end', 'DIRECTION' => 'rtl']));
		$this->assertSame('justify', TextAlign::matchParent(['TEXT-ALIGN' => 'justify', 'DIRECTION' => 'rtl']));
	}

	/**
	 * @param string $html Draws the text qq
	 * @param array $config
	 *
	 * @return float Where qq starts, in mm
	 */
	private function leftEdge($html, array $config = [])
	{
		$mpdf = $this->drawDocument($html, $config);

		return round($this->keyedByText($mpdf, $mpdf->drawnBoxes)['qq'][1], 3);
	}
}
