<?php

namespace Mpdf;

class Buffer
{

	/** @var array<int, string> */
	private $contents = [];

	/** @var int */
	private $length = 0;

	public function __construct()
	{
	}

	public function append($content, $newLine = false)
	{
		if ($content === null || $content === '') {
			return;
		}

		$content = (string) $content;
		$contentLength = strlen($content);

		// Do not create an additional buffer entry if the content is relatively small.
		if ($newLine && $contentLength < 1000000) {
			$content .= "\n";
			++$contentLength;
		}

		$this->contents[] = $content;
		$this->length += $contentLength;

		if ($newLine && $contentLength >= 1000000) {
			$this->contents[] = "\n";
			++$this->length;
		}
	}

	public function getLength()
	{
		return $this->length;
	}

	/**
	 * Empty the buffer and return what it held, one entry per append, so it can be written again in a new order
	 *
	 * @return array<int, string>
	 */
	public function detach()
	{
		$contents = $this->contents;
		$this->contents = [];
		$this->length = 0;

		return $contents;
	}

	public function writeToFile($handle)
	{
		foreach ($this->contents as $content) {
			fwrite($handle, $content);
		}
	}

	public function writeToOutput()
	{
		foreach ($this->contents as $content) {
			echo $content;
		}
	}

	public function writeToString()
	{
		return implode('', $this->contents);
	}

	public function getHash()
	{
		$hash = '';
		foreach ($this->contents as $content) {
			$hash = md5($hash.$content);
		}

		return $hash;
	}

}
