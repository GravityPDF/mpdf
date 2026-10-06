<?php

namespace Mpdf;

use Mpdf\Strict;
use Mpdf\Color\ColorConverter;
use Mpdf\Image\ImageProcessor;
use Mpdf\Language\LanguageToFontInterface;

class Tag
{

	use Strict;

	/**
	 * @var array<string, true> The start tags that close an open <p>
	 */
	private static $paragraphClosers = [
		'ADDRESS' => true, 'ARTICLE' => true, 'ASIDE' => true, 'BLOCKQUOTE' => true, 'CENTER' => true, 'DIV' => true,
		'DL' => true, 'FIELDSET' => true, 'FORM' => true, 'H1' => true, 'H2' => true, 'H3' => true, 'H4' => true,
		'H5' => true, 'H6' => true, 'HGROUP' => true, 'HR' => true, 'MAIN' => true, 'NAV' => true, 'OL' => true,
		'P' => true, 'PRE' => true, 'SECTION' => true, 'TABLE' => true, 'UL' => true,
	];

	/**
	 * @var array<string, int> How deep in a table each of its parts is: a row group, a row, a cell. Keep in step with
	 * TracksOpenElements::$tableImpliedEndTags, which closes the same parts on the stack of open elements
	 */
	private static $tablePartDepths = ['THEAD' => 1, 'TBODY' => 1, 'TFOOT' => 1, 'TR' => 2, 'TD' => 3, 'TH' => 3];

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Cache
	 */
	private $cache;

	/**
	 * @var \Mpdf\CssManager
	 */
	private $cssManager;

	/**
	 * @var \Mpdf\Form
	 */
	private $form;

	/**
	 * @var \Mpdf\Otl
	 */
	private $otl;

	/**
	 * @var \Mpdf\TableOfContents
	 */
	private $tableOfContents;

	/**
	 * @var \Mpdf\SizeConverter
	 */
	private $sizeConverter;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * @var \Mpdf\Image\ImageProcessor
	 */
	private $imageProcessor;

	/**
	 * @var \Mpdf\Language\LanguageToFontInterface
	 */
	private $languageToFont;

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 * @param \Mpdf\Cache $cache
	 * @param \Mpdf\CssManager $cssManager
	 * @param \Mpdf\Form $form
	 * @param \Mpdf\Otl $otl
	 * @param \Mpdf\TableOfContents $tableOfContents
	 * @param \Mpdf\SizeConverter $sizeConverter
	 * @param \Mpdf\Color\ColorConverter $colorConverter
	 * @param \Mpdf\Image\ImageProcessor $imageProcessor
	 * @param \Mpdf\Language\LanguageToFontInterface $languageToFont
	 */
	public function __construct(
		Mpdf $mpdf,
		Cache $cache,
		CssManager $cssManager,
		Form $form,
		Otl $otl,
		TableOfContents $tableOfContents,
		SizeConverter $sizeConverter,
		ColorConverter $colorConverter,
		ImageProcessor $imageProcessor,
		LanguageToFontInterface $languageToFont
	) {

		$this->mpdf = $mpdf;
		$this->cache = $cache;
		$this->cssManager = $cssManager;
		$this->form = $form;
		$this->otl = $otl;
		$this->tableOfContents = $tableOfContents;
		$this->sizeConverter = $sizeConverter;
		$this->colorConverter = $colorConverter;
		$this->imageProcessor = $imageProcessor;
		$this->languageToFont = $languageToFont;
	}

	/**
	 * @param string $tag The tag name
	 * @return \Mpdf\Tag\Tag
	 */
	private function getTagInstance($tag)
	{
		$className = self::getTagClassName($tag);
		if (class_exists($className)) {
			return new $className(
				$this->mpdf,
				$this->cache,
				$this->cssManager,
				$this->form,
				$this->otl,
				$this->tableOfContents,
				$this->sizeConverter,
				$this->colorConverter,
				$this->imageProcessor,
				$this->languageToFont
			);
		}
	}

	/**
	 * Returns the fully qualified name of the class handling the rendering of the given tag
	 *
	 * @param string $tag The tag name
	 * @return string The fully qualified name
	 */
	public static function getTagClassName($tag)
	{
		static $map = [
			'BARCODE' => 'BarCode',
			'BLOCKQUOTE' => 'BlockQuote',
			'COLUMN_BREAK' => 'ColumnBreak',
			'COLUMNBREAK' => 'ColumnBreak',
			'DOTTAB' => 'DotTab',
			'FIELDSET' => 'FieldSet',
			'FIGCAPTION' => 'FigCaption',
			'FORMFEED' => 'FormFeed',
			'HGROUP' => 'HGroup',
			'INDEXENTRY' => 'IndexEntry',
			'INDEXINSERT' => 'IndexInsert',
			'NEWCOLUMN' => 'NewColumn',
			'NEWPAGE' => 'NewPage',
			'PAGEFOOTER' => 'PageFooter',
			'PAGEHEADER' => 'PageHeader',
			'PAGE_BREAK' => 'PageBreak',
			'PAGEBREAK' => 'PageBreak',
			'SETHTMLPAGEFOOTER' => 'SetHtmlPageFooter',
			'SETHTMLPAGEHEADER' => 'SetHtmlPageHeader',
			'SETPAGEFOOTER' => 'SetPageFooter',
			'SETPAGEHEADER' => 'SetPageHeader',
			'TBODY' => 'TBody',
			'TFOOT' => 'TFoot',
			'THEAD' => 'THead',
			'TEXTAREA' => 'TextArea',
			'TEXTCIRCLE' => 'TextCircle',
			'TOCENTRY' => 'TocEntry',
			'TOCPAGEBREAK' => 'TocPageBreak',
			'VAR' => 'VarTag',
			'WATERMARKIMAGE' => 'WatermarkImage',
			'WATERMARKTEXT' => 'WatermarkText',
		];

		$className = 'Mpdf\Tag\\';
		$className .= isset($map[$tag]) ? $map[$tag] : ucfirst(strtolower($tag));

		return $className;
	}

