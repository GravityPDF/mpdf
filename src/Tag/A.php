<?php

namespace Mpdf\Tag;

use Mpdf\Ua\AriaIdResolver;

use Mpdf\Mpdf;
use Mpdf\Ua\UaPolicy;

class A extends Tag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if (isset($attr['NAME']) && $attr['NAME'] != '') {
			$e = '';
			/* -- BOOKMARKS -- */
			if ($this->mpdf->anchor2Bookmark) {
				$objattr = [];
				$objattr['CONTENT'] = htmlspecialchars_decode($attr['NAME'], ENT_QUOTES);
				$objattr['type'] = 'bookmark';
				if (!empty($attr['LEVEL'])) {
					$objattr['bklevel'] = $attr['LEVEL'];
				} else {
					$objattr['bklevel'] = 0;
				}
				$e = Mpdf::OBJECT_IDENTIFIER . "type=bookmark,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
			}
			/* -- END BOOKMARKS -- */
			if ($this->mpdf->tableLevel) { // *TABLES*
				$this->mpdf->_saveCellTextBuffer($e, '', $attr['NAME']); // *TABLES*
			} // *TABLES*
			else { // *TABLES*
				$this->mpdf->_saveTextBuffer($e, '', $attr['NAME']); //an internal link (adds a space for recognition)
			} // *TABLES*
		}

		// An empty href makes no hyperlink, only perhaps a destination, which is tagged as the text
		// around it rather than as a Link
		$rawHref = isset($attr['HREF']) ? $attr['HREF'] : null;
		$isHyperlink = $rawHref !== null && trim($rawHref) !== '';

		if ($isHyperlink) {
			$this->mpdf->InlineProperties['A'] = $this->mpdf->saveInlineProperties();
			$properties = $this->cssManager->MergeCSS('INLINE', 'A', $attr);
			if (!empty($properties)) {
				$this->mpdf->setCSS($properties, 'INLINE');
			}
			$this->mpdf->HREF = $attr['HREF']; // mPDF 5.7.4 URLs

			// A script link has no accessible alternative (Matterhorn 17-001). Under PDFUAauto the link
			// goes and its text stays, in a Span if it has a language or aria-* to keep.
			if ($this->mpdf->PDFUA && UaPolicy::isPolicyBlockedHref($attr['HREF'])) {
				if (empty($this->mpdf->PDFUAauto)) {
					throw new \Mpdf\MpdfException(
						'PDF/UA-1 Matterhorn 17-001 / 28-002: <a href="'
						. UaPolicy::formatHrefForMessage($attr['HREF'])
						. '"> uses a scheme with no accessible alternative. '
						. 'Remove the link, supply a real URL (https:, mailto:, '
						. 'tel:, #fragment, ...), or enable PDFUAauto to strip '
						. 'the link and keep the visible text.'
					);
				}
				$this->ua->addWarning(
					'PDF/UA-1: <a href="'
					. UaPolicy::formatHrefForMessage($attr['HREF'])
					. '"> stripped (no Link annotation emitted) — '
					. 'javascript:/vbscript: schemes have no accessible alternative.'
				);
				$this->mpdf->HREF = '';
				$spanDepth = $this->openStrippedAnchorSpan($attr) ? 1 : 0;
				$this->ua->getAnchorState()->pushStripFrame(true, $spanDepth);
				return;
			}

			// A link in an artifact, such as a running header, has no Link element to hang its
			// annotation from, and the annotation is dropped. The frame keeps close() in step.
			if ($this->mpdf->PDFUA && $this->ua->getStructureTree()->isInArtifact()) {
				$this->ua->getAnchorState()->pushStripFrame(false, 0);
				return;
			}

			if ($this->mpdf->PDFUA) {
				$structAttrs = [];
				if (isset($attr['LANG'])) {
					$structAttrs['Lang'] = $attr['LANG'];
				}
				// Names a link around nothing but decoration (Matterhorn 28-002); text inside still
				// names it first
				if (!empty($this->mpdf->PDFUAauto)) {
					$structAttrs['Alt'] = 'Link to ' . $attr['HREF'];
				}
				$this->ua->getStructureTree()->open('Link', $structAttrs);

				$elem = $this->ua->getStructureTree()->getCurrent();
				// For the message about an empty link; StructureWriter does not write it
				$elem->setAttribute('_href', $attr['HREF']);
				$this->ua->getAriaIdResolver()->queueAriaRefs($elem, $attr);

				// The link annotation is made a kid of this element (Matterhorn 02-003)
				$this->ua->getAnchorState()->setLinkStructElem($elem);
				$this->ua->getAnchorState()->pushStripFrame(false, 0);
			}
		} elseif ($this->mpdf->PDFUA) {
			// Not a link: a Span only if there is a language or aria-label to carry
			$structAttrs = [];
			if (isset($attr['LANG']) && $attr['LANG'] !== '') {
				$structAttrs['Lang'] = $attr['LANG'];
			}
			if (isset($attr['ARIA-LABEL']) && $attr['ARIA-LABEL'] !== '') {
				$structAttrs['Alt'] = $attr['ARIA-LABEL'];
			}
			$spanDepth = 0;
			if (!empty($structAttrs)) {
				$this->ua->getStructureTree()->open('Span', $structAttrs);
				$elem = $this->ua->getStructureTree()->getCurrent();
				$this->ua->getAriaIdResolver()->queueAriaRefs($elem, $attr);
				$spanDepth = 1;
			}
			// close() takes one frame for every <a>, with or without a Span
			$this->ua->getAnchorState()->pushStripFrame(true, $spanDepth);
		} elseif (isset($attr['HREF'])) {
			// An empty href is still styled as a link, though it links nowhere
			$this->mpdf->InlineProperties['A'] = $this->mpdf->saveInlineProperties();
			$properties = $this->cssManager->MergeCSS('INLINE', 'A', $attr);
			if (!empty($properties)) {
				$this->mpdf->setCSS($properties, 'INLINE');
			}
			$this->mpdf->HREF = $attr['HREF'];
		}
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->PDFUA && $this->ua->getAnchorState()->hasStripFrames()) {
			$entry     = $this->ua->getAnchorState()->popStripFrame();
			$stripped  = $entry[0];
			$spanDepth = $entry[1];
			if ($stripped) {
				while ($spanDepth > 0) {
					$this->ua->getStructureTree()->close();
					$spanDepth--;
				}
			} else {
				$this->ua->getStructureTree()->close();
			}
		}

		// A link made outside an <a> must not be taken for this one's
		if ($this->mpdf->PDFUA) {
			$this->ua->getAnchorState()->clearLinkStructElem();
		}

		$this->mpdf->HREF = '';
		if (isset($this->mpdf->InlineProperties['A'])) {
			$this->mpdf->restoreInlineProperties($this->mpdf->InlineProperties['A']);
		}
		unset($this->mpdf->InlineProperties['A']);
	}

	/**
	 * Opens a Span for the text of a link taken out, if it has a language, an id or aria-* to
	 * keep. As InlineTag::openInlineUaStruct(), which A does not inherit.
	 *
	 * @param array $attr
	 * @return bool Whether a Span was opened
	 */
	private function openStrippedAnchorSpan(array $attr)
	{
		$structAttrs = [];
		if (isset($attr['LANG']) && $attr['LANG'] !== '') {
			$structAttrs['Lang'] = $attr['LANG'];
		}
		if (isset($attr['ARIA-LABEL']) && $attr['ARIA-LABEL'] !== '') {
			$structAttrs['Alt'] = $attr['ARIA-LABEL'];
		}
		if (empty($structAttrs) && !AriaIdResolver::toObjattr($attr)) {
			return false;
		}
		$this->ua->getStructureTree()->open('Span', $structAttrs);
		$elem = $this->ua->getStructureTree()->getCurrent();
		$this->ua->getAriaIdResolver()->queueAriaRefs($elem, $attr);
		return true;
	}
}
