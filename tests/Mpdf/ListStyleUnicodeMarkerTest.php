<?php

namespace Mpdf;

/**
 * A U+XXXX marker set by the list-style shorthand is the character the list-style-type longhand gives, rather than a
 * decimal number, in both CSS modes.
 */
class ListStyleUnicodeMarkerTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The shorthand draws the same page as the longhand
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testShorthandDrawsWhatTheLonghandDraws($cssMode)
	{
		$list = '<ul style="%s: U+2022"><li>One</li><li>Two</li></ul>';

		$shorthand = $this->pages($this->render(sprintf($list, 'list-style'), ['cssMode' => $cssMode]));
		$longhand = $this->pages($this->render(sprintf($list, 'list-style-type'), ['cssMode' => $cssMode]));

		$this->assertSame($longhand, $shorthand);
		$this->assertNotContains('1.', $this->shownBytes($shorthand[0]));
	}

	/**
	 * @return array[] Each CSS mode
	 */
	public function cssModes()
	{
		return [
			'legacy' => [CssMode::LEGACY],
			'standard' => [CssMode::STANDARD],
		];
	}

}
