<?php

namespace Mpdf\Image;

use Mpdf\Mpdf;

/**
 * svgAutoFont sends the picture's text through Svg::markScriptToLang(), which splits the document at
 * its tags and reads the tag before each run of text to decide whether the run is inside a <text> or
 * <tspan>. The first piece an SVG splits into is the empty string ahead of <svg, so a run holding no
 * characters has to leave no entry behind for that loop to visit - reaching back for the tag before
 * the first one is a read off the start of the array.
 */
class SvgAutoFontTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	private function svg()
	{
		return '<svg width="40mm" height="20mm" xmlns="http://www.w3.org/2000/svg">'
			. '<text x="2" y="15">Hello</text></svg>';
	}

	public function testAutoFontingAnSvgReadsNothingOffTheStartOfTheDocument()
	{
		$raised = [];

		set_error_handler(function ($number, $message, $file, $line) use (&$raised) {
			// Filtered to the picture so a diagnostic raised elsewhere in the render cannot fail this
			if (false !== strpos($file, 'Svg.php')) {
				$raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			}

			return true;
		});

		$mpdf = new Mpdf();
		$mpdf->svgAutoFont = true;

		try {
			$mpdf->WriteHTML($this->svg());
			$pdf = $mpdf->Output('', 'S');
		} finally {
			restore_error_handler();
			$mpdf->cleanup();
		}

		$this->assertSame([], $raised);
		$this->assertStringContainsString('/Subtype /Form', $pdf, 'the picture is drawn');
	}

}
