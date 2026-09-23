<?php

namespace Snapshots;

/**
 * Every form field drawn into the page as it looks, without active forms
 *
 * @group snapshot
 */
class FormFieldsStaticSnapshotTest extends Snapshot
{

	use FormFields;

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'form-fields-static';
	}

	/**
	 * The fields in an ordinary document
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($this->formFields());
	}

}
