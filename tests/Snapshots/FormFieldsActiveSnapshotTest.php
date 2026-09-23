<?php

namespace Snapshots;

/**
 * Every form field as an interactive widget with its own appearance. The Arabic value has the document ask the viewer
 * to redraw the widgets, as the appearances do not shape it (#408).
 *
 * @group snapshot
 */
class FormFieldsActiveSnapshotTest extends Snapshot
{

	use FormFields;

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'form-fields-active';
	}

	/**
	 * The fields in a document with active forms
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf(['useActiveForms' => true]);
		$this->mpdf->WriteHTML($this->formFields());
	}

}
