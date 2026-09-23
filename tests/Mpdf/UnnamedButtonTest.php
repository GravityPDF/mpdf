<?php

namespace Mpdf;

/**
 * A button needs no name in HTML: a submit button without one triggers the form without being sent with it
 */
class UnnamedButtonTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Each kind of button, without a name, and the name it is given
	 *
	 * @return string[][]
	 */
	public function unnamedButtons()
	{
		return [
			'submit' => ['<input type="submit" value="Go">', 'Submit'],
			'reset' => ['<input type="reset" value="Clear">', 'Reset'],
			'button' => ['<input type="button" value="Run" onclick="app.alert(1)">', 'Button'],
			'image' => ['<input type="image" src="' . $this->pngImage() . '" onclick="app.alert(1)">', 'Button'],
		];
	}

	/**
	 * Each unnamed button is its own field, written without a warning (PHPUnit fails the test on one). Two
	 * buttons with the same name are one field to a viewer, and share the entry that holds an action or icon
	 *
	 * @dataProvider unnamedButtons
	 *
	 * @param string $button
	 * @param string $name
	 */
	public function testTwoUnnamedButtonsAreTwoFields($button, $name)
	{
		$pdf = $this->render('<form>' . $button . $button . '</form>', ['useActiveForms' => true]);

		$this->assertSame([$name . '_1', $name . '_2'], $this->fieldNames($pdf));
	}

	/**
	 * A button's action is looked up by its name, so under a shared name the second action would replace the first
	 */
	public function testEachUnnamedButtonKeepsItsOwnAction()
	{
		$pdf = $this->render(
			'<form><input type="button" value="A" onclick="app.alert(\'first\')"><input type="button" value="B" onclick="app.alert(\'second\')"></form>',
			['useActiveForms' => true]
		);

		$this->assertSame(1, substr_count($pdf, "/JS (app.alert\\('first'\\))"));
		$this->assertSame(1, substr_count($pdf, "/JS (app.alert\\('second'\\))"));
	}

	/**
	 * Each kind of field that cannot be written without a name
	 *
	 * @return string[][]
	 */
	public function unnamedFields()
	{
		return [
			'text input' => ['<input type="text" value="A">'],
			'hidden input' => ['<input type="hidden" value="A">'],
			'textarea' => ['<textarea>A</textarea>'],
			'select' => ['<select><option>A</option></select>'],
			'checkbox' => ['<input type="checkbox" value="A">'],
			'radio' => ['<input type="radio" value="A">'],
		];
	}

	/**
	 * The field is refused with mPDF's own message rather than a warning about the missing name
	 *
	 * @dataProvider unnamedFields
	 *
	 * @param string $field
	 */
	public function testAnUnnamedFieldIsRefused($field)
	{
		$this->expectException('Mpdf\MpdfException');
		$this->expectExceptionMessage('must have a name attribute');

		$this->render('<form>' . $field . '</form>', ['useActiveForms' => true]);
	}

	/**
	 * The /T of each field, in the order they are written
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function fieldNames($pdf)
	{
		preg_match_all('/\/FT \/\w+ .*?\/T \(([^)]*)\)/s', $pdf, $matches);

		return $matches[1];
	}
}
