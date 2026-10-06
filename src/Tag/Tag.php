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
		// A forced break parts a block kept with its next from it
		$this->mpdf->keepWithNext->drop();

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
	 * Keeps the bold, italic and small caps a row group's CSS gives its cells, as the legacy cascade hands them on.
	 * Under the standard cascade the cells inherit the row group's font from its frame
	 *
	 * @param array $properties The row group's merged CSS
	 * @param string $prefix thead or tfoot, naming the Mpdf fields that hold them
	 */
	protected function keepLegacyRowGroupFont(array $properties, $prefix)
	{
		if ($this->appliesStandardCascade()) {
			return;
		}

		$fonts = [
			'FONT-WEIGHT' => ['_font_weight', 'BOLD', 'B'],
			'FONT-STYLE' => ['_font_style', 'ITALIC', 'I'],
			'FONT-VARIANT' => ['_font_smCaps', 'SMALL-CAPS', 'S'],
		];
		foreach ($fonts as $property => $font) {
			if (isset($properties[$property])) {
				list($field, $value, $flag) = $font;
				$this->mpdf->{$prefix . $field} = strtoupper($properties[$property]) === $value ? $flag : '';
			}
		}
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

	abstract public function open($attr, &$ahtml, &$ihtml);

	abstract public function close(&$ahtml, &$ihtml);

	/**
	 * Sets aside the inline elements a block or table opens inside, under CssMode::STANDARD: their saved states, the
	 * text state they set and their bidirectional embeddings go on the enclosing block. restoreBlockTextState() puts
	 * them back when it closes, so the text after it is drawn in their style and their end tags restore what was there
	 * before them.
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
				'bidi' => $this->mpdf->InlineBDF,
				'bidiCount' => $this->mpdf->InlineBDFctr,
			];
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
			$this->reopenBidiEmbeddings($block['openInline']['bidi'], $block['openInline']['bidiCount']);
			unset($block['openInline']);
		}
	}

	/**
	 * Puts back the bidirectional embeddings of the inline elements a block or table opened in. It ended the paragraph
	 * they were in, so they are opened again for the text after it, in the order they were opened, and their end tags
	 * close them
	 *
	 * @param array $embeddings As Mpdf::$InlineBDF holds them
	 * @param int $count As Mpdf::$InlineBDFctr holds it
	 */
	private function reopenBidiEmbeddings(array $embeddings, $count)
	{
		$this->mpdf->InlineBDF = $embeddings;
		$this->mpdf->InlineBDFctr = $count;

		$open = [];
		foreach ($embeddings as $element) {
			foreach ($element as $embedding) {
				$open[$embedding[1]] = $embedding[0];
			}
		}
		ksort($open);

		$codes = '';
		foreach ($open as $popd) {
			$codes .= $this->mpdf->_setBidiCodes('start', $popd);
		}
		if ($codes !== '') {
			$this->mpdf->OTLdata = [];
			$this->mpdf->_saveTextBuffer($codes);
			$this->mpdf->biDirectional = true;
		}
	}

}
