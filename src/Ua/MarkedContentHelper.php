<?php

namespace Mpdf\Ua;

use Mpdf\Writer\BaseWriter;

/**
 * Writes the BDC, BMC and EMC operators that mark content.
 *
 * They go through BaseWriter::write() so they land in whichever buffer is being drawn (header,
 * column, rotated table…) rather than straight into the page.
 */
class MarkedContentHelper
{

	/** @var BaseWriter */
	private $writer;

	/**
	 * @var int How many sequences are open, which must be none when the document ends
	 */
	private $depth = 0;

	/**
	 * @param BaseWriter $writer
	 */
	public function __construct(BaseWriter $writer)
	{
		$this->writer = $writer;
	}

	/**
	 * Open a marked-content sequence, to be closed by one end()
	 *
	 * @param string $structType Unused for an artifact
	 * @param int    $mcid       From StructureTree::addContent(), or -1 for an artifact
	 *
	 * @return void
	 */
	public function begin($structType, $mcid)
	{
		// Catches a misspelt or non-standard type in development; assertions are off in production
		assert(
			$mcid === -1 || StructType::isValid($structType),
			'MarkedContentHelper::begin(): structType "' . $structType . '" is not a valid PDF/UA struct type'
		);
		if ($mcid === -1) {
			// An artifact has no property list, so BMC and not BDC
			$this->writer->write('/Artifact BMC');
		} else {
			$props = '/MCID ' . $mcid;
			$this->writer->write('/' . $structType . ' <<' . $props . '>> BDC');
		}
		$this->depth++;
	}

	/**
	 * Close the latest sequence. With none open nothing is written, so an unwinding error path
	 * cannot leave a stray EMC.
	 *
	 * @return void
	 */
	public function end()
	{
		if ($this->depth <= 0) {
			return;
		}
		$this->writer->write('EMC');
		$this->depth--;
	}

	/**
	 * @return int How many sequences are open
	 */
	public function getDepth()
	{
		return $this->depth;
	}
}
