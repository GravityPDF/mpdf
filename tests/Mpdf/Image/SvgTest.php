<?php

namespace Mpdf\Image;

use Mockery;
use Mpdf\CssManager;
use Mpdf\Color\ColorConverter;
use Mpdf\Language\LanguageToFont;
use Mpdf\Language\ScriptToLanguage;
use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\SizeConverter;

class SvgTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Image\Svg
	 */
	private $svg;

	private $sizeConverter;

	private $colorConverter;

	protected function set_up()
	{
		parent::set_up();

		$mpdf = Mockery::mock(Mpdf::class);

		$mpdf->shouldIgnoreMissing();
		$mpdf->shouldReceive('AddExtGState')->andReturn(1);

		$mpdf->img_dpi = 72;
		$mpdf->PDFAXwarnings = [];

		$otl = Mockery::mock(Otl::class);
		$cssManager = Mockery::mock(CssManager::class);
		$imageProcessor = Mockery::mock(ImageProcessor::class);
		$this->sizeConverter = Mockery::mock(SizeConverter::class);
		$this->colorConverter = Mockery::mock(ColorConverter::class);
		$languageToFontInterface = Mockery::mock(LanguageToFont::class);
		$scriptToLanguageInterface = Mockery::mock(ScriptToLanguage::class);

		$this->svg = new Svg(
			$mpdf,
			$otl,
			$cssManager,
			$imageProcessor,
			$this->sizeConverter,
			$this->colorConverter,
			$languageToFontInterface,
			$scriptToLanguageInterface
		);
	}

	protected function tear_down()
	{
		parent::tear_down();

		Mockery::close();
	}

	public function testSvgImage()
	{
		$data = file_get_contents(__DIR__ . '/../../data/img/demo.svg');

		$this->sizeConverter->shouldReceive('convert')->twice()->andReturn(0);
		$this->colorConverter->shouldReceive('convert')->times(140)->andReturn(0);

		$this->svg->ImageSVG($data);
	}

	public function testLogoManageroneSvgImage()
	{
		$data = file_get_contents(__DIR__ . '/../../data/img/logo_managerone.svg');

		$this->sizeConverter->shouldReceive('convert')->times(2)->andReturn(0);
		$this->colorConverter->shouldReceive('convert')->times(1)->andReturn(0);

		$this->svg->ImageSVG($data);
	}

	public function testLogoLivingparisianSvgImage()
	{
		$data = file_get_contents(__DIR__ . '/../../data/img/logo_livingparisian.svg');

		$this->colorConverter->shouldReceive('convert')->times(28)->andReturn(0);

		$this->svg->ImageSVG($data);
	}

	/**
	 * The title that is a direct child of the root is read.
	 */
	public function testAccessibleMetadataExtractsTopLevelTitle()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title>Hello</title>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertSame('Hello', $meta['title']);
		$this->assertNull($meta['desc']);
	}

	/**
	 * The desc that is a direct child of the root is read.
	 */
	public function testAccessibleMetadataExtractsTopLevelDesc()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<desc>Long description body.</desc>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertNull($meta['title']);
		$this->assertSame('Long description body.', $meta['desc']);
	}

	/**
	 * A title and a desc are both read.
	 */
	public function testAccessibleMetadataExtractsBoth()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title>Logo</title>'
			 . '<desc>Blue circle.</desc>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertSame('Logo', $meta['title']);
		$this->assertSame('Blue circle.', $meta['desc']);
	}

	/**
	 * A title inside a group labels the group, not the image, and is not read.
	 */
	public function testAccessibleMetadataIgnoresNestedTitle()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<g><title>NestedLabel</title>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</g>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertNull($meta['title']);
		$this->assertNull($meta['desc']);
	}

	/**
	 * An SVG that is not well formed gives neither, without a warning.
	 */
	public function testAccessibleMetadataMalformedSvgReturnsNulls()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title>Unclosed'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertNull($meta['title']);
		$this->assertNull($meta['desc']);
	}

	/**
	 * Entities and CDATA sections are decoded.
	 */
	public function testAccessibleMetadataDecodesEntitiesAndCdata()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title><![CDATA[A & B]]></title>'
			 . '<desc>Caf&#233;</desc>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertSame('A & B', $meta['title']);
		$this->assertSame("Caf\xC3\xA9", $meta['desc']);
	}

	/**
	 * An empty title counts as no title.
	 */
	public function testAccessibleMetadataEmptyTitleTreatedAsNull()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title></title>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertNull($meta['title']);
		$this->assertNull($meta['desc']);
	}

	/**
	 * Runs of whitespace in a title collapse to one space and the ends are trimmed.
	 */
	public function testAccessibleMetadataTitleWhitespaceCollapsed()
	{
		$svg = "<svg width=\"20\" height=\"20\" xmlns=\"http://www.w3.org/2000/svg\">\n"
			 . "  <title>\n    Pretty\n    Printed\n  </title>\n"
			 . "  <circle cx=\"10\" cy=\"10\" r=\"8\" fill=\"blue\"/>\n"
			 . "</svg>";
		$meta = $this->svg->extractAccessibleMetadata($svg);

		$this->assertSame('Pretty Printed', $meta['title']);
	}

	/**
	 * Input that is not SVG gives neither.
	 */
	public function testAccessibleMetadataNoSvgRootReturnsNulls()
	{
		$meta = $this->svg->extractAccessibleMetadata('not an svg');
		$this->assertNull($meta['title']);
		$this->assertNull($meta['desc']);
	}

}
