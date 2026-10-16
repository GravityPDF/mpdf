<?php

namespace Mpdf;

/**
 * An SVG <use>, <tref> or gradient finds the element its xlink:href names by its id attribute alone, whatever the
 * other attributes hold, and an SVG the parser cannot read to its end is reported
 */
class SvgReferenceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const OPEN = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="40">';

	const RED = '1.000 0.000 0.000 rg';

	const BLUE = '0.000 0.000 1.000 rg';

	/**
	 * The drawing an SVG becomes, as its form XObject's content
	 *
	 * @param string $svg
	 * @param array $config
	 *
	 * @return string
	 */
	private function drawn($svg, array $config = [])
	{
		$mpdf = new Mpdf($config + ['mode' => 'c']);
		$mpdf->WriteHTML($svg);

		$this->assertCount(1, $mpdf->formobjects);
		$form = reset($mpdf->formobjects);

		return $form['data'];
	}

	/**
	 * A <use>, empty or closed by its own end tag, with a ">" in another attribute
	 *
	 * @return array[]
	 */
	public static function uses()
	{
		return [
			'empty' => ['<use data-note="a > b" xlink:href="#r" x="50"/>'],
			'with an end tag' => ['<use data-note="a > b" xlink:href="#r" x="50"></use>'],
			'with a ">" after the reference' => ['<use xlink:href="#r" data-note="a > b" x="50"/>'],
		];
	}

	/**
	 * The element is drawn, moved by the <use>'s x
	 *
	 * @dataProvider uses
	 *
	 * @param string $use
	 */
	public function testAUseWithAGreaterThanSignInAnotherAttributeDrawsItsElement($use)
	{
		$drawn = $this->drawn(self::OPEN . '<defs><rect id="r" width="20" height="20" fill="#ff0000"/></defs>' . $use . '</svg>');

		$this->assertStringContainsString(self::RED, $drawn);
		$this->assertStringContainsString('1 0 0 1 37.500 0.000 cm', $drawn);
	}

	/**
	 * A <use> draws the element whose id it names, not an earlier one with that data-id
	 */
	public function testAUseIsNotResolvedToADataId()
	{
		$drawn = $this->drawn(self::OPEN . '<defs><rect data-id="r" width="20" height="20" fill="#ff0000"/>'
			. '<g id="r"><rect width="20" height="20" fill="#0000ff"/></g></defs><use xlink:href="#r"/></svg>');

		$this->assertStringContainsString(self::BLUE, $drawn);
		$this->assertStringNotContainsString(self::RED, $drawn);
	}

	/**
	 * The element a <use> references, holding elements of its own name
	 *
	 * @return array[]
	 */
	public static function nestedTargets()
	{
		$red = '<rect width="20" height="20" fill="#ff0000"/>';
		$blue = '<rect x="30" width="20" height="20" fill="#0000ff"/>';

		return [
			'a <g> in a <g>' => ['<g id="a"><g>' . $red . '</g>' . $blue . '</g>'],
			'two levels' => ['<g id="a"><g><g>' . $red . '</g></g>' . $blue . '</g>'],
			'an empty <g> and a <g> in a <g>' => ['<g id="a"><g/><g>' . $red . '</g>' . $blue . '</g>'],
		];
	}

	/**
	 * A <use> copies the whole of the element it references, not only up to the first end tag of its name
	 *
	 * @dataProvider nestedTargets
	 *
	 * @param string $target
	 */
	public function testAUseCopiesAllOfANestedElement($target)
	{
		$drawn = $this->drawn(self::OPEN . '<defs>' . $target . '</defs><use xlink:href="#a"/></svg>');

		$this->assertStringContainsString(self::RED, $drawn);
		$this->assertStringContainsString(self::BLUE, $drawn);
	}

	/**
	 * A <use> of an inner <g> copies that <g> alone, not the <g> around it or the one after it
	 */
	public function testAUseOfAnInnerGroupCopiesItAlone()
	{
		$drawn = $this->drawn(self::OPEN . '<defs><g><g id="a"><rect width="20" height="20" fill="#ff0000"/></g>'
			. '<rect x="30" width="20" height="20" fill="#0000ff"/></g></defs><use xlink:href="#a"/>'
			. '<g><rect x="60" width="20" height="20" fill="#00ff00"/></g></svg>');

		$this->assertSame(1, substr_count($drawn, self::RED));
		$this->assertStringNotContainsString(self::BLUE, $drawn);
	}

	/**
	 * The <use>'s x and y move the element after its own transform
	 */
	public function testAUsesPositionIsAppliedAfterItsTransform()
	{
		$drawn = $this->drawn(self::OPEN . '<defs><rect id="r" width="20" height="20" fill="#ff0000"/></defs>'
			. '<use data-note="a > b" xlink:href="#r" transform="scale(2)" x="4" y="8"/></svg>');

		$this->assertStringContainsString('2.000 0 0 2.000 0 0 cm  1 0 0 1 3.000 -6.000 cm  q ' . self::RED, $drawn);
	}

	/**
	 * A gradient that links to another, empty or closed by its own end tag, with a ">" in another attribute
	 *
	 * @return array[]
	 */
	public static function gradientLinks()
	{
		return [
			'empty' => ['<linearGradient data-note="a > b" id="g2" xlink:href="#g1"/>'],
			'with an end tag' => ['<linearGradient data-note="a > b" id="g2" xlink:href="#g1"></linearGradient>'],
		];
	}

	/**
	 * The gradient is drawn with the stops of the one it links to
	 *
	 * @dataProvider gradientLinks
	 *
	 * @param string $link
	 */
	public function testAGradientLinkWithAGreaterThanSignInAnotherAttributeIsDrawn($link)
	{
		$drawn = $this->drawn(self::OPEN . '<defs><linearGradient id="g1"><stop offset="0" stop-color="red"/>'
			. '<stop offset="1" stop-color="blue"/></linearGradient>' . $link
			. '</defs><rect width="100" height="40" fill="url(#g2)"/></svg>');

		$this->assertMatchesRegularExpression('/\/Sh\d+ sh/', $drawn);
	}

	/**
	 * A <tref> with a ">" in another attribute draws the text of the <text> whose id it names, not one with that
	 * data-id
	 */
	public function testATrefDrawsTheTextItsIdNames()
	{
		$drawn = $this->drawn(self::OPEN . '<defs><text data-id="t">Wrong</text><text id="t">Right</text></defs>'
			. '<text x="2" y="20"><tref data-note="a > b" xlink:href="#t"/></text></svg>');

		$this->assertStringContainsString('(Right) Tj', $drawn);
		$this->assertStringNotContainsString('Wrong', $drawn);
	}

	/**
	 * Only <pattern> and <marker> elements are dropped, not an element whose name begins the same way and what
	 * follows it
	 */
	public function testAnElementNamedLikeAPatternIsKept()
	{
		$drawn = $this->drawn(self::OPEN . '<markers/><patterns/><rect width="20" height="20" fill="#ff0000"/>'
			. '<marker id="m"><rect width="5" height="5" fill="#0000ff"/></marker>'
			. '<pattern id="p"><rect width="5" height="5" fill="#0000ff"/></pattern></svg>');

		$this->assertStringContainsString(self::RED, $drawn);
		$this->assertStringNotContainsString(self::BLUE, $drawn);
	}

	/**
	 * An empty <pattern> or <marker> is dropped alone, not with what follows it up to the next one's end tag
	 */
	public function testAnEmptyPatternIsDroppedAlone()
	{
		$drawn = $this->drawn(self::OPEN . '<pattern id="p"/><marker id="m" /><rect width="20" height="20" fill="#ff0000"/>'
			. '<marker id="n"><rect width="5" height="5" fill="#0000ff"/></marker>'
			. '<pattern id="q"><rect width="5" height="5" fill="#0000ff"/></pattern></svg>');

		$this->assertStringContainsString(self::RED, $drawn);
		$this->assertStringNotContainsString(self::BLUE, $drawn);
	}

	/**
	 * An SVG that is not well-formed is drawn as far as it could be read, and a warning says why it stopped
	 */
	public function testAnSvgThatIsNotWellFormedIsReported()
	{
		$logger = new TestLogger();
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->setLogger($logger);
		$mpdf->WriteHTML(self::OPEN . '<rect width="20" height="20" fill="#ff0000"/><g></svg>');

		$form = reset($mpdf->formobjects);
		$this->assertStringContainsString(self::RED, $form['data']);
		$this->assertTrue($logger->hasWarningThatContains('SVG file is not well-formed'));
	}

	/**
	 * Under debug an SVG that is not well-formed throws
	 */
	public function testAnSvgThatIsNotWellFormedThrowsUnderDebug()
	{
		$this->expectException(MpdfImageException::class);
		$this->expectExceptionMessage('SVG file is not well-formed');

		$mpdf = new Mpdf(['mode' => 'c', 'debug' => true]);
		$mpdf->WriteHTML(self::OPEN . '<rect width="20" height="20" fill="#ff0000"/><g></svg>');
	}

	/**
	 * A well-formed SVG warns of nothing
	 */
	public function testAWellFormedSvgIsNotReported()
	{
		$logger = new TestLogger();
		$mpdf = new Mpdf(['mode' => 'c', 'debug' => true]);
		$mpdf->setLogger($logger);
		$mpdf->WriteHTML(self::OPEN . '<rect width="20" height="20" fill="#ff0000"/></svg>');

		$this->assertFalse($logger->hasWarningRecords());
	}

}
