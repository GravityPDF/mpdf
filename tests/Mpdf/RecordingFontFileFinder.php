<?php

namespace Mpdf;

use Mpdf\Fonts\FontFileFinder;

class RecordingFontFileFinder extends FontFileFinder
{

	public $lookups = [];

	public function findFontFile($name)
	{
		$this->lookups[] = $name;

		return parent::findFontFile($name);
	}

}
