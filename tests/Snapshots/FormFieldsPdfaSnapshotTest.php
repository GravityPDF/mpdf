<?php

namespace Snapshots;

/**
 * Every form field as an interactive widget in a PDF/A-2b document, which viewers draw from the widgets' appearances
 * alone, and which leaves out the buttons' actions
 *
 * @group snapshot
 */
class FormFieldsPdfaSnapshotTest extends Snapshot
{

	use FormFields;

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'form-fields-pdfa';
	}

	/**
	 * The fields in a PDF/A-2b document with active forms
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf(['useActiveForms' => true, 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B']);
		$this->mpdf->WriteHTML($this->formFields());
	}

}
