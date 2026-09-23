<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;

final class OptionalContentWriter
{

	use Strict;

	/**
	 * The groups the visibility property draws in, by resource name: the bit of Mpdf::$hasOC that marks each one used,
	 * the property holding its object number, its name, and its print and view states
	 */
	const VISIBILITY_GROUPS = [
		'OC1' => [1, 'n_ocg_print', 'Print only', 'ON', 'OFF'],
		'OC2' => [2, 'n_ocg_view', 'Screen only', 'OFF', 'ON'],
		'OC3' => [4, 'n_ocg_hidden', 'Hidden', 'OFF', 'OFF'],
	];

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	public function __construct(Mpdf $mpdf, BaseWriter $writer)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
	}

	public function writeOptionalContentGroups() // _putocg Optional Content Groups
	{
		foreach (self::VISIBILITY_GROUPS as $group) {
			list($bit, $property, $name, $print, $view) = $group;

			// Only the groups the document uses are written, as the catalog lists only those
			$this->mpdf->$property = null;
			if (!($this->mpdf->hasOC & $bit)) {
				continue;
			}

			$this->writer->object();
			$this->mpdf->$property = $this->mpdf->n;
			$this->writer->write('<</Type /OCG /Name ' . $this->writer->string($name));
			$this->writer->write('/Usage <</Print <</PrintState /' . $print . '>> /View <</ViewState /' . $view . '>>>>>>');
			$this->writer->write('endobj');
		}

		if (count($this->mpdf->layers)) {

			ksort($this->mpdf->layers);
			foreach ($this->mpdf->layers as $id => $layer) {
				$this->writer->object();
				$this->mpdf->layers[$id]['n'] = $this->mpdf->n;

				if (isset($this->mpdf->layerDetails[$id]['name']) && $this->mpdf->layerDetails[$id]['name']) {
					$name = $this->mpdf->layerDetails[$id]['name'];
				} else {
					$name = $layer['name'];
				}

				$this->writer->write('<</Type /OCG /Name ' . $this->writer->utf16BigEndianTextString($name) . '>>');
				$this->writer->write('endobj');
			}
		}
	}

	/**
	 * The page resource entries naming each visibility group written
	 *
	 * @return string
	 */
	public function visibilityProperties()
	{
		$properties = '';
		foreach (self::VISIBILITY_GROUPS as $resource => $group) {
			if ($this->mpdf->{$group[1]}) {
				$properties .= '/' . $resource . ' ' . $this->mpdf->{$group[1]} . ' 0 R ';
			}
		}

		return $properties;
	}

}
