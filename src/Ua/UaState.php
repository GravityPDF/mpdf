<?php

namespace Mpdf\Ua;

/**
 * The PDF/UA state of a document and the collaborators that build its structure tree.
 *
 * ServiceFactory makes one and hands it to the tags and writers that need it. The PDFUA and
 * PDFUAauto switches stay on Mpdf, beside PDFA and PDFAauto.
 */
class UaState
{

	/**
	 * What PDFUAauto corrected instead of throwing
	 *
	 * @var string[]
	 */
	protected $warnings = [];

	/**
	 * The next /StructParents key. The keys of the parent tree run from 0 without gaps, and
	 * /ParentTreeNextKey is written from this.
	 *
	 * @var int
	 */
	protected $structParentsCounter = 0;

	/**
	 * @var int 0 until StructureWriter has written the tree, which ResourceWriter does before the catalog
	 */
	protected $structTreeRootObjNum = 0;

	/**
	 * One frame per open <dl>, true while a DT or DD has opened the LI that HTML leaves out.
	 * A frame each, and not a single flag, so a <dt> in a nested list closes its own LI and not
	 * the outer one.
	 *
	 * @var bool[]
	 */
	protected $implicitLIStack = [];

	/**
	 * @var int The level of the last heading put in the tree, 0 before the first
	 */
	protected $lastHeadingLevel = 0;

	/** @var MarkedContentHelper */
	protected $markedContentHelper;

	/** @var StructureTree */
	protected $structureTree;

	/** @var StructureWriter */
	protected $structureWriter;

	/** @var AriaIdResolver */
	protected $ariaIdResolver;

	/** @var LigatureActualTextWriter */
	protected $ligatureActualTextWriter;

	/** @var Import\FpdiStructMerger */
	protected $fpdiStructMerger;

	/** @var InlineStructStack */
	protected $inlineStructStack;

	/** @var AnchorState */
	protected $anchorState;

	/** @var ImageMap\ImageMapRegistry */
	protected $imageMapRegistry;

	/**
	 * @param StructureTree             $structureTree
	 * @param MarkedContentHelper       $markedContentHelper
	 * @param StructureWriter           $structureWriter
	 * @param AriaIdResolver            $ariaIdResolver
	 * @param LigatureActualTextWriter  $ligatureActualTextWriter
	 * @param Import\FpdiStructMerger   $fpdiStructMerger
	 * @param InlineStructStack         $inlineStructStack
	 * @param AnchorState               $anchorState
	 * @param ImageMap\ImageMapRegistry $imageMapRegistry
	 */
	public function __construct(
		StructureTree $structureTree,
		MarkedContentHelper $markedContentHelper,
		StructureWriter $structureWriter,
		AriaIdResolver $ariaIdResolver,
		LigatureActualTextWriter $ligatureActualTextWriter,
		Import\FpdiStructMerger $fpdiStructMerger,
		InlineStructStack $inlineStructStack,
		AnchorState $anchorState,
		ImageMap\ImageMapRegistry $imageMapRegistry
	) {
		$this->structureTree            = $structureTree;
		$this->markedContentHelper      = $markedContentHelper;
		$this->structureWriter          = $structureWriter;
		$this->ariaIdResolver           = $ariaIdResolver;
		$this->ligatureActualTextWriter = $ligatureActualTextWriter;
		$this->fpdiStructMerger         = $fpdiStructMerger;
		$this->inlineStructStack        = $inlineStructStack;
		$this->anchorState              = $anchorState;
		$this->imageMapRegistry         = $imageMapRegistry;
	}

	/**
	 * @return string[] What PDFUAauto corrected instead of throwing
	 */
	public function getWarnings()
	{
		return $this->warnings;
	}

	/**
	 * @return int The next /StructParents key to be handed out
	 */
	public function getStructParentsCounter()
	{
		return $this->structParentsCounter;
	}

	/**
	 * @return int 0 until StructureWriter has written the tree
	 */
	public function getStructTreeRootObjNum()
	{
		return $this->structTreeRootObjNum;
	}

	/**
	 * @return bool Whether the innermost <dl> has an LI of its own open; false outside a <dl>
	 */
	public function isOpenedImplicitLI()
	{
		return !empty($this->implicitLIStack) && end($this->implicitLIStack);
	}

	/**
	 * @return void
	 */
	public function pushImplicitLIFrame()
	{
		$this->implicitLIStack[] = false;
	}

	/**
	 * @return void
	 */
	public function popImplicitLIFrame()
	{
		array_pop($this->implicitLIStack);
	}

	/** @return MarkedContentHelper */
	public function getMarkedContentHelper()
	{
		return $this->markedContentHelper;
	}

	/** @return StructureTree */
	public function getStructureTree()
	{
		return $this->structureTree;
	}

	/** @return StructureWriter */
	public function getStructureWriter()
	{
		return $this->structureWriter;
	}

