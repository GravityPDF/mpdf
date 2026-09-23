<?php

namespace Mpdf\Ua;

/**
 * The logical structure of a PDF/UA document as it is read: the elements open at this point, the
 * marked content IDs each page has handed out, and the ParentTree that leads from content back to
 * its element. Content drawn as an artifact creates no element.
 */
class StructureTree
{

	/** @var StructureElement The Document element, which is never closed */
	protected $root;

	/** @var StructureElement[] The open elements, $root first */
	protected $stack;

	/**
	 * @var array<int,int> The next MCID of each /StructParents key; each key counts from 0 with no gaps
	 *   (ISO 32000-1 §14.7.4.4)
	 */
	protected $mcidByPage;

	/**
	 * @var array<int, array<int, StructureElement>> The element owning each MCID, by /StructParents key
	 */
	protected $parentTree;

	/**
	 * @var int How many artifacts are open; while any is, no elements are created and no MCIDs handed out
	 */
	protected $artifactDepth;

	/** @var array<int, StructureElement> The element owning each annotation, by its /StructParent */
	protected $annotParentTree;

	/**
	 * @var \Mpdf\Ua\UaState|null Set after construction, as it is built after this class. Pages and
	 *   annotations are keyed in the same ParentTree, so they draw their keys from its one counter.
	 */
	protected $uaState;

	/**
	 * @var array<string,string> The standard type each custom role is mapped to, for the RoleMap
	 */
	protected $roleMappings;

	/**
	 * Start with only the Document element open
	 */
	public function __construct()
	{
		$this->root              = new StructureElement('Document');
		$this->stack              = [$this->root];
		$this->mcidByPage         = [];
		$this->parentTree         = [];
		$this->artifactDepth      = 0;
		$this->annotParentTree    = [];
		$this->roleMappings       = [];
		$this->uaState            = null;
	}

	/**
	 * Give the tree the counter it shares with pages for ParentTree keys, once UaState has been built
	 *
	 * @param \Mpdf\Ua\UaState $uaState
	 */
	public function setUaState(UaState $uaState)
	{
		$this->uaState = $uaState;
	}

	/**
	 * @return StructureElement The Document element
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * @return StructureElement The innermost open element
	 */
	public function getCurrent()
	{
		return end($this->stack);
	}

	/**
	 * The innermost open element when it is an inline one, such as a Link or Span, that owns the
	 * text written inside it; null when that text belongs to the enclosing block.
	 *
	 * A marked content ID has one owner, so text in a Link or a Span carrying /Lang or /E has to be
	 * marked as the Link's or Span's; left on the block, the inline element would be empty.
	 *
	 * @return StructureElement|null
	 */
	public function getCurrentInline()
	{
		$top = end($this->stack);
		if ($top === false) {
			return null;
		}
		return in_array($top->getType(), self::$inlineContentTypes, true) ? $top : null;
	}

	/**
	 * The inline types that own the text written while they are the innermost open element
	 *
	 * @var string[]
	 */
	private static $inlineContentTypes = ['Link', 'Span', 'Ruby', 'RB', 'RT', 'RP'];

	/**
	 * @return array<int, array<int, StructureElement>> The element owning each MCID, by /StructParents key
	 */
	public function getParentTree()
	{
		return $this->parentTree;
	}

	/**
	 * @return array<int, StructureElement> The element owning each annotation, by its /StructParent
	 */
	public function getAnnotParentTree()
	{
		return $this->annotParentTree;
	}

	/**
	 * @return array<string,string> The standard type each custom role is mapped to
	 */
	public function getRoleMappings()
	{
		return $this->roleMappings;
	}

	/**
	 * @return bool Whether content drawn now is an artifact, such as a running header or an aria-hidden subtree
	 */
	public function isInArtifact()
	{
		return $this->artifactDepth > 0;
	}

