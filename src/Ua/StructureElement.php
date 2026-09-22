<?php

namespace Mpdf\Ua;

use Mpdf\Exception\InvalidArgumentException;

/**
 * One node of the document's structure tree: a P, H1, Figure, TD, Link and so on.
 *
 * StructureTree builds the tree while the HTML is parsed, and StructureWriter writes each
 * element out as a /StructElem object when the document is closed.
 */
class StructureElement
{

	/** @var string */
	protected $type;

	/** @var StructureElement|null Null for the Document root */
	protected $parent;

	/** @var StructureElement[] */
	protected $children;

	/**
	 * The marked content this element owns. `page` is the /StructParents key, `pageRef` the page
	 * object filled in at write time, and `stm` the Form XObject holding the content, or 0 when
	 * it is drawn on the page itself.
	 *
	 * @var array<int, array{page:int, mcid:int, pageRef:int, stm:int}>
	 */
	protected $mcids;

	/**
	 * The annotations (links, widgets, notes, file attachments) reached through /OBJR kids
	 *
	 * @var array<int, array{structParent:int, obj:int}>
	 */
	protected $objrefs;

	/**
	 * Entries such as Alt, ActualText, Lang, E, T and ID go straight into the dictionary;
	 * the Layout, Table and List ones (Placement, Scope, ColSpan, ListNumbering…) StructureWriter
	 * gathers into /A attribute objects.
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes;

	/**
	 * The text written under this element's own marked content, one entry per line, from which
	 * an aria-labelledby or aria-describedby reference to it takes its text
	 *
	 * @var string[]
	 */
	protected $textRuns;

	/**
	 * The elements aria-owns and aria-controls point at, written as /Ref. Kept apart from
	 * $attributes so nothing here can end up as a key of the dictionary.
	 *
	 * @var array<int, array{attr:string, target:StructureElement}>
	 */
	protected $relationships;

	/**
	 * @var string|null The /ID, which a Note needs and a TH referred to by a TD's /Headers needs
	 */
	protected $id;

	/** @var int The object number, 0 until StructureWriter writes the element */
	protected $objNum;

	/**
	 * @param string $type       A standard PDF structure type
	 * @param array  $attributes PDF attribute names, not HTML ones: ['Scope' => 'Row'], not ['scope' => 'row']
	 *
	 * @throws \Mpdf\Exception\InvalidArgumentException When $type is not a standard structure type
	 */
	public function __construct($type, $attributes = [])
	{
		// A custom role has to be role-mapped with StructureTree::addRoleMapping() instead
		if (!StructType::isValid($type)) {
			throw new InvalidArgumentException(
				'Invalid PDF struct type: "' . $type . '"'
			);
		}
		$this->type       = $type;
		$this->attributes = $attributes;
		$this->parent     = null;
		$this->children   = [];
		$this->mcids      = [];
		$this->objrefs    = [];
		$this->relationships = [];
		$this->textRuns   = [];
		$this->id         = null;
		$this->objNum     = 0;
	}

	/** @return string */
	public function getType()
	{
		return $this->type;
	}

	/** @return StructureElement|null Null for the Document root */
	public function getParent()
	{
		return $this->parent;
	}

	/** @return StructureElement[] */
	public function getChildren()
	{
		return $this->children;
	}

	/** @return array<int, array{page:int, mcid:int, pageRef:int, stm:int}> */
	public function getMcids()
	{
		return $this->mcids;
	}

	/** @return array<int, array{structParent:int, obj:int}> */
	public function getObjrefs()
	{
		return $this->objrefs;
	}

	/** @return array<int, array{attr:string, target:StructureElement}> The aria-owns and aria-controls targets */
	public function getRelationships()
	{
		return $this->relationships;
	}

	/** @return array<string,mixed> */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/** @return string|null */
	public function getId()
	{
		return $this->id;
	}

	/** @return int 0 until StructureWriter writes the element */
	public function getObjNum()
	{
		return $this->objNum;
	}

	// The mutators below are for StructureTree and StructureWriter; tag classes go through
	// StructureTree::open(), addContent() and addObjref().

