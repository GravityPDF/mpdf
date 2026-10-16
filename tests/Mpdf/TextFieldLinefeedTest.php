<?php

namespace Mpdf;

/**
 * A text field drops the linefeeds in its value, as a browser does: value="x(linefeed)y" shows "xy", where the
 * linefeed used to be drawn as a space (#794). Other attributes, and inputs not drawn as a text field, are left alone
 */
class TextFieldLinefeedTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;
	use PageStreams;

	/**
	 * Text fields of each type drawn as one, holding a linefeed written as it is or as an entity
	 *
	 * @return array[]
	 */
	public static function textFields()
	{
		return [
			'no type' => ['<input value="x' . "\n" . 'y">'],
			'text' => ['<input type="text" value="x' . "\n" . 'y">'],
			'search' => ['<input type="search" value="x' . "\n" . 'y">'],
			'email' => ['<input type="email" value="x' . "\n" . 'y">'],
			'url' => ['<input type="URL" value="x' . "\n" . 'y">'],
			'tel' => ['<input type="tel" value="x' . "\n" . 'y">'],
			'carriage return and linefeed' => ['<input type="text" value="x' . "\r\n" . 'y">'],
			'single quoted' => ['<input type="text" value=\'x' . "\n" . 'y\'>'],
			'several linefeeds' => ['<input type="text" value="' . "\n" . 'x' . "\n\n" . 'y' . "\n" . '">'],
			'linefeed entity' => ['<input type="text" value="x&#10;y">'],
			'carriage return and linefeed entities' => ['<input type="text" value="x&#13;&#10;y">'],
		];
	}

	/**
	 * A field drawn into the page shows its value without the linefeed
	 *
	 * @dataProvider textFields
	 *
	 * @param string $field
	 */
	public function testAStaticFieldDrawsItsValueWithoutTheLinefeed($field)
	{
		$mpdf = $this->drawDocument('<p>' . $field . '</p>');

		$this->assertSame(['xy'], $mpdf->drawnText);
	}

	/**
	 * An active field holds its value without the linefeed
	 *
	 * @dataProvider textFields
	 *
	 * @param string $field
	 */
	public function testAnActiveFieldHoldsItsValueWithoutTheLinefeed($field)
	{
		$pdf = $this->render('<form>' . str_replace('<input ', '<input name="f" ', $field) . '</form>', ['useActiveForms' => true]);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertStringContainsString('/V (xy)', $widget);
		$this->assertStringContainsString('/DV (xy)', $widget);
	}

	/**
	 * A password field shows one star for each character left once the linefeed is gone
	 */
	public function testAPasswordFieldDoesNotCountTheLinefeed()
	{
		$mpdf = $this->drawDocument('<p><input type="password" value="x' . "\n" . 'y"><input type="password" value="x&#10;y"></p>');

		$this->assertSame(['**', '**'], $mpdf->drawnText);
	}

	/**
	 * Only the value of a text field loses its linefeeds: another attribute, a "value=" written inside one, a hidden
	 * field, a button and the text between tags still have them turned into spaces
	 */
	public function testOnlyATextFieldValueLosesItsLinefeeds()
	{
		$mpdf = new Mpdf(['mode' => 'c']);

		$html = "<input title=\"value='a\nb'\" value=\"x\ny\"><input type=\"hidden\" value=\"c\nd\">"
			. "<input type=\"submit\" value=\"e\nf\">g\nh";

		$this->assertSame(
			'<input title="value=\'a b\'" value="xy" /><input type="hidden" value="c d" /><input type="submit" value="e f" />g h',
			$mpdf->AdjustHTML($html)
		);
	}

}
