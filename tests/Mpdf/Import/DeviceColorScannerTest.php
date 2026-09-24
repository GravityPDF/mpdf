<?php

namespace Mpdf\Import;

use setasign\Fpdi\PdfParser\PdfParser as FpdiParser; // Mpdf\Import\PdfParser takes the plain name here
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * The device colour spaces found in an imported page, by each way content paints in one. The resources are
 * direct objects, so no document needs parsing to resolve them.
 */
class DeviceColorScannerTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param string $content   A content stream
	 * @param array  $resources Its resources, by category
	 *
	 * @return string[] The device colour spaces found, sorted
	 */
	private function scan($content, array $resources = [])
	{
		$scanner = new DeviceColorScanner(new FpdiParser(StreamReader::createByString('')));
		$scanner->scan($this->form($content, $resources));

		$used = $scanner->used();
		sort($used);

		return $used;
	}

	/**
	 * @param string $content
	 * @param array  $resources By category, each a dictionary of entries
	 * @param array  $entries   More entries of the form's dictionary
	 *
	 * @return PdfStream A form XObject, its content unfiltered
	 */
	private function form($content, array $resources = [], array $entries = [])
	{
		$categories = [];
		foreach ($resources as $category => $named) {
			$categories[$category] = PdfDictionary::create($named);
		}

		return PdfStream::create(PdfDictionary::create($entries + [
			'Subtype' => PdfName::create('Form'),
			'Resources' => PdfDictionary::create($categories),
		]), $content);
	}

	/**
	 * @param string[] $names
	 *
	 * @return PdfArray
	 */
	private function names(array $names)
	{
		return PdfArray::create(array_map(function ($name) {
			return PdfName::create($name);
		}, $names));
	}

	/**
	 * The operators that set a device colour, and a device colour space selected by name
	 *
	 * @dataProvider operators
	 *
	 * @param string   $content
	 * @param string[] $used
	 */
	public function testOperatorsAndDeviceNames($content, array $used)
	{
		$this->assertSame($used, $this->scan($content));
	}

	/**
	 * @return array[] Content, and the device colour spaces it paints in
	 */
	public function operators()
	{
		return [
			'rg and RG' => ['1 0 0 rg 0 0 1 RG', ['RGB']],
			'g and G' => ['0.5 g', ['Gray']],
			'k and K' => ['0 0 0 1 K', ['CMYK']],
			'cs by device name' => ['/DeviceRGB cs 1 0 0 sc', ['RGB']],
			'each at once' => ['1 0 0 rg 0 g 0 0 0 1 k', ['CMYK', 'Gray', 'RGB']],
			'text is not an operator' => ['BT (1 0 0 rg) Tj ET', []],
			'none' => ['0 0 10 10 re f', []],
		];
	}

	/**
	 * A named colour space is looked up, and an indexed space is followed to its base and a separation to
	 * its alternate, which a reader paints in where it has no such ink
	 */
	public function testNamedIndexedAndSeparationSpaces()
	{
		$indexed = PdfArray::create([PdfName::create('Indexed'), PdfName::create('DeviceCMYK'), PdfNumeric::create(1)]);
		$separation = PdfArray::create([PdfName::create('Separation'), PdfName::create('Spot'), PdfName::create('DeviceRGB')]);
		$icc = PdfArray::create([PdfName::create('ICCBased'), PdfName::create('Profile')]);

		$this->assertSame(['CMYK'], $this->scan('/CS0 cs 0 sc', ['ColorSpace' => ['CS0' => $indexed]]));
		$this->assertSame(['RGB'], $this->scan('/CS0 CS 1 SC', ['ColorSpace' => ['CS0' => $separation]]));
		$this->assertSame([], $this->scan('/CS0 cs 1 0 0 sc', ['ColorSpace' => ['CS0' => $icc]]));
	}

	/**
	 * An inline image's colour space is read from its dictionary, and its data, however it reads, is stepped
	 * past to the operators after it; an inline mask paints in no colour space of its own
	 */
	public function testInlineImages()
	{
		$this->assertSame(['CMYK', 'RGB'], $this->scan("BI /W 1 /H 1 /BPC 8 /CS /RGB ID \x00rg\xff EI 0 0 0 1 k"));
		$this->assertSame(['Gray'], $this->scan("BI /W 1 /H 1 /IM true /CS /RGB ID \x80 EI 0 g"));
		$this->assertSame(['Gray'], $this->scan("BI /W 1 /H 1 /BPC 8 /CS /G ID \x80 EI"));
	}

	/**
	 * An image XObject paints in its colour space, and a form XObject in whatever it draws, followed however
	 * deep it nests; an image mask paints in none
	 */
	public function testXObjects()
	{
		$image = PdfStream::create(PdfDictionary::create(['Subtype' => PdfName::create('Image'), 'ColorSpace' => PdfName::create('DeviceRGB')]), '');
		$mask = PdfStream::create(PdfDictionary::create(['Subtype' => PdfName::create('Image'), 'ImageMask' => PdfBoolean::create(true), 'ColorSpace' => PdfName::create('DeviceCMYK')]), '');
		$inner = $this->form('0.5 g');
		$outer = $this->form('/Inner Do', ['XObject' => ['Inner' => $inner]]);

		$this->assertSame(['RGB'], $this->scan('/Im0 Do', ['XObject' => ['Im0' => $image]]));
		$this->assertSame([], $this->scan('/Mask Do', ['XObject' => ['Mask' => $mask]]));
		$this->assertSame(['Gray'], $this->scan('/Outer Do', ['XObject' => ['Outer' => $outer]]));
	}

	/**
	 * A shading painted with sh, and one a pattern paints with, are in their colour spaces, as is the
	 * transparency group a form is composited in
	 */
	public function testShadingsPatternsAndGroups()
	{
		$shading = PdfDictionary::create(['ShadingType' => PdfNumeric::create(2), 'ColorSpace' => PdfName::create('DeviceRGB')]);
		$pattern = PdfDictionary::create(['PatternType' => PdfNumeric::create(2), 'Shading' => PdfDictionary::create(['ColorSpace' => PdfName::create('DeviceCMYK')])]);

		$this->assertSame(['RGB'], $this->scan('/Sh0 sh', ['Shading' => ['Sh0' => $shading]]));
		$this->assertSame(['CMYK'], $this->scan('/Pattern cs /P0 scn 0 0 10 10 re f', ['Pattern' => ['P0' => $pattern]]));

		$scanner = new DeviceColorScanner(new FpdiParser(StreamReader::createByString('')));
		$scanner->scan($this->form('', [], ['Group' => PdfDictionary::create(['S' => PdfName::create('Transparency'), 'CS' => PdfName::create('DeviceRGB')])]));
		$this->assertSame(['RGB'], $scanner->used());
	}

	/**
	 * Content that stops parsing part way keeps what was found before it
	 */
	public function testContentThatDoesNotParseKeepsWhatCameBefore()
	{
		$this->assertSame(['RGB'], $this->scan('1 0 0 rg BI /W 1 ID no end'));
	}

}
