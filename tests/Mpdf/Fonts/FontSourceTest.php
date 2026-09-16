<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * FontSubsetter reads a font through FontSourceInterface, and TTFontFile is what implements it.
 *
 * The subset golden master pins what the subsetter emits when handed a TTFontFile. These pin the seam
 * itself: that TTFontFile answers the interface the way its own members do, and that the subsetter
 * builds the same program from any source, asking it for nothing the interface does not state.
 */
class FontSourceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Its glyf table is past the parser's maxStrLenRead, and it has compound glyphs to renumber
	 */
	const FONT = 'NotoSans-Regular';

	/**
	 * @var string Where the parser caches what it reads. Mpdf\Cache creates it.
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/font-source';

	public function testTheParserAnswersTheInterfaceAsItsOwnMembersDo()
	{
		$parser = $this->parser();
		$this->assertInstanceOf(FontSourceInterface::class, $parser);

		$parser->open($this->file())->skip(4);
		$parser->selectFont(0);
		$parser->readTableDirectory();

		foreach (['glyf', 'OS/2', 'post'] as $tag) {
			$this->assertTrue($parser->hasTable($tag));
			$this->assertSame([$parser->tables[$tag]['offset'], $parser->tables[$tag]['length']], $parser->getTablePosition($tag));
		}

		$this->assertFalse($parser->hasTable('CFF '));
		$this->assertSame([0, 0], $parser->getTablePosition('CFF '));

		$parser->maxStrLenRead = 1234;
		$this->assertSame(1234, $parser->getMaxStrLenRead());
	}

	/**
	 * @dataProvider builderProvider
	 */
	public function testBuildsTheSameProgramFromASourceThatIsNotTheParser($method)
	{
		$source = new DelegatingFontSource($this->parser());

		$this->assertSame($this->build(new FontSubsetter($this->parser()), $method), $this->build(new FontSubsetter($source), $method));

		foreach (['open', 'selectFont', 'readTableDirectory', 'hasTable', 'getTablePosition'] as $asked) {
			$this->assertContains($asked, $source->calls);
		}
	}

	/**
	 * Below maxStrLenRead the glyf table is read whole and each glyph cut from it, and above it each
	 * glyph is read from the file; the two have to come to the same bytes.
	 *
	 * @dataProvider subsetterProvider
	 */
	public function testBuildsTheSameProgramWhetherGlyfIsReadWholeOrAGlyphAtATime($method)
	{
		$whole = new DelegatingFontSource($this->parser());
		$whole->maxStrLenRead = PHP_INT_MAX;

		$perGlyph = new DelegatingFontSource($this->parser());
		$perGlyph->maxStrLenRead = 0;

		$this->assertSame($this->build(new FontSubsetter($whole), $method), $this->build(new FontSubsetter($perGlyph), $method));
		$this->assertContains('getMaxStrLenRead', $perGlyph->calls);
	}

	/**
	 * @dataProvider builderProvider
	 */
	public function testLeavesOutATableTheSourceDoesNotList($method)
	{
		$this->assertContains('post', $this->tags($this->build(new FontSubsetter($this->parser()), $method)));

		$source = new DelegatingFontSource($this->parser());
		$source->hiddenTables = ['post'];

		$this->assertNotContains('post', $this->tags($this->build(new FontSubsetter($source), $method)));
	}

	public function builderProvider()
	{
		return array_merge($this->subsetterProvider(), [['repackageTTF']]);
	}

	public function subsetterProvider()
	{
		return [['makeSubset'], ['makeSubsetSIP']];
	}

	private function build(FontSubsetter $subsetter, $method)
	{
		if ($method === 'repackageTTF') {
			return $subsetter->repackageTTF($this->file());
		}

		// The digits and both cases of the alphabet, plus a character NotoSans draws as a compound glyph
		$subset = array_merge([0x20], range(0x30, 0x39), range(0x41, 0x5A), range(0x61, 0x7A), [0xC5]);

		return $subsetter->$method($this->file(), $subset);
	}

	/**
	 * @return string[] The tags in a font program's table directory
	 */
	private function tags($program)
	{
		$reader = new BlobReader($program);
		$reader->seek(4);
		$numTables = $reader->readUInt16();

		$reader->skip(6); // searchRange, entrySelector, rangeShift

		$tags = [];
		for ($i = 0; $i < $numTables; $i++) {
			$tags[] = $reader->read(4);
			$reader->skip(12); // checksum, offset, length
		}

		return $tags;
	}

	private function file()
	{
		return GoldenMaster::FONT_DIR . '/' . self::FONT . '.ttf';
	}

	private function parser()
	{
		return new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win');
	}
}
