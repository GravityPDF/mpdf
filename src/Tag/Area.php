<?php

namespace Mpdf\Tag;

use Mpdf\Ua\UaPolicy;

/**
 * A clickable region of a <map>, recorded against the map under PDF/UA. Its link annotation is
 * drawn once the image that uses the map has been laid out.
 */
class Area extends Tag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if (!$this->mpdf->PDFUA) {
			return;
		}
		$registry = $this->ua->getImageMapRegistry();
		$mapName  = $registry->getCurrentMapName();
		if ($mapName === null) {
			$this->ua->addWarning('PDF/UA-1: <area> outside <map>; ignored.');
			return;
		}

		$shape = isset($attr['SHAPE']) ? strtolower($attr['SHAPE']) : 'rect';
		$coords = $this->parseCoords(isset($attr['COORDS']) ? $attr['COORDS'] : '');
		$href = isset($attr['HREF']) ? $attr['HREF'] : null;
		$alt = isset($attr['ALT']) ? $attr['ALT'] : null;
		$target = isset($attr['TARGET']) ? $attr['TARGET'] : null;

		// As for <a>, except that with nothing inside an area to keep, the whole area goes
		if ($href !== null && $href !== '' && UaPolicy::isPolicyBlockedHref($href)) {
			if (empty($this->mpdf->PDFUAauto)) {
				throw new \Mpdf\MpdfException(
					'PDF/UA-1 Matterhorn 17-001 / 28-002: <area href="'
					. UaPolicy::formatHrefForMessage($href)
					. '"> uses a scheme with no accessible alternative. '
					. 'Remove the area, supply a real URL, or enable PDFUAauto '
					. 'to drop the area silently.'
				);
			}
			$this->ua->addWarning(
				'PDF/UA-1: <area href="'
				. UaPolicy::formatHrefForMessage($href)
				. '"> stripped (no Link annotation emitted) — '
				. 'scheme has no accessible alternative.'
			);
			return;
		}

		// A link annotation needs a text alternative (Matterhorn 28-002)
		if ($alt === null && ($href !== null && $href !== '')) {
			if (empty($this->mpdf->PDFUAauto)) {
				throw new \Mpdf\MpdfException(
					'PDF/UA-1 (Matterhorn 28-002): <area> missing alt attribute. '
					. 'Provide alt="description" so the link annotation has a text alternative. '
					. 'Enable PDFUAauto to auto-correct (synthesises alt from href).'
				);
			}
			$alt = 'Link to ' . $href;
			$this->ua->addWarning('PDF/UA-1: <area> missing alt; synthesised "' . $alt . '" for Link/Alt.');
		}

		if ($href === null || $href === '') {
			// No link to draw, so nothing for strict mode to object to
			$this->ua->addWarning('PDF/UA-1: <area> without href in <map name="' . $mapName . '"> — skipped (no clickable region).');
			return;
		}

		$registry->addArea($shape, $coords, $href, $alt, $target);
	}

	/**
	 * <area> is void, so there is nothing to close.
	 *
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
	}

	/**
	 * The numbers of a coords attribute, split on commas or whitespace. Anything not a number is
	 * dropped, and a shape left with too few is rejected when it is drawn.
	 *
	 * @param string $raw
	 * @return float[]
	 */
	private function parseCoords($raw)
	{
		$raw = trim((string) $raw);
		if ($raw === '') {
			return [];
		}
		$parts = preg_split('/[\s,]+/', $raw);
		$nums = [];
		foreach ($parts as $p) {
			if ($p === '' || !is_numeric($p)) {
				continue;
			}
			$nums[] = (float) $p;
		}
		return $nums;
	}
}
