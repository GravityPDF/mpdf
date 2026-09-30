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

	/** @var string The language the <html> or <body> tag gives the document, which the document's frame carries */
	private $documentLang = '';

	/** @var array[]|null The open elements as they stood inside the positioned block being written, for its content */
	private $fixedPosBlockElements;

	/**
	 * @var array[] What readAhead() found each element to hold, by the key of its frame: its childTotal,
	 * childTypeTotals and empty, as getOpenElements() describes them. A frame takes them from here when it is made
	 */
	private $lookAhead = [];

	/** @var bool Whether readAhead() is walking the tokens, so that each element it closes is recorded in $lookAhead */
	private $readingAhead = false;

	/**
	 * @var array[] The elements open in the HTML being written, outermost first, kept apart from the blocks in $blk.
	 * See {@see getOpenElements()}
	 */
	private $openElements;

	/**
	 * @var array|null The element whose CSS is being merged: the open elements it sits in (path) and its tag and
	 * attributes (attr), or, for an element that is already open, the open elements down to it and a null tag. Null
	 * while no element of the document is being styled. WriteHTML() sets it while a start tag's handler runs, reopenBlock() while a block is opened again, and
	 * WriteFixedPosHTML() while a positioned block's own CSS is merged. See getStyledElementPath()
	 */
	private $styledElement;

	/**
	 * @var array[] For each start tag that ends an open element with no end tag of its own, when
	 * allow_html_optional_endtags is on: the tags it ends the nearest of, and the tags of the elements the search for
	 * one stops at
	 */
	private static $impliedEndTags = [
		'LI' => [['LI'], ['UL', 'OL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'DT' => [['DT', 'DD'], ['DL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'DD' => [['DT', 'DD'], ['DL', 'CAPTION', 'TD', 'TH', 'TABLE']],
		'OPTION' => [['OPTION'], ['SELECT']],
	];

	/**
	 * @var array[] As $impliedEndTags, for the start tags that end an open cell, row or row group whatever
	 * allow_html_optional_endtags says: mPDF lays each cell, row and row group out after the one before it, never
	 * inside it
	 */
	private static $tableImpliedEndTags = [
		'TD' => [['TD', 'TH'], ['TR', 'TABLE']],
		'TH' => [['TD', 'TH'], ['TR', 'TABLE']],
		'TR' => [['TR'], ['THEAD', 'TBODY', 'TFOOT', 'TABLE']],
		'THEAD' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
		'TBODY' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
		'TFOOT' => [['THEAD', 'TBODY', 'TFOOT'], ['TABLE']],
	];

	/**
	 * @var array[] The elements a browser keeps in each part of a table, by tag. Any other element written straight
	 * into one, such as mPDF's <bookmark> or <tocentry> between two cells, is moved out in front of the table, so it
	 * is not counted among the part's children. It is still opened inside the part, as mPDF reads it there
	 */
	private static $tableChildren = [
		'TABLE' => ['CAPTION' => true, 'THEAD' => true, 'TBODY' => true, 'TFOOT' => true],
		'THEAD' => ['TR' => true],
		'TBODY' => ['TR' => true],
		'TFOOT' => ['TR' => true],
		'TR' => ['TD' => true, 'TH' => true],
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
	 *   siblings before that child. A record holds a child's tag, id, classes, attr, nthOfType and empty; its position
	 *   is its place in the list
	 * - childTypes: how many children so far have each tag
	 * - key: where it is in the document, as the nthChild of it and of each of its ancestors. An element not counted
	 *   among its parent's children, such as a span around a run of another script, shares it with the next one, and
	 *   nothing is read ahead of it
	 * - childTotal and childTypeTotals: how many children it has in all, and how many of each tag, as readAhead()
	 *   found. Null when that is not known, as for an element still open at the end of a WriteHTML() call that
	 *   leaves it open, and in the legacy CSS mode, which reads nothing ahead
	 * - empty: whether it has no children and no text, white space included. Null when that is not known
	 * - computed: its computed values, not filled in yet
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

		if ($this->styledElement['tag'] === null) {
			return $this->styledElement['path'];
		}

		return $this->withChildFrame($this->styledElement['path'], $this->styledElement['tag'], $this->styledElement['attr']);
	}

	/**
	 * The position of the element whose CSS is being merged among its element siblings: the nthChild of the last frame
	 * getStyledElementPath() gives, found without making that path. The legacy tr, td and th:nth-child rules read it
	 *
	 * @return int|null From 1. Null when no element of the document is being styled
	 */
	public function getStyledElementNthChild()
	{
		if ($this->styledElement === null) {
			return null;
		}

		$path = $this->styledElement['path'];
		$parent = $path[count($path) - 1];
		if ($this->styledElement['tag'] === null) {
			return $parent['nthChild'];
		}

		return self::impliesTbody($this->styledElement['tag'], $parent) ? 1 : count($parent['children']) + 1;
	}

	/**
	 * The path an element would have if it were opened now, in the innermost open element, after the children that
	 * element has so far. Nothing is recorded, so CssMerger::previewBlockCss() can match the selectors against it
	 * without changing the stack
	 *
	 * @param string $tag Uppercased
	 * @param string[] $attr
	 *
	 * @return array[] The open elements from the document down to it, with the element's own frame last
	 */
	public function getOpenElementPathFor($tag, array $attr)
	{
		return $this->withChildFrame($this->openElements, $tag, $attr);
	}

	/**
	 * A path with a frame added for an element opened in its last element, after the children that element has so
	 * far, and put under the tbody the stack gives a row written straight into a table
	 *
	 * @param array[] $path Open elements, outermost first
	 * @param string $tag
	 * @param string[] $attr
	 *
	 * @return array[]
	 */
	private function withChildFrame(array $path, $tag, array $attr)
	{
		$parent = count($path) - 1;
		if (self::impliesTbody($tag, $path[$parent])) {
			$path[] = $this->newChildFrame($path[$parent], 'TBODY', []);
			$parent++;
		}
		$path[] = $this->newChildFrame($path[$parent], $tag, $attr);

		return $path;
	}

	/**
	 * Makes the stack a document starts from: a single frame standing for the document, with nothing written into it
	 * yet. The constructor starts from one, and so does a WriteHTML() call that starts a new document, such as the one
	 * InsertIndex() makes for the index, and a header or footer written apart from the flow. The frame carries the
	 * language of the <html> or <body> tag, so that :lang() matches an element that inherits it
	 *
	 * @return array[] A stack holding only the frame for a document with nothing written into it yet
	 */
	private function newOpenElementStack()
	{
		return [$this->newElementFrame('', [], $this->documentLang, 1, 1, '')];
	}

	/**
	 * Makes the frame that holds one element on the stack, before any of its children are read
	 *
	 * @param string $tag Uppercased, or '' for the document
	 * @param string[] $attr As the tag handlers are given them
	 * @param string $lang Its own lang attribute, or the one it inherits
	 * @param int $nthChild Its position among its element siblings, from 1
	 * @param int $nthOfType Its position among the siblings with its tag, from 1
	 * @param string $key Where it is in the document, as getOpenElements() describes
	 *
	 * @return array A frame for an element with no children yet, as getOpenElements() describes
	 */
	private function newElementFrame($tag, array $attr, $lang, $nthChild, $nthOfType, $key)
	{
		$frame = [
			'tag' => $tag,
			'id' => isset($attr['ID']) ? $attr['ID'] : '',
			'classes' => isset($attr['CLASS']) ? preg_split('/\s+/', $attr['CLASS'], -1, PREG_SPLIT_NO_EMPTY) : [],
			'lang' => $lang,
			'attr' => $attr,
			'nthChild' => $nthChild,
			'nthOfType' => $nthOfType,
			'children' => [],
			'childTypes' => [],
			'key' => $key,
			'childTotal' => null,
			'childTypeTotals' => null,
			'empty' => null,
			'computed' => null,
		];

		return isset($this->lookAhead[$key]) ? $this->lookAhead[$key] + $frame : $frame;
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
	 */
	private function startElement($tag, array $attr, $selfClosing)
	{
		if ($tag === '' || isset(self::$substitutionTags[$tag])) {
			return;
		}

		$parent = count($this->openElements) - 1;
		if (self::impliesTbody($tag, $this->openElements[$parent])) {
			$this->startElement('TBODY', [], false);
			$parent++;
		}

		$frame = $this->newChildFrame($this->openElements[$parent], $tag, $attr);

		if ($selfClosing || isset(self::$voidTags[$tag])) {
			$this->recordClosedElement($frame);
		} else {
			$this->openElements[] = $frame;
		}
	}

	/**
	 * Walks the tokens a WriteHTML() call is about to write, to find what each element holds: how many children it
	 * has, how many of each tag, and whether it is empty. :last-child, :only-child, :empty and the like need that when
	 * the element opens, before its end has been read. The walk goes through the methods WriteHTML() keeps the stack
	 * with, on a copy of it, so each element is counted as the stack counts it; no tree is built. What it finds is kept
	 * in $lookAhead for the frames made while the tokens are written, and given to the frames already open.
	 *
	 * An element the call leaves open is not known in full: its totals stay null, and so does whether it is empty,
	 * unless it holds something already
	 *
	 * @param string[] $tokens Text and tags in turn, as WriteHTML() splits the HTML: each tag without its brackets
	 * @param int $floor How many frames at the foot of the stack the HTML cannot close, as WriteHTML() counts them
	 * @param bool $standIn Whether the first start tag stands in for the positioned block whose content is written,
	 *                      whose frame is open already
	 * @param bool $close Whether the call closes what is still open at its end
	 */
	private function readAhead(array $tokens, $floor, $standIn, $close)
	{
		$openElements = $this->openElements;
		$this->lookAhead = [];
		$this->readingAhead = true;
		// The frames below the floor of a positioned block's content are the flow's, and were read with it
		$from = $standIn ? $floor : 0;

		foreach ($tokens as $i => $token) {
			if ($i % 2 === 0) {
				// White space is text, as browsers have it. Comments are gone by now, though the CSS reader leaves a space
				// for each
				if ($token !== '') {
					$this->openElements[count($this->openElements) - 1]['empty'] = false;
				}
			} elseif (isset($token[0]) && $token[0] === '/') {
				$this->closeElementsEndedBy(trim(strtoupper(substr($token, 1))), $floor);
			} elseif (preg_match('/[a-zA-Z][\w:.\-]*/', $token, $name)) {
				$tag = strtoupper($name[0]);
				$this->closeElementsImpliedBy($tag, $floor);
				if ($standIn) {
					$standIn = false;
					continue;
				}

				$this->openElements[count($this->openElements) - 1]['empty'] = false;
				$attr = stripos($token, 'data-mpdf-script-run') !== false ? [self::SCRIPT_RUN_ATTRIBUTE => ''] : [];
				$this->startElement($tag, $attr, substr($token, -1) === '/');
			}
		}

		if ($close) {
			$this->closeElementsDownTo($floor);
		}

		for ($depth = $from; $depth < count($this->openElements); $depth++) {
			$frame = $this->openElements[$depth];
			if ($close) {
				// Only the document is left, and nothing more is written into it
				$this->lookAhead[$frame['key']] = self::heldBy($frame);
			} else {
				$this->lookAhead[$frame['key']] = [
					'childTotal' => null,
					'childTypeTotals' => null,
					'empty' => ($frame['empty'] === false || $frame['children']) ? false : null,
				];
			}
		}

		$this->readingAhead = false;
		$this->openElements = $openElements;

		foreach ($this->openElements as $depth => $frame) {
			if (isset($this->lookAhead[$frame['key']])) {
				$this->openElements[$depth] = $this->lookAhead[$frame['key']] + $frame;
			}
		}
	}

	/**
	 * What readAhead() records of an element whose end has been read
	 *
	 * @param array $frame Its frame, as readAhead() kept it: empty is false once text or a start tag is read in it
	 *
	 * @return array Its childTotal, childTypeTotals and empty, as getOpenElements() describes them
	 */
	private static function heldBy(array $frame)
	{
		return [
			'childTotal' => count($frame['children']),
			'childTypeTotals' => $frame['childTypes'],
			'empty' => $frame['empty'] !== false && !$frame['children'],
		];
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

		$nthChild = count($parent['children']) + 1;

		return $this->newElementFrame(
			$tag,
			$attr,
			isset($attr['LANG']) ? $attr['LANG'] : $parent['lang'],
			$nthChild,
			(isset($parent['childTypes'][$tag]) ? $parent['childTypes'][$tag] : 0) + 1,
			$parent['key'] . '.' . $nthChild
		);
	}

	/**
	 * Whether a start tag is for an element of the document, which selectors match and count among its siblings.
	 * The tags mPDF wraps substituted characters in, and the spans it wraps a run of another script in for
	 * autoScriptToLang or a run of a substitute font in, are not. WriteHTML() only tells the merger which element it
	 * is styling for such a tag
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
		$this->styledElement = $depth === null ? null : ['path' => array_slice($this->openElements, 0, $depth + 1), 'tag' => null, 'attr' => []];
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
	 * script or of a substitute font in is left out, as it is not the document's, and so is an element a browser moves
	 * out of the table part it is written in. While readAhead() walks the tokens, what an element that is recorded
	 * turned out to hold is kept in $lookAhead too
	 *
	 * @param array $frame The closed element's frame
	 */
	private function recordClosedElement(array $frame)
	{
		if (isset($frame['attr'][self::SCRIPT_RUN_ATTRIBUTE])) {
			return;
		}

		$parent = count($this->openElements) - 1;
		$parentTag = $this->openElements[$parent]['tag'];
		if (isset(self::$tableChildren[$parentTag]) && !isset(self::$tableChildren[$parentTag][$frame['tag']])) {
			return;
		}

		if ($this->readingAhead) {
			$this->lookAhead[$frame['key']] = self::heldBy($frame);
		}

		$this->openElements[$parent]['children'][] = [
			'tag' => $frame['tag'],
			'id' => $frame['id'],
			'classes' => $frame['classes'],
			'attr' => $frame['attr'],
			'nthOfType' => $frame['nthOfType'],
			'empty' => $frame['empty'],
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
	 * blocks it lays out. With allow_html_optional_endtags off, only a cell, row or row group is closed, as mPDF's
	 * tables never nest them. WriteHTML() calls it for each start tag, before handling it
	 *
	 * @param string $tag The start tag's name, uppercased
	 * @param int $floor How many frames at the foot of the stack are not for the HTML being written to close: the
	 *                   document's frame, or the frames a positioned block's content is written inside
	 */
	private function closeElementsImpliedBy($tag, $floor)
	{
		if (isset(self::$tableImpliedEndTags[$tag])) {
			$this->closeNearestElement(self::$tableImpliedEndTags[$tag][0], self::$tableImpliedEndTags[$tag][1], $floor);
		}

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
