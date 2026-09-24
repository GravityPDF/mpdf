<?php

namespace Mpdf\Xmp;

/**
 * An XMP extension that writes the schema and values it is given
 */
class FixedXmpExtension implements XmpExtensionInterface
{

	/**
	 * @var mixed[]
	 */
	private $schema;

	/**
	 * @var string[]
	 */
	private $properties;

	/**
	 * @param mixed[] $schema
	 * @param string[] $properties
	 */
	public function __construct($schema, $properties)
	{
		$this->schema = $schema;
		$this->properties = $properties;
	}

	/**
	 * An extension in the example namespace, declaring and writing an order reference
	 *
	 * @param string $prefix
	 * @param string $namespaceURI
	 *
	 * @return self
	 */
	public static function orderReference($prefix = 'ex', $namespaceURI = 'http://example.com/ns/order/1.0/')
	{
		return new self(
			['schema' => 'Example order schema', 'namespaceURI' => $namespaceURI, 'prefix' => $prefix, 'properties' => ['OrderReference' => 'The buyer\'s order reference']],
			['OrderReference' => 'PO-4471 & 4472']
		);
	}

	/**
	 * @return mixed[]
	 */
	public function getXmpExtensionSchema()
	{
		return $this->schema;
	}

	/**
	 * @return string[]
	 */
	public function getXmpProperties()
	{
		return $this->properties;
	}

}
