<?php

namespace Mpdf;

/**
 * With forms inactive, a list box is drawn as HTML draws one, as rows of options with the selected ones highlighted,
 * while a drop-down keeps its one option and arrow (GravityPDF/mpdf#440)
 */
class StaticListBoxTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * The fill a selected option's row is painted with
	 */
	const HIGHLIGHT = '0.600 0.749 0.851 rg';

	/**
	 * The attributes that make a select a list box
	 *
	 * @return string[][]
	 */
	public function listBoxes()
	{
		return [
			'size' => ['size="4"'],
			'multiple' => ['multiple'],
			'multiple with a size' => ['multiple size="4"'],
		];
	}

	/**
	 * A list box draws as many options as it has rows, from the first, and no arrow
	 *
	 * @dataProvider listBoxes
	 *
	 * @param string $attributes
	 */
	public function testAListBoxDrawsARowForEachOptionThatFitsAndNoArrow($attributes)
	{
		$mpdf = $this->draw('<select name="fruit" ' . $attributes . '>' . $this->options(['Apple', 'Banana', 'Cherry', 'Damson', 'Elder'], [1]) . '</select>');

		$this->assertSame(['Apple', 'Banana', 'Cherry', 'Damson'], $mpdf->drawnText);
		$this->assertNotContains('czapfdingbats', $mpdf->drawnFontFamily, 'A list box has no arrow');
	}

	/**
	 * Every selected option's row is filled with the highlight, and no other row is
	 */
	public function testEverySelectedRowIsHighlighted()
	{
		$page = $this->page('<select name="fruit" size="4" multiple>' . $this->options(['Apple', 'Banana', 'Cherry', 'Damson', 'Elder'], [1, 3]) . '</select>');

		$this->assertSame(['Banana', 'Damson'], $this->highlightedOptions($page));
	}

	/**
	 * A list box with nothing selected highlights nothing, as a browser does
	 */
	public function testAListBoxWithNothingSelectedHighlightsNothing()
	{
		$page = $this->page('<select name="fruit" size="3">' . $this->options(['Apple', 'Banana'], []) . '</select>');

		$this->assertSame([], $this->highlightedOptions($page));
	}

	/**
	 * The highlight follows the document's colour space, as a PDF/X document keeps to CMYK
	 */
	public function testTheHighlightIsCmykInAPdfxDocument()
	{
		$page = $this->page('<select name="fruit" size="3">' . $this->options(['Apple', 'Banana'], [0]) . '</select>', ['PDFX' => true, 'PDFXversion' => '1a', 'PDFXauto' => true, 'mode' => 'utf-8']);

		$this->assertStringNotContainsString(self::HIGHLIGHT, $page);
		$this->assertMatchesRegularExpression('/[\d.]+ [\d.]+ [\d.]+ [\d.]+ k\n[\d.]+ [\d.]+ [\d.]+ -[\d.]+ re f\n/', $page);
	}

	/**
	 * A list box too short to show its first selected option starts at it
	 */
	public function testAListBoxScrollsToTheFirstSelectedOptionBelowTheLastRow()
	{
		$mpdf = $this->draw('<select name="fruit" size="2" multiple>' . $this->options(['Apple', 'Banana', 'Cherry', 'Damson', 'Elder'], [2, 4]) . '</select>');

		$this->assertSame(['Cherry', 'Damson'], $mpdf->drawnText);
	}

	/**
	 * A list box whose first selected option is already in view is not scrolled
	 */
	public function testAListBoxShowingItsSelectedOptionIsNotScrolled()
	{
		$mpdf = $this->draw('<select name="fruit" size="2">' . $this->options(['Apple', 'Banana', 'Cherry'], [1]) . '</select>');

		$this->assertSame(['Apple', 'Banana'], $mpdf->drawnText);
	}

	/**
	 * A list box is as wide as its widest option, whichever option is selected
	 */
	public function testAListBoxIsAsWideAsItsWidestOption()
	{
		$short = $this->boxWidth('<select name="fruit" size="2">' . $this->options(['Fig', 'Elderberry and apple'], [0]) . '</select>');
		$long = $this->boxWidth('<select name="fruit" size="2">' . $this->options(['Fig', 'Elderberry and apple'], [1]) . '</select>');
		$combo = $this->boxWidth('<select name="fruit">' . $this->options(['Fig', 'Elderberry and apple'], [1]) . '</select>');

		$this->assertEqualsWithDelta($long, $short, 0.001);
		$this->assertEqualsWithDelta($combo, $long, 0.001, 'The list box is as wide as a drop-down showing its widest option');
	}

	/**
	 * A drop-down still draws only its selected option beside an arrow
	 */
	public function testADropDownKeepsItsSelectedOptionAndArrow()
	{
		$mpdf = $this->draw('<select name="fruit">' . $this->options(['Elderberry and apple', 'Fig'], [1]) . '</select>');

		$this->assertSame(['Fig', chr(116)], $mpdf->drawnText);
		$this->assertSame('czapfdingbats', $mpdf->drawnFontFamily[1]);
	}

	/**
	 * Each option is drawn with the OpenType layout its own text was given, so a right-to-left option is shaped
	 */
	public function testEachOptionIsDrawnWithItsOwnLayout()
	{
		$mpdf = $this->draw('<select name="greeting" size="2"><option>مرحبا</option><option selected>سلام</option></select>', ['mode' => 'utf-8']);

		$this->assertCount(2, $mpdf->drawnOTLdata);
		foreach ($mpdf->drawnOTLdata as $OTLdata) {
			$this->assertIsArray($OTLdata);
			$this->assertNotEmpty($OTLdata['GPOSinfo'] + $OTLdata['char_data']);
		}
	}

	/**
	 * Option elements for the given labels
	 *
	 * @param string[] $labels
	 * @param int[] $selected which options are selected
	 *
	 * @return string
	 */
	private function options(array $labels, array $selected)
	{
		$html = '';
		foreach ($labels as $i => $label) {
			$html .= '<option' . (in_array($i, $selected, true) ? ' selected' : '') . '>' . $label . '</option>';
		}

		return $html;
	}

	/**
	 * A static form holding the select, drawn by a document that records each piece of text
	 *
	 * @param string $select
	 * @param mixed[] $config
	 *
	 * @return \Mpdf\TextRecordingMpdf
	 */
	private function draw($select, $config = [])
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c', 'useActiveForms' => false]);
		$mpdf->WriteHTML('<form>' . $select . '</form>');

		return $mpdf;
	}

	/**
	 * The first page's content stream of a static form holding the select
	 *
	 * @param string $select
	 * @param mixed[] $config
	 *
	 * @return string
	 */
	private function page($select, $config = [])
	{
		$pages = $this->pages($this->render('<form>' . $select . '</form>', $config + ['useActiveForms' => false]));

		return $pages[0];
	}

	/**
	 * The options whose baselines lie inside a rectangle filled with the highlight, in the order they are drawn
	 *
	 * @param string $page a content stream
	 *
	 * @return string[]
	 */
	private function highlightedOptions($page)
	{
		$highlights = [];
		$start = strpos($page, self::HIGHLIGHT . "\n");
		if ($start !== false) {
			preg_match_all('/\G[\d.]+ ([\d.]+) [\d.]+ -([\d.]+) re f\n/', $page, $rects, PREG_SET_ORDER, $start + strlen(self::HIGHLIGHT) + 1);
			foreach ($rects as $rect) {
				$highlights[] = [$rect[1] - $rect[2], (float) $rect[1]];
			}
		}

		preg_match_all('/BT [\d.]+ ([\d.]+) Td\s+\(([^)]*)\) Tj/', $page, $texts, PREG_SET_ORDER);
		$options = [];
		foreach ($texts as $text) {
			foreach ($highlights as $highlight) {
				if ($text[1] > $highlight[0] && $text[1] < $highlight[1]) {
					$options[] = $text[2];
				}
			}
		}

		return $options;
	}

	/**
	 * How far the select moves on the text that follows it, in points
	 *
	 * @param string $select
	 *
	 * @return float
	 */
	private function boxWidth($select)
	{
		$this->assertSame(1, preg_match('/BT ([\d.]+) [\d.]+ Td\s+\(END\) Tj/', $this->page($select . 'END'), $after), 'The text after the select should be drawn');

		return (float) $after[1];
	}
}
