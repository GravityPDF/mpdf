<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The vertical-align keywords middle, text-top, text-bottom, top and bottom on inline text under cssMode standard. Text
 * is aligned by its line-height box: middle puts its middle at half the block's x-height above the baseline, text-top
 * and text-bottom put its top or bottom at the top or bottom of the block's font, and top and bottom at the top or
 * bottom of the line, which grows to hold it. Under cssMode legacy the keywords leave text on the baseline, as before.
 *
 * Most tests read where text is drawn from Cell(): the top of its line and its baseline.
 */
class InlineVerticalAlignTest extends TestCase
{

	use PageStreams;

	/**
	 * A 20pt line holding 7pt text, "qq", aligned by the keyword, and a second line, "zz"
	 */
	const LINE = '<p style="font-size: 20pt">Hxg <span style="font-size: 7pt; vertical-align: %s">qq</span> Hxg<br>zz</p>';

	/**
	 * middle, text-top and text-bottom move the text from the baseline by what the block's font and the text's box give
	 *
	 * @dataProvider providerFontRelativeKeywords
	 *
	 * @param string $keyword
	 * @param string $wrapper The line, inside what %s marks
	 */
	public function testFontRelativeKeywords($keyword, $wrapper)
	{
		$mpdf = $this->draw(sprintf($wrapper, sprintf(self::LINE, $keyword)), CssMode::STANDARD);
		$baselines = $this->baselines($mpdf);
		list($block, $text, $xHeight) = $this->boxes($mpdf);

		$expected = [
			'middle' => $xHeight / 2 - ($text['exttop'] + $text['extbottom']) / 2,
			'text-top' => $block['boxtop'] - $text['exttop'],
			'text-bottom' => $block['boxbottom'] - $text['extbottom'],
		];

		$this->assertGreaterThan(0.1, abs($expected[$keyword]));
		$this->assertEqualsWithDelta($expected[$keyword], $baselines['Hxg'] - $baselines['qq'], 0.001);
	}

	/**
	 * @return array[] The keywords, each in a block and in a table cell
	 */
	public function providerFontRelativeKeywords()
	{
		$cases = [];
		foreach (['middle', 'text-top', 'text-bottom'] as $keyword) {
			$cases[$keyword] = [$keyword, '%s'];
			$cases[$keyword . ' in a table cell'] = [$keyword, '<table><tr><td style="padding: 0">%s</td></tr></table>'];
		}

		return $cases;
	}

	/**
	 * top puts the top of the text's box at the top of the line, and bottom its bottom at the bottom of the line
	 */
	public function testLineRelativeKeywords()
	{
		$mpdf = $this->draw(sprintf(self::LINE, 'top'), CssMode::STANDARD);
		list(, $text) = $this->boxes($mpdf);
		$this->assertEqualsWithDelta($this->tops($mpdf)['qq'], $this->baselines($mpdf)['qq'] - $text['exttop'], 0.001);

		$mpdf = $this->draw(sprintf(self::LINE, 'bottom'), CssMode::STANDARD);
		$this->assertEqualsWithDelta($this->tops($mpdf)['zz'], $this->baselines($mpdf)['qq'] - $text['extbottom'], 0.001);
	}

	/**
	 * Text aligned with the top or bottom of its line makes the line tall enough to hold it
	 *
	 * @dataProvider providerEdgeKeywords
	 *
	 * @param string $keyword
	 */
	public function testEdgeAlignedTextMakesTheLineTaller($keyword)
	{
		$mpdf = $this->draw('<p>aa <span style="line-height: 30mm; vertical-align: ' . $keyword . '">qq</span><br>zz</p>', CssMode::STANDARD);
		$tops = $this->tops($mpdf);

		$this->assertEqualsWithDelta(30.0, $tops['zz'] - $tops['aa'], 0.001);
	}

	/**
	 * @return array[]
	 */
	public function providerEdgeKeywords()
	{
		return ['top' => ['top'], 'bottom' => ['bottom']];
	}

	/**
	 * Beside a taller image, text aligned with the top of the line is at the image's top, and the line is as tall as
	 * without it
	 */
	public function testTopBesideATallerImage()
	{
		$image = '<img src="' . __DIR__ . '/../data/img/exif-orientation-none.jpg" style="height: 20mm">';
		$mpdf = $this->draw('<p>aa ' . $image . ' <span style="vertical-align: top">qq</span><br>zz</p>', CssMode::STANDARD);
		$without = $this->draw('<p>aa ' . $image . '<br>zz</p>', CssMode::STANDARD);
		list(, $text) = $this->boxes($mpdf, 'qq');

		$this->assertEqualsWithDelta($this->tops($mpdf)['qq'], $this->baselines($mpdf)['qq'] - $text['exttop'], 0.001);
		$this->assertEqualsWithDelta($this->tops($without)['zz'], $this->tops($mpdf)['zz'], 0.001);
	}