	/**
	 * Open an element inside the current one. Inside an artifact nothing is opened, and the
	 * matching close() closes nothing.
	 *
	 * @param string $type       A standard structure type
	 * @param array  $attributes Such as Alt, Scope or ColSpan
	 *
	 * @throws \Mpdf\Exception\InvalidArgumentException For a type that is not standard
	 */
	public function open($type, $attributes = [])
	{
		if (!StructType::isValid($type)) {
			throw new \Mpdf\Exception\InvalidArgumentException('Invalid struct type: "' . $type . '"');
		}
		if ($this->isInArtifact()) {
			return;
		}
		$elem = new StructureElement($type, $attributes);
		$this->getCurrent()->addChild($elem);
		$this->stack[] = $elem;
	}

	/**
	 * Add an element inside the current one without opening it, for content such as an annotation
	 * that joins it later
	 *
	 * @param string $type A standard structure type
	 *
	 * @return StructureElement|null Null inside an artifact
	 */
	public function addLeaf($type)
	{
		if ($this->isInArtifact()) {
			return null;
		}
		$this->open($type);
		$elem = $this->getCurrent();
		$this->close();

		return $elem;
	}

	/**
	 * Close the innermost open element, never the Document, and nothing inside an artifact
	 */
	public function close()
	{
		if (count($this->stack) <= 1) {
			return;
		}
		if ($this->isInArtifact()) {
			return;
		}
		array_pop($this->stack);
	}

	/**
	 * Close the innermost open element if it is a THead, TBody or TFoot.
	 *
	 * A row group can still be open when its table or the next group begins: its end tag is
	 * optional in HTML, and the TBody that Tr opens for rows written straight under <table> has none.
	 */
	public function closeRowGroup()
	{
		if (count($this->stack) <= 1 || $this->isInArtifact()) {
			return;
		}
		$type = end($this->stack)->getType();
		if ($type === 'THead' || $type === 'TBody' || $type === 'TFoot') {
			array_pop($this->stack);
		}
	}

	/**
	 * Reopen an element already in the tree, so what is drawn later, such as the contents of a table
	 * cell, opens its elements inside it. Close it again with close().
	 *
	 * @param StructureElement $elem
	 */
	public function pushExisting(StructureElement $elem)
	{
		if ($this->isInArtifact()) {
			return;
		}
		$this->stack[] = $elem;
	}

	/**
	 * Close the innermost open element and take it out of the tree, as Th does to the TD that Td
	 * opened for it. Left in the tree, the TD would count as an extra column.
	 */
	public function discardTop()
	{
		if (count($this->stack) <= 1) {
			return;
		}
		if ($this->isInArtifact()) {
			return;
		}
		$top = end($this->stack);
		$parent = $top->getParent();
		array_pop($this->stack);
		if ($parent !== null) {
			$parent->popLastChild();
		}
	}

	/**
	 * Hand out the marked content ID of content belonging to the innermost open element
	 *
	 * @param int $structParentsIndex The /StructParents of the page or form XObject drawn on
	 *
	 * @return int The MCID, or -1 inside an artifact, which MarkedContentHelper::begin() marks as /Artifact
	 *
	 * @throws \Mpdf\Exception\InvalidArgumentException For a /StructParents that has not been handed out
	 */
	public function addContent($structParentsIndex)
	{
		return $this->addContentForElement($this->getCurrent(), $structParentsIndex);
	}

