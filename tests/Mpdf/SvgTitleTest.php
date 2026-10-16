<?php

namespace Mpdf;

/**
 * Only the document's own <title> sets the PDF's title: an inline SVG's <title> names the image
 */
class SvgTitleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><title>Chart</title><rect width="40" height="40"/></svg>';

	/**
	 * Documents with and without a title of their own, and with an inline SVG before, after or instead of it
	 *
	 * @return array[]
	 */
	public static function documents()
	{
		return [
			'an SVG title alone' => [self::SVG, null],
			'an SVG title after the document title' => ['<html><head><title>Report</title></head><body>' . self::SVG . '</body></html>', 'Report'],
			'an SVG title before the document title' => [self::SVG . '<title>Report</title><p>Text</p>', 'Report'],
			'an SVG title in an SVG with a ">" in an attribute' => ['<svg xmlns="http://www.w3.org/2000/svg" data-note="a > b" width="40" height="40"><title>Chart</title></svg><p>Text</p>', null],
			'the document title alone' => ['<html><head><title>Report</title></head><body><p>Text</p></body></html>', 'Report'],
		];
	}

	/**
	 * The PDF's title is the document's own, or none
	 *
	 * @dataProvider documents
	 *
	 * @param string $html
	 * @param string|null $title
	 */
	public function testTheTitleIsTheDocumentsOwn($html, $title)
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		$this->assertSame($title, $mpdf->title);
	}

}
