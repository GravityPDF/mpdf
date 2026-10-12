<?php

namespace Mpdf;

/**
 * Mpdf::getStateSnapshot() and restoreStateSnapshot() carry the table of contents look-ahead and the measuring
 * pass of a page-break-inside:avoid block
 */
class StateSnapshotTest extends BaseMpdfTest
{

	/**
	 * CurrentFont is a reference into the font table. Restoring a snapshot taken in one font while another was
	 * selected must bind it to the restored selection: SetFont() does not while the family, style and size
	 * already match, and the glyphs Write() registered afterwards went to the other font's subset
	 */
	public function testRestoringASnapshotRebindsTheCurrentFont()
	{
		$this->mpdf = new Mpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans']);
		$this->mpdf->WriteHTML('<p>Before</p>');
		$snapshot = $this->mpdf->getStateSnapshot();

		$this->mpdf->SetFont('dejavusans', 'B');
		$this->mpdf->restoreStateSnapshot($snapshot);
		$this->mpdf->Write(5, "\xCE\xA9");

		$this->assertSame('dejavusans', $this->mpdf->FontFamily . $this->mpdf->FontStyle);
		$this->assertArrayHasKey(937, $this->mpdf->fonts['dejavusans']['subset']);
	}

	/**
	 * The first block holds the page width by reference. Restoring a snapshot puts the width back, there and in the
	 * block, rather than keep the width it was changed to
	 */
	public function testRestoringASnapshotPutsThePageWidthBack()
	{
		$this->mpdf->WriteHTML('<p>Before</p>');
		$width = $this->mpdf->pgwidth;
		$snapshot = $this->mpdf->getStateSnapshot();

		$this->mpdf->pgwidth = 10;
		$this->mpdf->restoreStateSnapshot($snapshot);

		$this->assertSame($width, $this->mpdf->pgwidth);
		$this->assertSame($width, $this->mpdf->blk[0]['width']);
	}

	/**
	 * A snapshot holds a property bound by reference as that reference, and would put back its current value rather
	 * than the one it was taken with. Mpdf::getStateSnapshot() copies out the two Mpdf binds; a document with a header,
	 * footer, columns, a table, a form field and a table of contents leaves no others
	 */
	public function testNoOtherPropertyIsHeldByReference()
	{
		if (!class_exists('ReflectionReference')) {
			$this->markTestSkipped('ReflectionReference needs PHP 7.4');
		}

		$this->mpdf->SetHTMLHeader('<div>Header</div>');
		$this->mpdf->SetHTMLFooter('<div>{PAGENO}</div>');
		$this->mpdf->WriteHTML('<tocpagebreak /><h1>Title</h1><tocentry content="Title" /><p>Text</p>'
			. '<columns column-count="2" /><p>In columns</p><columns column-count="1" />'
			. '<table><tr><td>Cell</td></tr></table><form><input type="text" name="field" /></form>');

		$properties = function () {
			return get_object_vars($this);
		};
		$collaborators = function () {
			return [$this->form, $this->tableOfContents];
		};

		$held = [];
		foreach (array_merge([$this->mpdf], $collaborators->call($this->mpdf)) as $object) {
			$snapshot = $properties->call($object);
			foreach (array_keys($snapshot) as $name) {
				if (\ReflectionReference::fromArrayElement($snapshot, $name) !== null) {
					$held[] = get_class($object) . '::' . $name;
				}
			}
		}

		sort($held);
		$this->assertSame(['Mpdf\\Mpdf::CurrentFont', 'Mpdf\\Mpdf::pgwidth'], $held);
	}
}