	/**
	 * Hand out the marked content ID of content belonging to $elem, for content such as a table
	 * cell's that is drawn after its element has closed
	 *
	 * @param StructureElement $elem
	 * @param int              $structParentsIndex The /StructParents of the page or form XObject drawn on
	 *
	 * @return int The MCID, or -1 inside an artifact
	 */
	public function addContentForElement(StructureElement $elem, $structParentsIndex)
	{
		if (!is_int($structParentsIndex) || $structParentsIndex < 0) {
			throw new \Mpdf\Exception\InvalidArgumentException(
				'structParentsIndex must be a non-negative integer'
			);
		}
		// A key that has not been handed out belongs to no page, and would leave its ParentTree entry unreachable
		if ($this->uaState !== null) {
			$ceiling = $this->uaState->peekStructParents();
			if ($structParentsIndex >= $ceiling) {
				throw new \Mpdf\Exception\InvalidArgumentException(
					'structParentsIndex ' . $structParentsIndex
					. ' is outside the allocated range [0, ' . $ceiling . ')'
				);
			}
		}
		if ($this->isInArtifact()) {
			return -1;
		}
		$mcid = $this->nextMcidForPage($structParentsIndex);
		$elem->addMcid($structParentsIndex, $mcid);
		$this->parentTree[$structParentsIndex][$mcid] = $elem;
		return $mcid;
	}

	/**
	 * @return int The -1 that MarkedContentHelper::begin() marks as /Artifact
	 */
	public function addArtifact()
	{
		return -1;
	}

	/**
	 * Start drawing content as an artifact, until the matching closeArtifact(). Artifacts nest.
	 */
	public function openArtifact()
	{
		$this->artifactDepth++;
	}

	/**
	 * Close one artifact; a close with none open is ignored rather than thrown mid-render
	 */
	public function closeArtifact()
	{
		if ($this->artifactDepth > 0) {
			$this->artifactDepth--;
		}
	}

	/**
	 * Map a custom role to a standard type in the RoleMap. A role keeps the first type it is mapped
	 * to, as a RoleMap can give it only one.
	 *
	 * @param string $role
	 * @param string $standardType
	 */
	public function addRoleMapping($role, $standardType)
	{
		if (!isset($this->roleMappings[$role])) {
			$this->roleMappings[$role] = $standardType;
		}
	}

	/**
	 * Hand out the /StructParent of an annotation owned by $elem
	 *
	 * @param StructureElement $elem
	 *
	 * @return int
	 */
	public function nextAnnotStructParent(StructureElement $elem)
	{
		// Drawn from the counter pages use, as both are keys in the one ParentTree
		$idx = $this->uaState->nextStructParents();
		$this->annotParentTree[$idx] = $elem;
		return $idx;
	}

	/**
	 * Hand out an annotation's /StructParent before its element exists, as a form field's is written
	 * ahead of its Form element; registerAnnotStructParent() names the owner later
	 *
	 * @return int
	 */
	public function reserveAnnotStructParent()
	{
		return $this->uaState->nextStructParents();
	}

	/**
	 * @param int              $idx  A /StructParent from reserveAnnotStructParent()
	 * @param StructureElement $elem The element owning the annotation
	 */
	public function registerAnnotStructParent($idx, StructureElement $elem)
	{
		$this->annotParentTree[$idx] = $elem;
	}

	/**
	 * Take out the Links left with no content and no annotation, such as <a href="x"></a> or a link
	 * around a decorative image. A Link must hold one or the other, and one with neither has
	 * nothing to click.
	 *
	 * Run once the annotations are written, so their references are in place, and before the tree is.
	 */
	public function pruneEmptyLinks()
	{
		$this->pruneEmptyLinksRecursive($this->root);
	}

	/**
	 * Prune the children of $elem after their own descendants, so a Link emptied below is caught
	 *
	 * @param StructureElement $elem
	 */
	private function pruneEmptyLinksRecursive(StructureElement $elem)
	{
		foreach ($elem->getChildren() as $child) {
			$this->pruneEmptyLinksRecursive($child);
		}
		$toRemove = [];
		foreach ($elem->getChildren() as $child) {
			if ($child->getType() === 'Link'
				&& count($child->getChildren()) === 0
				&& count($child->getMcids()) === 0
				&& count($child->getObjrefs()) === 0
			) {
				$toRemove[] = $child;
			}
		}
		foreach ($toRemove as $child) {
			$elem->removeChild($child);
		}
	}

