<?php

namespace Mpdf\Ua;

use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * Tagging of what the drawing methods draw when called directly rather than from WriteHTML():
 * text joins the open structure element or becomes a P of its own, and graphics are artifacts.
 *
 * @group pdfua
 */
class DirectDrawingTest extends PdfUaTestCase
{

	/**
	 * The text of each call is marked content of one P under the Document, and nothing it draws
	 * is left unmarked.
	 *
	 * @dataProvider textCallProvider
	 *
	 * @param callable $draw
	 *
	 * @return void
	 */
	public function testTextCallBecomesParagraph($draw)
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		call_user_func($draw, $mpdf);
		$pdf = $mpdf->Output('', 'S');

		$children = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$this->assertSame(['H1', 'P'], $this->types($children));
		$this->assertNotEmpty($children[1]->getMcids());
		$this->assertSame([], $children[1]->getChildren());
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * @return array<string, array{0: callable}>
	 */
	public static function textCallProvider()
	{
		return [
			'Cell' => [function (Mpdf $mpdf) {
				$mpdf->Cell(40, 10, 'Cell text', 1, 1, 'L', 1);
			}],
			'MultiCell' => [function (Mpdf $mpdf) {
				$mpdf->MultiCell(60, 5, 'Text long enough to wrap onto a second line of the cell', 1);
			}],
			'Write' => [function (Mpdf $mpdf) {
				$mpdf->Write(5, 'Text written along the line.');
			}],
			'Text' => [function (Mpdf $mpdf) {
				$mpdf->Text(20, 80, 'Placed text');
			}],
			'WriteText' => [function (Mpdf $mpdf) {
				$mpdf->WriteText(20, 90, 'Written text');
			}],
			'WriteCell' => [function (Mpdf $mpdf) {
				$mpdf->WriteCell(40, 10, 'Written cell', 1);
			}],
			'CircularText' => [function (Mpdf $mpdf) {
				$mpdf->CircularText(100, 150, 30, 'Circular text', 'top');
			}],
			'Shaded_box' => [function (Mpdf $mpdf) {
				$mpdf->Shaded_box('Shaded box text');
			}],
		];
	}

	/**
	 * Linked text is marked content of a Link inside the P, and the link annotation belongs to
	 * that Link, whichever call drew it.
	 *
	 * @dataProvider linkedTextCallProvider
	 *
	 * @param callable $draw
	 *
	 * @return void
	 */
	public function testLinkedTextIsInLinkInsideParagraph($draw)
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		call_user_func($draw, $mpdf);
		$pdf = $mpdf->Output('', 'S');

		$children = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$this->assertSame(['H1', 'P'], $this->types($children));
		$this->assertSame([], $children[1]->getMcids());
		$links = $children[1]->getChildren();
		$this->assertSame(['Link'], $this->types($links));
		$this->assertNotEmpty($links[0]->getMcids());
		$this->assertCount(1, $links[0]->getObjrefs());
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * @return array<string, array{0: callable}>
	 */
	public static function linkedTextCallProvider()
	{
		return [
			'Cell' => [function (Mpdf $mpdf) {
				$mpdf->Cell(40, 10, 'Linked', 0, 1, 'L', 0, 'https://example.com/');
			}],
			'Write' => [function (Mpdf $mpdf) {
				$mpdf->Write(5, 'Linked text', 0, 'https://example.com/');
			}],
		];
	}

	/**
	 * A graphic drawn directly is an artifact and adds nothing to the structure tree.
	 *
	 * @dataProvider graphicCallProvider
	 *
	 * @param callable $draw
	 *
	 * @return void
	 */
	public function testGraphicCallIsArtifact($draw)
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$artifacts = substr_count($this->pageContent($mpdf->Output('', 'S')), '/Artifact BMC');

		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		call_user_func($draw, $mpdf);
		$pdf = $mpdf->Output('', 'S');

		$this->assertSame(['H1'], $this->types($mpdf->getPdfUaStructureTree()->getRoot()->getChildren()));
		$this->assertSame($artifacts + 1, substr_count($this->pageContent($pdf), '/Artifact BMC'));
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * @return array<string, array{0: callable}>
	 */
	public static function graphicCallProvider()
	{
		return [
			'Rect' => [function (Mpdf $mpdf) {
				$mpdf->Rect(20, 100, 30, 10);
			}],
			'Line' => [function (Mpdf $mpdf) {
				$mpdf->Line(20, 120, 80, 120);
			}],
			'RoundedRect' => [function (Mpdf $mpdf) {
				$mpdf->RoundedRect(20, 130, 30, 10, 2);
			}],
			'Circle' => [function (Mpdf $mpdf) {
				$mpdf->Circle(50, 200, 10);
			}],
			'Ellipse' => [function (Mpdf $mpdf) {
				$mpdf->Ellipse(50, 200, 10, 5);
			}],
			'Arrow' => [function (Mpdf $mpdf) {
				$mpdf->Arrow(20, 220, 60, 220);
			}],
			'Cell with no text' => [function (Mpdf $mpdf) {
				$mpdf->Cell(40, 10, '', 1, 1, 'L', 1);
			}],
		];
	}

	/**
	 * The box Shaded_box() draws behind its text is an artifact beside the text's P.
	 *
	 * @return void
	 */
	public function testShadedBoxDrawsItsBoxAsArtifact()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$mpdf->Shaded_box('Shaded box text');
		$content = $this->pageContent($mpdf->Output('', 'S'));

