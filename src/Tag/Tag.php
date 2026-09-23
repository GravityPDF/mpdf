<?php

namespace Mpdf\Tag;

use Mpdf\Strict;

use Mpdf\Cache;
use Mpdf\Color\ColorConverter;
use Mpdf\Css\BorderMerger;
use Mpdf\CssManager;
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
		// longhand brings BorderMerger's defaults for the others, which the field should not take.
		$border = array_combine(array_keys(BorderMerger::DEFAULTS), array_pad(preg_split('/\s+/', trim($properties['BORDER-TOP']), 3), 3, ''));
		$longhand = isset($properties['BORDER-TOP-WIDTH']) || isset($properties['BORDER-TOP-STYLE']) || isset($properties['BORDER-TOP-COLOR']);
		foreach ($border as $part => $value) {
			if ($value === '' || ($longhand && !isset($properties['BORDER-TOP-' . $part]) && $value === BorderMerger::DEFAULTS[$part])) {
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

}
