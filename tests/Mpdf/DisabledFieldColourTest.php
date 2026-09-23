<?php

namespace Mpdf;

/**
 * An active field that is disabled writes its text in mid grey
 */
class DisabledFieldColourTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * A disabled field of each kind that is greyed out
	 *
	 * @return string[][]
	 */
	public function disabledFields()
	{
		return [
			'text input' => ['<input type="text" name="field" value="A" disabled>'],
			'textarea' => ['<textarea name="field" disabled>A</textarea>'],
			'select' => ['<select name="field" disabled><option>A</option></select>'],
			'button' => ['<input type="button" name="field" value="A" disabled>'],
		];
	}

	/**
	 * @dataProvider disabledFields
	 *
	 * @param string $field
	 */
	public function testTheFieldTextIsGrey($field)
	{
		$pdf = $this->render('<form>' . $field . '</form>', ['useActiveForms' => true]);

		$this->assertSame(1, preg_match('/\/T \(field\).*?\/DA \(([^)]*)\)/s', $pdf, $match), 'The field should carry a default appearance');
		$this->assertStringEndsWith('0.502 g', $match[1]);
	}
}