	/**
	 * For a warning, where the first Link that pruneEmptyLinks() would take out pointed. A Link
	 * holding only its annotation is not empty.
	 *
	 * @return string|null The Link's '_href', '<unknown>' without one, or null when no Link is empty
	 */
	public function findFirstEmptyLinkHref()
	{
		return $this->findFirstEmptyLinkHrefRecursive($this->root);
	}

	/**
	 * @param StructureElement $elem
	 *
	 * @return string|null Where the first empty Link in $elem pointed, as findFirstEmptyLinkHref()
	 */
	private function findFirstEmptyLinkHrefRecursive(StructureElement $elem)
	{
		if ($elem->getType() === 'Link'
			&& count($elem->getChildren()) === 0
			&& count($elem->getMcids()) === 0
			&& count($elem->getObjrefs()) === 0
		) {
			$attrs = $elem->getAttributes();
			return isset($attrs['_href']) ? (string) $attrs['_href'] : '<unknown>';
		}
		foreach ($elem->getChildren() as $child) {
			$href = $this->findFirstEmptyLinkHrefRecursive($child);
			if ($href !== null) {
				return $href;
			}
		}
		return null;
	}

	/**
	 * Record the owner of marked content in an imported page, keeping the MCID the page's content
	 * stream already carries rather than handing out a new one
	 *
	 * @param int              $structParents The /StructParents of the form XObject the page became
	 * @param int              $mcid
	 * @param StructureElement $elem          The element copied from the imported document
	 */
	public function registerImportedMcr($structParents, $mcid, StructureElement $elem)
	{
		$this->parentTree[$structParents][$mcid] = $elem;
	}

	/**
	 * @param int $page A /StructParents key, not a page number
	 *
	 * @return int The next MCID of that key, counting from 0
	 */
	private function nextMcidForPage($page)
	{
		if (!isset($this->mcidByPage[$page])) {
			$this->mcidByPage[$page] = 0;
		}
		return $this->mcidByPage[$page]++;
	}

	/**
	 * Puts the top-level elements in the order of the pages they start on.
	 *
	 * A table of contents is written after the body and its pages moved into place, so its element
	 * would otherwise be read last. One with no content on a page keeps its place after the one before it.
	 *
	 * @param array $pageDim Mpdf::$pageDim, by page number, after the pages are moved
	 *
	 * @return void
	 */
	public function orderByPage(array $pageDim)
	{
		$pageOf = [];
		foreach ($pageDim as $page => $dim) {
			if (isset($dim['structParents'])) {
				$pageOf[$dim['structParents']] = $page;
			}
		}

		$order = [];
		$page = 0;
		foreach ($this->root->getChildren() as $i => $child) {
			$first = $this->firstPage($child, $pageOf);
			$page = $first === null ? $page : $first;
			$order[] = [$page, $i, $child];
		}

		// The index keeps the sort stable on PHP < 8
		usort($order, function ($a, $b) {
			return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
		});

		$this->root->reorderChildren(array_map(function ($entry) {
			return $entry[2];
		}, $order));
	}

	/**
	 * @param StructureElement $elem
	 * @param int[]            $pageOf Page number by /StructParents
	 *
	 * @return int|null The first page with content of the element or its descendants
	 */
	private function firstPage(StructureElement $elem, array $pageOf)
	{
		$first = null;
		foreach ($elem->getMcids() as $mcr) {
			if ($mcr['stm'] === 0 && isset($pageOf[$mcr['page']]) && ($first === null || $pageOf[$mcr['page']] < $first)) {
				$first = $pageOf[$mcr['page']];
			}
		}

		foreach ($elem->getChildren() as $child) {
			$page = $this->firstPage($child, $pageOf);
			if ($page !== null && ($first === null || $page < $first)) {
				$first = $page;
			}
		}

		return $first;
	}
}
