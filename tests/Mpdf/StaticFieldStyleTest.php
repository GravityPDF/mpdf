<?php

namespace Mpdf;

/**
 * A form field drawn into the page takes its CSS text colour, background and border (#432)
 */
class StaticFieldStyleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const STYLE = 'font-size: 14pt; color: #c00000; background-color: #ffffcc; border: 0.5mm solid #0000ff';

	/**
	 * The issue's text input is drawn in dark red on pale yellow, inside a 0.5mm blue border
	 */
	public function testTheIssuesTextInputTakesItsStyle()
	{
		$page = $this->page('<input type="text" name="s" value="Styled" style="' . self::STYLE . '" />');

		$this->assertMatchesRegularExpression(
			'/1\.417 w\n0\.000 0\.000 1\.000 RG\n1\.000 1\.000 0\.800 rg\n[\d. -]+ re B q 0\.753 0\.000 0\.000 rg  0 Tr BT [\d. ]+ Td  \(Styled\) Tj/',
			$page
		);
	}

	/**
	 * Every kind of field is filled and outlined as its CSS says, and writes its text in its CSS colour
	 *
	 * @dataProvider fields
	 *
	 * @param string $field
	 * @param bool $text whether the field writes text in its own colour
	 */
	public function testEachFieldTakesItsStyle($field, $text)
	{
		$page = $this->page($field);

		$this->assertStringContainsString("1.417 w\n0.000 0.000 1.000 RG\n1.000 1.000 0.800 rg\n", $page);
		if ($text) {
			$this->assertStringContainsString('0.753 0.000 0.000 rg  0 Tr BT', $page);
		}
	}

	/**
	 * One field of each kind, styled as the issue's text input
	 *
	 * @return mixed[][]
	 */
	public function fields()
	{
		$style = ' style="' . self::STYLE . '"';

		return [
			'textarea' => ['<textarea name="t"' . $style . '>Styled</textarea>', true],
			'select' => ['<select name="s"' . $style . '><option>Styled</option></select>', true],
			'submit button' => ['<input type="submit" name="b" value="Styled"' . $style . ' />', true],
			'checkbox' => ['<input type="checkbox" name="c" value="y" checked="checked"' . $style . ' />', false],
			'radio button' => ['<input type="radio" name="r" value="y" checked="checked"' . $style . ' />', false],
			'style sheet' => ['<style>input.styled { ' . self::STYLE . ' }</style><input type="text" name="s" value="Styled" class="styled" />', true],
		];
	}

	/**
	 * A field without CSS keeps mPDF's near-white fill, black text and 0.2mm border in the colour already set
	 */
	public function testAFieldWithoutStyleKeepsTheDefaults()
	{
		$page = $this->page('<input type="text" name="s" value="Plain" />');

		$this->assertMatchesRegularExpression('/0\.980 g\n[\d. -]+ re B q 0\.000 g  0 Tr BT [\d. ]+ Td  \(Plain\) Tj/', $page);
		$this->assertLineIs('0.567', null, $page);
	}

	/**
	 * A border colour, width or style given on its own changes only that part of the default border
	 *
	 * @dataProvider longhands
	 *
	 * @param string $style
	 * @param string $width the line width the border is drawn with, in points
	 * @param string|null $color the stroke colour set for it, or null for none
	 */
	public function testABorderLonghandKeepsTheRestOfTheDefault($style, $width, $color)
	{
		$page = $this->page('<input type="text" name="s" value="Part" style="' . $style . '" />');

		$this->assertMatchesRegularExpression('/0\.980 g\n[\d. -]+ re B /', $page);
		$this->assertLineIs($width, $color, $page);
	}

	/**
	 * Border longhands given without the shorthand
	 *
	 * @return mixed[][]
	 */
	public function longhands()
	{
		return [
			'colour' => ['border-color: #0000ff', '0.567', '0.000 0.000 1.000'],
			'width' => ['border-width: 0.5mm', '1.417', null],
			'style' => ['border-style: dashed', '0.567', null],
		];
	}

	/**
	 * A border of style none, or of no width, is not drawn, and the field is only filled
	 *
	 * @dataProvider noBorders
	 *
	 * @param string $style
	 */
	public function testAFieldWithoutABorderIsOnlyFilled($style)
	{
		$page = $this->page('<input type="text" name="s" value="Bare" style="' . $style . '" />'
			. '<textarea name="t" style="' . $style . '">Bare</textarea>');

		$this->assertSame(2, preg_match_all('/ re f\b/', $page));
		$this->assertStringNotContainsString(' re B', $page);
	}

	/**
	 * Ways CSS takes a border away
	 *
	 * @return string[][]
	 */
	public function noBorders()
	{
		return [
			'none' => ['border: none'],
			'zero' => ['border: 0'],
			'zero width' => ['border-width: 0'],
			'hidden' => ['border-style: hidden'],
		];
	}

	/**
	 * A disabled field keeps its grey fill and text, and a read-only one its grey fill, whatever the CSS says
	 */
	public function testDisabledAndReadOnlyGreysWin()
	{
		$page = $this->page('<input type="text" name="d" value="Disabled" disabled="disabled" style="' . self::STYLE . '" />'
			. '<input type="text" name="r" value="Read" readonly="readonly" style="' . self::STYLE . '" />');

		$this->assertMatchesRegularExpression('/0\.882 g\n[\d. -]+ re B q 0\.498 g  0 Tr BT [\d. ]+ Td  \(Disabled\) Tj/', $page);
		$this->assertMatchesRegularExpression('/0\.882 g\n[\d. -]+ re B q 0\.753 0\.000 0\.000 rg  0 Tr BT [\d. ]+ Td  \(Read\) Tj/', $page);
	}

	/**
	 * Asserts the only line widths a page sets are mPDF's 0.2mm and the one given, and the only colour it strokes
	 * with, other than the black it starts in, is the one given
	 *
	 * @param string $width in points
	 * @param string|null $color
	 * @param string $page
	 */
	private function assertLineIs($width, $color, $page)
	{
		preg_match_all('/^([\d.]+) w$/m', $page, $widths);
		preg_match_all('/^([\d. ]+) RG$/m', $page, $colors);

		$this->assertSame(array_values(array_unique(['0.567', $width])), array_values(array_unique($widths[1])));
		$this->assertSame($color === null ? [] : [$color], array_values(array_unique($colors[1])));
	}

	/**
	 * The page a field is drawn into, without active forms
	 *
	 * @param string $field
	 *
	 * @return string
	 */
	private function page($field)
	{
		$pages = $this->pages($this->render('<form>' . $field . '</form>', ['useActiveForms' => false]));

		return $pages[0];
	}

}
