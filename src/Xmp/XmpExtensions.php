<?php

namespace Mpdf\Xmp;

use Mpdf\MpdfException;
use Mpdf\Strict;
use Mpdf\Writer\MetadataWriter;

/**
 * The XMP extensions a document carries, each checked when it is registered against the packet and the others
 */
class XmpExtensions
{

	use Strict;

	/**
	 * The extension each call keeps only one of, by that call
	 *
	 * @var mixed[]
	 */
	private $kept = [];

	/**
	 * @var mixed[]
	 */
	private $added = [];

	/**
	 * Register the one extension a call keeps, in place of any it registered before
	 *
	 * @param string $registeredBy The call that registers it, e.g. SetEmbeddedInvoice(), for error messages to name
	 * @param \Mpdf\Xmp\XmpExtensionInterface $extension
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function set($registeredBy, XmpExtensionInterface $extension)
	{
		$others = $this->kept;
		unset($others[$registeredBy]);

		$this->kept[$registeredBy] = $this->check($registeredBy, $extension, array_merge(array_values($others), $this->added));
	}

	/**
	 * Register an extension beside those already registered
	 *
	 * @param string $registeredBy The call that registers it, e.g. AddXmpExtension(), for error messages to name
	 * @param \Mpdf\Xmp\XmpExtensionInterface $extension
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function add($registeredBy, XmpExtensionInterface $extension)
	{
		$this->added[] = $this->check($registeredBy, $extension, $this->all());
	}

	/**
	 * The extension a call keeps, registered with set()
	 *
	 * @param string $registeredBy
	 *
	 * @return \Mpdf\Xmp\XmpExtensionInterface|null
	 */
	public function get($registeredBy)
	{
		return isset($this->kept[$registeredBy]) ? $this->kept[$registeredBy]['extension'] : null;
	}

	/**
	 * Every extension, those set before those added
	 *
	 * @return mixed[] Each with its 'extension', the 'schema' it gave when registered, and the call it was 'registeredBy'
	 */
	public function all()
	{
		return array_merge(array_values($this->kept), $this->added);
	}

	/**
	 * The extension with its schema, provided the schema is well formed and its prefix and namespace are free
	 *
	 * @param string $registeredBy
	 * @param \Mpdf\Xmp\XmpExtensionInterface $extension
	 * @param mixed[] $others The extensions already registered, as all() gives them
	 *
	 * @return mixed[]
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function check($registeredBy, XmpExtensionInterface $extension, $others)
	{
		$schema = $extension->getXmpExtensionSchema();
		$which = 'The XMP extension ' . get_class($extension);
		$xmlName = '/^[A-Za-z_][\w.-]*$/';

		if (!isset($schema['schema'], $schema['prefix']) || !is_string($schema['schema'])
			|| empty($schema['namespaceURI']) || !is_string($schema['namespaceURI'])
			|| empty($schema['properties']) || !is_array($schema['properties'])) {
			throw new MpdfException($which . ' must describe its schema with a schema name, a namespaceURI, a prefix and at least one property.');
		}

		$prefix = $schema['prefix'];
		if (!is_string($prefix) || !preg_match($xmlName, $prefix)) {
			throw new MpdfException(sprintf('%s has the prefix "%s", which is not an XML name. Use letters, digits, "_", "-" and ".", starting with a letter or "_".', $which, is_string($prefix) ? $prefix : gettype($prefix)));
		}

		foreach ($schema['properties'] as $name => $description) {
			if (!is_string($name) || !preg_match($xmlName, $name) || !is_string($description)) {
				throw new MpdfException($which . ' must give each property\'s description, a string, by the property\'s name, an XML name.');
			}
		}

		$undeclared = array_diff(array_keys($extension->getXmpProperties()), array_keys($schema['properties']));
		if ($undeclared) {
			throw new MpdfException(sprintf('%s writes %s, which its schema does not declare. Add each property it writes to its schema.', $which, implode(', ', $undeclared)));
		}

		$taken = MetadataWriter::PACKET_NAMESPACES;
		foreach ($others as $other) {
			$taken[$other['schema']['prefix']] = $other['schema']['namespaceURI'];
		}

		if (isset($taken[$prefix]) || in_array($schema['namespaceURI'], $taken, true)) {
			throw new MpdfException(sprintf('%s uses the prefix "%s" and namespace %s, but the document\'s XMP already has one or the other. Give each extension a prefix and namespace of its own.', $which, $prefix, $schema['namespaceURI']));
		}

		return ['extension' => $extension, 'schema' => $schema, 'registeredBy' => $registeredBy];
	}

}
