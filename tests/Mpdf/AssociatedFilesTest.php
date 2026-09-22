<?php

namespace Mpdf;

class AssociatedFilesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A file given only its name and content is embedded without a warning, and without the entries it left out
	 */
	public function testEmbedsAFileWithoutItsOptionalKeys()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetAssociatedFiles([['name' => 'note.xml', 'content' => '<note/>']]);
		$mpdf->WriteHTML('Hello');
		$output = $this->output($mpdf);

		$this->assertMatchesRegularExpression('/<<\/F \(note\.xml\)\n\/Type \/Filespec/', $output);
		$this->assertStringNotContainsString('/AFRelationship', $output);
		$this->assertMatchesRegularExpression('/<<\/Type \/EmbeddedFile\n\/Length/', $output);
	}

	/**
	 * A file given by content that has none is named in the error, since it has no path to name
	 */
	public function testNamesAnEmptyFileByItsName()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetAssociatedFiles([['name' => 'empty.xml', 'content' => '']]);
		$mpdf->WriteHTML('Hello');

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Cannot access associated file - empty.xml');

		$this->output($mpdf);
	}

}
