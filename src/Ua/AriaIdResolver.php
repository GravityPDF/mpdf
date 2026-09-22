<?php

namespace Mpdf\Ua;

/**
 * Resolves the ARIA attributes that refer to other elements by id.
 *
 * The id an aria-labelledby names may come later in the HTML, so references are queued as the
 * document is parsed and resolved when it ends, before the structure tree is written:
 * aria-labelledby as /Alt, aria-describedby and aria-details as /E, and aria-owns and
 * aria-controls as /Ref. aria-flowto and aria-activedescendant have nothing to become in a
 * static PDF and are warned about.
 */
class AriaIdResolver
{

	/**
	 * The longest id list accepted, in bytes; far beyond any real use, and short of the memory a
	 * list of a megabyte would take once split
	 */
	const MAX_ARIA_IDS_LENGTH = 16384;

	/**
	 * The most ids taken from one attribute
	 */
	const MAX_ARIA_IDS_TOKENS = 256;

	/**
	 * @var string[]
	 */
	const REFERENCE_ARIA_ATTRS = [
		'aria-labelledby', 'aria-describedby', 'aria-details',
		'aria-controls', 'aria-owns', 'aria-flowto', 'aria-activedescendant',
	];

	/** @var StructureTree */
	private $tree;

	/** @var array<string, StructureElement> Keyed by the lowercased id */
	private $idMap = [];

	/**
	 * @var array [referring element, attribute name, target id]
	 */
	private $pending = [];

	/**
	 * @var string[] For ids nothing in the document has
	 */
	private $unresolvedWarnings = [];

	/**
	 * A name or description that points at nothing, or at something without text. An empty
	 * /Alt or /E would hide the referring element's own content, so none is written, and the
	 * document fails or, under PDFUAauto, warns.
	 *
	 * @var string[]
	 */
	private $nameResolutionErrors = [];

	/**
	 * @var string[] For aria-flowto and aria-activedescendant, which a static PDF cannot express
	 */
	private $relationshipWarnings = [];

	/**
	 * @var int Numbers the ids made up for a TH without one, across the whole document so two
	 *          tables cannot give theirs the same
	 */
	private $syntheticThCounter = 0;

	/**
	 * @param StructureTree $tree
	 */
	public function __construct(StructureTree $tree)
	{
		$this->tree = $tree;
	}

	/**
	 * The first element to use an id keeps it, as getElementById() does in a browser
	 *
	 * @param string           $id
	 * @param StructureElement $elem
	 *
	 * @return void
	 */
	public function registerId($id, StructureElement $elem)
	{
		// The HTML parser uppercases id="" but not the ids an aria attribute lists
		$id = strtolower((string) $id);
		if ($id !== '' && !isset($this->idMap[$id])) {
			$this->idMap[$id] = $elem;
		}
	}

	/**
	 * Register an element's id and queue each of its ARIA references.
	 *
	 * A tag passes its HTML attributes (ID, ARIA-LABELLEDBY…). An object drawn later passes what
	 * toObjattr() made (pdfua_id, pdfua_aria_labelledby…) with $objattr set; that has to be said,
	 * not guessed, as an object also carries an unrelated 'ID', its Form XObject number.
	 *
	 * @param StructureElement $elem
	 * @param array            $attr
	 * @param bool             $objattr Whether the keys are those toObjattr() makes
	 *
	 * @return void
	 */
	public function queueAriaRefs(StructureElement $elem, array $attr, $objattr = false)
	{
		$idKey = $objattr ? 'pdfua_id' : 'ID';
		if (!empty($attr[$idKey])) {
			$this->registerId($attr[$idKey], $elem);
		}
		foreach (self::REFERENCE_ARIA_ATTRS as $ariaName) {
			$key = $objattr
				? 'pdfua_' . str_replace('-', '_', $ariaName) // pdfua_aria_labelledby
				: strtoupper($ariaName);                       // ARIA-LABELLEDBY
			if (!empty($attr[$key])) {
				$this->queue($elem, $ariaName, $attr[$key]);
			}
		}
	}

	/**
	 * The id and ARIA references of an element whose structure element is opened when its object is
	 * drawn, keyed as queueAriaRefs() reads them with $objattr set.
	 *
	 * @param array $attr HTML tag attributes, keys in upper case
	 *
	 * @return array<string, string>
	 */
	public static function toObjattr(array $attr)
	{
		$objattr = [];
		if (!empty($attr['ID'])) {
			$objattr['pdfua_id'] = $attr['ID'];
		}
		foreach (self::REFERENCE_ARIA_ATTRS as $ariaName) {
			if (!empty($attr[strtoupper($ariaName)])) {
				$objattr['pdfua_' . str_replace('-', '_', $ariaName)] = $attr[strtoupper($ariaName)];
			}
		}

		return $objattr;
	}

	/**
	 * @return int The number for the next id made up for a TH, from 1
	 */
	public function nextSyntheticThCounter()
	{
		return ++$this->syntheticThCounter;
	}

