<?php

namespace Mpdf;

/**
 * Keeps the tree of elements in the HTML WriteHTML() reads, so that each CSS rule can be applied to the elements its
 * selector matches, as in a browser. Selectors that name a parent, an ancestor or an earlier sibling, such as
 * `ul > li`, `.note b`, `h2 + p` and `li:nth-child(2)`, need it to be matched. mPDF's block stack cannot answer
 * them: it holds only blocks and table parts, and nothing of an element's siblings.
 *
 * Each open element is held with its position among its siblings and a record of the siblings closed before it. The
 * stack follows the tags as they are read, not the blocks mPDF lays out, so it is the same when a forced page break
 * splits an element or a page-break-inside: avoid block is laid out twice. WriteHTML() drives it through these
 * methods.
 *
 * It also holds the element whose CSS is being merged, which the selector matcher reads through
 * getStyledElementPath().
 *
 * @internal
 */
trait TracksOpenElements
{

	/** @var array[]|null The open elements as they stood inside the positioned block being written, for its content */
	private $fixedPosBlockElements;

	/**
	 * @var array[] The elements open in the HTML being written, outermost first, kept apart from the blocks in $blk.
	 * See {@see getOpenElements()}
	 */
	private $openElements;

	/**
	 * @var array|null The element whose CSS is being merged: the open elements it sits in (path), its tag and
	 * attributes (attr), and whether it opens a level of the legacy descendant rules (level), or, for an element that
	 * is already open, the open elements down to it and a null tag. Null while no element of the document is being
	 * styled. WriteHTML() sets it while a start tag's handler runs, reopenBlock() while a block is opened again, and
	 * WriteFixedPosHTML() while a positioned block's own CSS is merged. See getStyledElementPath()
	 */
	private $styledElement;

