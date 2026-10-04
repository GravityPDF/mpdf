<?php

namespace Mpdf\Tag;

use Mpdf\Strict;

use Mpdf\Cache;
use Mpdf\Color\ColorConverter;
use Mpdf\Css\BorderMerger;
use Mpdf\Css\InheritedProperties;
use Mpdf\CssManager;
use Mpdf\CssMode;
use Mpdf\Form;
use Mpdf\Image\ImageProcessor;
use Mpdf\Language\LanguageToFontInterface;
use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\SizeConverter;
use Mpdf\TableOfContents;

abstract class Tag
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	protected $mpdf;

	/**
	 * @var \Mpdf\Cache
	 */
	protected $cache;

	/**
	 * @var \Mpdf\CssManager
	 */
	protected $cssManager;

	/**
	 * @var \Mpdf\Form
	 */
	protected $form;

	/**
	 * @var \Mpdf\Otl
	 */
	protected $otl;

	/**
	 * @var \Mpdf\TableOfContents
	 */
	protected $tableOfContents;

	/**
	 * @var \Mpdf\SizeConverter
	 */
	protected $sizeConverter;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	protected $colorConverter;

	/**
	 * @var \Mpdf\Image\ImageProcessor
	 */
	protected $imageProcessor;

	/**
	 * @var \Mpdf\Language\LanguageToFontInterface
	 */
	protected $languageToFont;

	const ALIGN = [
		'left' => 'L',
		'center' => 'C',
		'right' => 'R',
		'top' => 'T',
		'text-top' => 'TT',
		'middle' => 'M',
		'baseline' => 'BS',
		'bottom' => 'B',
		'text-bottom' => 'TB',
		'justify' => 'J'
	];

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

	public function getTagName()
	{
		$tag = get_class($this);
		return strtoupper(str_replace('Mpdf\Tag\\', '', $tag));
	}

	protected function getAlign($property)
	{
		$property = strtolower($property);
		return array_key_exists($property, self::ALIGN) ? self::ALIGN[$property] : '';
	}

	/**
	 * Whether the standard cascade applies the document's CSS. Its presentational attributes then reach a tag
	 * handler as hints among the merged properties, below any stylesheet rule, and are not read again from the
	 * attributes
	 *
	 * @return bool
	 */
	protected function appliesStandardCascade()
	{
		return $this->mpdf->cssMode === CssMode::STANDARD;
	}

	/**
	 * Whether a page-break-before or page-break-after value starts a new page: always, left or right
	 *
	 * @param string[] $properties the element's computed CSS
	 * @param string $property PAGE-BREAK-BEFORE or PAGE-BREAK-AFTER
	 *
	 * @return bool
	 */
	protected function forcesPageBreak(array $properties, $property)
	{
		return isset($properties[$property]) && in_array(strtoupper($properties[$property]), ['ALWAYS', 'LEFT', 'RIGHT'], true);
	}

	/**
	 * Start a new page for a page-break-before or page-break-after, closing the open blocks and opening them again on
	 * the new page as the defaultPagebreakType option says
	 *
	 * @param string $pageBreak ALWAYS, LEFT or RIGHT
	 *
	 * @return void
	 */
	protected function forcePageBreak($pageBreak)
	{
		$save_blklvl = $this->mpdf->blklvl;
		$save_blk = $this->mpdf->blk;
		$save_silp = $this->mpdf->saveInlineProperties();
		$save_ilp = $this->mpdf->InlineProperties;
		$save_bflp = $this->mpdf->InlineBDF;
		$save_bflpc = $this->mpdf->InlineBDFctr; // mPDF 6
		// mPDF 6 pagebreaktype
		$startpage = $this->mpdf->page;
		$pagebreaktype = $this->mpdf->defaultPagebreakType;
		if ($this->mpdf->ColActive) {
			$pagebreaktype = 'cloneall';
		}

		// mPDF 6 pagebreaktype
		$this->mpdf->_preForcedPagebreak($pagebreaktype);

		if ($pageBreak === 'RIGHT') {
			$this->mpdf->AddPage($this->mpdf->CurOrientation, 'NEXT-ODD');
		} elseif ($pageBreak === 'LEFT') {
			$this->mpdf->AddPage($this->mpdf->CurOrientation, 'NEXT-EVEN');
		} else {
			$this->mpdf->AddPage($this->mpdf->CurOrientation);
		}

		// mPDF 6 pagebreaktype
		$this->mpdf->_postForcedPagebreak($pagebreaktype, $startpage, $save_blk, $save_blklvl);

		$this->mpdf->InlineProperties = $save_ilp;
		$this->mpdf->InlineBDF = $save_bflp;
		$this->mpdf->InlineBDFctr = $save_bflpc; // mPDF 6
		$this->mpdf->restoreInlineProperties($save_silp);
	}

	/**
	 * An object's attributes with the visibility of the span it is in. Printing reads a span's visibility from its
	 * text buffer entry, and an object put straight into the buffer has none.
	 *
	 * @param mixed[] $objattr
	 *
	 * @return mixed[]
	 */
	protected function withSpanVisibility(array $objattr)
	{
		if (!empty($this->mpdf->textparam['visibility'])) {
			$objattr['visibility'] = $this->mpdf->textparam['visibility'];
		}

		return $objattr;
	}

	/**
	 * Sets the font size a font-size gives a form field or text circle, read against the document's size. larger and
	 * smaller leave it as it is in the legacy CSS mode, which ignores them
	 *
	 * @param string $size
	 */
	protected function setFontSizeAgainstDocument($size)
	{
		$mmsize = $this->sizeConverter->convertFontSizeToMm($size, $this->mpdf->default_font_size / Mpdf::SCALE);
		if ($mmsize !== null) {
			$this->mpdf->SetFontSize($mmsize * Mpdf::SCALE, false);
		}
	}

	/**
	 * The background and border a form field's CSS sets, for Form to draw whether or not forms are active. What the
	 * CSS leaves unset is left out, so Form keeps its default.
	 *
	 * @param string[] $properties the field's computed CSS
	 *
	 * @return mixed[] any of 'background-col', 'border-col', 'border-width' in mm and 'border-style'
	 */
	protected function formFieldStyle(array $properties)
	{
		$style = [];
		if (isset($properties['BACKGROUND-COLOR'])) {
			$style['background-col'] = $this->colorConverter->convert($properties['BACKGROUND-COLOR'], $this->mpdf->PDFAXwarnings);
		}

		if (!isset($properties['BORDER-TOP'])) {
			return $style;
		}

		// The cascade folds border-top-width, -style and -color into BORDER-TOP, and keeps them. A part given only as a
		// longhand brings BorderMerger's defaults for the others, which the field should not take. In standard mode the
		// default colour is the field's own, and a shorthand always sets the colour longhand
		$border = array_combine(array_keys(BorderMerger::DEFAULTS), array_pad(preg_split('/\s+/', trim($properties['BORDER-TOP']), 3), 3, ''));
		$longhand = isset($properties['BORDER-TOP-WIDTH']) || isset($properties['BORDER-TOP-STYLE']) || isset($properties['BORDER-TOP-COLOR']);
		foreach ($border as $part => $value) {
			$default = $value === BorderMerger::DEFAULTS[$part] || ($part === 'COLOR' && $this->mpdf->cssMode === CssMode::STANDARD);
			if ($value === '' || ($longhand && !isset($properties['BORDER-TOP-' . $part]) && $default)) {
				continue;
			}
			if ($part === 'WIDTH') {
				$style['border-width'] = $this->sizeConverter->convert($value, $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->FontSize, false);
			} elseif ($part === 'STYLE') {
				$style['border-style'] = strtolower($value);
			} elseif ($color = $this->colorConverter->convert($value, $this->mpdf->PDFAXwarnings)) {
				$style['border-col'] = $color;
			}
		}

		return $style;
	}

	/**
	 * Under the standard cascade, keeps what a row group opened in the innermost table hands the cells of its rows.
	 * Tr reads it back
	 *
	 * @param string[] $properties The row group's merged CSS
	 */
	protected function inheritRowGroup(array $properties)
	{
		if ($this->mpdf->cssMode === CssMode::STANDARD && $this->mpdf->tableLevel) {
			$this->mpdf->table[$this->mpdf->tableLevel][$this->mpdf->tbctr[$this->mpdf->tableLevel]]['rowGroupInherited'] = $this->inheritedByTablePart($properties, $this->mpdf->base_table_properties);
		}
	}

	/**
	 * The inherited properties a row group or a row hands its cells under the standard cascade: its own, over those
	 * of the table or row group it is in. Its font size is resolved against theirs
	 *
	 * @param string[] $properties Its merged CSS
	 * @param string[] $parent What the table or row group it is in hands its cells, with a font size in mm
	 *
	 * @return string[]
	 */
	protected function inheritedByTablePart(array $properties, array $parent)
	{
		$inherited = array_merge($parent, InheritedProperties::of($properties, InheritedProperties::names()));

		if (isset($properties['FONT-SIZE'])) {
			$inherited['FONT-SIZE'] = $this->relativeFontSize($properties['FONT-SIZE'], $parent['FONT-SIZE']);
		}

		return $inherited;
	}

	/**
	 * A font size given as a number, in any unit, resolved against the size it is relative to. Keywords such as small
	 * are kept, since setCSS() reads them against the default size whatever the parent's
	 *
	 * @param string $size
	 * @param string $parentSize With a unit
	 *
	 * @return string
	 */
	protected function relativeFontSize($size, $parentSize)
	{
		if (!$this->sizeConverter->isLength($size)) {
			return $size;
		}

		return $this->sizeConverter->convert($size, $this->sizeConverter->convert($parentSize)) . 'mm';
	}

	abstract public function open($attr, &$ahtml, &$ihtml);

	abstract public function close(&$ahtml, &$ihtml);

	/**
	 * Sets aside the inline elements a block or table opens inside, under CssMode::STANDARD: their saved states and the
	 * text state they set go on the enclosing block. The block or table inherits that text state, and
	 * restoreBlockTextState() puts both back when it closes, so the text after it is drawn in their style and their end tags restore what was there before them.
	 *
	 * A block opened a second time, as a kept block laid out again or a block reopened after a forced page break, finds
	 * them already set aside and keeps them, as by then the text state has changed
	 */
	protected function setOpenInlineElementsAside()
	{
		$block = &$this->mpdf->blk[$this->mpdf->blklvl];

		if (!isset($block['openInline']) && array_filter($this->mpdf->InlineProperties)) {
			$block['openInline'] = [
				'properties' => $this->mpdf->InlineProperties,
				'state' => $this->mpdf->saveInlineProperties(),
			];

			// The line before the block or table is printed next, and its text with no colour or link of its own is drawn in the
			// current state
			if (isset($block['InlineProperties'])) {
				$this->mpdf->restoreInlineProperties($block['InlineProperties']);
			}
		}

		$this->mpdf->InlineProperties = [];
	}

	/**
	 * Restores, when a block or table closes, the text state of the block that is current again: that of the inline
	 * elements setOpenInlineElementsAside() set aside on it, which are put back, or else the block's own
	 */
	protected function restoreBlockTextState()
	{
		$state = InheritedProperties::blockTextState($this->mpdf->blk, $this->mpdf->blklvl);
		if ($state !== null) {
			$this->mpdf->restoreInlineProperties($state);
		}

		$block = &$this->mpdf->blk[$this->mpdf->blklvl];
		if (isset($block['openInline'])) {
			$this->mpdf->InlineProperties = $block['openInline']['properties'];
			unset($block['openInline']);
		}
	}

}
