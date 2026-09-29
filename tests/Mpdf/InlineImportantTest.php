<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Inline styles with !important.
 *
 * The flag is stripped as it is from a stylesheet, so a declaration draws the same with it as without it rather than
 * shorthand expansion counting the flag as one of the values.
 */
class InlineImportantTest extends TestCase
{

	use PageStreams;

	/**
	 * An element draws the same with the flag as without it, and differently from one with no style
	 *
	 * @dataProvider declarations
	 *
	 * @param string $html A document with a %s where the element's style goes
	 * @param string $flagged
	 * @param string $plain The same declarations without the flag
	 */
	public function testTheFlagChangesNothingThatIsDrawn($html, $flagged, $plain)
	{
		$drawnPlain = $this->pages($this->render(sprintf($html, $plain)));

		$this->assertNotSame($this->pages($this->render(sprintf($html, ''))), $drawnPlain);
		$this->assertSame($drawnPlain, $this->pages($this->render(sprintf($html, $flagged))));
	}

	/**
	 * A document, declarations with the flag that each change what it draws, and the same declarations without it
	 *
	 * @return array[]
	 */
	public function declarations()
	{
		$block = '<div style="%s">Styled</div><p>After</p>';

		return [
			'a border shorthand' => [
				$block,
				'border:1px solid #f00 !important',
				'border:1px solid #f00',
			],
			'a two-value padding shorthand' => [
				$block,
				'background-color:#ff0; padding:5mm 10mm !important',
				'background-color:#ff0; padding:5mm 10mm',
			],
			'a font size' => [
				$block,
				'font-size:20pt !important',
				'font-size:20pt',
			],
			'an image height and vertical alignment (mpdf/mpdf#1707)' => [
				'<p>Before <img src="' . $this->backgroundImage() . '" style="%s"> after</p>',
				'width:24px; height:22px !important; vertical-align:middle !important',
				'width:24px; height:22px; vertical-align:middle',
			],
		];
	}

}