	/**
	 * @var array[] For each start tag that ends an open element with no end tag of its own: the tags it ends the nearest
	 * of, and the tags of the elements the search for one stops at
	 */
	private static $impliedEndTags = [
		'LI' => [['LI'], ['UL', 'OL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'DT' => [['DT', 'DD'], ['DL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'DD' => [['DT', 'DD'], ['DL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'OPTION' => [['OPTION'], ['SELECT']],
		'TD' => [['TD', 'TH'], ['TR', 'TABLE']],
		'TH' => [['TD', 'TH'], ['TR', 'TABLE']],
		'TR' => [['TR'], ['THEAD', 'TBODY', 'TFOOT', 'TABLE']],
		'THEAD' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
		'TBODY' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
		'TFOOT' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
	];

	/** @var array<string, true> The tags mPDF wraps substituted characters in, which are not the document's elements */
	private static $substitutionTags = ['TTA' => true, 'TTS' => true, 'TTZ' => true];

	/**
	 * @var array<string, true> The elements that have no content and no end tag: HTML's void elements, and mPDF's own
	 * tags that are written without content. They count among their siblings but are never open, whether or not the
	 * start tag ends with a slash
	 */
	private static $voidTags = [
		'AREA' => true, 'BASE' => true, 'BR' => true, 'COL' => true, 'EMBED' => true, 'HR' => true, 'IMG' => true,
		'INPUT' => true, 'LINK' => true, 'META' => true, 'PARAM' => true, 'SOURCE' => true, 'TRACK' => true, 'WBR' => true,
		'ANNOTATION' => true, 'BARCODE' => true, 'BOOKMARK' => true, 'COLUMN_BREAK' => true, 'COLUMNBREAK' => true,
		'COLUMNS' => true, 'DOTTAB' => true, 'FORMFEED' => true, 'INDEXENTRY' => true, 'INDEXINSERT' => true,
		'NEWCOLUMN' => true, 'NEWPAGE' => true, 'PAGE_BREAK' => true, 'PAGEBREAK' => true, 'PAGEFOOTER' => true,
		'PAGEHEADER' => true, 'SETHTMLPAGEFOOTER' => true, 'SETHTMLPAGEHEADER' => true, 'SETPAGEFOOTER' => true,
		'SETPAGEHEADER' => true, 'TEXTCIRCLE' => true, 'TOC' => true, 'TOCPAGEBREAK' => true, 'WATERMARKIMAGE' => true,
		'WATERMARKTEXT' => true,
	];

	/**
	 * The elements open in the HTML being written, outermost first. They follow the tags as they are read, whatever
	 * blocks are closed and opened again to lay them out, as around a forced page break, and whatever is laid out
	 * twice, as a page-break-inside: avoid block that ran onto another page is.
	 *
	 * The first frame stands for the document, or for the header, footer or positioned block being written, and has an
	 * empty tag. Each frame holds:
	 * - tag, id and classes: uppercased, as the tokenizer leaves them
	 * - lang: its lang attribute, or the one it inherits from an open ancestor
	 * - attr: its attributes, as the tag handlers are given them
	 * - nthChild and nthOfType: its position among its element siblings, and among those of its own tag, from 1
	 * - children: a record of each child closed so far, oldest first, which for the child open inside it are the
	 *   siblings before that child. A record holds a child's tag, id, classes, attr and nthOfType; its position is its
	 *   place in the list
	 * - childTypes: how many children so far have each tag
	 * - computed: its computed values, not filled in yet
	 * - level: whether it opened a level of the descendant rules the legacy parser stores, as a block, and a table and
	 *   its parts do. The document does too
	 *
	 * @return array[]
	 */
	public function getOpenElements()
	{
		return $this->openElements;
	}

	/**
	 * The element whose CSS is being merged, with the open elements around it, for the selectors that look at its
	 * parent, ancestors and earlier siblings. CssMerger asks for it when a compiled rule is filed under the element's
	 * tag, id or class. An element whose start tag is being read is not on the stack yet, so its frame is made here,
	 * counted after the siblings closed before it, and a row written straight into a table is put under the tbody
	 * the stack will give it
	 *
	 * @return array[]|null The open elements from the document down to it, in the shape getOpenElements() gives,
	 *                      with the element's own frame last. Null when no element of the document is being styled
	 */
	public function getStyledElementPath()
	{
		if ($this->styledElement === null) {
			return null;
		}

		$path = $this->styledElement['path'];
		$tag = $this->styledElement['tag'];
		if ($tag === null) {
			return $path;
		}

		$parent = count($path) - 1;
		if (self::impliesTbody($tag, $path[$parent])) {
			$path[] = $this->newChildFrame($path[$parent], 'TBODY', []);
			$parent++;
		}
		$path[] = $this->newChildFrame($path[$parent], $tag, $this->styledElement['attr']);

		return $path;
	}

	/**
	 * Notes that the element whose CSS is being merged opens a level of the descendant rules the legacy parser
	 * stores, as a block, a table and a table part do. CssMerger calls it when it lifts those rules at the element.
	 * WriteHTML() then marks the element's frame, and the matcher leaves the rules that go through it to the legacy
	 * engine, so none is applied twice
	 */
	public function markStyledElementAsLevel()
	{
		if ($this->styledElement !== null) {
			$this->styledElement['level'] = true;
		}
	}

	/**
	 * Makes the stack a document starts from: a single frame standing for the document, with nothing written into it
	 * yet. The constructor starts from one, and so does a WriteHTML() call that starts a new document, such as the one
	 * InsertIndex() makes for the index, and a header or footer written apart from the flow
	 *
	 * @return array[] A stack holding only the frame for a document with nothing written into it yet
	 */
	private function newOpenElementStack()
	{
		$document = $this->newElementFrame('', [], '', 1, 1);
		$document['level'] = true;

		return [$document];
	}

	/**
	 * Makes the frame that holds one element on the stack, before any of its children are read
	 *
	 * @param string $tag Uppercased, or '' for the document
	 * @param string[] $attr As the tag handlers are given them
	 * @param string $lang Its own lang attribute, or the one it inherits
	 * @param int $nthChild Its position among its element siblings, from 1
	 * @param int $nthOfType Its position among the siblings with its tag, from 1
	 *
	 * @return array A frame for an element with no children yet, as getOpenElements() describes
	 */
	private function newElementFrame($tag, array $attr, $lang, $nthChild, $nthOfType)
	{
		return [
			'tag' => $tag,
			'id' => isset($attr['ID']) ? $attr['ID'] : '',
			'classes' => isset($attr['CLASS']) ? preg_split('/\s+/', $attr['CLASS'], -1, PREG_SPLIT_NO_EMPTY) : [],
			'lang' => $lang,
			'attr' => $attr,
			'nthChild' => $nthChild,
			'nthOfType' => $nthOfType,
			'children' => [],
			'childTypes' => [],
			'computed' => null,
			'level' => false,
		];
	}

	/**
	 * Takes in an element whose start tag has been read: onto the stack, or, for a void element or one closed by its
	 * own start tag, straight into its parent's record of closed children. A void element counts among its siblings
	 * but is never open, even when a slash in an attribute value, as in `<img src="images/a.png">`, keeps AdjustHTML()
	 * from closing its start tag. WriteHTML() calls it for each start tag, once the tag's handler has run
	 *
	 * @param string $tag
	 * @param string[] $attr
	 * @param bool $selfClosing Whether the start tag ends with a slash
	 * @param bool $level Whether the legacy engine lifted descendant rules at it, as it does at a block, a table and a
	 *                    table part. The matcher leaves the rules that go through such an element to the legacy engine
	 */
	private function startElement($tag, array $attr, $selfClosing, $level)
	{
		if ($tag === '' || isset(self::$substitutionTags[$tag])) {
			return;
		}

		$parent = count($this->openElements) - 1;
		if (self::impliesTbody($tag, $this->openElements[$parent])) {
			$this->startElement('TBODY', [], false, false);
			$parent++;
		}

		$frame = $this->newChildFrame($this->openElements[$parent], $tag, $attr);
		$frame['level'] = $level;

		if ($selfClosing || isset(self::$voidTags[$tag])) {
			$this->recordClosedElement($frame);
		} else {
			$this->openElements[] = $frame;
		}
	}

	/**
	 * Whether a start tag opens its element inside a tbody that the HTML leaves out. A row written straight into a
	 * table does, as in a browser's tree, so `table > tbody > tr` matches it
	 *
	 * @param string $tag
	 * @param array $parent The frame of the element it is opened in
	 *
	 * @return bool
	 */
	private static function impliesTbody($tag, array $parent)
	{
		return $tag === 'TR' && $parent['tag'] === 'TABLE';
	}

	/**
	 * Makes the frame for an element opened in $parent after the children $parent has so far. Its nthChild and
	 * nthOfType are counted from those children, and it inherits $parent's lang when it has no lang of its own
	 *
	 * @param array $parent The frame of the element it is opened in
	 * @param string $tag
	 * @param string[] $attr
	 *
	 * @return array A frame for an element opened after the children its parent has so far
	 */
	private function newChildFrame(array $parent, $tag, array $attr)
	{
		// Marks a page-break-inside: avoid block laid out a second time. unset() would copy the array even without it
		if (isset($attr['PAGEBREAKAVOIDCHECKED'])) {
			unset($attr['PAGEBREAKAVOIDCHECKED']);
		}

		return $this->newElementFrame(
			$tag,
			$attr,
			isset($attr['LANG']) ? $attr['LANG'] : $parent['lang'],
			count($parent['children']) + 1,
			(isset($parent['childTypes'][$tag]) ? $parent['childTypes'][$tag] : 0) + 1
		);
	}

	/**
	 * Whether a start tag is for an element of the document, which selectors match and count among its siblings.
	 * The tags mPDF wraps substituted characters in, and the spans it wraps a run of another script in for
	 * autoScriptToLang, are not. WriteHTML() only tells the merger which element it is styling for such a tag
	 *
	 * @param string $tag
	 * @param string[] $attr
	 *
	 * @return bool
	 */
	private function isDocumentElement($tag, array $attr)
	{
		return $tag !== '' && !isset(self::$substitutionTags[$tag]) && !isset($attr[self::SCRIPT_RUN_ATTRIBUTE]);
	}

	/**
	 * Finds each open block's element on the stack. The blocks are open elements too, in the same order, with the
	 * inline elements and table parts they sit in between them. _postForcedPagebreak() calls it before it opens again
	 * the blocks a forced page break closed, so that each is styled as its own element
	 *
	 * @param array[] $blocks The open blocks, from level 1
	 * @param int $count How many there are
	 *
	 * @return array<int, int|null> For each level, the depth of its element's frame, or null if it has none
	 */
	private function openBlockDepths(array $blocks, $count)
	{
		$depths = [];
		$depth = 1;
		$frames = count($this->openElements);
		for ($b = 1; $b <= $count; $b++) {
			while ($depth < $frames && $this->openElements[$depth]['tag'] !== $blocks[$b]['tag']) {
				$depth++;
			}
			$depths[$b] = $depth < $frames ? $depth : null;
			$depth++;
		}

		return $depths;
	}

	/**
	 * Opens again, through Tag::OpenTag(), a block a forced page break closed, with the selectors matched against the
	 * block's own element. _postForcedPagebreak() calls it for each block it opens again. The element being styled
	 * before is put back after, as the page break may come from the start tag of an element still to be styled
	 *
	 * @param array $block The block as it was before the page break
	 * @param int|null $depth Where its element is on the stack of open elements, or null if it has none
	 */
	private function reopenBlock(array $block, $depth)
	{
		$arr = [];
		$i = 0;
		$outerElement = $this->styledElement;
		$this->styledElement = $depth === null ? null : ['path' => array_slice($this->openElements, 0, $depth + 1), 'tag' => null, 'attr' => [], 'level' => false];
		$this->tag->OpenTag($block['tag'], $block['attr'], $arr, $i);
		$this->styledElement = $outerElement;
	}

	/**
	 * Closes the elements at the top of the stack until $depth frames are left. Each element it closes is recorded
	 * among its parent's children. WriteHTML() calls it when it closes the document, for what is still open
	 *
	 * @param int $depth
	 */
	private function closeElementsDownTo($depth)
	{
		while (count($this->openElements) > $depth) {
			$this->recordClosedElement(array_pop($this->openElements));
		}
	}

	/**
	 * Records a closed element among its parent's children, which is what sibling selectors and the nth counts of
	 * later siblings read. The parent is the frame now at the top of the stack. A span mPDF wraps a run of another
	 * script in is left out, as it is not the document's
	 *
	 * @param array $frame The closed element's frame
	 */
	private function recordClosedElement(array $frame)
	{
		if (isset($frame['attr'][self::SCRIPT_RUN_ATTRIBUTE])) {
			return;
		}

		$parent = count($this->openElements) - 1;
		$this->openElements[$parent]['children'][] = [
			'tag' => $frame['tag'],
			'id' => $frame['id'],
			'classes' => $frame['classes'],
			'attr' => $frame['attr'],
			'nthOfType' => $frame['nthOfType'],
		];
		$this->openElements[$parent]['childTypes'][$frame['tag']] = $frame['nthOfType'];
	}

	/**
	 * Closes the nearest open element with one of the tags given, and every element open inside it. An end tag and
	 * an implied end tag close elements through it
	 *
	 * @param string[] $tags
	 * @param string[] $boundaries The tags of the elements the search stops at
	 * @param int $floor How many frames at the foot of the stack are not for the HTML being written to close: the
	 *                   document's frame, or the frames a positioned block's content is written inside
	 */
	private function closeNearestElement(array $tags, array $boundaries, $floor)
	{
		for ($depth = count($this->openElements) - 1; $depth >= $floor; $depth--) {
			$tag = $this->openElements[$depth]['tag'];
			if (in_array($tag, $tags, true)) {
				$this->closeElementsDownTo($depth);

				return;
			}
			if (in_array($tag, $boundaries, true)) {
				return;
			}
		}
	}

	/**
	 * Closes the elements an end tag ends: the nearest open element of its name, and every element open inside it.
	 * An end tag with no element of its name open is ignored, as a browser ignores it, and so is one written inside a
	 * table cell or caption for an element outside it. An end tag for a void element or for a tag mPDF wraps
	 * substituted characters in closes nothing. WriteHTML() calls it for each end tag, before CloseTag()
	 *
	 * @param string $tag The end tag's name, uppercased
	 * @param int $floor How many frames at the foot of the stack are not for the HTML being written to close: the
	 *                   document's frame, or the frames a positioned block's content is written inside
	 */
	private function closeElementsEndedBy($tag, $floor)
	{
		if (isset(self::$voidTags[$tag]) || isset(self::$substitutionTags[$tag])) {
			return;
		}

		$top = count($this->openElements) - 1;
		if ($top >= $floor && $this->openElements[$top]['tag'] === $tag) {
			$this->closeElementsDownTo($top);

			return;
		}

		if ($tag === 'TABLE') {
			$boundaries = [];
		} elseif (in_array($tag, ['CAPTION', 'TD', 'TH', 'TR', 'THEAD', 'TBODY', 'TFOOT'], true)) {
			$boundaries = ['TABLE'];
		} else {
			$boundaries = ['CAPTION', 'TD', 'TH', 'TABLE'];
		}

		$this->closeNearestElement([$tag], $boundaries, $floor);
	}

	/**
	 * Closes the elements whose end tag HTML lets be left out before this start tag, as Tag::OpenTag() does for the
	 * blocks it lays out. WriteHTML() calls it for each start tag, before handling it
	 *
	 * @param string $tag The start tag's name, uppercased
	 * @param int $floor How many frames at the foot of the stack are not for the HTML being written to close: the
	 *                   document's frame, or the frames a positioned block's content is written inside
	 */
	private function closeElementsImpliedBy($tag, $floor)
	{
		if (!$this->allow_html_optional_endtags) {
			return;
		}

		if (isset(self::$impliedEndTags[$tag])) {
			$this->closeNearestElement(self::$impliedEndTags[$tag][0], self::$impliedEndTags[$tag][1], $floor);
		}

		if (Tag::closesParagraph($tag)) {
			$this->closeNearestElement(['P'], ['CAPTION', 'TD', 'TH', 'TABLE'], $floor);
		}
	}
}
