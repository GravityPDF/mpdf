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
	 * The shorthand hands the marker over with its U+, as the longhand does
	 *
	 * @dataProvider providerShorthands
	 *
	 * @param string $cssMode
	 * @param string $listStyle The list's list-style
	 * @param string $type The list-style-type the marker should be given
	 */
	public function testShorthandKeepsTheUPrefix($cssMode, $listStyle, $type)
	{
		$mpdf = new ListMarkerRecordingMpdf(['cssMode' => $cssMode]);
		$mpdf->WriteHTML('<ul style="list-style: ' . $listStyle . '"><li>One</li><li>Two</li></ul>');

		$this->assertSame([$type, $type], $mpdf->markerTypes);
	}

	/**
	 * @return array[] CSS modes, list-style shorthands and the list-style-type each should give
	 */
	public function providerShorthands()
	{
		$cases = [];
		foreach ([CssMode::LEGACY, CssMode::STANDARD] as $cssMode) {
			$cases[$cssMode . ', capitals'] = [$cssMode, 'U+2022', 'u+2022'];
			$cases[$cssMode . ', lowercase hex digits'] = [$cssMode, 'u+25ba', 'u+25ba'];
			$cases[$cssMode . ', with a position'] = [$cssMode, 'U+25BA inside', 'u+25ba'];
		}

		return $cases;
	}

	/**
	 * The shorthand draws the same page as the longhand
	 *
	 * @dataProvider providerCssModes
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
	public function providerCssModes()
	{
		return [
			'legacy' => [CssMode::LEGACY],
			'standard' => [CssMode::STANDARD],
		];
	}

}
