<?php

namespace Mpdf\Ua;

use Mpdf\Mpdf;

/**
 * Opens and closes the marked content that a flowing block's lines are drawn in under PDF/UA.
 *
 * Its state is in the pdfua_* keys of Mpdf::$flowingBlockAttr, as the block tags set the element
 * there too. reset() clears them when a flowing block begins.
 */
class FlowingBlockMarker
{

	/** @var Mpdf */
	private $mpdf;

	/** @var UaState */
	private $ua;

	/**
	 * @param Mpdf    $mpdf
	 * @param UaState $ua
	 */
	public function __construct(Mpdf $mpdf, UaState $ua)
	{
		$this->mpdf = $mpdf;
		$this->ua = $ua;
	}

	/**
	 * Close what the last flowing block left open, and mark nothing until a tag opens the new
	 * block's element.
	 *
	 * The block's marked content is opened as its text is drawn, not when its tag opens, as a
	 * block across pages needs an MCID on each. A <br> starts a new flowing block inside the same
	 * one, so whatever the last line opened is closed first.
	 *
	 * @return void
	 */
	public function reset()
	{
		$this->closeBlockBdcIfOpen();
		$this->mpdf->flowingBlockAttr['pdfua_struct_open'] = false;
		$this->mpdf->flowingBlockAttr['pdfua_type'] = 'P';
		$this->mpdf->flowingBlockAttr['pdfua_artifact_open'] = false;
		// Whether a BDC is open on this page that owes an EMC before the page or block ends
		$this->mpdf->flowingBlockAttr['pdfua_bdc_active'] = false;
		// The inline element the open BDC belongs to, or null when it is the block's
		$this->mpdf->flowingBlockAttr['pdfua_bdc_elem'] = null;
		// The block's own element, which the top of the structure stack may not be when an inline
		// element is open inside it
		$this->mpdf->flowingBlockAttr['pdfua_struct_elem'] = null;
	}

	/**
	 * Open the block's marked content on this page, if it is not open already.
	 *
	 * The MCID is given to the block's own element rather than the top of the structure stack,
	 * which may be an inline element inside the block.
	 *
	 * @return void
	 */
	public function ensureBlockBdcOpen()
	{
		$attr = $this->mpdf->flowingBlockAttr;
		// Text after an inline element goes back into the block's own marked content
		if (!empty($attr['pdfua_bdc_active']) && !empty($attr['pdfua_bdc_elem'])) {
			$this->closeBlockBdcIfOpen();
		} elseif (!empty($attr['pdfua_bdc_active'])) {
			return;
		}
		if (!empty($attr['pdfua_artifact_open'])) {
			$this->begin('Artifact', null);
		} elseif (!empty($attr['pdfua_struct_open']) && isset($attr['pdfua_struct_elem'])) {
			$this->begin($attr['pdfua_type'], $attr['pdfua_struct_elem']);
		}
	}

	/**
	 * End the marked content the flowing block has open, if any.
	 *
	 * @return bool Whether any was open
	 */
	public function closeBlockBdcIfOpen()
	{
		if (empty($this->mpdf->flowingBlockAttr['pdfua_bdc_active'])) {
			return false;
		}
		$this->ua->getMarkedContentHelper()->end();
		$this->mpdf->flowingBlockAttr['pdfua_bdc_active'] = false;
		$this->mpdf->flowingBlockAttr['pdfua_bdc_elem'] = null;

		return true;
	}

	/**
	 * Whether a list marker drawn now is the content of the Lbl element Li::open() made.
	 *
	 * @return bool
	 */
	public function listMarkerHasLbl()
	{
		return !$this->mpdf->ColActive && isset($this->mpdf->blk[$this->mpdf->blklvl]['pdfua_li_lbl_elem']);
	}