	/**
	 * Queue each id of a space-separated list
	 *
	 * @param StructureElement $elem         The element carrying the attribute
	 * @param string           $ariaAttrName Lowercase, e.g. 'aria-labelledby'
	 * @param string           $targetIds
	 *
	 * @return void
	 */
	public function queue(StructureElement $elem, $ariaAttrName, $targetIds)
	{
		$targetIds = (string) $targetIds;

		if (strlen($targetIds) > self::MAX_ARIA_IDS_LENGTH) {
			$this->unresolvedWarnings[] = $ariaAttrName
				. ' attribute exceeded ' . self::MAX_ARIA_IDS_LENGTH
				. ' bytes; ignored to prevent memory amplification (UA1 audit M-1).';
			return;
		}

		$tokens = preg_split(
			'/\s+/',
			trim($targetIds),
			self::MAX_ARIA_IDS_TOKENS + 1,
			PREG_SPLIT_NO_EMPTY
		);
		if (!is_array($tokens)) {
			return;
		}
		if (count($tokens) > self::MAX_ARIA_IDS_TOKENS) {
			$this->unresolvedWarnings[] = $ariaAttrName
				. ' attribute had more than ' . self::MAX_ARIA_IDS_TOKENS
				. ' IDs; truncated (UA1 audit M-1).';
			$tokens = array_slice($tokens, 0, self::MAX_ARIA_IDS_TOKENS);
		}

		foreach ($tokens as $id) {
			$this->pending[] = [$elem, $ariaAttrName, strtolower($id)];
		}
	}

	/**
	 * Give each queued reference to the element it came from, once the document has ended and
	 * before the structure tree is written
	 *
	 * @return void
	 */
	public function resolveAll()
	{
		// These name or describe the element, and an empty /Alt or /E would hide its content
		$namingAttrs = ['aria-labelledby', 'aria-describedby', 'aria-details'];

		foreach ($this->pending as $pending) {
			$elem = $pending[0];
			$attr = $pending[1];
			$id   = $pending[2];

			if (!isset($this->idMap[$id])) {
				$msg = 'Unresolved ARIA reference: ' . $attr . '="' . $id
					. '" has no matching id="' . $id . '" in the document';
				if (in_array($attr, $namingAttrs, true)) {
					$this->nameResolutionErrors[] = $msg;
				} else {
					$this->unresolvedWarnings[] = $msg;
				}
				continue;
			}
			$target = $this->idMap[$id];
			switch ($attr) {
				case 'aria-labelledby':
					// An alt="" or aria-label="" on the element itself comes first
					$existing = $elem->getAttributes();
					if (!isset($existing['Alt'])) {
						$text = $this->collectText($target);
						if ($text === '') {
							$this->nameResolutionErrors[] = 'ARIA reference resolved to empty text: '
								. $attr . '="' . $id . '" target carries no text content; '
								. 'refusing to emit an empty /Alt (Matterhorn 13-004)';
						} else {
							$elem->setAttribute('Alt', $text);
						}
					}
					break;
				case 'aria-describedby':
				case 'aria-details':
					$text = $this->collectText($target);
					if ($text === '') {
						$this->nameResolutionErrors[] = 'ARIA reference resolved to empty text: '
							. $attr . '="' . $id . '" target carries no text content; '
							. 'refusing to emit an empty /E (Matterhorn 28-002)';
					} else {
						$elem->setAttribute('E', $text);
					}
					break;
				case 'aria-owns':
				case 'aria-controls':
					$elem->addRelationship($attr, $target);
					break;
				case 'aria-flowto':
				case 'aria-activedescendant':
					// A tagged PDF reads in the order of its structure tree and has no focus, so
					// there is nothing to write; the loss is reported rather than kept quiet
					$this->relationshipWarnings[] = 'ARIA relationship has no PDF/UA-1 representation: '
						. $attr . '="' . $id . '" cannot be expressed in a static PDF/UA-1 '
						. 'structure tree; the relationship was not emitted.';
					break;
			}
		}
	}

	/**
	 * The text an element gives a reference to it: its /ActualText or /Alt when it has one,
	 * otherwise the text it and its descendants draw, in order and joined by spaces
	 *
	 * @param StructureElement $elem
	 *
	 * @return string
	 */
	private function collectText(StructureElement $elem)
	{
		$attrs = $elem->getAttributes();
		if (isset($attrs['ActualText']) && $attrs['ActualText'] !== '') {
			return $attrs['ActualText'];
		}
		if (isset($attrs['Alt']) && $attrs['Alt'] !== '') {
			return $attrs['Alt'];
		}
		$parts = [];
		$own = $elem->getOwnText();
		if ($own !== '') {
			$parts[] = $own;
		}
		foreach ($elem->getChildren() as $child) {
			$childText = $this->collectText($child);
			if ($childText !== '') {
				$parts[] = $childText;
			}
		}
		return implode(' ', $parts);
	}

	/**
	 * @return string[] For ids nothing in the document has
	 */
	public function getUnresolvedWarnings()
	{
		return $this->unresolvedWarnings;
	}

	/**
	 * The names and descriptions that could not be given, which fail the document or, under
	 * PDFUAauto, become warnings
	 *
	 * @return string[]
	 */
	public function getNameResolutionErrors()
	{
		return $this->nameResolutionErrors;
	}

	/**
	 * The aria-flowto and aria-activedescendant references left out. The document conforms
	 * without them, so they only ever warn.
	 *
	 * @return string[]
	 */
	public function getRelationshipWarnings()
	{
		return $this->relationshipWarnings;
	}
}
