<?php

namespace Mpdf;

class FormObjectResourcesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SVG = __DIR__ . '/../data/img/form-xobject-resources.svg';

	/**
	 * The fixture paints with a gradient, a half-transparent rectangle and a line of text, so
	 * the Form XObject names an entry from all three resource categories.
	 */
	public function testEveryNameTheStreamUsesIsDeclared()
	{
		$objects = $this->renderFormObjects(false);
		$this->assertCount(1, $objects);

		list($header, $stream) = $objects[0];

		$names = $this->namesUsedBy($stream);
		$this->assertGreaterThanOrEqual(3, count($names), 'Expected the fixture to use several resources');

		foreach ($names as $name) {
			$this->assertMatchesRegularExpression(
				'#/' . preg_quote($name, '#') . ' [1-9]\d* 0 R#',
				$header,
				sprintf('/%s is painted with but not declared in the Form XObject /Resources', $name)
			);
		}
	}

	public function testResourcesAreWrittenForPdfA()
	{
		$objects = $this->renderFormObjects(true);
		$this->assertCount(1, $objects);

		list($header) = $objects[0];

		$this->assertStringContainsString('/Resources <<', $header);
	}

	/**
	 * @param  string $stream
	 * @return string[]
	 */
	private function namesUsedBy($stream)
	{
		preg_match_all('#/([A-Za-z0-9]+)\s+(?:gs|sh)[^A-Za-z0-9]#', $stream, $states);
		preg_match_all('#/([A-Za-z0-9]+)\s+[\d.]+\s+Tf#', $stream, $fonts);

		return array_values(array_unique(array_merge($states[1], $fonts[1])));
	}

	/**
	 * @param  bool $pdfa
	 * @return array[] One [header, stream] pair per Form XObject mPDF wrote for the image
	 */
	private function renderFormObjects($pdfa)
	{
		$mpdf = new Mpdf();
		$mpdf->compress = false;

		if ($pdfa) {
			$mpdf->PDFA = true;
			$mpdf->PDFAauto = true;
			$mpdf->PDFAversion = '3-B';
		}

		$mpdf->WriteHTML('<img src="' . self::SVG . '" style="width:40mm">');

		$output = $mpdf->OutputBinaryData();
		$mpdf->cleanup();

		preg_match_all('/\d+ 0 obj\s*(.*?)\bstream\r?\n(.*?)\r?\nendstream/s', $output, $matches, PREG_SET_ORDER);

		$objects = [];
		foreach ($matches as $match) {
			// The transparency group is written as a separate object, which is what tells a
			// Form XObject mPDF wrote here apart from the inline groups a soft mask uses
			if (strpos($match[1], '/Subtype /Form') !== false && preg_match('#/Group \d+ 0 R#', $match[1])) {
				$objects[] = [$match[1], $match[2]];
			}
		}

		return $objects;
	}

}