	/**
	 * Open the marked content a line's chunk is drawn in: that of the inline element restoreFont()
	 * says it is in, or else the block's.
	 *
	 * Table cell text always has an inline element (the cell, or one inside it). An object that
	 * marks its own content is drawn by printLineObject() after its chunk, and opens nothing here
	 * unless its chunk draws a span's background or border. In an artifact every object is drawn
	 * after the line's text, inside the artifact's marked content.
	 *
	 * @param int  $k The chunk's key in $objectbuffer
	 * @param bool $is_table
	 * @return bool Whether the chunk is an object that marks its own content
	 */
	public function markLineChunk($k, $is_table)
	{
		$object = empty($this->mpdf->objectbuffer[$k]) ? null : $this->mpdf->objectbuffer[$k];
		$tagged = $object !== null
			&& in_array($object['type'], ['image', 'barcode', 'textcircle', 'listmarker', 'input', 'textarea', 'select'], true)
			&& ($object['type'] !== 'listmarker' || $this->listMarkerHasLbl())
			&& empty($this->mpdf->flowingBlockAttr['pdfua_artifact_open'])
			&& !$this->ua->getStructureTree()->isInArtifact();
		if ($tagged && !$this->mpdf->spanbgcolor && empty($this->mpdf->spanborddet)) {
			return true;
		}
		$inlineElem = $object === null ? $this->ua->getAnchorState()->getInlineContentElem() : null;
		if ($inlineElem !== null) {
			$this->ensureInlineBdcOpen($inlineElem);
		} elseif (!$is_table) {
			$this->ensureBlockBdcOpen();
		}

		return $tagged;
	}

	/**
	 * Draw the object of a line's chunk now, so its marked content comes between the text before
	 * and after it, and not inside the text's. The next chunk of text begins its marked content again.
	 *
	 * What the object tags goes in the element the chunk is in, such as the Link around an image.
	 *
	 * @param int         $k        The chunk's key in $objectbuffer
	 * @param bool        $is_table
	 * @param string|bool $blockdir
	 * @return void
	 */
	public function printLineObject($k, $is_table, $blockdir)
	{
		$this->closeBlockBdcIfOpen();

		$parent = $this->ua->getAnchorState()->getInlineContentElem();
		if ($parent === null && !$is_table && isset($this->mpdf->flowingBlockAttr['pdfua_struct_elem'])) {
			$parent = $this->mpdf->flowingBlockAttr['pdfua_struct_elem'];
		}

		if ($parent !== null) {
			$this->ua->getStructureTree()->pushExisting($parent);
		}
		$this->mpdf->printobjectbuffer($is_table, $blockdir, [$k => $this->mpdf->objectbuffer[$k]]);
		if ($parent !== null) {
			$this->ua->getStructureTree()->close();
		}
		unset($this->mpdf->objectbuffer[$k]);
	}

	/**
	 * Open marked content for text inside an inline element (a Link, a Span with its own
	 * language, an Abbr, ruby), closing whatever the block had open.
	 *
	 * An MCID belongs to one element, so the text needs its own for the Link to have content and
	 * for the Span's /Lang or the Abbr's /E to apply to anything.
	 *
	 * @param StructureElement $elem The inline element the chunk is in
	 * @return void
	 */
	private function ensureInlineBdcOpen(StructureElement $elem)
	{
		if (!empty($this->mpdf->flowingBlockAttr['pdfua_bdc_active'])
			&& $this->mpdf->flowingBlockAttr['pdfua_bdc_elem'] === $elem) {
			return;
		}
		$this->closeBlockBdcIfOpen();
		// Inside an artifact block no inline element was made, so the text is artifact too
		if (!empty($this->mpdf->flowingBlockAttr['pdfua_artifact_open'])) {
			$this->begin('Artifact', null);
		} else {
			$this->begin($elem->getType(), $elem, $elem);
		}
	}

	/**
	 * Begin the flowing block's marked content.
	 *
	 * @param string                $type
	 * @param StructureElement|null $elem    The element given the MCID, or null for an artifact
	 * @param StructureElement|null $bdcElem The inline element it is for, or null when it is the block's
	 * @return void
	 */
	private function begin($type, $elem, $bdcElem = null)
	{
		$mcid = $elem === null
			? -1
			: $this->ua->getStructureTree()->addContentForElement($elem, $this->mpdf->getPdfUaStructParents());
		$this->ua->getMarkedContentHelper()->begin($type, $mcid);
		$this->mpdf->flowingBlockAttr['pdfua_bdc_active'] = true;
		$this->mpdf->flowingBlockAttr['pdfua_bdc_elem'] = $bdcElem;
	}
}
