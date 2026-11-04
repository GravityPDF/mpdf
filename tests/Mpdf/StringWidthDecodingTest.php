<?php

namespace Mpdf;

/**
 * GetStringWidth() decodes a string and measures its code points. A line is handed to it twice, once to
 * find where it breaks and once to draw it, and it now decodes the line only on the first of those. What
 * the decoded run and the string say about a piece of text therefore has to agree: how many characters it
 * has, which of its characters a width or a spacing is charged on, and which font's subset they reach.
 */
class StringWidthDecodingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SHY = "\xc2\xad"; // U+00AD SOFT HYPHEN
	const ZWSP = "\xe2\x80\x8b"; // U+200B ZERO WIDTH SPACE
	const CLEF = "\xf0\x9d\x84\x9e"; // U+1D11E MUSICAL SYMBOL G CLEF, four bytes, a surrogate pair in UTF-16
	const LONG_WORD = 'uncopyrightableness'; // Too long for the share of the table its column would otherwise get
	const GREEK = "\xce\xa9\xce\xbc\xce\xad\xce\xb3\xce\xb1"; // Characters no other text on the page will register

	/**
	 * A string is as wide as its characters measured one at a time. GetCharWidth() takes the other route to
	 * the same widths, a character at a time, so the two agree only if nothing has been dropped from or
	 * doubled in the decoded run.
	 *
	 * @dataProvider strings
	 *
	 * @param string $text
	 */
	public function testAStringIsAsWideAsItsCharactersMeasuredOneAtATime($text)
	{
		$mpdf = $this->mpdf();

		$sum = 0;
		foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
			$sum += $mpdf->GetCharWidth($char, false);
		}

		$this->assertEqualsWithDelta($sum, $mpdf->GetStringWidth($text, false), 1e-9);
		$mpdf->cleanup();
	}

	/**
	 * Text with the characters that a byte count and a character count disagree about
	 *
	 * @return array[]
	 */
	public function strings()
	{
		return [
			'plain' => ['Typographers measure every character'],
			'soft hyphen' => ['extra' . self::SHY . 'ordinarily'],
			'zero width space' => ['one' . self::ZWSP . 'two'],
			'astral' => ['a ' . self::CLEF . ' b'],
			'astral beside a soft hyphen' => [self::CLEF . self::SHY . self::CLEF],
			'outside Latin-1' => ['lines end — kerning pulls AV'],
			'nothing' => [''],
		];
	}

	/**
	 * Letter spacing is charged on every character but the soft hyphens, word spacing on every space, and a
	 * four-byte character counts once. The counts come from the decoded run rather than from byte counts of
	 * the string, so this pins what each of those characters contributes.
	 *
	 * @dataProvider spacedStrings
	 *
	 * @param string $text
	 * @param int $nbCarac Characters letter spacing is charged on
	 * @param int $nbSpaces Spaces word spacing is charged on
	 */
	public function testLetterAndWordSpacingAreChargedPerCharacterAndSpace($text, $nbCarac, $nbSpaces)
	{
		$mpdf = $this->mpdf();
		$plain = $mpdf->GetStringWidth($text, false);

		$mpdf->fixedlSpacing = 0.25;
		$mpdf->minwSpacing = 1.5;

		$this->assertEqualsWithDelta(
			$plain + (($nbCarac + $nbSpaces) * 0.25) + ($nbSpaces * 1.5),
			$mpdf->GetStringWidth($text, false),
			1e-9
		);
		$mpdf->cleanup();
	}

	/**
	 * @return array[]
	 */
	public function spacedStrings()
	{
		return [
			'plain' => ['one two three', 13, 2],
			'soft hyphens do not count' => ['ex' . self::SHY . 'tra' . self::SHY . 'or', 7, 0],
			'an astral character counts once' => ['a ' . self::CLEF . ' b', 5, 2],
			'a zero width space counts' => ['one' . self::ZWSP . 'two', 7, 0],
		];
	}

	/**
	 * A column is no narrower than the longest word any of its cells holds, which the minimum-width pass
	 * finds by measuring each word of each cell, and with overflow:wrap each letter. Give one cell a word
	 * too long for the share of the table it would otherwise get and the column widens to hold it, which
	 * shows in where the prose beside it wraps.
	 *
	 * @dataProvider tables
	 *
	 * @param string $html
	 * @param string[] $expected
	 */
	public function testATableColumnHoldsTheLongestWordOfItsCells($html, $expected)
	{
		$this->assertSame($expected, $this->drawnLines($html));
	}

	/**
	 * @return array[]
	 */
	public function tables()
	{
		$prose = [
			'Typographers measure',
			'every character of a',
			'paragraph before they',
			'decide where its lines',
			'end',
		];

		return [
			'short words' => [$this->table('alpha beta gamma delta'), [
				'alpha beta',
				'gamma delta',
				'Typographers measure every',
				'character of a paragraph before',
				'they decide where its lines end',
			]],
			'one long word' => [$this->table('alpha ' . self::LONG_WORD . ' delta'), array_merge([
				'alpha',
				self::LONG_WORD,
				'delta',
			], $prose)],
			'soft hyphens in the long word' => [$this->table('alpha ' . $this->hyphenated() . ' delta'), array_merge([
				'alpha uncopyrightable-',
				'ness delta',
			], $prose)],
			'joined by a zero width space' => [$this->table('alpha' . self::ZWSP . self::LONG_WORD . self::ZWSP . 'delta'), array_merge([
				'alpha',
				self::LONG_WORD,
				'delta',
			], $prose)],
			'an astral character in the long word' => [$this->table('alpha ' . self::CLEF . self::LONG_WORD . self::CLEF . ' delta'), [
				'alpha',
				self::CLEF . self::LONG_WORD . self::CLEF,
				'delta',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before they',
				'decide where its lines',
				'end',
			]],
			'overflow wrap measures letters' => [$this->table('alpha ' . self::LONG_WORD . ' delta', 'overflow: wrap'), [
				'alpha',
				'uncopyright',
				'ableness',
				'delta',
				'Typographers measure every',
				'character of a paragraph before',
				'they decide where its lines end',
			]],
		];
	}

	/**
	 * Decoding a chunk is also what puts its characters in the current font's subset. The same word twice on
	 * one line in two fonts has to reach both subsets, or one of the embedded fonts comes out short of a
	 * glyph.
	 *
	 * @dataProvider repeatedText
	 *
	 * @param string $html
	 */
	public function testTheSameTextInTwoFontsReachesBothSubsets($html)
	{
		$mpdf = new Mpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML($html);

		$greek = $mpdf->UTF8StringToArray(self::GREEK, false);
		$subsets = [];
		foreach (['dejavusans', 'dejavuserif'] as $font) {
			$subsets[$font] = array_values(array_intersect($mpdf->fonts[$font]['subset'], $greek));
			sort($subsets[$font]);
		}
		$mpdf->cleanup();

		sort($greek);
		$this->assertSame(['dejavusans' => $greek, 'dejavuserif' => $greek], $subsets);
	}

	/**
	 * @return array[]
	 */
	public function repeatedText()
	{
		$both = '<span style="font-family: dejavusans">' . self::GREEK . '</span> '
			. '<span style="font-family: dejavuserif">' . self::GREEK . '</span>';

		return [
			'inline' => ['<p>' . $both . '</p>'],
			'a cell each' => ['<table style="width: 100%"><tr><td>' . $both . '</td><td>' . $both . '</td></tr></table>'],
			'one cell, width to work out' => ['<table><tr><td>' . $both . ' ' . $both . '</td></tr></table>'],
		];
	}

	/**
	 * A two column table whose first cell decides how wide the first column has to be
	 *
	 * @param string $first The first cell's text
	 * @param string $style Extra style for the table
	 *
	 * @return string
	 */
	private function table($first, $style = '')
	{
		return '<table style="width: 100%; ' . $style . '"><tr><td>' . $first . '</td>'
			. '<td>Typographers measure every character of a paragraph before they decide where its lines end</td></tr></table>';
	}

	/**
	 * The long word with soft hyphens, which take no width of their own but offer places to break it
	 *
	 * @return string
	 */
	private function hyphenated()
	{
		return str_replace('|', self::SHY, 'un|co|py|right|able|ness');
	}

	/**
	 * @param string $html
	 *
	 * @return string[] The text of each line, in the order it is drawn in
	 */
	private function drawnLines($html)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'format' => [120, 400]]);
		$mpdf->WriteHTML('<div style="font-family: dejavusans; font-size: 8pt">' . $html . '</div>');
		$lines = $mpdf->drawnText;
		$mpdf->cleanup();

		return $lines;
	}

	/**
	 * @return Mpdf
	 */
	private function mpdf()
	{
		$mpdf = new Mpdf(['mode' => 'utf-8']);
		$mpdf->SetFont('dejavusans', '', 11);

		return $mpdf;
	}

}
