<?php

namespace Mpdf;

/**
 * An element nested inside another of the same name ends where its own end tag is, not the outer one's, where
 * AdjustHTML() reads whole elements: inline SVG and tables with captions
 */
class NestedSameNameElementTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';

	/**
	 * An SVG nested inside an inline SVG is drawn as part of it, and what follows it in the outer SVG is drawn too
	 */
	public function testANestedSvgStaysInsideTheOuterOne()
	{
		$mpdf = $this->drawDocument('<p>Before</p><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">'
			. '<svg width="20" height="20"><rect width="10" height="10"/></svg>'
			. '<rect fill="red" x="20" width="10" height="10"/></svg><p>After</p>');

		$this->assertSame(['Before', 'After'], array_map('trim', $mpdf->drawnText));
		$this->assertCount(1, $mpdf->formobjects);

		$image = reset($mpdf->formobjects);
		$this->assertStringContainsString(self::RED, $image['data']);
	}

	/**
	 * The caption of a table with another table in a cell is drawn below the whole table with caption-side: bottom,
	 * not inside the cell after the inner table
	 */
	public function testACaptionBelowATableWithANestedTable()
	{
		$mpdf = $this->drawDocument('<table border="1"><caption style="caption-side: bottom">Caption</caption>'
			. '<tr><td><table><tr><td>Inner</td></tr></table></td><td>Cell</td></tr></table><p>After</p>');

		$this->assertSame(['Inner', 'Cell', 'Caption', 'After'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * The caption of a table nested in a cell of another stays with the nested table
	 */
	public function testACaptionOfANestedTable()
	{
		$mpdf = $this->drawDocument('<table border="1"><tr><td><table><caption style="caption-side: bottom">Caption</caption>'
			. '<tr><td>Inner</td></tr></table></td><td>Cell</td></tr></table><p>After</p>');

		$this->assertSame(['Inner', 'Caption', 'Cell', 'After'], array_map('trim', $mpdf->drawnText));
	}

}
