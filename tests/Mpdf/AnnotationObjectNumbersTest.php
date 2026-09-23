<?php

namespace Mpdf;

/**
 * The object numbers a page's links, annotations and form widgets are given are the ones they are written under
 */
class AnnotationObjectNumbersTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Every /Annots entry is an annotation of its page, and every /Popup, /Parent, /EF, /AP and radio /Parent
	 * reference names an object of the kind it should
	 *
	 * @dataProvider documents
	 *
	 * @param mixed[] $config
	 * @param bool $forms
	 * @param string[][] $expected the subtypes each page lists in /Annots
	 */
	public function testReferencesNameTheRightObjects($config, $forms, $expected)
	{
		$mpdf = new Mpdf($config);
		$mpdf->compress = false;
		$mpdf->WriteHTML($this->html($forms));
		$pdf = $this->output($mpdf);

		$this->assertEveryObjectWritten($pdf);

		$subtypes = [];
		foreach ($this->pageObjects($pdf) as $i => $page) {
			$refs = $this->annotsOf($pdf, $page);
			$subtypes[$i] = [];

			foreach ($refs as $ref) {
				$annot = $this->object($pdf, $ref);
				$this->assertStringContainsString('/Type /Annot', $annot);
				preg_match('/\/Subtype \/(\w+)/', $annot, $subtype);
				$subtypes[$i][] = $subtype[1];

				if (in_array($subtype[1], ['Text', 'FileAttachment'], true)) {
					$this->assertStringContainsString('/P ' . $page . ' 0 R', $annot);
				}

				if (preg_match('/\/Popup (\d+) 0 R/', $annot, $popup)) {
					$this->assertContains($popup[1], $refs, 'A popup is listed on the page of its note');
					$this->assertStringContainsString('/Subtype /Popup', $this->object($pdf, $popup[1]));
					$this->assertStringContainsString('/Parent ' . $ref . ' 0 R', $this->object($pdf, $popup[1]));
				}

				if ($subtype[1] === 'Popup') {
					preg_match('/\/Parent (\d+) 0 R/', $annot, $parent);
					$this->assertStringContainsString('/Popup ' . $ref . ' 0 R', $this->object($pdf, $parent[1]));
				}

				if (preg_match('/\/EF <<\/F (\d+) 0 R>>/', $annot, $file)) {
					$this->assertStringContainsString('/Type /EmbeddedFile', $this->object($pdf, $file[1]));
				}

				if (preg_match('/\/AP <<\/N (\d+) 0 R>>/', $annot, $appearance)) {
					$this->assertStringContainsString('/Type /XObject /Subtype /Form', $this->object($pdf, $appearance[1]));
				}

				if ($subtype[1] === 'Widget' && preg_match('/\/AP << (.*?) >>\n/', $annot, $appearances)) {
					preg_match_all('/(\d+) 0 R/', $appearances[1], $streams);
					foreach ($streams[1] as $stream) {
						$this->assertMatchesRegularExpression('/\/Length \d+ \/Resources 2 0 R>>\nstream\n/', $this->object($pdf, $stream));
					}
				}

				if ($subtype[1] === 'Widget' && preg_match('/\/Parent (\d+) 0 R/', $annot, $group)) {
					$this->assertMatchesRegularExpression('/\/Kids \[[^\]]*\b' . $ref . ' 0 R/', $this->object($pdf, $group[1]));
				}
			}
		}

		$this->assertSame($expected, $subtypes);
	}

	/**
	 * Configurations that change which objects an annotation writes, with and without form widgets after them
	 *
	 * @return mixed[][]
	 */
	public function documents()
	{
		$notes = ['Link', 'Text', 'Popup', 'Text', 'Text', 'Text', 'Popup'];
		$widgets = ['Widget', 'Widget', 'Widget', 'Widget', 'Widget'];
		$second = ['Link', 'Text', 'Popup'];

		$withFile = ['Link', 'Text', 'Popup', 'FileAttachment', 'Text', 'Text', 'Popup'];

		return [
			'file not allowed, forms' => [['mode' => 'c', 'useActiveForms' => true], true, [array_merge($notes, $widgets), array_merge($second, ['Widget', 'Widget', 'Widget'])]],
			'embedded fonts, forms' => [['useActiveForms' => true], true, [array_merge($notes, $widgets), array_merge($second, ['Widget', 'Widget', 'Widget'])]],
			'file allowed, forms' => [['mode' => 'c', 'useActiveForms' => true, 'allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true], true, [array_merge($withFile, $widgets), array_merge($second, ['Widget', 'Widget', 'Widget'])]],
			'PDF/A-2 appearances, file not a PDF/A' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B', 'allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true], false, [$notes, $second]],
			'PDF/A-2 appearances, forms' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B', 'useActiveForms' => true], true, [array_merge($notes, $widgets), array_merge($second, ['Widget', 'Widget', 'Widget'])]],
			'PDF/A-3 appearances and file' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B', 'allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true], false, [$withFile, $second]],
		];
	}

	/**
	 * Two pages of links, notes with and without popups, a note with a file and, when asked, form widgets with a
	 * radio group on each page
	 *
	 * @param bool $forms
	 *
	 * @return string
	 */
	private function html($forms)
	{
		$html = '<p><a href="#b">link</a> <annotation content="Popup" popup="true" /> <annotation content="File" file="' . __DIR__ . '/../data/annotation-files/sample.txt' . '" />'
			. ' <annotation content="Plain" /> <annotation content="Placed" popup="[10,10,50,50]" color="#ff0000" /></p>';

		if ($forms) {
			$html .= '<form><input type="text" name="t1" value="x" onchange="alert(1)" /> <input type="checkbox" name="c1" value="1" checked="checked" />'
				. ' <input type="radio" name="r1" value="a" checked="checked" /> <input type="radio" name="r1" value="b" />'
				. ' <select name="s1" onchange="x()"><option value="1">1</option></select></form>';
		}

		$html .= '<pagebreak /><p id="b"><a href="https://example.com">ext</a> <annotation content="Second" popup="true" /></p>';

		if ($forms) {
			$html .= '<form><input type="radio" name="r2" value="c" /> <input type="radio" name="r2" value="d" checked="checked" /> <input type="text" name="t2" /></form>';
		}

		return $html;
	}

	/**
	 * The object numbers a page lists in /Annots
	 *
	 * @param string $pdf
	 * @param string $page the page's object number
	 *
	 * @return string[]
	 */
	private function annotsOf($pdf, $page)
	{
		if (!preg_match('/\/Annots \[([^\]]*)\]/', $this->object($pdf, $page), $list)) {
			return [];
		}

		preg_match_all('/(\d+) 0 R/', $list[1], $refs);

		return $refs[1];
	}

	/**
	 * Every object number below the trailer's /Size is written, so no number was given out and left unused
	 *
	 * @param string $pdf
	 */
	private function assertEveryObjectWritten($pdf)
	{
		preg_match('/\/Size (\d+)/', $pdf, $size);

		for ($i = 1; $i < $size[1]; $i++) {
			$this->assertMatchesRegularExpression('/\n' . $i . ' 0 obj\n/', $pdf);
		}
	}
}