	/**
	 * Text inside an element aligned by a keyword moves with it
	 */
	public function testNestedText()
	{
		$mpdf = $this->draw('<p style="font-size: 20pt">Hxg <span style="font-size: 7pt; vertical-align: middle">qq <b>bb</b> <span style="vertical-align: baseline">cc</span></span></p>', CssMode::STANDARD);
		$baselines = $this->baselines($mpdf);

		$this->assertGreaterThan(0.5, $baselines['Hxg'] - $baselines['qq']);
		$this->assertEqualsWithDelta($baselines['qq'], $baselines['bb'], 0.001);
		$this->assertEqualsWithDelta($baselines['qq'], $baselines['cc'], 0.001);
	}

	/**
	 * Values mPDF already read, and the keywords under cssMode legacy, place text as before
	 *
	 * @dataProvider providerUnchanged
	 *
	 * @param string $value
	 * @param string $mode A CssMode value
	 * @param float|null $raised How far "qq" is raised in mm, or null to compare with cssMode legacy
	 */
	public function testUnchanged($value, $mode, $raised)
	{
		$baselines = $this->baselines($this->draw(sprintf(self::LINE, $value), $mode));
		if ($raised === null) {
			$legacy = $this->baselines($this->draw(sprintf(self::LINE, $value), CssMode::LEGACY));
			$raised = $legacy['Hxg'] - $legacy['qq'];
			$this->assertGreaterThan(0.1, abs($raised));
		}

		$this->assertEqualsWithDelta($raised, $baselines['Hxg'] - $baselines['qq'], 0.001);
	}

	/**
	 * @return array[]
	 */
	public function providerUnchanged()
	{
		return [
			'baseline' => ['baseline', CssMode::STANDARD, 0],
			'super' => ['super', CssMode::STANDARD, null],
			'sub' => ['sub', CssMode::STANDARD, null],
			'a length' => ['2mm', CssMode::STANDARD, 2],
			'a percentage' => ['50%', CssMode::STANDARD, null],
			'middle under legacy' => ['middle', CssMode::LEGACY, 0],
			'top under legacy' => ['top', CssMode::LEGACY, 0],
			'text-bottom under legacy' => ['text-bottom', CssMode::LEGACY, 0],
		];
	}

	/**
	 * vertical-align does not apply to a block, which mPDF lays out as inline text in a table cell
	 */
	public function testBlockInATableCell()
	{
		$html = '<table><tr><td>Hxg<div style="vertical-align: %s">qq</div></td></tr></table>';
		$aligned = $this->baselines($this->draw(sprintf($html, 'middle'), CssMode::STANDARD));
		$plain = $this->baselines($this->draw(sprintf($html, 'baseline'), CssMode::STANDARD));

		$this->assertEqualsWithDelta($plain['qq'], $aligned['qq'], 0.001);
	}

	/**
	 * An image on the baseline of an element aligned by a keyword sits on the element's moved baseline
	 */
	public function testImageMovesWithItsElement()
	{
		$html = '<p style="font-size: 20pt">Hxg <span style="font-size: 7pt; vertical-align: %s">qq<img src="' . $this->pngImage() . '" style="height: 2mm"></span></p>';
		$aligned = $this->imageFootAndBaseline(sprintf($html, 'middle'));
		$plain = $this->imageFootAndBaseline(sprintf($html, 'baseline'));

		$this->assertEqualsWithDelta($aligned['qq'], $aligned['image'], 0.01);
		$this->assertGreaterThan($plain['image'] + 1, $aligned['image']);
	}

	/**
	 * The underline, background and border of an element aligned by a keyword move with its text, and the underline of
	 * the block around it stays on the block's baseline
	 */
	public function testDecorationsMoveWithTheirElement()
	{
		$style = 'font-size: 7pt; text-decoration: underline; background-color: #ff0000; border: 0.2mm solid #00ff00';
		$html = '<p style="font-size: 20pt">Hxg <span style="' . $style . '; vertical-align: %s">qq</span></p>';
		$aligned = $this->decorationOffsets(sprintf($html, 'middle'));
		$plain = $this->decorationOffsets(sprintf($html, 'baseline'));

		$this->assertGreaterThan($plain['text'] + 1, $aligned['text']);
		foreach (['underline', 'background', 'border'] as $decoration) {
			$this->assertEqualsWithDelta($plain[$decoration], $aligned[$decoration], 0.01, $decoration . ' does not move with the text');
		}

		$stream = $this->pages($this->render('<p style="font-size: 20pt; text-decoration: underline">Hxg <span style="font-size: 7pt; vertical-align: middle">qq</span></p>'))[0];
		$this->assertSame(2, preg_match_all('/[-\d.]+ ([-\d.]+) m [-\d.]+ [-\d.]+ l S/', $stream, $lines));
		$this->assertEqualsWithDelta($lines[1][0], $lines[1][1], 0.01, 'The block\'s underline moves with the aligned text');
	}

