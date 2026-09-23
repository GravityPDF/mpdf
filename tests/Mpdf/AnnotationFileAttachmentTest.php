<?php

namespace Mpdf;

/**
 * A page lists its annotations by object number, and the numbers are reserved before the objects are written.
 * The reservation and the writing have to agree on how many objects an annotation takes, or every annotation
 * after the first one that disagrees is referenced by the number of something else.
 *
 * `allowAnnotationFiles` is where they disagreed: a rejected file attachment is written as one plain text
 * annotation, with no stream for the file, but two numbers were reserved for it - so the widget of a form field
 * on the same page went unreferenced and the page pointed at the ExtGState written after it instead. That is
 * GravityPDF/mpdf#343.
 */
class AnnotationFileAttachmentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Whether the file attachment is allowed, and whether the page also carries a form field. Only a page with
	 * something to reference after the annotation can show the miscount; the other three are here so they
	 * stay working
	 *
	 * @return array[]
	 */
	public function attachments()
	{
		return [
			'allowed, with a form field' => [true, true],
			'allowed, without a form field' => [true, false],
			'rejected, with a form field' => [false, true],
			'rejected, without a form field' => [false, false],
		];
	}

	/**
	 * Whether the file attachment is allowed, for the tests a form field makes no difference to
	 *
	 * @return array[]
	 */
	public function permissions()
	{
		return [
			'allowed' => [true],
			'rejected' => [false],
		];
	}

	/**
	 * @dataProvider attachments
	 *
	 * @param bool $allow
	 * @param bool $field
	 */
	public function testEveryObjectThePageListsAsAnAnnotationIsOne($allow, $field)
	{
		$pdf = $this->document($allow, $field);
		$refs = $this->annotationRefs($pdf);

		$this->assertNotSame([], $refs[0], 'The page should list its annotations');
		foreach ($refs[0] as $number) {
			$this->assertStringContainsString('/Type /Annot', $this->object($pdf, $number), "Object $number is listed in /Annots and should be an annotation");
		}
	}

	/**
	 * The widget of the field is what the miscount left unreferenced
	 *
	 * @dataProvider permissions
	 *
	 * @param bool $allow
	 */
	public function testTheWidgetOfAFormFieldOnThePageIsListed($allow)
	{
		$pdf = $this->document($allow, true);

		$this->assertSame(1, substr_count($pdf, '/Subtype /Widget'), 'The document should carry one widget');
		$this->assertSame(1, substr_count($this->annotations($pdf)[0], '/Subtype /Widget'), 'The page should list the widget');
	}

	/**
	 * What the gate decides is not what the fix changes: a rejection still writes the annotation as a note
	 * with the file left out, and only the number of objects that takes was ever wrong
	 *
	 * @dataProvider permissions
	 *
	 * @param bool $allow
	 */
	public function testTheFileIsEmbeddedOnlyWhereItIsAllowed($allow)
	{
		$pdf = $this->document($allow, true);

		$this->assertSame($allow ? 1 : 0, substr_count($pdf, '/Type /EmbeddedFile'), 'The file should be embedded only where it is allowed');
		$this->assertStringContainsString($allow ? '/Subtype /FileAttachment' : '/Subtype /Text', $this->annotations($pdf)[0]);
	}

	/**
	 * A popup takes the object an embedded file would have, so an annotation asking for both is two objects
	 * either way and the count was never wrong here - the one thing #343 did not break. A popup is an
	 * annotation in its own right, so the page lists it wherever it is written
	 *
	 * @dataProvider permissions
	 *
	 * @param bool $allow
	 */
	public function testAPopupIsListedWhereverItIsWritten($allow)
	{
		$pdf = $this->document($allow, true, true);

		$this->assertSame($allow ? 0 : 1, substr_count($pdf, '/Subtype /Popup'), 'A popup should be written only where the file is not');
		$this->assertSame($allow ? 0 : 1, substr_count($this->annotations($pdf)[0], '/Subtype /Popup'), 'The page should list the popup it has');
	}

	/**
	 * A document holding an annotation that attaches a file, optionally with a form field in front of it and a
	 * popup on it
	 *
	 * @param bool $allow Whether allowAnnotationFiles lets the file be embedded
	 * @param bool $field Whether the page carries an active form field
	 * @param bool $popup Whether the annotation asks for a popup
	 *
	 * @return string
	 */
	private function document($allow, $field, $popup = false)
	{
		if ($allow && !class_exists('finfo')) {
			$this->markTestSkipped('ext-fileinfo is needed to embed the file of an annotation');
		}

		$mpdf = $this->mpdf(['allowAnnotationFiles' => $allow, 'useActiveForms' => $field]);
		$mpdf->WriteHTML($field ? '<p><input type="text" name="field" value="Hello" /></p>' : '<p>Text</p>');
		$mpdf->Annotation('Attached', 0, 0, 'Paperclip', '', '', 0, false, $popup, __DIR__ . '/../data/xml/test.xml');

		return $this->output($mpdf);
	}

}
