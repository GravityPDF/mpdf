<?php

namespace Mpdf;

/**
 * Windows-1252 has no code for a zero-width space (U+200B), so core-font text carries it as
 * Mpdf::CORE_ZERO_WIDTH_SPACE through line breaking and drops it before drawing.
 */
class CoreFontZeroWidthSpaceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const ZWSP = "\xe2\x80\x8b";

	/**
	 * Two words too long for the box break at the zero-width space between them, which is not drawn.
	 */
	public function testALineBreaksAtAZeroWidthSpaceWhichIsNotDrawn()
	{
		$word = str_repeat('abcdefghij', 6);

		$this->assertSame([
			'abcdefghijabcdefghijabc',
			'defghijabcdefghijabcdefg',
			'hijabcdefghij',
			'abcdefghijabcdefghijabc',
			'defghijabcdefghijabcdefg',
			'hijabcdefghij',
		], $this->drawnLines('<div style="width: 40mm">' . $word . self::ZWSP . $word . '</div>'));
	}

	/**
	 * A zero-width space adds nothing to the width of the line it sits on.
	 */
	public function testAZeroWidthSpaceTakesNoWidth()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$width = $mpdf->GetStringWidth('aaaabbbb') + 0.1;

		$this->assertSame(['aaaa' . 'bbbb'], $this->drawnLines('<div style="width: ' . $width . 'mm">aaaa' . self::ZWSP . 'bbbb</div>'));
	}

	/**
	 * A U+001F in the source is dropped rather than taken for the marker, so it neither breaks the line nor is drawn.
	 */
	public function testAUnitSeparatorIsNotTakenForAZeroWidthSpace()
	{
		$lines = $this->drawnLines('<div style="width: 12mm">aaaa' . "\x1f" . 'bbbb</div>');

		$this->assertSame('aaaabbbb', implode('', $lines));
		$this->assertNotSame(['aaaa', 'bbbb'], $lines);
	}

	/**
	 * A table sizes its column by the words either side of a zero-width space, so it has no need to shrink the
	 * text to fit a run that would be wider than the page.
	 */
	public function testATableMeasuresTheWordsEitherSideOfAZeroWidthSpace()
	{
		$word = str_repeat('abcdefghij', 6);
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<table><tr><td>' . $word . self::ZWSP . $word . '</td></tr></table>');

		$this->assertSame([$word, $word], $mpdf->drawnText);
		foreach ($mpdf->drawnFontSize as $size) {
			$this->assertEqualsWithDelta(11, $size, 0.001);
		}
	}

	/**
	 * A heading's bookmark leaves the zero-width space out of its title.
	 */
	public function testAHeadingBookmarkLeavesTheZeroWidthSpaceOut()
	{
		$mpdf = new Mpdf(['mode' => 'c', 'h2bookmarks' => ['H1' => 0]]);
		$mpdf->WriteHTML('<h1>Head' . self::ZWSP . 'ing</h1>');

		$this->assertSame('Heading', $mpdf->BMoutlines[0]['t']);
	}

	/**
	 * @param string $html
	 *
	 * @return string[] The text of each line drawn, in order
	 */
	private function drawnLines($html)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		return $mpdf->drawnText;
	}
}
