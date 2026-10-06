<?php

namespace Mpdf;

/**
 * Under the standard cascade an !important declaration of an @page rule beats a declaration that is not important in a
 * :first, :left, :right or named page rule merged after it, and two important declarations resolve in the order those
 * rules are merged. The legacy cascade merges the rules one over the other as before.
 */
class ImportantPageRuleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * The top margin of the first and the second page
	 *
	 * @dataProvider firstPageProvider
	 *
	 * @param string $cascade
	 * @param string $css
	 * @param float[] $tops The top of the first line on the first two pages
	 * @param array $config
	 */
	public function testTheFirstPage($cascade, $css, $tops, $config = [])
	{
		$mpdf = $this->write($css, $this->story(20), ['cssMode' => $cascade] + $config);

		$this->assertSame($tops, array_slice($this->firstLineTops($mpdf->drawnBoxes), 0, 2, true));
	}

	/**
	 * Rules giving the first page a top margin of its own, with what each cascade makes of them
	 *
	 * @return array[]
	 */
	public function firstPageProvider()
	{
		$important = '@page { margin-top: 30mm !important; } @page :first { margin-top: 10mm; }';
		$firstImportant = '@page { margin-top: 30mm; } @page :first { margin-top: 10mm !important; }';
		$bothImportant = '@page { margin-top: 30mm !important; } @page :first { margin-top: 10mm !important; }';
		$firstWrittenFirst = '@page :first { margin-top: 10mm !important; } @page { margin-top: 30mm !important; }';
		$default = ['defaultCssFile' => __DIR__ . '/../data/css/important-page-default.css'];
		$overDefault = '@page :first { margin-top: 10mm !important; } @page { margin-top: 20mm; }';

		return $this->bothModes([
			'an important @page beats a plain :first' => [$important, [1 => 30.0, 2 => 30.0], [1 => 10.0, 2 => 30.0]],
			'an important :first beats a plain @page' => [$firstImportant, [1 => 10.0, 2 => 30.0], [1 => 10.0, 2 => 30.0]],
		]) + [
			'an important :first beats an important @page (standard)' => [CssMode::STANDARD, $bothImportant, [1 => 10.0, 2 => 30.0]],
			'an important :first written first beats an important @page (standard)' => [CssMode::STANDARD, $firstWrittenFirst, [1 => 10.0, 2 => 30.0]],
			'the default stylesheet\'s important @page beats an important :first (standard)' => [CssMode::STANDARD, $overDefault, [1 => 40.0, 2 => 40.0], $default],
			'the default stylesheet\'s important @page beats an important :first (legacy)' => [CssMode::LEGACY, $overDefault, [1 => 10.0, 2 => 20.0], $default],
		];
	}

	/**
	 * The side margins of a right and a left page, the :left and :right rules turning mirrored margins on. A plain
	 * @page margin is mirrored, so an important margin-left is the inner margin: the left of a right page and the
	 * right of a left page, where it beats the side margin a :left or :right rule gives that edge
	 *
	 * @dataProvider sidesProvider
	 *
	 * @param string $cascade
	 * @param string $css
	 * @param array[] $spans The left and right edges of the lines on the first two pages
	 */
	public function testLeftAndRightPages($cascade, $css, $spans)
	{
		$mpdf = $this->write($css, $this->story(20), ['cssMode' => $cascade]);

		$this->assertSame($spans, array_slice($this->spans($mpdf->drawnBoxes), 0, 2, true));
	}

	/**
	 * Rules giving the right and left pages side margins of their own, with what each cascade makes of them
	 *
	 * @return array[]
	 */
	public function sidesProvider()
	{
		$inner = '@page { margin-left: 30mm !important; margin-right: 10mm; }
			@page :right { margin-left: 20mm; margin-right: 20mm; }
			@page :left { margin-left: 20mm; margin-right: 20mm; }';
		$outer = '@page { margin-left: 10mm; margin-right: 40mm !important; }
			@page :right { margin-right: 20mm; }
			@page :left { margin-left: 20mm; }';
		$pseudoImportant = '@page { margin-left: 30mm !important; margin-right: 10mm !important; }
			@page :left { margin-right: 25mm !important; }';

		return $this->bothModes([
			'an important inner margin' => [$inner, [1 => [30.0, 190.0], 2 => [20.0, 180.0]], [1 => [20.0, 190.0], 2 => [20.0, 190.0]]],
			'an important outer margin' => [$outer, [1 => [10.0, 170.0], 2 => [40.0, 200.0]], [1 => [10.0, 190.0], 2 => [20.0, 200.0]]],
		]) + [
			'an important :left beats an important @page (standard)' => [CssMode::STANDARD, $pseudoImportant, [1 => [30.0, 200.0], 2 => [10.0, 185.0]]],
		];
	}

	/**
	 * The side margins of a named page and of the page after it
	 *
	 * @dataProvider namedPageProvider
	 *
	 * @param string $cascade
	 * @param string $css
	 * @param float[] $lefts The left edge of the lines on the first two pages
	 */
	public function testANamedPage($cascade, $css, $lefts)
	{
		$mpdf = $this->write($css, '<div style="page: story">' . $this->story(20) . '</div>', ['cssMode' => $cascade]);

		$this->assertSame($lefts, array_map(static function ($span) {
			return $span[0];
		}, array_slice($this->spans($mpdf->drawnBoxes), 0, 2, true)));
	}

	/**
	 * Rules giving a named page, and its first page, a left margin of their own, with what each cascade makes of them
	 *
	 * @return array[]
	 */
	public function namedPageProvider()
	{
		$plain = '@page { margin-left: 50mm !important; } @page story { margin-left: 10mm; }';
		$first = '@page story { margin-left: 50mm !important; } @page story :first { margin-left: 10mm; }';
		$named = '@page { margin-left: 50mm !important; } @page story { margin-left: 10mm !important; }';

		return $this->bothModes([
			'an important @page beats a plain named page' => [$plain, [1 => 50.0, 2 => 50.0], [1 => 10.0, 2 => 10.0]],
			'an important named page beats its plain :first' => [$first, [1 => 50.0, 2 => 50.0], [1 => 10.0, 2 => 50.0]],
		]) + [
			'an important named page beats an important @page (standard)' => [CssMode::STANDARD, $named, [1 => 10.0, 2 => 10.0]],
		];
	}

	/**
	 * A document with the style sheet and justified paragraphs, whose lines have been recorded as they were drawn
	 *
	 * @param string $css
	 * @param string $body
	 * @param array $config
	 *
	 * @return TextRecordingMpdf
	 */
	private function write($css, $body, array $config)
	{
		return $this->drawDocument('<style>p { text-align: justify; } ' . $css . '</style>' . $body, $config);
	}

}
