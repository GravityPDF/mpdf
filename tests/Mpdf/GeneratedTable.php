<?php

namespace Mpdf;

/**
 * What the generators of Ucdn's tables have in common: reading the registry they are built from, and
 * writing one array of a class back without disturbing anything else in it.
 */
trait GeneratedTable
{

	/**
	 * Reads one file of a registry, keeping a copy so the next run needs no network. The registries are
	 * larger between them than the tables they produce, so the copies are not committed.
	 *
	 * @param string $file The copy to read, and to download to when it is not there
	 * @param string $url  Where to download it from
	 *
	 * @return string The file, in LF
	 */
	private function cached($file, $url)
	{
		if (!is_file($file)) {
			$body = file_get_contents($url);
			if ($body === false) {
				throw new \RuntimeException(sprintf('Could not read %s', $url));
			}
			if (!is_dir(dirname($file))) {
				mkdir(dirname($file), 0777, true);
			}
			file_put_contents($file, $body);
		}

		return str_replace("\r\n", "\n", file_get_contents($file));
	}

	/**
	 * The class at $path in LF. The patterns a generator matches with are anchored on the line, and a
	 * Windows checkout of the class ends its lines with CRLF.
	 *
	 * @return string
	 */
	private function sourceInLf($path)
	{
		return str_replace("\r\n", "\n", file_get_contents($path));
	}

	/**
	 * Writes a rewritten class back in the line endings the copy on disk has, rather than the LF the
	 * work was done in.
	 */
	private function writeBack($path, $source)
	{
		if (strpos(file_get_contents($path), "\r\n") !== false) {
			$source = str_replace("\n", "\r\n", $source);
		}

		file_put_contents($path, $source);
	}

	/**
	 * Replaces the line naming the release a class was generated from.
	 *
	 * @param string $name    The marker, e.g. 'UNIDATA_VERSION'
	 * @param string $version What to write after it
	 *
	 * @return string
	 */
	private function replaceVersion($source, $name, $version)
	{
		$replaced = preg_replace(
			'/\t\/\/ ' . preg_quote($name, '/') . " [\d.]+\n/",
			"\t// " . $name . ' ' . $version . "\n",
			$source,
			1,
			$count
		);

		if ($count !== 1) {
			throw new \RuntimeException(sprintf('Could not find the %s line to rewrite', $name));
		}

		return $replaced;
	}

	/**
	 * Replaces the body of one array in a class, matching it by its declaration.
	 *
	 * @return string
	 */
	private function replaceArray($source, $declaration, $body)
	{
		$pattern = '/(' . preg_quote($declaration, '/') . " = \[\n).*?(\n\t\];\n)/s";
		$replaced = preg_replace_callback(
			$pattern,
			function ($m) use ($body) {
				return $m[1] . $body . $m[2];
			},
			$source,
			1,
			$count
		);

		if ($count !== 1) {
			throw new \RuntimeException(sprintf('Could not find %s to rewrite', $declaration));
		}

		return $replaced;
	}

}
