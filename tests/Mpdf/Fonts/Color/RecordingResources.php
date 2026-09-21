<?php

namespace Mpdf\Fonts\Color;

/**
 * Hands out a name per image, and keeps the images
 */
class RecordingResources implements GlyphResources
{

	/**
	 * @var string[] Each image registered, in order
	 */
	public $images = [];

	/**
	 * @var bool Whether every image is refused, as one that cannot be decoded is
	 */
	public $refuse = false;

	/**
	 * @inheritdoc
	 */
	public function image($data)
	{
		if ($this->refuse) {
			return null;
		}

		$this->images[] = $data;

		return '/I' . count($this->images);
	}
}
