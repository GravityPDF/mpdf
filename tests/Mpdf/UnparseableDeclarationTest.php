<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Declarations whose value mPDF cannot read - a CSS function it does not implement, a length it cannot parse or in a
 * unit it does not know, a colour it does not recognise.
 *
 * A browser drops such a declaration, so the value it would have replaced still applies: the default margins, an
 * earlier rule, an earlier declaration in the same block, the inherited colour. mPDF drops it the same way, in
 * stylesheets and style attributes alike, rather than reading it as 0, as black or as pixels.
 */
class UnparseableDeclarationTest extends TestCase
{

	use PageStreams;

	const GREEN = '0.000 1.000 0.000 rg';

	/**
	 * The page's left margin, where a paragraph with no margin of its own starts
	 */
	const LEFT = 15.0;

	/**
	 * The left edge of the text is where the declaration, or what it would have replaced, puts it
	 *
	 * @dataProvider margins
	 *
	 * @param string $html
	 * @param float $expected The left margin of the text, in millimetres, on top of the page's
	 */
	public function testTheMarginIsTheOneThatApplies($html, $expected)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		$this->assertEqualsWithDelta(self::LEFT + $expected, $mpdf->drawnBoxes[0][1], 0.01);
	}

	/**
	 * A document, and the left margin its text should have
	 *
	 * @return array[]
	 */
	public function margins()
	{
		$blockquote = 40 * 25.4 / 96;

		return [
			'calc() in a stylesheet keeps the default margins' => [
				'<style>blockquote { margin: calc(5mm + 5mm); }</style><blockquote>text</blockquote>',
				$blockquote,
			],
			'calc() in a style attribute keeps the default margins' => [
				'<blockquote style="margin: calc(5mm + 5mm)">text</blockquote>',
				$blockquote,
			],
			'var() among lengths keeps the default margins' => [
				'<blockquote style="margin: 1mm 2mm var(--x) 4mm">text</blockquote>',
				$blockquote,
			],
			'a comma for a decimal point keeps the default margins' => [
				'<blockquote style="margin-left: 1,5mm">text</blockquote>',
				$blockquote,
			],
			'a unit mPDF does not know keeps the default margins' => [
				'<blockquote style="margin-left: 5dvw">text</blockquote>',
				$blockquote,
			],
			'calc() keeps an earlier rule' => [
				'<style>p { margin-left: 10mm; } p { margin-left: calc(20mm); }</style><p>text</p>',
				10.0,
			],
			'calc() keeps an earlier declaration in the same attribute' => [
				'<p style="margin-left: 10mm; margin-left: calc(20mm)">text</p>',
				10.0,
			],
			'vw is a hundredth of the page width' => [
				'<p style="margin-left: 5vw">text</p>',
				10.5,
			],
			'Q is a quarter-millimetre' => [
				'<p style="margin-left: 40Q">text</p>',
				10.0,
			],
			'a plus sign' => [
				'<p style="margin-left: +5mm">text</p>',
				5.0,
			],
			'an exponent with a plus sign' => [
				'<p style="margin-left: 1e+1mm">text</p>',
				10.0,
			],
		];
	}

	/**
	 * A font size with a plus sign is read, rather than ignored
	 */
	public function testAFontSizeWithAPlusSignIsRead()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<p style="font-size: +20pt">text</p>');

		$this->assertEquals(20, $mpdf->drawnFontSize[0]);
	}

	/**
	 * The text keeps the colour it inherits, rather than being drawn black
	 *
	 * @dataProvider colours
	 *
	 * @param string $html
	 */
	public function testTheTextKeepsTheInheritedColour($html)
	{
		$colours = $this->textColours($html);

		$this->assertNotEmpty($colours);
		foreach ($colours as $text => $colour) {
			$this->assertSame(self::GREEN, $colour, sprintf('"%s" is drawn in the wrong colour', $text));
		}
	}

	/**
	 * A green block with a paragraph in it given a colour mPDF cannot read
	 *
	 * @return array[]
	 */
	public function colours()
	{
		return [
			'a word that is not a colour, in a stylesheet' => ['<style>div { color: #00ff00; } p { color: bogus; }</style><div><p>text</p></div>'],
			'a word that is not a colour, in a style attribute' => ['<div style="color: #00ff00"><p style="color: bogus">text</p></div>'],
			'var()' => ['<style>div { color: #00ff00; } p { color: var(--c); }</style><div><p>text</p></div>'],
			'a colour function mPDF does not know' => ['<div style="color: #00ff00"><p style="color: oklch(0.7 0.1 120)">text</p></div>'],
		];
	}

}
