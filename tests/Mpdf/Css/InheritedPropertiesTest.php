<?php

namespace Mpdf\Css;

/**
 * The text state a block inherits from the block stack
 */
class InheritedPropertiesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider providerBlockTextState
	 *
	 * @param array $blocks A block stack
	 * @param int $level
	 * @param array|null $expected
	 */
	public function testBlockTextState(array $blocks, $level, $expected)
	{
		$this->assertSame($expected, InheritedProperties::blockTextState($blocks, $level));
	}

	/**
	 * @return array[] Block stacks, the level read, and the text state a block opened inside that level inherits
	 */
	public function providerBlockTextState()
	{
		$own = ['family' => 'serif'];
		$aside = ['family' => 'sans'];

		return [
			'the block\'s own state' => [[1 => ['InlineProperties' => $own]], 1, $own],
			'the inline elements set aside on the block' => [
				[1 => ['InlineProperties' => $own, 'openInline' => ['properties' => [], 'state' => $aside]]],
				1,
				$aside,
			],
			'a block with no saved state' => [[1 => []], 1, null],
			'below the document\'s block' => [[0 => ['InlineProperties' => $own]], -1, null],
		];
	}

}
