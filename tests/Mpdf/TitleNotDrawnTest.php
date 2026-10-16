<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A <title> is never drawn, wherever it is written, and the first one gives the document its title
 */
class TitleNotDrawnTest extends TestCase
{

	/**
	 * Each CSS mode, and core fonts
	 *
	 * @return array[]
	 */
	public static function configs()
	{
		return [
			'standard' => [['mode' => '', 'cssMode' => CssMode::STANDARD]],
			'legacy' => [['mode' => '', 'cssMode' => CssMode::LEGACY]],
			'core fonts' => [['mode' => 'c']],
		];
	}

	/**
	 * A <title> between two paragraphs is not drawn in any mode
	 *
	 * @dataProvider configs
	 *
	 * @param array $config
	 */
	public function testATitleBetweenParagraphsIsNotDrawn(array $config)
	{
		$mpdf = new TextRecordingMpdf($config);
		$mpdf->WriteHTML('<p>Before</p><title>Hidden</title><p>After</p>');

		$this->assertSame(['Before', 'After'], $mpdf->drawnText);
		$this->assertSame('Hidden', $mpdf->title);
	}

	/**
	 * A <title> is not drawn wherever it is written, and the first one is the document's title
	 *
	 * @dataProvider placements
	 *
	 * @param string $html
	 * @param string[] $drawn
	 * @param string|null $title
	 */
	public function testATitleIsNotDrawnWhereverItIs($html, array $drawn, $title)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html);

		$this->assertSame($drawn, $mpdf->drawnText);
		$this->assertSame($title, $mpdf->title);
	}

	/**
	 * HTML holding a <title>, the text drawn from it and the document title
	 *
	 * @return array[]
	 */
	public static function placements()
	{
		return [
			'head without body' => ['<head><title>Hidden</title></head><p>Shown</p>', ['Shown'], 'Hidden'],
			'body of a document' => ['<html><head><title>Head</title></head><body><p>Before</p><title>Hidden</title><p>After</p></body></html>', ['Before', 'After'], 'Head'],
			'table cell' => ['<table><tr><td>Cell<title>Hidden</title></td></tr></table>', ['Cell'], 'Hidden'],
			'inline' => ['<p>Before <span><title>Hidden</title></span>after</p>', ['Before ', 'after'], 'Hidden'],
			'capitals and attributes' => ['<p>Before</p><TITLE lang="en" dir="ltr">Hidden</TITLE><p>After</p>', ['Before', 'After'], 'Hidden'],
			'markup inside' => ['<p>Before</p><title>Hidden <b>bold</b></title><p>After</p>', ['Before', 'After'], 'Hidden <b>bold</b>'],
			'two titles' => ['<title>One</title><p>Shown</p><title>Two</title>', ['Shown'], 'One'],
			'inside attribute values' => ['<p data-note="<title>">Shown</p><p data-note="</title>">Also shown</p>', ['Shown', 'Also shown'], null],
		];
	}

	/**
	 * A <title> in an HTML header is not drawn in it
	 */
	public function testATitleInAHeaderIsNotDrawn()
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'c']);
		$mpdf->SetHTMLHeader('<div>Header<title>Hidden</title></div>');
		$mpdf->WriteHTML('<p>Body</p>');
		$mpdf->Output('', Output\Destination::STRING_RETURN);

		$this->assertEqualsCanonicalizing(['Header', 'Body'], $mpdf->drawnText);
	}

}
