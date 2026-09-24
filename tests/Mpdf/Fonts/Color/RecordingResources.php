<?php

namespace Mpdf\Fonts\Color;

/**
 * Hands out a name per image, shading, group and soft mask, and keeps what each was registered with
 */
class RecordingResources implements GlyphResources
{

	/**
	 * @var string[] Each image registered, in order
	 */
	public $images = [];

	/**
	 * @var array[] Each shading registered, in order
	 */
	public $shadings = [];

	/**
	 * @var array[] Each group registered, in order, as [content, box, isolated]
	 */
	public $groups = [];

	/**
	 * @var array[] Each soft mask registered, in order, as [content, box, luminosity, inverted]
	 */
	public $masks = [];

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

		return ['/I' . count($this->images), 64, 64];
	}

	/**
	 * @inheritdoc
	 */
	public function rgb(array $rgb, $stroking = false)
	{
		return vsprintf('%.3F %.3F %.3F ', $rgb) . ($stroking ? 'RG' : 'rg');
	}

	/**
	 * @inheritdoc
	 */
	public function gray($level)
	{
		return Geometry::number($level) . ' g';
	}

	/**
	 * @inheritdoc
	 */
	public function alpha($opacity)
	{
		return sprintf('/GS%.2F gs', $opacity);
	}

	/**
	 * @inheritdoc
	 */
	public function blend($mode)
	{
		return '/' . $mode . ' gs';
	}

	/**
	 * @inheritdoc
	 */
	public function shading(array $shading)
	{
		$this->shadings[] = $shading;

		return '/Sh' . count($this->shadings);
	}

	/**
	 * @inheritdoc
	 */
	public function group($content, array $box, $isolated = false)
	{
		$this->groups[] = [$content, $box, $isolated];

		return '/Fx' . count($this->groups);
	}

	/**
	 * @inheritdoc
	 */
	public function softMask($content, array $box, $luminosity = false, $inverted = false)
	{
		$this->masks[] = [$content, $box, $luminosity, $inverted];

		return '/SM' . count($this->masks) . ' gs';
	}
}
