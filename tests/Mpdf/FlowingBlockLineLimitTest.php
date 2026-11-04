<?php

namespace Mpdf;

/**
 * WriteFlowingBlock() works out how wide a line may be - the block's width less the first line's
 * text-indent, a dot tab's outdent, and the floats beside the line - once for each line rather than
 * once for each character. Each paragraph here is a single run of text whose lines are not all the
 * same width, so a width kept past the end of the line it was worked out for breaks the lines after
 * it in the wrong places.
 */
class FlowingBlockLineLimitTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider paragraphs
	 *
	 * @param string $html
	 * @param string[] $expected
	 */
	public function testEachLineBreaksAtTheWidthLeftBesideIt($html, $expected)
	{
		$this->assertSame($expected, $this->drawnLines($html));
	}

	/**
	 * Paragraphs whose first lines are narrower, or wider, than the ones after them.
	 *
	 * @return array[]
	 */
	public function paragraphs()
	{
		$text = $this->text();

		return [
			'text-indent narrows only the first line' => ['<p style="text-indent: 30mm">' . $text . '</p>', [
				'Every line of a paragraph is',
				'as wide as the block less whatever stands',
				'beside it, and that changes from one line to',
				'the next: the first line gives up its indent,',
				'and the lines beside a float give up the',
				'width of the float until the text has gone',
				'past the bottom of it, after which the lines',
				'are as wide as the block again and hold',
				'more words than the ones above them did.',
			]],
			'a negative text-indent widens only the first line' => ['<p style="text-indent: -15mm; margin-left: 15mm">' . $text . '</p>', [
				'Every line of a paragraph is as wide',
				'as the block less whatever',
				'stands beside it, and that',
				'changes from one line to',
				'the next: the first line gives',
				'up its indent, and the lines',
				'beside a float give up the',
				'width of the float until the',
				'text has gone past the',
				'bottom of it, after which',
				'the lines are as wide as the',
				'block again and hold more',
				'words than the ones above',
				'them did.',
			]],
			'a float narrows only the lines beside it' => ['<div><img src="' . __DIR__ . '/../data/img/bayeux2.jpg" style="float: right; width: 30mm; height: 25mm" />' . $text . '</div>', [
				'Every line of a paragraph is',
				'as wide as the block less',
				'whatever stands beside it,',
				'and that changes from one',
				'line to the next: the first',
				'line gives up its indent, and',
				'the lines beside a float give up the width of',
				'the float until the text has gone past the',
				'bottom of it, after which the lines are as',
				'wide as the block again and hold more',
				'words than the ones above them did.',
			]],
			'a table cell takes no text-indent, and keeps one width' => ['<table style="width: 80mm"><tr><td style="text-indent: 10mm">' . $text . '</td></tr></table>', [
				'Every line of a paragraph is as wide as the',
				'block less whatever stands beside it, and',
				'that changes from one line to the next: the',
				'first line gives up its indent, and the lines',
				'beside a float give up the width of the float',
				'until the text has gone past the bottom of',
				'it, after which the lines are as wide as the',
				'block again and hold more words than the',
				'ones above them did.',
			]],
		];
	}

	/**
	 * @param string $html
	 *
	 * @return string[] The text of each line, in the order it is drawn in
	 */
	private function drawnLines($html)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'format' => [100, 400], 'margin_left' => 10, 'margin_right' => 10]);
		$mpdf->WriteHTML('<style>body { font-family: dejavuserif; font-size: 10pt }</style>' . $html);
		$lines = $mpdf->drawnText;
		$mpdf->cleanup();

		return $lines;
	}

	/**
	 * Long enough for a dozen lines at this width, in one run.
	 *
	 * @return string
	 */
	private function text()
	{
		return 'Every line of a paragraph is as wide as the block less whatever stands beside it, and that '
			. 'changes from one line to the next: the first line gives up its indent, and the lines beside a '
			. 'float give up the width of the float until the text has gone past the bottom of it, after which '
			. 'the lines are as wide as the block again and hold more words than the ones above them did.';
	}

}