		$this->assertMatchesRegularExpression('/\/Artifact BMC\s[^E]*? c\s.*?EMC\s*\/P <<\/MCID \d+>> BDC/s', $content);
	}

	/**
	 * Text drawn while an element is open from WriteHTML() joins that element.
	 *
	 * @return void
	 */
	public function testTextJoinsTheOpenElement()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1><p>Opened', HTMLParserMode::HTML_BODY, true, false);
		$mpdf->Cell(40, 10, 'Joined');
		$mpdf->WriteHTML('</p>', HTMLParserMode::HTML_BODY, false, true);
		$pdf = $mpdf->Output('', 'S');

		$children = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$this->assertSame(['H1', 'P'], $this->types($children));
		$this->assertCount(2, $children[1]->getMcids());
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * An open element that only groups others, such as a Div, gets a P holding the text.
	 *
	 * @return void
	 */
	public function testTextInsideGroupingElementGetsParagraph()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1><div>', HTMLParserMode::HTML_BODY, true, false);
		$mpdf->Cell(40, 10, 'In the div');
		$mpdf->WriteHTML('</div>', HTMLParserMode::HTML_BODY, false, true);
		$mpdf->Output('', 'S');

		$children = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$this->assertSame(['H1', 'Div'], $this->types($children));
		$this->assertSame(['P'], $this->types($children[1]->getChildren()));
		$this->assertNotEmpty($children[1]->getChildren()[0]->getMcids());
	}

	/**
	 * Each call is a P of its own.
	 *
	 * @return void
	 */
	public function testEachCallIsItsOwnParagraph()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$mpdf->Cell(40, 10, 'First', 0, 1);
		$mpdf->Cell(40, 10, 'Second', 0, 1);
		$mpdf->Output('', 'S');

		$this->assertSame(['H1', 'P', 'P'], $this->types($mpdf->getPdfUaStructureTree()->getRoot()->getChildren()));
	}

	/**
	 * A MultiCell() that breaks the page is one P, with marked content ending on the page it began.
	 *
	 * @return void
	 */
	public function testMultiCellAcrossPagesIsOneParagraph()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$mpdf->MultiCell(60, 5, str_repeat("A line of text\n", 80));
		$pdf = $mpdf->Output('', 'S');

		$children = $mpdf->getPdfUaStructureTree()->getRoot()->getChildren();
		$this->assertSame(['H1', 'P'], $this->types($children));
		$pages = array_unique(array_map(function ($mcr) {
			return $mcr['page'];
		}, $children[1]->getMcids()));
		$this->assertGreaterThan(1, count($pages));
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * Text in PDFUAauto mode is tagged the same way, without a warning.
	 *
	 * @return void
	 */
	public function testAutoModeTagsTextTheSameWay()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$mpdf->Write(5, 'Written text');
		$mpdf->Rect(20, 100, 30, 10);
		$pdf = $mpdf->Output('', 'S');

		$this->assertSame(['H1', 'P'], $this->types($mpdf->getPdfUaStructureTree()->getRoot()->getChildren()));
		$this->assertSame([], $mpdf->getPdfUaWarnings());
		$this->assertSame([], $this->unmarkedOperators($pdf));
	}

	/**
	 * A text watermark, which is drawn with Text(), stays an artifact.
	 *
	 * @return void
	 */
	public function testWatermarkTextIsNotTagged()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML('<h1>Heading</h1>');
		$mpdf->Output('', 'S');

		$this->assertSame(['H1'], $this->types($mpdf->getPdfUaStructureTree()->getRoot()->getChildren()));
	}

	/**
	 * Without PDF/UA nothing is marked.
	 *
	 * @return void
	 */
	public function testNothingIsMarkedWithoutPdfUa()
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->compress = false;
		$mpdf->AddPage();
		$mpdf->Cell(40, 10, 'Cell text');
		$mpdf->Rect(20, 100, 30, 10);
		$content = $this->pageContent($mpdf->Output('', 'S'));

		$this->assertStringNotContainsString('BDC', $content);
		$this->assertStringNotContainsString('BMC', $content);
	}

	/**
	 * @param \Mpdf\Ua\StructureElement[] $elements
	 *
	 * @return string[]
	 */
	private function types(array $elements)
	{
		return array_map(function ($elem) {
			return $elem->getType();
		}, $elements);
	}

	/**
	 * The content streams of the pages, one after another
	 *
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function pageContent($pdf)
	{
		preg_match_all('/\/Contents (\d+) 0 R/', $pdf, $refs);
		$content = '';
		foreach ($refs[1] as $num) {
			preg_match('/\n' . $num . ' 0 obj\s*<<[^>]*>>\s*stream\n(.*?)\nendstream/s', $pdf, $m);
			$content .= $m[1] . "\n";
		}

		return $content;
	}

	/**
	 * The text objects and painting operators drawn outside any marked content, which veraPDF
	 * reports as neither tagged nor an artifact (ISO 14289-1 §7.1)
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function unmarkedOperators($pdf)
	{
		// String operands could hold anything
		$content = preg_replace('/\((?:\\\\.|[^\\\\)])*\)/s', '()', $this->pageContent($pdf));
		$depth = 0;
		$unmarked = [];
		foreach (preg_split('/\s+/', $content) as $token) {
			if ($token === 'BDC' || $token === 'BMC') {
				$depth++;
			} elseif ($token === 'EMC') {
				$depth--;
			} elseif ($depth === 0 && in_array($token, ['BT', 'S', 's', 'f', 'F', 'f*', 'B', 'B*', 'b', 'b*', 'Do', 'sh'], true)) {
				$unmarked[] = $token;
			}
		}

		return $unmarked;
	}
}