	/**
	 * @param string $html
	 * @param string $mode A CssMode value
	 *
	 * @return TextRecordingMpdf The document, written, with paragraphs that have no margins
	 */
	private function draw($html, $mode)
	{
		$mpdf = new TextRecordingMpdf(['cssMode' => $mode]);
		$mpdf->WriteHTML('<style>p { margin: 0 }</style>' . $html);

		return $mpdf;
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written
	 *
	 * @return float[] Each piece of text drawn, trimmed, and the top of its line, in mm from the top of the page
	 */
	private function tops(TextRecordingMpdf $mpdf)
	{
		return array_combine(array_map('trim', $mpdf->drawnText), $mpdf->drawnY);
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written
	 *
	 * @return float[] Each piece of text drawn, trimmed, and its baseline, in mm from the top of the page
	 */
	private function baselines(TextRecordingMpdf $mpdf)
	{
		$baselines = [];
		foreach ($mpdf->drawnText as $i => $text) {
			$box = $mpdf->drawnLineBoxes[$i];
			$baselines[trim($text)] = $mpdf->drawnY[$i] + $box['glyphYorigin'] - $box['baseline-shift'];
		}

		return $baselines;
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written, whose first line is in the font "Hxg" is drawn in
	 * @param string $text The piece of text whose box to give
	 *
	 * @return array [the box of the block's font, the box of $text, the block's x-height], as _setLineYpos() gives
	 *               boxes, for normal line heights
	 */
	private function boxes(TextRecordingMpdf $mpdf, $text = 'qq')
	{
		$pieces = array_map('trim', $mpdf->drawnText);
		$block = array_search('Hxg', $pieces, true);
		$piece = array_search($text, $pieces, true);
		if ($block === false) {
			$block = $piece;
		}
		$desc = $mpdf->fonts[$mpdf->drawnFontFamily[$block] . $mpdf->drawnFontStyles[$block]]['desc'];
		$normal = 'N';

		$size = $mpdf->drawnFontSize[$block] / Mpdf::SCALE;
		$blockBox = $mpdf->_setLineYpos($size, $desc, $normal);
		$xHeight = $desc['XHeight'] / 1000 * $size;

		$size = $mpdf->drawnFontSize[$piece] / Mpdf::SCALE;
		$textBox = $mpdf->_setLineYpos($size, $desc, $normal);

		return [$blockBox, $textBox, $xHeight];
	}

	/**
	 * @param string $html A document in core fonts with the text "qq" and one image
	 *
	 * @return float[] The baseline of "qq" and the foot of the image, in points up from the foot of the page
	 */
	private function imageFootAndBaseline($html)
	{
		$stream = $this->pages($this->render($html))[0];
		$this->assertSame(1, preg_match('/([-\d.]+) Td\s+\(qq\) Tj/', $stream, $text));
		$this->assertSame(1, preg_match('/[-\d.]+ 0 0 [-\d.]+ [-\d.]+ ([-\d.]+) cm \/I\d+ Do/', $stream, $image));

		return ['qq' => (float) $text[1], 'image' => (float) $image[1]];
	}

	/**
	 * @param string $html A document in core fonts with the text "qq", drawn last on its line, with an underline, and a
	 *                     background and border in other colours
	 *
	 * @return float[] Where "qq" is drawn, and how far above it the decorations of its line are drawn, in points
	 */
	private function decorationOffsets($html)
	{
		$stream = $this->pages($this->render($html))[0];
		$this->assertSame(1, preg_match('/([-\d.]+) Td\s+\(qq\) Tj/', $stream, $text));
		$this->assertGreaterThan(0, preg_match_all('/[-\d.]+ ([-\d.]+) m [-\d.]+ [-\d.]+ l S/', $stream, $lines));
		$offsets = ['text' => (float) $text[1], 'underline' => end($lines[1]) - $text[1]];

		if (preg_match('/1\.000 0\.000 0\.000 rg\s+[-\d.]+ ([-\d.]+) [-\d.]+ [-\d.]+ re f/', $stream, $background)) {
			$offsets['background'] = $background[1] - $text[1];
		}
		if (preg_match('/0\.000 1\.000 0\.000 RG.*?[-\d.]+ ([-\d.]+) m /s', $stream, $border)) {
			$offsets['border'] = $border[1] - $text[1];
		}

		return $offsets;
	}
}