	/** @return AriaIdResolver */
	public function getAriaIdResolver()
	{
		return $this->ariaIdResolver;
	}

	/** @return LigatureActualTextWriter */
	public function getLigatureActualTextWriter()
	{
		return $this->ligatureActualTextWriter;
	}

	/** @return Import\FpdiStructMerger */
	public function getFpdiStructMerger()
	{
		return $this->fpdiStructMerger;
	}

	/** @return InlineStructStack */
	public function getInlineStructStack()
	{
		return $this->inlineStructStack;
	}

	/** @return AnchorState */
	public function getAnchorState()
	{
		return $this->anchorState;
	}

	/** @return ImageMap\ImageMapRegistry */
	public function getImageMapRegistry()
	{
		return $this->imageMapRegistry;
	}

	/**
	 * @param int $n
	 *
	 * @return void
	 */
	public function setStructTreeRootObjNum($n)
	{
		$this->structTreeRootObjNum = (int) $n;
	}

	/**
	 * Does nothing outside a <dl>
	 *
	 * @param bool $v Whether the innermost <dl> has an LI of its own open
	 *
	 * @return void
	 */
	public function setOpenedImplicitLI($v)
	{
		if (empty($this->implicitLIStack)) {
			return;
		}
		$this->implicitLIStack[count($this->implicitLIStack) - 1] = (bool) $v;
	}

	/**
	 * @return int The level of the last heading put in the tree, 0 before the first
	 */
	public function getLastHeadingLevel()
	{
		return $this->lastHeadingLevel;
	}

	/**
	 * @param int $level The level the heading was given, after any correction
	 *
	 * @return void
	 */
	public function setLastHeadingLevel($level)
	{
		$this->lastHeadingLevel = (int) $level;
	}

	/**
	 * Record what PDFUAauto corrected; without PDFUAauto the caller throws instead
	 *
	 * @param string $msg
	 *
	 * @return void
	 */
	public function addWarning($msg)
	{
		$this->warnings[] = (string) $msg;
	}

	/**
	 * Every /StructParents key comes from here, so the keys have no gaps and /ParentTreeNextKey is right
	 *
	 * @return int
	 */
	public function nextStructParents()
	{
		return $this->structParentsCounter++;
	}

	/**
	 * @return int The key nextStructParents() will hand out next
	 */
	public function peekStructParents()
	{
		return $this->structParentsCounter;
	}

	/**
	 * The state of this object and of every PDF/UA object reachable from it: the collaborators, the
	 * structure tree and each of its elements.
	 *
	 * A look-ahead that is unwound (page-break-inside: avoid, the table of contents) opens elements
	 * and numbers marked content as it goes. Each object is put back in place rather than replaced,
	 * as the blocks and links Mpdf restores hold the elements themselves.
	 *
	 * @return array<int, array{0: object, 1: array<string, mixed>}>
	 */
	public function getStateSnapshot()
	{
		$snapshot = [];
		$seen = [];
		$this->collectState($this, $snapshot, $seen);

		return $snapshot;
	}

	/**
	 * @param array<int, array{0: object, 1: array<string, mixed>}> $snapshot
	 *
	 * @return void
	 */
	public function restoreStateSnapshot(array $snapshot)
	{
		foreach ($snapshot as $entry) {
			$accessors = self::stateAccessors(get_class($entry[0]));
			$accessors[1]($entry[0], $entry[1]);
		}
	}

	/**
	 * @param mixed               $value
	 * @param array               $snapshot
	 * @param array<string, bool> $seen     By spl_object_hash()
	 *
	 * @return void
	 */
	private function collectState($value, array &$snapshot, array &$seen)
	{
		if (is_array($value)) {
			foreach ($value as $item) {
				$this->collectState($item, $snapshot, $seen);
			}

			return;
		}

		if (!is_object($value) || isset($seen[spl_object_hash($value)]) || strpos(get_class($value), __NAMESPACE__ . '\\') !== 0) {
			return;
		}

		$seen[spl_object_hash($value)] = true;
		$accessors = self::stateAccessors(get_class($value));
		$vars = $accessors[0]($value);
		$snapshot[] = [$value, $vars];

		foreach ($vars as $var) {
			$this->collectState($var, $snapshot, $seen);
		}
	}

	/**
	 * @param string $class
	 *
	 * @return \Closure[] What reads the properties of an object of the class, and what writes them back
	 */
	private static function stateAccessors($class)
	{
		static $accessors = [];

		if (!isset($accessors[$class])) {
			$accessors[$class] = [
				\Closure::bind(static function ($object) {
					return get_object_vars($object);
				}, null, $class),
				\Closure::bind(static function ($object, array $vars) {
					foreach ($vars as $key => $value) {
						$object->{$key} = $value;
					}
				}, null, $class),
			];
		}

		return $accessors[$class];
	}
}