	/**
	 * @param string $id Unique in the document; the caller makes sure of it
	 *
	 * @return void
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * An id whose bytes are the same written as a PDF name and as a PDF string.
	 *
	 * A TH's /ID is a string while a TD's /Headers names it, and readers match the two on their
	 * bytes. Letters are lowercased because the HTML parser uppercases id="" but leaves headers=""
	 * and the aria attributes as written, and anything outside [a-z0-9_.-] is #-escaped.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public static function sanitiseIdForPdf($id)
	{
		// A name is limited to 127 bytes (ISO 32000-1 §7.3.5) and each escape costs three, so a
		// long id is cut and given a hash of the whole to keep it apart from ids sharing its
		// start. Two ids that collide break the /Headers lookup, hence 64 bits of hash.
		$maxBytes  = 127;
		$hashChars = 16;
		$truncTo   = $maxBytes - 3 - $hashChars; // = 108

		$id = (string) $id;
		$out = '';
		$len = strlen($id);
		for ($i = 0; $i < $len; $i++) {
			$ord = ord($id[$i]);
			if ($ord >= 0x41 && $ord <= 0x5A) {
				$out .= chr($ord + 0x20);
			} elseif (($ord >= 0x30 && $ord <= 0x39) // 0-9
				|| ($ord >= 0x61 && $ord <= 0x7A) // a-z
				|| $ord === 0x5F // _
				|| $ord === 0x2D // -
				|| $ord === 0x2E // .
			) {
				$out .= $id[$i];
			} else {
				$out .= sprintf('#%02X', $ord);
			}
		}

		// Cut between escapes, never inside one: a '#' without its two hex digits is not a valid
		// name. The hash is joined with '#2D', an escaped '-'.
		if (strlen($out) > $maxBytes) {
			$prefixLen = 0;
			$outLen    = strlen($out);
			while ($prefixLen < $outLen) {
				$tokenLen = ($out[$prefixLen] === '#') ? 3 : 1;
				if ($prefixLen + $tokenLen > $truncTo) {
					break;
				}
				$prefixLen += $tokenLen;
			}
			$prefix = substr($out, 0, $prefixLen);
			$suffix = '#2D' . substr(sha1($id), 0, $hashChars);
			$out    = $prefix . $suffix;
		}

		return $out;
	}

	/**
	 * @param int $n The object number StructureWriter reserved for the element
	 *
	 * @return void
	 */
	public function setObjNum($n)
	{
		$this->objNum = $n;
	}

	/**
	 * @param string $key   A PDF attribute name, not an HTML one
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setAttribute($key, $value)
	{
		$this->attributes[$key] = $value;
	}

	/**
	 * Record a piece of marked content the element owns; an element crossing pages has several.
	 *
	 * @param int $page    The /StructParents key of the page or Form XObject
	 * @param int $mcid
	 * @param int $pageRef The page object, 0 until it is written
	 * @param int $stm     The Form XObject holding the content (an imported tagged page), or 0
	 *
	 * @return void
	 */
	public function addMcid($page, $mcid, $pageRef = 0, $stm = 0)
	{
		$this->mcids[] = ['page' => $page, 'mcid' => $mcid, 'pageRef' => $pageRef, 'stm' => $stm];
	}

	/**
	 * Keep a line of the text the element draws, for an aria-labelledby or aria-describedby
	 * reference to it to read back. Without it the reference resolves to an empty /Alt, which
	 * hides the referring element's own content.
	 *
	 * @param string $text
	 *
	 * @return void
	 */
	public function appendText($text)
	{
		$text = (string) $text;
		if ($text !== '') {
			$this->textRuns[] = $text;
		}
	}

	/**
	 * The text drawn directly under this element, not its descendants, its lines trimmed and
	 * joined by a space so the words either side of a line break stay apart
	 *
	 * @return string
	 */
	public function getOwnText()
	{
		$parts = [];
		foreach ($this->textRuns as $run) {
			$run = trim($run);
			if ($run !== '') {
				$parts[] = $run;
			}
		}
		return implode(' ', $parts);
	}

	/**
	 * Fill in the page and Form XObject of content merged from an imported tagged page, which
	 * are not known until those objects are written
	 *
	 * @param int $idx     Index into the element's marked content
	 * @param int $pageRef
	 * @param int $stm
	 *
	 * @return void
	 */
	public function patchMcr($idx, $pageRef, $stm)
	{
		if (isset($this->mcids[$idx])) {
			$this->mcids[$idx]['pageRef'] = $pageRef;
			$this->mcids[$idx]['stm']     = $stm;
		}
	}

	/**
	 * Point the element at its annotation, which a Link, Widget, Note or file attachment must
	 * reach from the structure tree
	 *
	 * @param int $structParent The annotation's /StructParent key
	 * @param int $obj          The annotation's object number
	 *
	 * @return void
	 */
	public function addObjref($structParent, $obj)
	{
		$this->objrefs[] = ['structParent' => $structParent, 'obj' => $obj];
	}

	/**
	 * @param StructureElement $child
	 *
	 * @return void
	 */
	public function addChild(StructureElement $child)
	{
		$child->parent    = $this;
		$this->children[] = $child;
	}

	/**
	 * Take back the child just added, as when a TH swaps the TD it inherited for its own
	 * element; a cell left behind would give the row an extra column.
	 *
	 * @return void
	 */
	public function popLastChild()
	{
		array_pop($this->children);
	}

	/**
	 * @param StructureElement[] $children The children this element already has, in a new order
	 *
	 * @return void
	 */
	public function reorderChildren(array $children)
	{
		$this->children = array_values($children);
	}

	/**
	 * @param StructureElement $child
	 *
	 * @return void
	 */
	public function removeChild(StructureElement $child)
	{
		foreach ($this->children as $i => $existing) {
			if ($existing === $child) {
				array_splice($this->children, $i, 1);
				return;
			}
		}
	}

	/**
	 * Record the element an aria-owns or aria-controls attribute points at, written as /Ref
	 *
	 * @param string           $ariaAttr 'aria-owns' or 'aria-controls'
	 * @param StructureElement $target
	 *
	 * @return void
	 */
	public function addRelationship($ariaAttr, StructureElement $target)
	{
		$this->relationships[] = ['attr' => $ariaAttr, 'target' => $target];
	}
}
