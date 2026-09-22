<?php

namespace Mpdf;

use Mpdf\Fonts\BlobReader;
use Mpdf\Fonts\TableWriter;

/**
 * The TTCfontID a fontdata entry sets picks which font of a TrueType Collection is registered and embedded
 */
class TtcFontIdTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/** Holds the collection and the cache the documents write */
	private $tempDir;

	/**
	 * Pack two of the fixture fonts into a collection, in a directory of its own
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-ttc-font-id-' . uniqid('', true);
		mkdir($this->tempDir);

		$fixtures = __DIR__ . '/../data/ttf/';
		file_put_contents($this->tempDir . '/Pair.ttc', $this->collection([
			file_get_contents($fixtures . 'NotoSans-GPOS3-Synthetic.ttf'),
			file_get_contents($fixtures . 'Carlito-MarkAttachmentType-Subset.ttf'),
		]));
	}

	/**
	 * Remove the collection and the cache
	 */
	protected function tear_down()
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The face registered, and the one embedded, is the one TTCfontID names
	 *
	 * @dataProvider faceProvider
	 *
	 * @param int $TTCfontID
	 * @param string $name The PostScript name of that face
	 */
	public function testTheConfiguredFaceIsTheOneUsed($TTCfontID, $name)
	{
		$this->assertFace($TTCfontID, $name);
	}

	/**
	 * Each face of the collection, with its PostScript name
	 *
	 * @return array
	 */
	public function faceProvider()
	{
		return [
			'the first face' => [1, 'NotoSans-Regular'],
			'the second face' => [2, 'Carlito-Regular'],
		];
	}

	/**
	 * Metrics cached for one face are not reused for another, which is also what catches a cache written while every
	 * configured TTCfontID was read as the first face
	 */
	public function testTheCacheOfAnotherFaceIsNotReused()
	{
		$this->assertFace(1, 'NotoSans-Regular');
		$this->assertFace(2, 'Carlito-Regular');
	}

	/**
	 * Render a document in the collection's face $TTCfontID and assert it is the face named $name
	 *
	 * @param int $TTCfontID
	 * @param string $name
	 */
	private function assertFace($TTCfontID, $name)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'fontDir' => [$this->tempDir],
			'default_font' => 'pair',
			'fontdata' => ['pair' => ['R' => 'Pair.ttc', 'TTCfontID' => ['R' => $TTCfontID]]],
		]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>abc</p>');

		$this->assertSame($TTCfontID, $mpdf->fonts['pair']['TTCfontID']);
		$this->assertSame($name, $mpdf->fonts['pair']['name']);
		$this->assertMatchesRegularExpression('/\/FontDescriptor\s+\/FontName \/[A-Z]{6}\+' . $name . '\s/', $this->output($mpdf));
	}

	/**
	 * A TrueType Collection of $fonts, each whole font copied in with its table offsets moved to where it now starts.
	 * Nothing is shared between them, which the format allows but does not require.
	 *
	 * @param string[] $fonts
	 *
	 * @return string
	 */
	private function collection(array $fonts)
	{
		$header = 'ttcf' . TableWriter::uint32(0x00010000) . TableWriter::uint32(count($fonts));
		$start = strlen($header) + 4 * count($fonts);
		$body = '';

		foreach ($fonts as $font) {
			$header .= TableWriter::uint32($start);

			$reader = new BlobReader($font);
			$reader->seek(4);
			$numTables = $reader->readUInt16();

			for ($i = 0; $i < $numTables; $i++) {
				$record = 12 + 16 * $i + 8;
				$reader->seek($record);
				$font = TableWriter::replace($font, $record, TableWriter::uint32($reader->readUInt32() + $start));
			}

			$font .= str_repeat("\0", (4 - strlen($font) % 4) % 4);
			$body .= $font;
			$start += strlen($font);
		}

		return $header . $body;
	}

}
