<?php

namespace Mpdf;

class PageBox implements \ArrayAccess
{

	private $container = [];

	public function __construct()
	{
		$this->container = [
			'current' => null,
			'outer_width_LR' => null,
			'outer_width_TB' => null,
			// The outer widths that Mpdf::$orig_lMargin and the other original margins include
			'orig_outer_width_LR' => 0,
			'orig_outer_width_TB' => 0,
			'using' => null,
		];
	}

	/**
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value)
	{
		if (!$this->offsetExists($offset)) {
			throw new \Mpdf\MpdfException('Invalid key to set for PageBox');
		}

		$this->container[$offset] = $value;
	}

	/**
	 * @return bool
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return array_key_exists($offset, $this->container);
	}

	/**
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
	{
		if (!$this->offsetExists($offset)) {
			throw new \Mpdf\MpdfException('Invalid key to set for PageBox');
		}

		$this->container[$offset] = null;
	}

	/**
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		if (!$this->offsetExists($offset)) {
			throw new \Mpdf\MpdfException('Invalid key to set for PageBox');
		}

		return $this->container[$offset];
	}

}