	/**
	 * Whether the start tag of an element closes an open <p>, as HTML lets its end tag be left out before it
	 *
	 * @param string $tag The tag name, uppercased
	 *
	 * @return bool
	 */
	public static function closesParagraph($tag)
	{
		return isset(self::$paragraphClosers[$tag]);
	}

	public function OpenTag($tag, $attr, &$ahtml, &$ihtml)
	{
		// Correct for tags where HTML5 specifies optional end tags excluding table elements (cf WriteHTML() )
		if ($this->mpdf->allow_html_optional_endtags) {
			if (isset($this->mpdf->blk[$this->mpdf->blklvl]['tag'])) {
				$closed = false;
				// li end tag may be omitted if immediately followed by another li element
				if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'LI' && $tag == 'LI') {
					$this->CloseTag('LI', $ahtml, $ihtml);
					$closed = true;
				}
				// dt end tag may be omitted if immediately followed by another dt element or a dd element
				if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'DT' && ($tag == 'DT' || $tag == 'DD')) {
					$this->CloseTag('DT', $ahtml, $ihtml);
					$closed = true;
				}
				// dd end tag may be omitted if immediately followed by another dd element or a dt element
				if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'DD' && ($tag == 'DT' || $tag == 'DD')) {
					$this->CloseTag('DD', $ahtml, $ihtml);
					$closed = true;
				}
				// p end tag may be omitted if immediately followed by one of the elements closesParagraph() lists
				if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'P' && self::closesParagraph($tag)) {
					$this->CloseTag('P', $ahtml, $ihtml);
					$closed = true;
				}
				// option end tag may be omitted if immediately followed by another option element
				// (or if it is immediately followed by an optgroup element)
				if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'OPTION' && $tag == 'OPTION') {
					$this->CloseTag('OPTION', $ahtml, $ihtml);
					$closed = true;
				}
			}

			// A cell, row or row group ends the open ones as deep in the table as it is, or deeper (see also WriteHTML())
			if (isset(self::$tablePartDepths[$tag])) {
				$this->closeTableParts(self::$tablePartDepths[$tag], $ahtml, $ihtml);
			}
		}

		if ($object = $this->getTagInstance($tag)) {
			$this->trackTablePart($tag, true);

			return $object->open($attr, $ahtml, $ihtml);
		}
	}

	public function CloseTag($tag, &$ahtml, &$ihtml)
	{
		if ($object = $this->getTagInstance($tag)) {
			$this->trackTablePart($tag, false);

			return $object->close($ahtml, $ihtml);
		}
	}

	/**
	 * Closes the cells, rows and row groups open inside a table part, or a table, whose end tag is being read: the
	 * parts whose end tags HTML lets be left out. WriteHTML() calls it before closing the part itself
	 *
	 * @param string $tag The end tag's name, uppercased
	 * @param string[] $ahtml
	 * @param int $ihtml
	 */
	public function closeTablePartsInside($tag, &$ahtml, &$ihtml)
	{
		if ($tag === 'TABLE') {
			$this->closeTableParts(1, $ahtml, $ihtml);
		} elseif (isset(self::$tablePartDepths[$tag])) {
			$this->closeTableParts(self::$tablePartDepths[$tag] + 1, $ahtml, $ihtml);
		}
	}

	/**
	 * Closes, innermost first, the innermost table's open cells, rows and row groups at least $depth deep. They come
	 * from a list of the open parts, not the part last opened: a block in a cell, or a row closed by its own end tag,
	 * would hide the cell or row group still open around it
	 *
	 * @param int $depth As in $tablePartDepths
	 * @param string[] $ahtml
	 * @param int $ihtml
	 */
	private function closeTableParts($depth, &$ahtml, &$ihtml)
	{
		foreach (array_reverse($this->openTableParts()) as $part) {
			if (self::$tablePartDepths[$part] < $depth) {
				return;
			}
			$this->CloseTag($part, $ahtml, $ihtml);
		}
	}

	/**
	 * Keeps the list of the innermost table's open cells, rows and row groups, outermost first, as one opens or closes.
	 * Only the closes allow_html_optional_endtags turns on read it
	 *
	 * @param string $tag The tag being opened or closed, uppercased
	 * @param bool $opening
	 */
	private function trackTablePart($tag, $opening)
	{
		if (!isset(self::$tablePartDepths[$tag]) || !$this->mpdf->tableLevel || !$this->mpdf->allow_html_optional_endtags) {
			return;
		}

		$parts = &$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['openParts'];
		if ($opening) {
			$parts[] = $tag;
		} elseif ($parts && self::$tablePartDepths[end($parts)] === self::$tablePartDepths[$tag]) {
			array_pop($parts);
		}
	}

	/**
	 * The innermost table's open cells, rows and row groups, outermost first
	 *
	 * @return string[] Their tags
	 */
	private function openTableParts()
	{
		if (!$this->mpdf->tableLevel) {
			return [];
		}

		$table = $this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]];

		return isset($table['openParts']) ? $table['openParts'] : [];
	}
}
