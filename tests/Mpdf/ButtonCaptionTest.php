<?php

namespace Mpdf;

/**
 * A push button shows its value as its caption whether forms are active or not, as a browser does: an empty value
 * leaves it blank, and no value at all gives it the browser's default caption. Its field name is never shown.
 */
class ButtonCaptionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Each kind of push button with an empty value, with no value, and with a value, and the caption it shows
	 *
	 * @return string[][]
	 */
	public function buttons()
	{
		return [
			'submit with an empty value' => ['<input type="submit" name="save_and_continue" value="" />', ''],
			'reset with an empty value' => ['<input type="reset" name="clear_form" value="" />', ''],
			'button with an empty value' => ['<input type="button" name="run_script" value="" onclick="app.alert(1)" />', ''],
			'unnamed submit with an empty value' => ['<input type="submit" value="" />', ''],
			'submit with no value' => ['<input type="submit" name="save_and_continue" />', 'Submit'],
			'reset with no value' => ['<input type="reset" name="clear_form" />', 'Reset'],
			'button with no value' => ['<input type="button" name="run_script" onclick="app.alert(1)" />', 'Button'],
			'submit with a value' => ['<input type="submit" name="save_and_continue" value="Save" />', 'Save'],
			'reset with a value' => ['<input type="reset" name="clear_form" value="Clear" />', 'Clear'],
			'button with a value' => ['<input type="button" name="run_script" value="Run" onclick="app.alert(1)" />', 'Run'],
		];
	}

	/**
	 * An active button's normal, rollover and down captions are all its caption
	 *
	 * @dataProvider buttons
	 *
	 * @param string $button
	 * @param string $caption
	 */
	public function testActiveButtonIsCaptionedWithItsValue($button, $caption)
	{
		$widget = $this->widget($this->render('<form>' . $button . '</form>', ['mode' => 'c', 'useActiveForms' => true]));

		$this->assertStringContainsString('/CA (' . $caption . ') /RC (' . $caption . ') /AC (' . $caption . ')', $widget);
	}

	/**
	 * An active button's appearance draws the caption a viewer would draw from /CA
	 *
	 * @dataProvider buttons
	 *
	 * @param string $button
	 * @param string $caption
	 */
	public function testActiveButtonAppearanceDrawsItsCaption($button, $caption)
	{
		$pdf = $this->render('<form>' . $button . '</form>', ['mode' => 'c', 'useActiveForms' => true]);

		$this->assertSame(1, preg_match('/\/AP << \/N << \/Push (\d+) 0 R/', $this->widget($pdf), $appearance));
		$this->assertSame($caption, $this->drawnText($this->object($pdf, $appearance[1])));
	}

	/**
	 * A button drawn on the page when forms are not active shows the same caption
	 *
	 * @dataProvider buttons
	 *
	 * @param string $button
	 * @param string $caption
	 */
	public function testStaticButtonDrawsItsCaption($button, $caption)
	{
		$contents = $this->pageContents($this->render('<form>' . $button . '</form>', ['mode' => 'c']));

		$this->assertSame($caption, trim($this->drawnText($contents[0])));
	}

	/**
	 * With an embedded font an empty caption is an empty UTF-16 string, and the appearance draws no text
	 */
	public function testEmptyCaptionInAnEmbeddedFont()
	{
		$pdf = $this->render('<form><input type="submit" name="save_and_continue" value="" /></form>', ['mode' => 'utf-8', 'useActiveForms' => true]);
		$widget = $this->widget($pdf);

		$this->assertStringContainsString("/CA (\xFE\xFF) ", $widget);
		$this->assertSame(1, preg_match('/\/AP << \/N << \/Push (\d+) 0 R/', $widget, $appearance));
		$this->assertSame('', $this->drawnText($this->object($pdf, $appearance[1])));
	}

	/**
	 * An image button shows its icon and no caption, and keeps its field name as /CA
	 */
	public function testImageButtonKeepsItsName()
	{
		$widget = $this->widget($this->render(
			'<form><input type="image" name="go" src="' . $this->pngImage() . '" onclick="app.alert(1)" /></form>',
			['mode' => 'c', 'useActiveForms' => true]
		));

		$this->assertStringContainsString('/TP 1 ', $widget);
		$this->assertStringContainsString('/CA (go) /RC (go) /AC (go)', $widget);
	}

	/**
	 * The only widget on the first page
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function widget($pdf)
	{
		$refs = $this->annotationRefs($pdf);
		$this->assertCount(1, $refs[0]);

		return $this->object($pdf, $refs[0][0]);
	}

}
