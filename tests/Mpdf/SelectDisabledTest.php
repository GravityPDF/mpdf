<?php

namespace Mpdf;

/**
 * A bare disabled attribute greys out a static select, just as disabled="disabled" does
 */
class SelectDisabledTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * The two ways of writing the attribute
	 *
	 * @return string[][]
	 */
	public function disabledAttributes()
	{
		return [
			'bare' => ['disabled'],
			'with a value' => ['disabled="disabled"'],
		];
	}

	/**
	 * @dataProvider disabledAttributes
	 *
	 * @param string $attribute
	 */
	public function testAStaticSelectIsGreyedOut($attribute)
	{
		$pages = $this->pages($this->render('<form><select name="choice" ' . $attribute . '><option>A</option></select></form>'));

		$this->assertStringContainsString('0.882 g', $pages[0], 'The box should be filled light grey');
		$this->assertStringContainsString('0.498 g', $pages[0], 'The text should be grey');
	}
}
