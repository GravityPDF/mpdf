<?php

namespace Mpdf\File;

/**
 * Maps file extensions to the MIME types a file with that extension may contain
 */
final class FileTypeAllowList
{

	/**
	 * The MIME types for each extension, all in lower case
	 *
	 * @var array[]
	 */
	private $types = [];

	/**
	 * @param array $map Extensions (a leading dot is ignored) to one MIME type or a list of them, in any case
	 */
	public function __construct(array $map)
	{
		foreach ($map as $extension => $types) {
			$extension = strtolower(ltrim($extension, '.'));
			foreach ((array) $types as $type) {
				$this->types[$extension][] = strtolower(trim($type));
			}
		}
	}

	/**
	 * Whether the list names no extension at all
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return $this->types === [];
	}

	/**
	 * @param string $extension Lower case, without the dot
	 *
	 * @return bool
	 */
	public function allowsExtension($extension)
	{
		return isset($this->types[$extension]);
	}

	/**
	 * Whether a file with the extension may hold content of the MIME type
	 *
	 * @param string $extension Lower case, without the dot
	 * @param string $type
	 *
	 * @return bool
	 */
	public function allowsType($extension, $type)
	{
		return $this->allowsExtension($extension) && in_array(strtolower($type), $this->types[$extension], true);
	}

}
