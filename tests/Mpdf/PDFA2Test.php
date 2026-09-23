<?php

namespace Mpdf;

/**
 * What PDF/A-2 lets a document keep that PDF/A-1 takes away, and what it asks of the document in return
 */
class PDFA2Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * PDF/A-1 and PDF/X-1a forbid transparency; PDF/A-2 and PDF/A-3 do not, nor does a document that is neither
	 *
	 * @dataProvider transparencyConfigs
	 */
	public function testTransparencyAllowed($config, $allowed)
	{
		$mpdf = new Mpdf($config + ['PDFAauto' => true, 'PDFXauto' => true]);

		$this->assertSame($allowed, $mpdf->transparencyAllowed());
	}

	/**
	 * Configurations and whether they allow transparency
	 *
	 * @return mixed[][]
	 */
	public function transparencyConfigs()
	{
		return [
			'plain' => [[], true],
			'PDF/A-1b' => [['PDFA' => true, 'PDFAversion' => '1-B'], false],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAversion' => '2-B'], true],
			'PDF/A-2u' => [['PDFA' => true, 'PDFAversion' => '2-U'], true],
			'PDF/A-3b' => [['PDFA' => true, 'PDFAversion' => '3-B'], true],
			'PDF/X-1a' => [['PDFX' => true], false],
		];
	}

	/**
	 * PDF/A-2 keeps the opacity of an image and the alpha of a gradient, and gives its pages a transparency group
	 */
	public function testPdfa2KeepsOpacity()
	{
		$pdf = $this->renderPdfa('2-B', $this->translucentContent());

		$this->assertStringContainsString('/ca 0.4', $pdf);
		$this->assertStringContainsString('/SMask', $pdf);
		$this->assertStringContainsString('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >>', $pdf);
	}

	/**
	 * PDF/A-1 still draws the same content opaque
	 */
	public function testPdfa1DrawsOpaque()
	{
		$pdf = $this->renderPdfa('1-B', $this->translucentContent());

		$this->assertStringNotContainsString('/ca 0.4', $pdf);
		$this->assertStringNotContainsString('/SMask', $pdf);
		$this->assertStringNotContainsString('/S /Transparency', $pdf);
	}

	/**
	 * A PNG's alpha channel becomes a soft mask under PDF/A-2, and is flattened away under PDF/A-1
	 *
	 * @dataProvider alphaImages
	 */
	public function testAlphaChannel($version, $image, $masked)
	{
		$pdf = $this->renderPdfa($version, '<img src="' . __DIR__ . '/../data/img/pngpixels/' . $image . '" />');

		$this->assertSame($masked, (bool) preg_match('/\/SMask \d+ 0 R/', $pdf));
	}

	/**
	 * Versions, images with an alpha channel, and whether the image is drawn through a soft mask
	 *
	 * @return mixed[][]
	 */
	public function alphaImages()
	{
		return [
			['1-B', 'rgba8-None.png', false],
			['2-B', 'rgba8-None.png', true],
			['1-B', 'la8-PNG.png', false],
			['2-B', 'la8-PNG.png', true],
		];
	}

	/**
	 * PDF/A-2 draws a watermark, where PDF/A-1 refuses it
	 *
	 * @dataProvider watermarkVersions
	 */
	public function testWatermark($version, $allowed)
	{
		$mpdf = $this->pdfa($version);
		$mpdf->SetWatermarkText('DRAFT', 0.2);
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML('<p>Text</p>');

		if (!$allowed) {
			$this->expectException(MpdfException::class);
			$this->expectExceptionMessage('do not permit transparency');
		}

		$this->assertStringContainsString('/ca 0.2', $mpdf->Output('', 'S'));
	}

	/**
	 * Versions and whether they draw a watermark
	 *
	 * @return mixed[][]
	 */
	public function watermarkVersions()
	{
		return [
			['1-B', false],
			['2-B', true],
		];
	}

	/**
	 * An SVG image names the graphics states of the pages' resource dictionary, so it carries that dictionary, and
	 * carries a transparency group only where the document may be transparent
	 *
	 * @dataProvider svgGroups
	 */
	public function testSvgFormObject($version, $group)
	{
		$pdf = $this->renderPdfa($version, '<img src="' . __DIR__ . '/../data/img/demo.svg" width="40" />');

		$this->assertSame(1, preg_match('/\/Subtype \/Form\n(?:.*\n)*?\/Length/', $pdf, $form));
		$this->assertStringContainsString('/Resources 2 0 R', $form[0]);
		$this->assertSame($group, strpos($form[0], '/Group') !== false);
	}

	/**
	 * Versions and whether their SVG images carry a transparency group
	 *
	 * @return mixed[][]
	 */
	public function svgGroups()
	{
		return [
			['1-B', false],
			['2-B', true],
		];
	}

	/**
	 * PDF/A-2 gives every annotation but a popup or link an appearance, drawn by a form object
	 */
	public function testAnnotationsHaveAppearances()
	{
		$pdf = $this->renderPdfa('2-B', '<p>One <annotation content="Note" /> two <annotation content="Popup" popup="true" /></p>');

		preg_match_all('/\/Subtype \/Text .*\/AP <<\/N (\d+) 0 R>>/', $pdf, $appearances);

		$this->assertCount(2, $appearances[1]);
		foreach ($appearances[1] as $n) {
			$this->assertStringContainsString('/Subtype /Form /BBox [0 0 20.000 20.000]', $this->object($pdf, $n));
		}
	}

	/**
	 * Every entry a page lists in /Annots is an annotation, whatever else the annotations before it wrote
	 *
	 * @dataProvider annotationConfigs
	 */
	public function testAnnotsListOnlyAnnotations($config)
	{
		$mpdf = new Mpdf($config);
		$mpdf->compress = false;
		$mpdf->WriteHTML(
			'<p><a href="#b">link</a> <annotation content="Popup" popup="true" /> <annotation content="File" file="' . __DIR__ . '/../data/annotation-files/sample.txt" /> <annotation content="Plain" /></p>'
			. '<pagebreak /><p id="b"><annotation content="Second page" popup="true" /> <a href="https://example.com">external</a></p>'
		);
		$pdf = $mpdf->Output('', 'S');

		// Page one lists the link, the popup's note and the popup, the file's note and the plain note
		$this->assertSame([5, 3], array_map(function ($annotations) {
			return substr_count($annotations, '<</Type /Annot');
		}, $this->annotations($pdf)));
	}

	/**
	 * Configurations that change which objects an annotation writes
	 *
	 * @return mixed[][]
	 */
	public function annotationConfigs()
	{
		return [
			'files not allowed' => [[]],
			'files allowed' => [['allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true]],
			'PDF/A-2 appearances' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B', 'allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true]],
			'PDF/A-3 associated files' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '3-B', 'allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true]],
		];
	}

	/**
	 * An annotation keeps its subject under PDF/A-2 and PDF/A-3, which are based on PDF 1.7, and loses it under
	 * PDF/A-1 and PDF/X-1a, which predate /Subj
	 *
	 * @dataProvider subjectConfigs
	 */
	public function testAnnotationSubject($config, $written)
	{
		$pdf = $this->render('<p>Note <annotation content="Body" subject="A subject" /></p>', $config + ['mode' => '']);

		$this->assertSame(1, substr_count($pdf, '/Subtype /Text'));
		$this->assertSame($written, strpos($pdf, '/Subj ') !== false);
	}

	/**
	 * Configurations and whether an annotation written under them keeps its subject
	 *
	 * @return mixed[][]
	 */
	public function subjectConfigs()
	{
		return [
			'plain' => [[], true],
			'PDF/A-1b' => [$this->pdfaConfig('1-B'), false],
			'PDF/A-2b' => [$this->pdfaConfig('2-B'), true],
			'PDF/A-3b' => [$this->pdfaConfig('3-B'), true],
			'PDF/X-1a' => [['PDFX' => true, 'PDFXauto' => true], false],
		];
	}

	/**
	 * PDF/A-2 embeds a file only if it is a PDF/A document, and says so of the file it leaves out
	 */
	public function testPdfa2EmbedsOnlyPdfaFiles()
	{
		$pdfa = $this->pdfaFile();
		$plain = __DIR__ . '/../data/pdfs/2-Page-PDF_1_4.pdf';

		$mpdf = $this->pdfa('2-B', ['allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true, 'PDFAauto' => false]);
		$mpdf->WriteHTML('<p><annotation content="PDF/A" file="' . $pdfa . '" /> <annotation content="Plain" file="' . $plain . '" /></p>');

		try {
			$mpdf->Output('', 'S');
			$this->fail('The file PDF/A-2 cannot embed went unreported');
		} catch (MpdfException $e) {
			$this->assertSame([sprintf('PDFA version 2-B cannot embed the file "%s" (Annotation written without the file)', $plain)], $mpdf->PDFAXwarnings);
		} finally {
			unlink($pdfa);
		}
	}

	/**
	 * With PDFAauto, the file PDF/A-2 cannot embed is left out and the one it can is kept
	 */
	public function testPdfa2AutoLeavesOutFileItCannotEmbed()
	{
		$pdfa = $this->pdfaFile();

		$mpdf = $this->pdfa('2-B', ['allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true]);
		$mpdf->WriteHTML('<p><annotation content="PDF/A" file="' . $pdfa . '" /> <annotation content="Plain" file="' . __DIR__ . '/../data/pdfs/2-Page-PDF_1_4.pdf" /></p>');
		$pdf = $mpdf->Output('', 'S');
		unlink($pdfa);

		$this->assertSame(1, substr_count($pdf, '/Type /EmbeddedFile'));
		$this->assertSame(1, substr_count($pdf, '/Subtype /FileAttachment'));
		$name = str_replace('-', '', basename($pdfa)); // mPDF keeps letters, digits, dots and underscores
		$this->assertStringContainsString('/F (' . $name . ') /UF (' . $name . ')', $pdf);
	}

	/**
	 * PDF/A-3 writes an annotation's file as an associated file: a file specification with an AFRelationship, named by
	 * the annotation's /AF, and a stream with a MIME type
	 */
	public function testPdfa3AssociatesAnnotationFile()
	{
		$pdf = $this->render('<p><annotation content="File" file="' . __DIR__ . '/../data/annotation-files/sample.txt' . '" /></p>', ['allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true] + $this->pdfaConfig('3-B'));

		$annotation = $this->annotations($pdf)[0];
		$this->assertSame(1, preg_match('/\/FS (\d+) 0 R \/AF \[\1 0 R\]/', $annotation, $spec));
		$this->assertSame(1, preg_match('/\/AP <<\/N (\d+) 0 R>>/', $annotation, $appearance));
		$this->assertStringContainsString('/Subtype /Form', $this->object($pdf, $appearance[1]));

		$filespec = $this->object($pdf, $spec[1]);
		$this->assertStringContainsString('/Type /Filespec', $filespec);
		$this->assertStringContainsString('/AFRelationship /Unspecified', $filespec);
		$this->assertStringContainsString('/UF (sample.txt)', $filespec);

		$this->assertSame(1, preg_match('/\/EF <<\s*\/F (\d+) 0 R/', $filespec, $stream));
		$this->assertStringContainsString("/Type /EmbeddedFile\n/Subtype /text#2Fplain", $this->object($pdf, $stream[1]));
	}

	/**
	 * An image at 40% opacity and a gradient that fades out
	 *
	 * @return string
	 */
	private function translucentContent()
	{
		return '<img style="opacity: 0.4" src="' . __DIR__ . '/../data/img/tiger.jpg" width="20" />'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 1), rgba(255, 0, 0, 0.3)); height: 10mm"></div>';
	}

	/**
	 * A PDF/A-2 document written to a file, for another document to embed
	 *
	 * @return string its path
	 */
	private function pdfaFile()
	{
		$file = sys_get_temp_dir() . '/mpdf-pdfa2-' . uniqid() . '.pdf';
		$this->pdfa('2-B')->Output($file, 'F');

		return $file;
	}

	/**
	 * A PDF/A document of the given version that writes uncompressed and fixes what it can
	 *
	 * @param string $version
	 * @param mixed[] $config
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfa($version, $config = [])
	{
		return $this->mpdf($config + $this->pdfaConfig($version));
	}

	/**
	 * The PDF of a document of the given version holding the given HTML
	 *
	 * @param string $version
	 * @param string $html
	 *
	 * @return string
	 */
	private function renderPdfa($version, $html)
	{
		return $this->render($html, $this->pdfaConfig($version));
	}

	/**
	 * The configuration of a PDF/A document that fixes what it can, in the embedded fonts PDF/A needs rather than the
	 * core fonts PageStreams defaults to
	 *
	 * @param string $version
	 *
	 * @return mixed[]
	 */
	private function pdfaConfig($version)
	{
		return ['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version];
	}

}
