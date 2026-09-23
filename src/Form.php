<?php

namespace Mpdf;

use Mpdf\Strict;
use Mpdf\Color\ColorConverter;
use Mpdf\Shaper\OtlData;
use Mpdf\Writer\BaseWriter;
use Mpdf\Writer\FormWriter;

class Form
{

	use StateSnapshot;

	use Strict;

	// Input flags
	const FLAG_READONLY = 1;
	const FLAG_REQUIRED = 2;
	const FLAG_NO_EXPORT = 3;
	const FLAG_TEXTAREA = 13;
	const FLAG_PASSWORD = 14;
	const FLAG_RADIO = 15;
	const FLAG_NOTOGGLEOFF = 16;
	const FLAG_COMBOBOX = 18;
	const FLAG_EDITABLE = 19;
	const FLAG_MULTISELECT = 22;
	const FLAG_NO_SPELLCHECK = 23;
	const FLAG_NO_SCROLL = 24;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Otl
	 */
	private $otl;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	private $colorConverter;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Writer\FormWriter
	 */
	private $formWriter;

	/**
	 * @var array
	 */
	public $forms;

	/**
	 * @var int
	 */
	private $formCount;

	/**
	 * @var float[] the width of each word appearanceText() has measured in the current field's font, in ems
	 */
	private $emWidths = [];

	// Active Forms
	var $formSubmitNoValueFields;
	var $formExportType;
	var $formSelectDefaultOption;

	// Form Styles
	var $form_border_color;
	var $form_background_color;
	var $form_border_width;
	var $form_border_style;
	var $form_button_border_color;
	var $form_button_background_color;
	var $form_button_border_width;
	var $form_button_border_style;
	var $form_radio_color;
	var $form_radio_background_color;
	var $form_element_spacing;

	// Active forms
	var $formMethod;
	var $formAction;
	var $form_fonts;
	var $form_radio_groups;

	/**
	 * @var array[] by name, the push buttons that share it, which are written as one field with the widgets as kids
	 */
	private $buttonGroups = [];

	var $form_checkboxes;
	var $pdf_acro_array;
	var $pdf_array_co;
	var $array_form_button_js;
	var $array_form_choice_js;
	var $array_form_text_js;

	// Button Text
	var $form_button_text;
	var $form_button_text_over;
	var $form_button_text_click;
	var $form_button_icon;

	// FORMS
	var $textarea_lineheight;

	public function __construct(Mpdf $mpdf, Otl $otl, ColorConverter $colorConverter, BaseWriter $writer, FormWriter $formWriter)
	{
		$this->mpdf = $mpdf;
		$this->otl = $otl;
		$this->colorConverter = $colorConverter;
		$this->writer = $writer;
		$this->formWriter = $formWriter;

		// ACTIVE FORMS
		$this->formExportType = 'xfdf'; // 'xfdf' or 'html'
		$this->formSubmitNoValueFields = true; // Whether to include blank fields when submitting data
		$this->formSelectDefaultOption = true; // for Select drop down box; if no option is explicitly maked as selected,
		// this determines whether to select 1st option (as per browser)
		// - affects whether "required" attribute is relevant
		// FORM STYLES
		// These can alternatively use a 4 number string to represent CMYK colours
		$this->form_border_color = '0.6 0.6 0.72';   // RGB
		$this->form_background_color = '0.975 0.975 0.975';  // RGB
		$this->form_border_width = '1';  // 0 doesn't seem to work as it should
		$this->form_border_style = 'S';  // B - Bevelled; D - Double
		$this->form_button_border_color = '0.2 0.2 0.55';
		$this->form_button_background_color = '0.941 0.941 0.941';
		$this->form_button_border_width = '1';
		$this->form_button_border_style = 'S';
		$this->form_radio_color = '0.0 0.0 0.4';  // radio and checkbox
		$this->form_radio_background_color = '0.9 0.9 0.9';

		// FORMS
		$this->textarea_lineheight = 1.25;

		// FORM ELEMENT SPACING
		$this->form_element_spacing['select']['outer']['h'] = 0.5; // Horizontal spacing around SELECT
		$this->form_element_spacing['select']['outer']['v'] = 0.5; // Vertical spacing around SELECT
		$this->form_element_spacing['select']['inner']['h'] = 0.7; // Horizontal padding around SELECT
		$this->form_element_spacing['select']['inner']['v'] = 0.7; // Vertical padding around SELECT
		$this->form_element_spacing['input']['outer']['h'] = 0.5;
		$this->form_element_spacing['input']['outer']['v'] = 0.5;
		$this->form_element_spacing['input']['inner']['h'] = 0.7;
		$this->form_element_spacing['input']['inner']['v'] = 0.7;
		$this->form_element_spacing['textarea']['outer']['h'] = 0.5;
		$this->form_element_spacing['textarea']['outer']['v'] = 0.5;
		$this->form_element_spacing['textarea']['inner']['h'] = 1;
		$this->form_element_spacing['textarea']['inner']['v'] = 0.5;
		$this->form_element_spacing['button']['outer']['h'] = 0.5;
		$this->form_element_spacing['button']['outer']['v'] = 0.5;
		$this->form_element_spacing['button']['inner']['h'] = 2;
		$this->form_element_spacing['button']['inner']['v'] = 1;

		// INITIALISE non-configurable
		$this->formMethod = 'POST';
		$this->formAction = '';
		$this->form_fonts = [];
		$this->form_radio_groups = [];
		$this->form_checkboxes = false;
		$this->forms = [];
		$this->pdf_array_co = '';
	}

	function print_ob_text($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
	{
		// TEXT/PASSWORD INPUT
		if ($this->mpdf->useActiveForms) {

			$flags = [];

			if (!empty($objattr['disabled']) || !empty($objattr['readonly'])) {
				$flags[] = self::FLAG_READONLY;
			}

			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_NO_EXPORT;
				$objattr['color'] = $this->colorConverter->convert(128, $this->mpdf->PDFAXwarnings); // gray out disabled
			}

			if (!empty($objattr['required'])) {
				$flags[] = self::FLAG_REQUIRED;
			}

			if (!isset($objattr['spellcheck']) || !$objattr['spellcheck']) {
				$flags[] = self::FLAG_NO_SPELLCHECK;
			}

			if (isset($objattr['subtype']) && $objattr['subtype'] === 'PASSWORD') {
				$flags[] = self::FLAG_PASSWORD;
			}

			$this->mpdf->SetTColor(isset($objattr['color']) ? $objattr['color'] : $this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

			$fieldalign = $rtlalign;

			if (!empty($objattr['text_align'])) {
				$fieldalign = $objattr['text_align'];
				$val = $objattr['text'];
			} else {
				$val = $objattr['text'];
			}

			// mPDF 5.3.25
			$js = [];
			if (!empty($objattr['onCalculate'])) {
				$js[] = ['C', $objattr['onCalculate']];
			}
			if (!empty($objattr['onValidate'])) {
				$js[] = ['V', $objattr['onValidate']];
			}
			if (!empty($objattr['onFormat'])) {
				$js[] = ['F', $objattr['onFormat']];
			}
			if (!empty($objattr['onKeystroke'])) {
				$js[] = ['K', $objattr['onKeystroke']];
			}

			if (!empty($objattr['use_auto_fontsize']) && $objattr['use_auto_fontsize'] === true) {
				$this->mpdf->FontSizePt = 0.0;
			}

			$this->SetFormText($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $val, $val, $objattr['title'], $flags, $fieldalign, false, (isset($objattr['maxlength']) ? $objattr['maxlength'] : false), $js, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), $this->activeBorder($objattr));

		} else {

			$w -= $this->form_element_spacing['input']['outer']['h'] * 2 / $k;
			$h -= $this->form_element_spacing['input']['outer']['v'] * 2 / $k;
			$this->mpdf->x += $this->form_element_spacing['input']['outer']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['input']['outer']['v'] / $k;

			$border = $this->setStaticBorder($objattr, $k);

			$this->setStaticColors($objattr, !empty($objattr['disabled']) || !empty($objattr['readonly']));

			$this->fittedCell($w, $h, $texto, $border ? 1 : 0, $rtlalign, 1, $this->form_element_spacing['input']['inner']['h'] / $k);
			$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			$this->resetStaticBorder($objattr);
		}
	}

	function print_ob_textarea($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
	{
		// TEXTAREA
		if ($this->mpdf->useActiveForms) {

			$flags = [self::FLAG_TEXTAREA];

			if (!empty($objattr['disabled']) || !empty($objattr['readonly'])) {
				$flags[] = self::FLAG_READONLY;
			}

			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_NO_EXPORT;
				$objattr['color'] = $this->colorConverter->convert(128, $this->mpdf->PDFAXwarnings); // gray out disabled
			}

			if (!empty($objattr['required'])) {
				$flags[] = self::FLAG_REQUIRED;
			}

			if (!isset($objattr['spellcheck']) || !$objattr['spellcheck']) {
				$flags[] = self::FLAG_NO_SPELLCHECK;
			}

			if (!empty($objattr['donotscroll'])) {
				$flags[] = self::FLAG_NO_SCROLL;
			}

			if (isset($objattr['color'])) {
				$this->mpdf->SetTColor($objattr['color']);
			} else {
				$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			}

			$fieldalign = $rtlalign;

			if ($texto === ' ') {
				$texto = '';
			}

			// mPDF 5.3.24
			if (!empty($objattr['text_align'])) {
				$fieldalign = $objattr['text_align'];
			}

			// mPDF 5.3.25
			$js = [];
			if (!empty($objattr['onCalculate'])) {
				$js[] = ['C', $objattr['onCalculate']];
			}
			if (!empty($objattr['onValidate'])) {
				$js[] = ['V', $objattr['onValidate']];
			}
			if (!empty($objattr['onFormat'])) {
				$js[] = ['F', $objattr['onFormat']];
			}
			if (!empty($objattr['onKeystroke'])) {
				$js[] = ['K', $objattr['onKeystroke']];
			}

			if (!empty($objattr['use_auto_fontsize']) && $objattr['use_auto_fontsize'] === true) {
				$this->mpdf->FontSizePt = 0.0;
			}

			$this->SetFormText($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $texto, $texto, (isset($objattr['title']) ? $objattr['title'] : ''), $flags, $fieldalign, false, -1, $js, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), $this->activeBorder($objattr));
			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

		} else {

			$w -= $this->form_element_spacing['textarea']['outer']['h'] * 2 / $k;
			$h -= $this->form_element_spacing['textarea']['outer']['v'] * 2 / $k;

			$this->mpdf->x += $this->form_element_spacing['textarea']['outer']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['textarea']['outer']['v'] / $k;

			$border = $this->setStaticBorder($objattr, $k);

			$this->setStaticColors($objattr, !empty($objattr['disabled']) || !empty($objattr['readonly']));

			$this->mpdf->Rect($this->mpdf->x, $this->mpdf->y, $w, $h, $border ? 'DF' : 'F');
			$ClipPath = sprintf('q %.3F %.3F %.3F %.3F re W n ', $this->mpdf->x * Mpdf::SCALE, ($this->mpdf->h - $this->mpdf->y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$h * Mpdf::SCALE);
			$this->writer->write($ClipPath);

			$w -= $this->form_element_spacing['textarea']['inner']['h'] * 2 / $k;
			$this->mpdf->x += $this->form_element_spacing['textarea']['inner']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['textarea']['inner']['v'] / $k;

			if ($texto != '') {
				$this->mpdf->MultiCell($w, $this->mpdf->FontSize * $this->textarea_lineheight, $texto, 0, '', 0, '', $blockdir, true, $objattr['OTLdata'], $objattr['rows']);
			}

			$this->writer->write('Q');
			$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			$this->resetStaticBorder($objattr);
		}
	}

	function print_ob_select($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
	{
		// SELECT
		// As in HTML, a select is a drop-down unless it is multiple or given a size of two or more rows
		$multiple = !empty($objattr['multiple']);
		$combo = !$multiple && (!isset($objattr['size']) || $objattr['size'] < 2);
		if ($this->mpdf->useActiveForms) {
			$flags = [];
			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_READONLY;
				$flags[] = self::FLAG_NO_EXPORT;
				$objattr['color'] = $this->colorConverter->convert(128, $this->mpdf->PDFAXwarnings); // gray out disabled
			}
			if (!empty($objattr['required'])) {
				$flags[] = self::FLAG_REQUIRED;
			}
			if ($multiple) {
				$flags[] = self::FLAG_MULTISELECT;
			}
			if ($combo) {
				$flags[] = self::FLAG_COMBOBOX;
				if (!empty($objattr['editable'])) {
					$flags[] = self::FLAG_EDITABLE;
				}
			}

			// only allow spellcheck if combo and editable
			if (!$combo || empty($objattr['spellcheck']) || empty($objattr['editable'])) {
				$flags[] = self::FLAG_NO_SPELLCHECK;
			}

			if (isset($objattr['subtype']) && $objattr['subtype'] === 'PASSWORD') {
				$flags[] = self::FLAG_PASSWORD;
			}

			if (!empty($objattr['onChange'])) {
				$js = $objattr['onChange'];
			} else {
				$js = '';
			} // mPDF 5.3.37

			$data = ['VAL' => [], 'OPT' => [], 'SEL' => [],];
			if (isset($objattr['items'])) {
				for ($i = 0; $i < count($objattr['items']); $i++) {
					$item = $objattr['items'][$i];
					$data['VAL'][] = (isset($item['exportValue']) ? $item['exportValue'] : '');
					$data['OPT'][] = (isset($item['content']) ? $item['content'] : '');
					if (!empty($item['selected'])) {
						$data['SEL'][] = $i;
					}
				}
			}

			if (count($data['SEL']) === 0 && $this->formSelectDefaultOption) {
				$data['SEL'][] = 0;
			}

			if (isset($objattr['color'])) {
				$this->mpdf->SetTColor($objattr['color']);
			} else {
				$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			}

			$this->SetFormChoice($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $flags, $data, $rtlalign, $js, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), $this->activeBorder($objattr), (isset($objattr['rows']) ? $objattr['rows'] : 1));
			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

		} else {
			$border = $this->setStaticBorder($objattr, $k) ? 1 : 0;
			$this->setStaticColors($objattr, !empty($objattr['disabled']));
			$w -= $this->form_element_spacing['select']['outer']['h'] * 2 / $k;
			$h -= $this->form_element_spacing['select']['outer']['v'] * 2 / $k;
			$this->mpdf->x += $this->form_element_spacing['select']['outer']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['select']['outer']['v'] / $k;

			if (!$combo) {
				$this->printListBox($objattr, $w, $h, $rtlalign, $k);
				$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
				$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

				return;
			}

			// DIRECTIONALITY
			if (preg_match('/([' . $this->mpdf->pregRTLchars . '])/u', $texto)) {
				$this->mpdf->biDirectional = true;
			} // *RTL*

			$this->fittedCell($w - ($this->mpdf->FontSize * 1.4), $h, $texto, $border, $rtlalign, 1, $this->form_element_spacing['select']['inner']['h'] / $k, $objattr['OTLdata']);
			$this->mpdf->SetFColor($this->colorConverter->convert(190, $this->mpdf->PDFAXwarnings));
			$save_font = $this->mpdf->FontFamily;
			$save_currentfont = $this->mpdf->currentfontfamily;
			if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
				if (($this->mpdf->PDFA && !$this->mpdf->PDFAauto) || ($this->mpdf->PDFX && !$this->mpdf->PDFXauto)) {
					$this->mpdf->PDFAXwarnings[] = 'Core Adobe font Zapfdingbats cannot be embedded in mPDF - used in Form element: Select - which is required for PDFA1-b or PDFX/1-a. (Different character/font will be substituted.)';
				}
				$this->mpdf->SetFont('sans');
				if ($this->mpdf->_charDefined($this->mpdf->CurrentFont['cw'], 9660)) {
					$down = "\xe2\x96\xbc";
				} else {
					$down = '=';
				}
				$this->mpdf->Cell($this->mpdf->FontSize * 1.4, $h, $down, $border, 0, 'C', 1);
			} else {
				$this->mpdf->SetFont('czapfdingbats');
				$this->mpdf->Cell($this->mpdf->FontSize * 1.4, $h, chr(116), $border, 0, 'C', 1);
			}
			$this->mpdf->SetFont($save_font);
			$this->mpdf->currentfontfamily = $save_currentfont;
			$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			$this->resetStaticBorder($objattr);
		}
	}

	/**
	 * Draws a static list box as a browser does: one option a row, every selected one highlighted, scrolled to the first
	 * selected option if it would fall below the last row
	 *
	 * @param mixed[] $objattr
	 * @param float $w the box's width, in mm
	 * @param float $h its height, in mm
	 * @param string $rtlalign
	 * @param float $k how much a table has shrunk the box
	 */
	private function printListBox($objattr, $w, $h, $rtlalign, $k)
	{
		$x = $this->mpdf->x;
		$y = $this->mpdf->y;
		$padding = $this->form_element_spacing['select']['inner']['h'] / $k;
		$rowHeight = $this->mpdf->FontSize;
		$rows = $objattr['rows'];

		$items = isset($objattr['items']) ? $objattr['items'] : [];
		$top = 0;
		foreach ($items as $i => $item) {
			if (!empty($item['selected'])) {
				if ($i >= $rows) {
					$top = $i;
				}
				break;
			}
		}

		$this->mpdf->Rect($x, $y, $w, $h, 'F');
		$this->writer->write(sprintf('q %.3F %.3F %.3F %.3F re W n', $x * Mpdf::SCALE, ($this->mpdf->h - $y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$h * Mpdf::SCALE));

		$items = array_slice($items, $top, $rows);
		$rowsTop = $y + ($h - $rows * $rowHeight) / 2;

		// Highlights go down before the text so none covers the descenders of the option above. The colour is mPDF's
		// own, so converting it for the colour space raises no warning.
		$this->mpdf->SetFColor($this->colorConverter->convert('rgb(153, 191, 217)'));
		foreach ($items as $row => $item) {
			if (!empty($item['selected'])) {
				$this->mpdf->Rect($x, $rowsTop + $row * $rowHeight, $w, $rowHeight, 'F');
			}
		}

		// Cell() would break the page on the line's height; the box is already known to fit
		$divheight = $this->mpdf->divheight;
		$this->mpdf->divheight = 0;
		foreach ($items as $row => $item) {
			$text = $item['content'];
			$OTLdata = $item['OTLdata'];
			if (preg_match('/([' . $this->mpdf->pregRTLchars . '])/u', $text)) {
				$this->mpdf->biDirectional = true;
			} // *RTL*
			$this->mpdf->magic_reverse_dir($text, $this->mpdf->directionality, $OTLdata);

			$this->mpdf->x = $x;
			$this->mpdf->y = $rowsTop + $row * $rowHeight;
			$this->mpdf->Cell($w, $rowHeight, $text, 0, 0, $rtlalign, 0, '', 0, $padding, $padding, 'M', 0, false, $OTLdata);
		}

		$this->mpdf->divheight = $divheight;
		$this->writer->write('Q');
		$this->mpdf->Rect($x, $y, $w, $h, 'D');
		$this->mpdf->x = $x + $w;
		$this->mpdf->y = $y;
	}

	function print_ob_imageinput($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $is_table)
	{
		// INPUT/BUTTON as IMAGE
		if ($this->mpdf->useActiveForms) {
			$flags = [];
			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_READONLY;
				$flags[] = self::FLAG_NO_EXPORT;
			}
			if (!empty($objattr['onClick'])) {
				$js = $objattr['onClick'];
			} else {
				$js = '';
			}
			$this->SetJSButton($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), (isset($objattr['value']) ? $objattr['value'] : ''), $js, $objattr['ID'], $objattr['title'], $flags, (isset($objattr['Indexed']) ? $objattr['Indexed'] : false));
		} else {
			$this->mpdf->y = $objattr['INNER-Y'];
			$this->writer->write(sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q', $objattr['INNER-WIDTH'] * Mpdf::SCALE, $objattr['INNER-HEIGHT'] * Mpdf::SCALE, $objattr['INNER-X'] * Mpdf::SCALE, ($this->mpdf->h - ($objattr['INNER-Y'] + $objattr['INNER-HEIGHT'] )) * Mpdf::SCALE, $objattr['ID']));
			if (!empty($objattr['BORDER-WIDTH'])) {
				$this->mpdf->PaintImgBorder($objattr, $is_table);
			}
		}
	}

	function print_ob_button($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
	{
		// BUTTON
		if ($this->mpdf->useActiveForms) {
			$flags = [];
			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_READONLY;
				$flags[] = self::FLAG_NO_EXPORT;
				$objattr['color'] = $this->colorConverter->convert(128, $this->mpdf->PDFAXwarnings);
			}

			$this->mpdf->SetTColor(isset($objattr['color']) ? $objattr['color'] : $this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

			if (isset($objattr['subtype'])) {

				if ($objattr['subtype'] === 'RESET') {
					$this->SetFormButtonText($objattr['value']);
					$this->SetFormReset($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $objattr['value'], $objattr['title'], $flags, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), (isset($objattr['noprint']) ? $objattr['noprint'] : false), $this->activeBorder($objattr));
				} elseif ($objattr['subtype'] === 'SUBMIT') {
					$url = $this->formAction;
					$type = $this->formExportType;
					$method = $this->formMethod;
					$this->SetFormButtonText($objattr['value']);
					$this->SetFormSubmit($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $objattr['value'], $url, $objattr['title'], $type, $method, $flags, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), (isset($objattr['noprint']) ? $objattr['noprint'] : false), $this->activeBorder($objattr));
				} elseif ($objattr['subtype'] === 'BUTTON') {
					$this->SetFormButtonText($objattr['value']);
					if (isset($objattr['onClick']) && $objattr['onClick']) {
						$js = $objattr['onClick'];
					} else {
						$js = '';
					}
					$this->SetJSButton($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $objattr['value'], $js, 0, $objattr['title'], $flags, false, (isset($objattr['background-col']) ? $objattr['background-col'] : false), (isset($objattr['border-col']) ? $objattr['border-col'] : false), (isset($objattr['noprint']) ? $objattr['noprint'] : false), $this->activeBorder($objattr));
				}
			}

			$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));

		} else {

			$border = $this->setStaticBorder($objattr, $k);
			$this->mpdf->SetFColor($this->fieldColor($objattr, 'background-col', 190));
			if (isset($objattr['color'])) {
				$this->mpdf->SetTColor($objattr['color']);
			}

			$w -= $this->form_element_spacing['button']['outer']['h'] * 2 / $k;
			$h -= $this->form_element_spacing['button']['outer']['v'] * 2 / $k;

			$this->mpdf->x += $this->form_element_spacing['button']['outer']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['button']['outer']['v'] / $k;
			$this->mpdf->RoundedRect($this->mpdf->x, $this->mpdf->y, $w, $h, 0.5 / $k, $border ? 'DF' : 'F');

			$w -= $this->form_element_spacing['button']['inner']['h'] * 2 / $k;
			$h -= $this->form_element_spacing['button']['inner']['v'] * 2 / $k;

			$this->mpdf->x += $this->form_element_spacing['button']['inner']['h'] / $k;
			$this->mpdf->y += $this->form_element_spacing['button']['inner']['v'] / $k;

			// DIRECTIONALITY
			if (preg_match('/([' . $this->mpdf->pregRTLchars . '])/u', $texto)) {
				$this->mpdf->biDirectional = true;
			}

			$this->fittedCell($w, $h, $texto, '', 'C', 0, 0);
			$this->mpdf->SetFColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			if (isset($objattr['color'])) {
				$this->mpdf->SetTColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
			}
			$this->resetStaticBorder($objattr);
		}
	}

	function print_ob_checkbox($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y)
	{
		// CHECKBOX
		if ($this->mpdf->useActiveForms) {
			$flags = [];
			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_READONLY;
				$flags[] = self::FLAG_NO_EXPORT;
			}
			$checked = false;
			if (!empty($objattr['checked'])) {
				$checked = true;
			}
			$this->SetCheckBox($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $objattr['value'], $objattr['title'], $checked, $flags, (isset($objattr['disabled']) ? $objattr['disabled'] : false));
		} else {
			$iw = $w * 0.7;
			$ih = $h * 0.7;
			$lx = $x + (($w - $iw) / 2);
			$ty = $y + (($h - $ih) / 2);
			$rx = $lx + $iw;
			$by = $ty + $ih;
			$border = $this->setStaticBorder($objattr, $k);
			if (!empty($objattr['disabled'])) {
				$this->mpdf->SetFColor($this->colorConverter->convert(225, $this->mpdf->PDFAXwarnings));
				$this->mpdf->SetDColor($this->colorConverter->convert(127, $this->mpdf->PDFAXwarnings));
			} else {
				$this->mpdf->SetFColor($this->fieldColor($objattr, 'background-col', 250));
				$this->mpdf->SetDColor($this->fieldColor($objattr, 'border-col', 0));
			}
			$this->mpdf->Rect($lx, $ty, $iw, $ih, $border ? 'DF' : 'F');
			if (!empty($objattr['checked'])) {
				//Round join and cap
				$this->mpdf->SetLineCap(1);
				$this->mpdf->Line($lx, $ty, $rx, $by);
				$this->mpdf->Line($lx, $by, $rx, $ty);
				//Set line cap style back to square
				$this->mpdf->SetLineCap();
			}
			$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
			$this->mpdf->SetDColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
		}
	}

	function print_ob_radio($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y)
	{
		// RADIO
		if ($this->mpdf->useActiveForms) {
			$flags = [];
			if (!empty($objattr['disabled'])) {
				$flags[] = self::FLAG_READONLY;
				$flags[] = self::FLAG_NO_EXPORT;
			}
			$checked = false;
			if (!empty($objattr['checked'])) {
				$checked = true;
			}
			$this->SetRadio($w, $h, (isset($objattr['fieldname']) ? $objattr['fieldname'] : ''), $objattr['value'], (isset($objattr['title']) ? $objattr['title'] : ''), $checked, $flags, (isset($objattr['disabled']) ? $objattr['disabled'] : false));
		} else {
			$border = $this->setStaticBorder($objattr, $k);
			$radius = $this->mpdf->FontSize * 0.35;
			$cx = $x + ($w / 2);
			$cy = $y + ($h / 2);
			$color = $this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings);
			if (isset($objattr['color']) && $objattr['color']) {
				$color = $objattr['color'];
			}
			// The ring takes the text colour unless the border has its own
			$ring = isset($objattr['border-col']) ? $objattr['border-col'] : $color;
			if (!empty($objattr['disabled'])) {
				$color = $ring = $this->colorConverter->convert(127, $this->mpdf->PDFAXwarnings);
			}
			$background = isset($objattr['background-col']);
			$this->mpdf->SetFColor($background ? $objattr['background-col'] : $color);
			$this->mpdf->SetDColor($ring);
			if ($border || $background) {
				$this->mpdf->Circle($cx, $cy, $radius, ($border ? 'D' : '') . ($background ? 'F' : ''));
			}
			if (!empty($objattr['checked'])) {
				$this->mpdf->SetFColor($color);
				$this->mpdf->SetDColor($color);
				$this->mpdf->Circle($cx, $cy, $radius * 0.4, 'DF');
			}
			$this->mpdf->SetFColor($this->colorConverter->convert(255, $this->mpdf->PDFAXwarnings));
			$this->mpdf->SetDColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
		}
	}

	/**
	 * Whether the field's CSS border style is none or hidden
	 *
	 * @param mixed[] $objattr
	 *
	 * @return bool
	 */
	private function borderStyleNone(array $objattr)
	{
		return isset($objattr['border-style']) && in_array($objattr['border-style'], ['none', 'hidden'], true);
	}

	/**
	 * The /BS width and style an active field's CSS border sets, in place of the defaults SetFormText(),
	 * SetFormChoice() and SetFormButton() use. A border of style none or hidden gets no width.
	 *
	 * @param mixed[] $objattr
	 *
	 * @return string[] any of 'W', in points, and 'S', the style's /BS name with any dash array
	 */
	private function activeBorder(array $objattr)
	{
		$styles = ['solid' => 'S', 'dashed' => 'D /D [3]', 'dotted' => 'D /D [1]', 'inset' => 'I', 'outset' => 'B'];

		$border = [];
		if (isset($objattr['border-width'])) {
			$border['W'] = sprintf('%.3F', $objattr['border-width'] * Mpdf::SCALE);
		}
		if (isset($objattr['border-style'])) {
			if ($this->borderStyleNone($objattr)) {
				$border['W'] = '0';
			} else {
				// PDF has no double, groove or ridge border
				$border['S'] = isset($styles[$objattr['border-style']]) ? $styles[$objattr['border-style']] : 'S';
			}
		}

		return $border;
	}

	/**
	 * A colour the field's CSS sets, or else a default
	 *
	 * @param mixed[] $objattr
	 * @param string $key 'color', 'background-col' or 'border-col'
	 * @param int $grey the default, from 0 for black to 255 for white
	 *
	 * @return string
	 */
	private function fieldColor(array $objattr, $key, $grey)
	{
		return isset($objattr[$key]) ? $objattr[$key] : $this->colorConverter->convert($grey, $this->mpdf->PDFAXwarnings);
	}

	/**
	 * Fills a text field, text area or select drawn into the page and colours its text as its CSS says, or else black
	 * on near-white. Grey fill for a field that cannot be edited, and grey text for a disabled one, win over the CSS.
	 *
	 * @param mixed[] $objattr
	 * @param bool $greyed whether it cannot be edited
	 */
	private function setStaticColors(array $objattr, $greyed)
	{
		$this->mpdf->SetFColor($greyed ? $this->colorConverter->convert(225, $this->mpdf->PDFAXwarnings) : $this->fieldColor($objattr, 'background-col', 250));
		$this->mpdf->SetTColor(!empty($objattr['disabled']) ? $this->colorConverter->convert(127, $this->mpdf->PDFAXwarnings) : $this->fieldColor($objattr, 'color', 0));
	}

	/**
	 * Sets the line a field drawn into the page is outlined with: its CSS border width and colour, or else 0.2mm in
	 * the colour already set. A field without a border keeps the 0.2mm line for a checkbox's cross.
	 *
	 * @param mixed[] $objattr
	 * @param float $k how much a table shrinks the field
	 *
	 * @return bool whether the field has a border
	 */
	private function setStaticBorder(array $objattr, $k)
	{
		$width = isset($objattr['border-width']) ? $objattr['border-width'] : 0.2;
		$border = $width > 0 && !$this->borderStyleNone($objattr);

		$this->mpdf->SetLineWidth(($border ? $width : 0.2) / $k);
		if (isset($objattr['border-col'])) {
			$this->mpdf->SetDColor($objattr['border-col']);
		}

		return $border;
	}

	/**
	 * Strokes in black again after a field drawn in its CSS border colour
	 *
	 * @param mixed[] $objattr
	 */
	private function resetStaticBorder(array $objattr)
	{
		if (isset($objattr['border-col'])) {
			$this->mpdf->SetDColor($this->colorConverter->convert(0, $this->mpdf->PDFAXwarnings));
		}
	}

	private function getCountItems($form)
	{
		$total = 1;
		if ($form['typ'] === 'Tx') {
			if (isset($this->array_form_text_js[$form['T']])) {
				if (isset($this->array_form_text_js[$form['T']]['F'])) {
					$total++;
				}
				if (isset($this->array_form_text_js[$form['T']]['K'])) {
					$total++;
				}
				if (isset($this->array_form_text_js[$form['T']]['V'])) {
					$total++;
				}
				if (isset($this->array_form_text_js[$form['T']]['C'])) {
					$total++;
				}
			}
		}

		if ($form['typ'] === 'Bt') {
			if (isset($this->array_form_button_js[$form['n']])) {
				$total++;
			}
			if (isset($this->form_button_icon[$form['n']])) {
				$total++;
				if ($this->form_button_icon[$form['n']]['Indexed']) {
					$total++;
				}
			}
			if ($form['subtype'] === 'radio' || $form['subtype'] === 'checkbox') {
				$total += 2;
			}
		}
		if ($form['typ'] === 'Ch') {
			if (isset($this->array_form_choice_js[$form['T']])) {
				$total++;
			}
		}
		if (isset($form['AP'])) {
			$total++;
		}
		return $total;
	}

	/**
	 * Numbers the widgets of page $n from $id, adding each to the page's annotations
	 *
	 * @param int $n
	 * @param int[] $annots the objects the page lists in /Annots
	 * @param int $id the first widget's object number, left at the next free one
	 */
	function addFormIds($n, array &$annots, &$id)
	{
		foreach ($this->forms as $form) {
			if ($form['page'] == $n) {
				$annots[] = $id;
				$id += $this->getCountItems($form);
			}
		}
	}

	/**
	 * Gathers the push buttons that share a name and numbers the field each group is written as, from $id. A button
	 * with a name of its own stays a single field and widget.
	 *
	 * @param int $id the first group's object number, left at the next free one
	 */
	function addButtonGroupIds(&$id)
	{
		$names = [];
		foreach ($this->forms as $form) {
			if ($this->isPushButton($form)) {
				$names[$form['T']][] = $form['n'];
			}
		}

		$this->buttonGroups = [];
		foreach ($names as $name => $kids) {
			if (count($kids) > 1) {
				$this->buttonGroups[$name] = ['obj_id' => $id++, 'kids' => $kids];
			}
		}
	}

	/**
	 * Whether a field is a submit, reset, script or image button, which is pressed rather than switched on and off
	 *
	 * @param mixed[] $form
	 *
	 * @return bool
	 */
	private function isPushButton($form)
	{
		return $form['typ'] === 'Bt' && $form['subtype'] !== 'radio' && $form['subtype'] !== 'checkbox';
	}

	/**
	 * Writes each group of same-named push buttons as a field holding the name and the push-button flag, with the
	 * widgets as its kids. It is not an annotation, so no page lists it.
	 */
	function putButtonGroups()
	{
		foreach ($this->buttonGroups as $name => $group) {
			$this->writer->object();
			$this->pdf_acro_array .= $this->mpdf->n . ' 0 R ';

			$kids = '';
			foreach ($group['kids'] as $kid) {
				$kids .= $this->forms[$kid]['obj'] . ' 0 R ';
			}

			$this->writer->write('<< /FT /Btn /Ff ' . $this->_setflag([17]) . ' /T ' . $this->writer->string($name) . ' /Kids [ ' . $kids . '] >>');
			$this->writer->write('endobj');
		}
	}

	// In _putannots
	function _putFormItems($n, $hPt)
	{
		foreach ($this->forms as $val) {
			if ($val['page'] == $n) {
				if ($val['typ'] === 'Tx') {
					$this->_putform_tx($val, $hPt);
				}
				if ($val['typ'] === 'Ch') {
					$this->_putform_ch($val, $hPt);
				}
				if ($val['typ'] === 'Bt') {
					$this->_putform_bt($val, $hPt);
				}
			}
		}
	}

	// In _putannots
	function _putRadioItems($n)
	{
		// Output Radio Groups
		$key = 1;
		foreach ($this->form_radio_groups as $name => $frg) {
			$this->writer->object();
			$this->pdf_acro_array .= $this->mpdf->n . ' 0 R ';
			$this->writer->write('<<');
			$this->writer->write('/Type /Annot ');
			$this->writer->write('/Subtype /Widget');
			$this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 3000 + $key++)));
			$this->writer->write('/M ' . $this->writer->dateString());
			$this->writer->write('/Rect [0 0 0 0] ');
			$this->writer->write('/FT /Btn ');
			if (!empty($frg['disabled'])) {
				$flags = [self::FLAG_READONLY, self::FLAG_NO_EXPORT, self::FLAG_RADIO, self::FLAG_NOTOGGLEOFF];
			} else {
				$flags = [self::FLAG_RADIO, self::FLAG_NOTOGGLEOFF];
			}
			$this->writer->write('/Ff ' . $this->_setflag($flags));
			$kstr = '';
			// $optstr = '';
			foreach ($frg['kids'] as $kid) {
				$kstr .= $this->forms[$kid['n']]['obj'] . ' 0 R ';
				//		$optstr .= ' '.$this->writer->string($kid['OPT']).' ';
			}
			$this->writer->write('/Kids [ ' . $kstr . ' ] '); // 11 0 R 12 0 R etc.
			//	$this->writer->write('/Opt [ '.$optstr.' ] ');
			//V entry holds index corresponding to the appearance state of
			//whichever child field is currently in the on state = or Off
			if (isset($frg['on'])) {
				$state = $frg['on'];
			} else {
				$state = 'Off';
			}
			$this->writer->write('/V /' . $state . ' ');
			$this->writer->write('/DV /' . $state . ' ');
			$this->writer->write('/T ' . $this->writer->string($name) . ' ');
			$this->writer->write('>>');
			$this->writer->write('endobj');
		}
	}

	function _putFormsCatalog()
	{
		if (isset($this->pdf_acro_array)) {
			$this->writer->write('/AcroForm << /DA ' . $this->writer->string('/F1 0 Tf 0 g '));
			$this->writer->write('/Q 0');
			$this->writer->write('/Fields [' . $this->pdf_acro_array . ']');
			$f = '';
			foreach ($this->form_fonts as $fn) {
				if (is_array($this->mpdf->fonts[$fn]['n'])) {
					throw new \Mpdf\MpdfException('Cannot use fonts with SMP or SIP characters for interactive Form elements');
				}
				$f .= '/F' . $this->mpdf->fonts[$fn]['i'] . ' ' . $this->mpdf->fonts[$fn]['n'] . ' 0 R ';
			}
			$this->writer->write('/DR << /Font << ' . $f . ' >> >>');
			// CO Calculation Order
			if ($this->pdf_array_co) {
				$this->writer->write('/CO [' . $this->pdf_array_co . ']');
			}
			$this->writer->write('>>');
		}
	}

	/**
	 * Whether a form field may run JavaScript, submit or reset the form. PDF/A forbids all three, so there the action
	 * is left out, with a warning unless PDFAauto is on.
	 *
	 * @return bool
	 */
	private function actionAllowed()
	{
		if (!$this->mpdf->PDFA) {
			return true;
		}

		$this->mpdf->pdfaxWarning('Form fields cannot run JavaScript, submit or reset the form in PDFA files (Action removed)');

		return false;
	}

	/**
	 * Gives a button its script, by the button's number rather than its name, which other buttons may share
	 *
	 * @param int $n the button's number, its key in $forms
	 * @param string $js
	 */
	function SetFormButtonJS($n, $js)
	{
		if (!$this->actionAllowed()) {
			return;
		}
		$this->array_form_button_js[$n] = [
			'js' => str_replace("\t", ' ', trim($js))
		];
	}

	function SetFormChoiceJS($name, $js)
	{
		if (!$this->actionAllowed()) {
			return;
		}
		$js = str_replace("\t", ' ', trim($js));
		if (isset($name) && isset($js)) {
			$this->array_form_choice_js[$this->writer->escape($name)] = [
				'js' => $js
			];
		}
	}

	function SetFormTextJS($name, $js)
	{
		if (!$this->actionAllowed()) {
			return;
		}
		for ($i = 0; $i < count($js); $i++) {
			$j = str_replace("\t", ' ', trim($js[$i][1]));
			$format = $js[$i][0];
			if ($name) {
				$this->array_form_text_js[$this->writer->escape($name)][$format] = ['js' => $j];
			}
		}
	}

	function Win1252ToPDFDocEncoding($txt)
	{
		$Win1252ToPDFDocEncoding = [
			chr(0200) => chr(0240), chr(0214) => chr(0226), chr(0212) => chr(0227), chr(0237) => chr(0230),
			chr(0225) => chr(0200), chr(0210) => chr(0032), chr(0206) => chr(0201), chr(0207) => chr(0202),
			chr(0205) => chr(0203), chr(0227) => chr(0204), chr(0226) => chr(0205), chr(0203) => chr(0206),
			chr(0213) => chr(0210), chr(0233) => chr(0211), chr(0211) => chr(0213), chr(0204) => chr(0214),
			chr(0223) => chr(0215), chr(0224) => chr(0216), chr(0221) => chr(0217), chr(0222) => chr(0220),
			chr(0202) => chr(0221), chr(0232) => chr(0235), chr(0230) => chr(0037), chr(0231) => chr(0222),
			chr(0216) => chr(0231), chr(0240) => chr(0040)
		]; // mPDF 5.3.46
		return strtr($txt, $Win1252ToPDFDocEncoding);
	}

	function SetFormText($w, $h, $name, $value = '', $default = '', $title = '', $flags = [], $align = 'L', $hidden = false, $maxlen = -1, $js = '', $background_col = false, $border_col = false, $border = [])
	{
		$this->formCount++;
		if ($align === 'C') {
			$align = '1';
		} elseif ($align === 'R') {
			$align = '2';
		} else {
			$align = '0';
		}
		if ($maxlen < 1) {
			$maxlen = false;
		}
		if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
			throw new \Mpdf\MpdfException('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
		}
		// A hidden input passes its flags as 0
		$text = in_array(self::FLAG_PASSWORD, (array) $flags, true) ? str_repeat('*', mb_strlen($value, $this->mpdf->mb_enc)) : $value;
		$border += ['W' => $this->form_border_width, 'S' => $this->form_border_style];
		$appearance = $this->appearanceText($w, $h, $border['W'], preg_split('/\r\n|\r|\n/', $text), $align, in_array(self::FLAG_TEXTAREA, (array) $flags, true) ? 'wrap' : 'line', [], !$hidden);
		if ($this->mpdf->onlyCoreFonts) {
			$value = $this->Win1252ToPDFDocEncoding($value);
			$default = $this->Win1252ToPDFDocEncoding($default);
			$title = $this->Win1252ToPDFDocEncoding($title);
		} else {
			if (isset($this->mpdf->CurrentFont['subset'])) {
				$this->mpdf->UTF8StringToArray($value); // Add characters to font subset
				$this->mpdf->UTF8StringToArray($default); // Add characters to font subset
				$this->mpdf->UTF8StringToArray($title); // Add characters to font subset
			}
			if ($value) {
				$value = $this->writer->utf8ToUtf16BigEndian($value);
			}
			if ($default) {
				$default = $this->writer->utf8ToUtf16BigEndian($default);
			}
			$title = $this->writer->utf8ToUtf16BigEndian($title);
		}

		$f = [
			'n' => $this->formCount,
			'typ' => 'Tx',
			'page' => $this->mpdf->page,
			'x' => $this->mpdf->x,
			'y' => $this->mpdf->y,
			'w' => $w,
			'h' => $h,
			'T' => $name,
			'FF' => $flags,
			'V' => $value,
			'DV' => $default,
			'TU' => $title,
			'hidden' => $hidden,
			'Q' => $align,
			'maxlen' => $maxlen,
			'BS_W' => $border['W'],
			'BS_S' => $border['S'],
			'BC_C' => $this->activeColor($border_col, $this->form_border_color),
			'BG_C' => $this->activeColor($background_col, $this->form_background_color),
			'style' => [
				'font' => $this->mpdf->FontFamily,
				// A value drawn smaller to fit leaves the viewer to size it too, so it does not grow back once edited
				'fontsize' => $appearance['size'] < $this->mpdf->FontSizePt ? 0 : $this->mpdf->FontSizePt,
				'fontcolor' => $this->mpdf->TextColor,
			],
			'AP' => $appearance,
		];

		if (is_array($js) && count($js) > 0) {
			$this->SetFormTextJS($name, $js);
		} // mPDF 5.3.25
		if ($this->mpdf->writingHTMLheader || $this->mpdf->writingHTMLfooter) {
			$this->mpdf->HTMLheaderPageForms[] = $f;
		} else {
			if ($this->mpdf->ColActive) {
				$this->mpdf->columnbuffer[] = [
					's' => 'ACROFORM',
					'col' => $this->mpdf->CurrCol,
					'x' => $this->mpdf->x,
					'y' => $this->mpdf->y,
					'h' => $h
				];
				$this->mpdf->columnForms[$this->mpdf->CurrCol][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->formCount;
			}
			$this->forms[$this->formCount] = $f;
		}
		if (!in_array($this->mpdf->FontFamily, $this->form_fonts)) {
			$this->form_fonts[] = $this->mpdf->FontFamily;
			$this->mpdf->fonts[$this->mpdf->FontFamily]['used'] = true;
		}
		if (!$hidden) {
			$this->mpdf->x += $w;
		}
	}

	function SetFormChoice($w, $h, $name, $flags, $array, $align = 'L', $js = '', $background_col = false, $border_col = false, $border = [], $rows = 1)
	{
		$this->formCount++;
		if ($this->mpdf->blk[$this->mpdf->blklvl]['direction'] === 'rtl') {
			$align = '2';
		} else {
			$align = '0';
		}
		if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
			throw new \Mpdf\MpdfException('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
		}
		$border += ['W' => $this->form_border_width, 'S' => $this->form_border_style];
		if (in_array(self::FLAG_COMBOBOX, $flags, true)) {
			$appearance = $this->appearanceText($w, $h, $border['W'], [$array['SEL'] ? $array['OPT'][$array['SEL'][0]] : ''], $align, 'line');
		} else {
			$appearance = $this->appearanceText($w, $h, $border['W'], $array['OPT'], $align, 'list', $array['SEL'], true, $rows);
		}
		if ($this->mpdf->onlyCoreFonts) {
			for ($i = 0; $i < count($array['VAL']); $i++) {
				$array['VAL'][$i] = $this->Win1252ToPDFDocEncoding($array['VAL'][$i]);
				$array['OPT'][$i] = $this->Win1252ToPDFDocEncoding($array['OPT'][$i]);
			}
		} else {
			for ($i = 0; $i < count($array['VAL']); $i++) {
				if (isset($this->mpdf->CurrentFont['subset'])) {
					$this->mpdf->UTF8StringToArray($array['VAL'][$i]); // Add characters to font subset
					$this->mpdf->UTF8StringToArray($array['OPT'][$i]); // Add characters to font subset
				}
				if ($array['VAL'][$i]) {
					$array['VAL'][$i] = $this->writer->utf8ToUtf16BigEndian($array['VAL'][$i]);
				}
				if ($array['OPT'][$i]) {
					$array['OPT'][$i] = $this->writer->utf8ToUtf16BigEndian($array['OPT'][$i]);
				}
			}
		}
		$f = ['n' => $this->formCount,
			'typ' => 'Ch',
			'page' => $this->mpdf->page,
			'x' => $this->mpdf->x,
			'y' => $this->mpdf->y,
			'w' => $w,
			'h' => $h,
			'T' => $name,
			'OPT' => $array,
			'FF' => $flags,
			'Q' => $align,
			'BS_W' => $border['W'],
			'BS_S' => $border['S'],
			'BC_C' => $this->activeColor($border_col, $this->form_border_color),
			'BG_C' => $this->activeColor($background_col, $this->form_background_color),
			'style' => [
				'font' => $this->mpdf->FontFamily,
				// As with a text field, a choice drawn smaller to fit leaves the viewer to size the next one too
				'fontsize' => $appearance['size'] < $this->mpdf->FontSizePt ? 0 : $this->mpdf->FontSizePt,
				'fontcolor' => $this->mpdf->TextColor,
			],
			'AP' => $appearance,
		];
		if ($js) {
			$this->SetFormChoiceJS($name, $js);
		}
		if ($this->mpdf->writingHTMLheader || $this->mpdf->writingHTMLfooter) {
			$this->mpdf->HTMLheaderPageForms[] = $f;
		} else {
			if ($this->mpdf->ColActive) {
				$this->mpdf->columnbuffer[] = ['s' => 'ACROFORM', 'col' => $this->mpdf->CurrCol, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y,
					'h' => $h];
				$this->mpdf->columnForms[$this->mpdf->CurrCol][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->formCount;
			}
			$this->forms[$this->formCount] = $f;
		}
		if (!in_array($this->mpdf->FontFamily, $this->form_fonts)) {
			$this->form_fonts[] = $this->mpdf->FontFamily;
			$this->mpdf->fonts[$this->mpdf->FontFamily]['used'] = true;
		}
		$this->mpdf->x += $w;
	}

	// CHECKBOX
	function SetCheckBox($w, $h, $name, $value, $title = '', $checked = false, $flags = [], $disabled = false)
	{
		$this->SetFormButton($w, $h, $name, $value, 'checkbox', $title, $flags, $checked, $disabled);
		$this->mpdf->x += $w;
	}

	// RADIO
	function SetRadio($w, $h, $name, $value, $title = '', $checked = false, $flags = [], $disabled = false)
	{
		$this->SetFormButton($w, $h, $name, $value, 'radio', $title, $flags, $checked, $disabled);
		$this->mpdf->x += $w;
	}

	function SetFormReset($w, $h, $name, $value = 'Reset', $title = '', $flags = [], $background_col = false, $border_col = false, $noprint = false, $border = [])
	{
		if (!$name) {
			$name = $this->unnamedButtonName('Reset');
		}
		$this->SetFormButton($w, $h, $name, $value, 'reset', $title, $flags, false, false, $background_col, $border_col, $noprint, $border);
		$this->mpdf->x += $w;
	}

	function SetJSButton($w, $h, $name, $value, $js, $image_id = 0, $title = '', $flags = [], $indexed = false, $background_col = false, $border_col = false, $noprint = false, $border = [])
	{
		if (!$name) {
			$name = $this->unnamedButtonName('Button');
		}
		// pos => 1 = no caption, icon only; 0 = caption only. It is kept under the number SetFormButton() is about to
		// give the field, which then knows the button shows an icon.
		if ($image_id) {
			$this->form_button_icon[$this->formCount + 1] = [
				'pos' => 1,
				'image_id' => $image_id,
				'Indexed' => $indexed,
			];
		}
		$this->SetFormButton($w, $h, $name, $value, 'js_button', $title, $flags, false, false, $background_col, $border_col, $noprint, $border);
		if ($js) {
			$this->SetFormButtonJS($this->formCount, $js);
		}
		$this->mpdf->x += $w;
	}

	function SetFormSubmit($w, $h, $name, $value = 'Submit', $url = '', $title = '', $typ = 'html', $method = 'POST', $flags = [], $background_col = false, $border_col = false, $noprint = false, $border = [])
	{
		if (!$name) {
			$name = $this->unnamedButtonName('Submit');
		}

		$this->SetFormButton($w, $h, $name, $value, 'submit', $title, $flags, false, false, $background_col, $border_col, $noprint, $border);
		// The button is not on record while a block is only being measured, or inside a header or footer
		if (isset($this->forms[$this->formCount])) {
			$this->forms[$this->formCount]['URL'] = $url;
			$this->forms[$this->formCount]['method'] = $method;
			$this->forms[$this->formCount]['exporttype'] = $typ;
		}
		$this->mpdf->x += $w;
	}

	/**
	 * A name for a button that has none. Buttons sharing a name are one field to a viewer and share the entry
	 * that holds an action or icon, so it carries the number SetFormButton() is about to give the field.
	 *
	 * @param string $kind
	 *
	 * @return string
	 */
	private function unnamedButtonName($kind)
	{
		return $kind . '_' . ($this->formCount + 1);
	}

	function SetFormButtonText($ca, $rc = '', $ac = '')
	{
		if ($this->mpdf->onlyCoreFonts) {
			$ca = $this->Win1252ToPDFDocEncoding($ca);
			if ($rc) {
				$rc = $this->Win1252ToPDFDocEncoding($rc);
			}
			if ($ac) {
				$ac = $this->Win1252ToPDFDocEncoding($ac);
			}
		} else {
			if (isset($this->mpdf->CurrentFont['subset'])) {
				$this->mpdf->UTF8StringToArray($ca); // Add characters to font subset
			}
			$ca = $this->writer->utf8ToUtf16BigEndian($ca);
			if ($rc) {
				if (isset($this->mpdf->CurrentFont['subset'])) {
					$this->mpdf->UTF8StringToArray($rc);
				}
				$rc = $this->writer->utf8ToUtf16BigEndian($rc);
			}
			if ($ac) {
				if (isset($this->mpdf->CurrentFont['subset'])) {
					$this->mpdf->UTF8StringToArray($ac);
				}
				$ac = $this->writer->utf8ToUtf16BigEndian($ac);
			}
		}
		$this->form_button_text = $ca;
		$this->form_button_text_over = $rc ?: $ca;
		$this->form_button_text_click = $ac ?: $ca;
	}

	function SetFormButton($bb, $hh, $name, $value, $type, $title = '', $flags = [], $checked = false, $disabled = false, $background_col = false, $border_col = false, $noprint = false, $border = [])
	{
		$this->formCount++;
		if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
			throw new \Mpdf\MpdfException('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
		}
		$border += ['W' => $this->form_button_border_width, 'S' => $this->form_button_border_style];
		$appearance = null;
		if ($type !== 'radio' && $type !== 'checkbox') {
			// A button showing an icon draws no caption to fit
			$appearance = $this->appearanceText($bb, $hh, $border['W'], [$value === '' ? $name : $value], '1', 'line', [], !isset($this->form_button_icon[$this->formCount]));
		}
		if (!$this->mpdf->onlyCoreFonts) {
			if (isset($this->mpdf->CurrentFont['subset'])) {
				$this->mpdf->UTF8StringToArray($title); // Add characters to font subset
				$this->mpdf->UTF8StringToArray($value); // Add characters to font subset
			}
			$title = $this->writer->utf8ToUtf16BigEndian($title);
			if ($type === 'checkbox') {
				$uvalue = $this->writer->utf8ToUtf16BigEndian($value);
			} elseif ($type === 'radio') {
				$uvalue = $this->writer->utf8ToUtf16BigEndian($value);
				$value = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
			} else {
				$value = $this->writer->utf8ToUtf16BigEndian($value);
				$uvalue = $value;
			}
		} else {
			$title = $this->Win1252ToPDFDocEncoding($title);
			$value = $this->Win1252ToPDFDocEncoding($value);     //// ??? not needed
			$uvalue = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
			$uvalue = $this->writer->utf8ToUtf16BigEndian($uvalue);
		}
		if ($type === 'radio' || $type === 'checkbox') {
			if (!preg_match('/^[a-zA-Z0-9_:\-\.]+$/', $value)) {
				throw new \Mpdf\MpdfException("Field '" . $name . "' must have a value, which can only contain letters, numbers, colon(:), underscore(_), hyphen(-) or period(.)");
			}
		}
		if ($type === 'radio') {
			if (!isset($this->form_radio_groups[$name])) {
				$this->form_radio_groups[$name] = [
					'page' => $this->mpdf->page,
					'kids' => [],
				];
			}
			$this->form_radio_groups[$name]['kids'][] = [
				'n' => $this->formCount, 'V' => $value, 'OPT' => $uvalue, 'disabled' => $disabled
			];
			if ($checked) {
				$this->form_radio_groups[$name]['on'] = $value;
			}
			// Disable the whole radio group if one is disabled, because of inconsistency in PDF readers
			if ($disabled) {
				$this->form_radio_groups[$name]['disabled'] = true;
			}
		}
		if ($type === 'checkbox') {
			$this->form_checkboxes = true;
		}
		if ($checked) {
			$activ = 1;
		} else {
			$activ = 0;
		}
		$f = ['n' => $this->formCount,
			'typ' => 'Bt',
			'page' => $this->mpdf->page,
			'subtype' => $type,
			'x' => $this->mpdf->x,
			'y' => $this->mpdf->y,
			'w' => $bb,
			'h' => $hh,
			'T' => $name,
			'V' => $value,
			'OPT' => $uvalue,
			'TU' => $title,
			'FF' => $flags,
			'CA' => $this->form_button_text,
			'RC' => $this->form_button_text_over,
			'AC' => $this->form_button_text_click,
			'BS_W' => $border['W'],
			'BS_S' => $border['S'],
			'BC_C' => $this->activeColor($border_col, $this->form_button_border_color),
			'BG_C' => $this->activeColor($background_col, $this->form_button_background_color),
			'activ' => $activ,
			'disabled' => $disabled,
			'noprint' => $noprint,
			'style' => [
				'font' => $this->mpdf->FontFamily,
				// A push button's caption cannot be edited, so a viewer redrawing it keeps to the size it was fitted at
				'fontsize' => $appearance ? $appearance['size'] : $this->mpdf->FontSizePt,
				'fontcolor' => $this->mpdf->TextColor,
			],
			'AP' => $appearance,
		];
		if ($this->mpdf->writingHTMLheader || $this->mpdf->writingHTMLfooter) {
			$this->mpdf->HTMLheaderPageForms[] = $f;
		} else {
			if ($this->mpdf->ColActive) {
				$this->mpdf->columnbuffer[] = ['s' => 'ACROFORM', 'col' => $this->mpdf->CurrCol, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y,
					'h' => $hh];
				$this->mpdf->columnForms[$this->mpdf->CurrCol][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->formCount;
			}
			$this->forms[$this->formCount] = $f;
		}
		if (!in_array($this->mpdf->FontFamily, $this->form_fonts)) {
			$this->form_fonts[] = $this->mpdf->FontFamily;
			$this->mpdf->fonts[$this->mpdf->FontFamily]['used'] = true;
		}

		$this->form_button_text = null;
		$this->form_button_text_over = null;
		$this->form_button_text_click = null;
	}

	function SetFormBorderWidth($string)
	{
		switch ($string) {
			case 'S':
				$this->form_border_width = '1';
				break;
			case 'M':
				$this->form_border_width = '2';
				break;
			case 'B':
				$this->form_border_width = '3';
				break;
			case '0':
				$this->form_border_width = '0';
				break;
			default:
				$this->form_border_width = '0';
				break;
		}
	}

	function SetFormBorderStyle($string)
	{
		switch ($string) {
			case 'S':
				$this->form_border_style = 'S';
				break;
			case 'D':
				$this->form_border_style = 'D /D [3]';
				break;
			case 'B':
				$this->form_border_style = 'B';
				break;
			case 'I':
				$this->form_border_style = 'I';
				break;
			case 'U':
				$this->form_border_style = 'U';
				break;
			default:
				$this->form_border_style = 'B';
				break;
		}
	}

	function SetFormBorderColor($r, $g = -1, $b = -1)
	{
		$this->form_border_color = $this->getColor($r, $g, $b);
	}

	function SetFormBackgroundColor($r, $g = -1, $b = -1)
	{
		$this->form_background_color = $this->getColor($r, $g, $b);
	}

	private function getColor($r, $g = -1, $b = -1)
	{
		if (($r == 0 && $g == 0 && $b == 0) || $g == -1) {
			return sprintf('%.3F', $r / 255);
		}
		return sprintf('%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255);
	}

	function SetFormD($W, $S, $BC, $BG)
	{
		$this->SetFormBorderWidth($W);
		$this->SetFormBorderStyle($S);
		$this->SetFormBorderColor($BC);
		$this->SetFormBackgroundColor($BG);
	}

	function _setflag($array)
	{
		$flag = 0;
		foreach ($array as $val) {
			$flag += 1 << ($val - 1);
		}
		return $flag;
	}

	/**
	 * Draws a field's text into the page with Cell(), shaped, and fitted inside its padding by fitLine()
	 *
	 * @param float $w
	 * @param float $h
	 * @param string $text
	 * @param int|string $border
	 * @param string $align
	 * @param int $fill
	 * @param float $padding on the left and on the right
	 * @param mixed[]|false|null $OTLdata the text's OTL data if it has been shaped already, as a select's option has
	 */
	private function fittedCell($w, $h, $text, $border, $align, $fill, $padding, $OTLdata = null)
	{
		// Each cut is shaped on its own, as letters can join differently at the end
		$cuts = [];
		$cut = function ($length) use ($text, $OTLdata, &$cuts) {
			if (!isset($cuts[$length])) {
				$data = $OTLdata ? OtlData::slice($OTLdata, 0, $length) : $OTLdata;
				$cuts[$length] = $this->shapeText(mb_substr($text, 0, $length, $this->mpdf->mb_enc), $data);
			}

			return $cuts[$length];
		};

		$size = $this->mpdf->FontSizePt;
		list($fit, $length) = $this->fitLine($size, $w - 2 * $padding, mb_strlen($text, $this->mpdf->mb_enc), function ($length) use ($cut) {
			list($shaped, $OTLdata) = $cut($length);

			return $this->mpdf->GetStringWidth($shaped, true, $OTLdata);
		});
		list($shaped, $OTLdata) = $cut($length);

		$this->mpdf->SetFontSize($fit);
		$this->mpdf->Cell($w, $h, $shaped, $border, 0, $align, $fill, '', 0, $padding, $padding, 'M', 0, false, $OTLdata);
		$this->mpdf->SetFontSize($size);
	}

	/**
	 * Runs text through OTL, unless it has been already, and puts it in visual order, in the current font, as page text
	 * is drawn. Core fonts are neither shaped nor reordered.
	 *
	 * @param string $text
	 * @param mixed[]|false|null $OTLdata its OTL data if it has been shaped already
	 *
	 * @return mixed[] the text and its OTL data
	 */
	private function shapeText($text, $OTLdata = null)
	{
		if (preg_match('/[' . $this->mpdf->pregRTLchars . ']/u', $text)) {
			$this->mpdf->biDirectional = true;
		}

		if ($OTLdata === null && !empty($this->mpdf->CurrentFont['useOTL'])) {
			$text = $this->otl->applyOTL($text, $this->mpdf->CurrentFont['useOTL']);
			$OTLdata = $this->otl->OTLdata;
		}

		$this->mpdf->magic_reverse_dir($text, $this->mpdf->directionality, $OTLdata);

		return [$text, $OTLdata];
	}

	/**
	 * Fits a line of a field's text to the room it has. Text too wide is drawn smaller in proportion, rounded down to the
	 * precision Tf is written at, but not below half the field's size or 6pt, whichever is larger. Past that it loses
	 * the characters at its end that still do not fit.
	 *
	 * @param float $size the field's font size, in points
	 * @param float $room
	 * @param int $length the text's length in characters
	 * @param callable $measure the width, in $room's units, of the text's first so many characters at $size
	 *
	 * @return mixed[] the font size, and how many of the characters are drawn
	 */
	private function fitLine($size, $room, $length, $measure)
	{
		$width = $measure($length);
		// A button or select is as wide as its text, and taking its padding back off that can leave a rounding error
		if ($width <= $room + 1e-6) {
			return [$size, $length];
		}

		$fit = max(min($size, max($size / 2, 6)), floor($size * $room / $width * 1000) / 1000);
		// Widths scale with the font size, so the text is measured at the field's size against room scaled to match
		$room *= $size / $fit;

		if ($width > $room) {
			// The longest start of the text that fits, found by halving so that long text is measured O(log n) times
			$longest = $length;
			$length = 0;
			while ($longest - $length > 1) {
				$middle = (int) (($length + $longest) / 2);
				if ($measure($middle) > $room) {
					$longest = $middle;
				} else {
					$length = $middle;
				}
			}
		}

		return [$fit, $length];
	}

	/**
	 * Lays out the text a widget's appearance shows, in the current font, which can only be measured while the widget
	 * is being placed. Each line is shaped and put in visual order as page text is, and written by Mpdf::Text().
	 *
	 * @param float $w the widget's width
	 * @param float $h its height
	 * @param float $border its border width, in points
	 * @param string[] $lines the text, a line each, in the document's encoding
	 * @param string $align '0', '1' or '2', left, centred or right as /Q has it
	 * @param string $flow 'line' centres the first line on the height, 'wrap' runs the lines down from the top wrapped
	 *  to the width, and 'list' shows them unwrapped a row each, as a list box shows its options
	 * @param int[] $selected the lines a list box highlights
	 * @param bool $fit whether a 'line' is fitted to the widget as fitLine() does. Not for a hidden field, which has no
	 *  room, or a button that shows an icon instead
	 * @param int $rows the rows a list box shows
	 *
	 * @return mixed[] the font size, the operators that draw each line, and the highlights, in points from the bottom
	 *  left
	 */
	private function appearanceText($w, $h, $border, array $lines, $align, $flow, array $selected = [], $fit = true, $rows = 1)
	{
		$width = $w * Mpdf::SCALE;
		$height = $h * Mpdf::SCALE;
		$padding = $border + 2;
		$room = $width - 2 * $padding;
		$roomHeight = $height - 2 * $padding;

		$desc = $this->mpdf->CurrentFont['desc'];
		$ascent = (isset($desc['Ascent']) ? $desc['Ascent'] : 800) / 1000;
		$descent = (isset($desc['Descent']) ? $desc['Descent'] : -200) / 1000;

		// The text is measured and shaped at the size it is drawn at. The field keeps its own, which is 0 for auto.
		$this->emWidths = [];
		$fieldSize = $this->mpdf->FontSizePt;
		$size = $fieldSize;
		$this->mpdf->SetFontSize($size ?: 12, false);
		if (!$size) {
			$rows = $roomHeight / ($ascent - $descent);
			if ($flow === 'line') {
				$size = max(1, $rows);
			} elseif ($flow === 'wrap') {
				$size = $this->wrappedFontSize($lines, $room, $rows);
			} else {
				$size = 12;
			}
		} elseif ($fit && $flow === 'line') {
			$line = $lines[0];
			list($size, $length) = $this->fitLine($size, $room, mb_strlen($line, $this->mpdf->mb_enc), function ($length) use ($line, $size) {
				return $this->emWidth(mb_substr($line, 0, $length, $this->mpdf->mb_enc)) * $size;
			});
			$lines = [mb_substr($line, 0, $length, $this->mpdf->mb_enc)];
		}

		// A list box's rows share the height inside the border. Tag\Select allowed one font size a row, less than the
		// font's line height, so the font shrinks to keep each row whole
		$rowHeight = ($height - 2 * $border) / $rows;
		if ($flow === 'list') {
			$size = min($size, $rowHeight / ($ascent - $descent));
		}
		$this->mpdf->SetFontSize($size, false);
		$leading = ($ascent - $descent) * $size;

		$top = 0;
		if ($flow === 'wrap') {
			$lines = $this->wrap($lines, $room / $size);
		} elseif ($flow !== 'list') {
			$lines = array_slice($lines, 0, 1);
		} elseif ($selected && min($selected) >= $rows) {
			// A list box too short to show its first selected option starts at it
			$top = min($selected);
		}

		$layout = ['size' => $size, 'lines' => [], 'highlights' => []];
		foreach (array_slice($lines, $top, $flow === 'list' ? $rows : null, true) as $i => $line) {
			if ($flow === 'list') {
				$rowBottom = $height - $border - ($i - $top + 1) * $rowHeight;
				$y = $rowBottom + ($rowHeight - $leading) / 2 - $descent * $size;
				if (in_array($i, $selected, true)) {
					$layout['highlights'][] = [$border, $rowBottom, $width - 2 * $border, $rowHeight];
				}
			} elseif ($flow === 'wrap') {
				$y = $height - $padding - $ascent * $size - $i * $leading;
				if ($y + $ascent * $size < 0) {
					break;
				}
			} else {
				$y = ($height - $leading) / 2 - $descent * $size;
			}

			list($text, $OTLdata) = $this->shapeText($line);
			$lineWidth = $this->mpdf->GetStringWidth($text, true, $OTLdata) * Mpdf::SCALE;
			if ($align === '1') {
				$x = ($width - $lineWidth) / 2;
			} elseif ($align === '2') {
				$x = $width - $padding - $lineWidth;
			} else {
				$x = $padding;
			}

			$layout['lines'][] = trim($this->mpdf->Text($x, $y, $text, $OTLdata, 0, '', 'SVG', true));
		}

		$this->mpdf->SetFontSize($fieldSize, false);

		return $layout;
	}

	/**
	 * The font size a multi-line field with an auto size is drawn at, as a viewer sizes one: the largest, from 12pt
	 * down in half points to 4pt, at which the wrapped text fits the height
	 *
	 * @param string[] $lines
	 * @param float $room the width the text has, in points
	 * @param float $rows how many lines of 1pt text fit the height
	 *
	 * @return float
	 */
	private function wrappedFontSize(array $lines, $room, $rows)
	{
		for ($size = 12; $size > 4; $size -= 0.5) {
			if (count($this->wrap($lines, $room / $size)) <= $rows / $size) {
				break;
			}
		}

		return $size;
	}

	/**
	 * Breaks lines of text at spaces so that each fits a width
	 *
	 * @param string[] $lines
	 * @param float $width in ems of the current font
	 *
	 * @return string[]
	 */
	private function wrap(array $lines, $width)
	{
		$space = $this->emWidth(' ');
		$wrapped = [];
		foreach ($lines as $line) {
			$current = null;
			$currentWidth = 0;
			foreach (explode(' ', $line) as $word) {
				$wordWidth = $this->emWidth($word);
				if ($current === null) {
					list($current, $currentWidth) = [$word, $wordWidth];
				} elseif ($currentWidth + $space + $wordWidth > $width) {
					$wrapped[] = $current;
					list($current, $currentWidth) = [$word, $wordWidth];
				} else {
					$current .= ' ' . $word;
					$currentWidth += $space + $wordWidth;
				}
			}
			$wrapped[] = $current;
		}

		return $wrapped;
	}

	/**
	 * The width of some text in ems of the current font, shaped as it is drawn, adding its characters to the font's
	 * subset
	 *
	 * @param string $text in the document's encoding: Windows-1252 bytes in a core font, UTF-8 otherwise
	 *
	 * @return float
	 */
	private function emWidth($text)
	{
		if (!isset($this->emWidths[$text])) {
			list($shaped, $OTLdata) = $this->shapeText($text);
			$this->emWidths[$text] = $this->mpdf->GetStringWidth($shaped, true, $OTLdata) / $this->mpdf->FontSize;
		}

		return $this->emWidths[$text];
	}

	/**
	 * Points a widget with an appearanceText() layout at the appearance writeAppearance() writes, the last of the
	 * widget's objects. A button names its appearance by state, as a checkbox does, even with only the one.
	 *
	 * @param mixed[] $form
	 */
	private function writeAppearanceReference($form)
	{
		if (!isset($form['AP'])) {
			return;
		}

		$appearance = ($this->mpdf->n + $this->getCountItems($form) - 1) . ' 0 R';
		if ($form['typ'] === 'Bt') {
			$this->writer->write('/AP << /N << /Push ' . $appearance . ' >> >> /AS /Push');
		} else {
			$this->writer->write('/AP << /N ' . $appearance . ' >>');
		}
	}

	/**
	 * Writes the appearance of a text field, choice or push button: its background and border, then its icon or the
	 * text appearanceText() laid out
	 *
	 * @param mixed[] $form
	 */
	private function writeAppearance($form)
	{
		if (!isset($form['AP'])) {
			return;
		}

		$width = $form['w'] * Mpdf::SCALE;
		$height = $form['h'] * Mpdf::SCALE;
		$border = (float) $form['BS_W'];

		$s = sprintf('%s 0 0 %.3F %.3F re f', $this->appearanceColor($form['BG_C'], 'rg'), $width, $height);
		if ($border > 0) {
			// A dashed border's style carries its dash array, as /S /D /D [3]
			$dash = preg_match('/\/D (\[[^]]*\])/', $form['BS_S'], $m) ? ' ' . $m[1] . ' 0 d' : '';
			$s .= sprintf(' %s %.3F w%s %.3F %.3F %.3F %.3F re S', $this->appearanceColor($form['BC_C'], 'RG'), $border, $dash, $border / 2, $border / 2, $width - $border, $height - $border);
		}

		if ($form['AP']['highlights']) {
			$s .= ' ' . $this->appearanceColor('0.6 0.75 0.85', 'rg');
			foreach ($form['AP']['highlights'] as $highlight) {
				$s .= vsprintf(' %.3F %.3F %.3F %.3F re f', $highlight);
			}
		}

		if (isset($this->form_button_icon[$form['n']])) {
			$s .= sprintf(' q %.3F 0 0 %.3F 0 0 cm /I%d Do Q', $width, $height, $this->form_button_icon[$form['n']]['image_id']);
		} elseif ($form['AP']['lines']) {
			$s .= sprintf(' /Tx BMC q %.3F %.3F %.3F %.3F re W n', $border, $border, $width - 2 * $border, $height - 2 * $border);
			$s .= sprintf(' BT /F%d %.3F Tf ET %s', $this->mpdf->fonts[$form['style']['font']]['i'], $form['AP']['size'], $form['style']['fontcolor']);
			$s .= ' ' . implode(' ', $form['AP']['lines']) . ' Q EMC';
		}

		$this->writeAppearanceStream($s, [$width, $height]);
	}

	/**
	 * An active field's /MK colour: its CSS colour, or else the default
	 *
	 * @param mixed $color a colour the field's CSS sets, or false
	 * @param string $default e.g. '0.6 0.6 0.72'
	 *
	 * @return string
	 */
	private function activeColor($color, $default)
	{
		return $color ? $this->mpdf->SetColor($color, 'CodeOnly') : $default;
	}

	/**
	 * The border and background colours of a text or choice field's /MK. A viewer that redraws the field draws a border
	 * wherever /BC is given, whatever the width, so a field without a border has none.
	 *
	 * @param mixed[] $form
	 *
	 * @return string
	 */
	private function markColors($form)
	{
		$colors = (float) $form['BS_W'] > 0 ? '/BC [ ' . $form['BC_C'] . ' ] ' : '';

		return $colors . '/BG [ ' . $form['BG_C'] . ' ] ';
	}

	/**
	 * Writes a stream a widget shows
	 *
	 * @param string $content
	 * @param float[] $box the width and height it draws in, in points, which the viewer fits to the widget
	 */
	private function writeAppearanceStream($content, array $box)
	{
		$filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';
		$p = $this->mpdf->compress ? gzcompress($content) : $content;

		$this->writer->object();
		$this->writer->write(sprintf('<</Type /XObject /Subtype /Form /BBox [0 0 %.3F %.3F] %s/Length %d /Resources 2 0 R>>', $box[0], $box[1], $filter, $this->writer->streamLength($p)));
		$this->writer->stream($p);
		$this->writer->write('endobj');
	}

	/**
	 * The operator that paints a form colour, given as 1, 3 or 4 components, in the colour space the document is
	 * restricted to
	 *
	 * @param string $color e.g. '0.6 0.6 0.72'
	 * @param string $operator 'rg' to fill, 'RG' to stroke
	 *
	 * @return string
	 */
	private function appearanceColor($color, $operator)
	{
		$c = preg_split('/\s+/', trim($color));
		if (count($c) === 1) {
			return $color . ($operator === 'rg' ? ' g' : ' G');
		}

		if (count($c) === 4) {
			$css = vsprintf('cmyk(%.1F, %.1F, %.1F, %.1F)', array_map(function ($v) {
				return (float) $v * 100;
			}, $c));
		} else {
			$css = vsprintf('rgb(%d, %d, %d)', array_map(function ($v) {
				return round((float) $v * 255);
			}, $c));
		}

		$warnings = [];

		return $this->mpdf->SetColor($this->colorConverter->convert($css, $warnings), $operator === 'rg' ? 'Fill' : 'Draw');
	}

	function _form_rect($x, $y, $w, $h, $hPt)
	{
		$x *= Mpdf::SCALE;
		$y = $hPt - ($y * Mpdf::SCALE);
		$x2 = $x + ($w * Mpdf::SCALE);
		$y2 = $y - ($h * Mpdf::SCALE);

		return sprintf('%.3F %.3F %.3F %.3F', $x, $y2, $x2, $y);
	}

	function _put_button_icon($array, $w, $h)
	{
		$info = true;

		if (isset($array['image_id'])) {
			$info = false;
			foreach ($this->mpdf->images as $iid => $img) {
				if ($img['i'] == $array['image_id']) {
					$info = $this->mpdf->images[$iid];
					break;
				}
			}
		}

		if (!$info) {
			throw new \Mpdf\MpdfException('Cannot find Button image');
		}

		$this->writer->object();
		$this->writer->write('<<');
		$this->writer->write('/Type /XObject');
		$this->writer->write('/Subtype /Image');
		$this->writer->write('/BBox [0 0 1 1]');
		$this->writer->write('/Length ' . $this->writer->streamLength($info['data']));
		$this->writer->write('/BitsPerComponent ' . $info['bpc']);

		if ($info['cs'] === 'Indexed') {
			$this->writer->write('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->mpdf->n + 1) . ' 0 R]');
		} else {
			$this->writer->write('/ColorSpace /' . $info['cs']);
			if ($info['cs'] === 'DeviceCMYK') {
				if ($info['type'] === 'jpg') {
					$this->writer->write('/Decode [1 0 1 0 1 0 1 0]');
				}
			}
		}

		if (isset($info['f'])) {
			$this->writer->write('/Filter /' . $info['f']);
		}

		if (isset($info['parms'])) {
			$this->writer->write($info['parms']);
		}

		$this->writer->write('/Width ' . $info['w']);
		$this->writer->write('/Height ' . $info['h']);
		$this->writer->write('>>');
		$this->writer->stream($info['data']);
		$this->writer->write('endobj');

		//Palette
		if ($info['cs'] === 'Indexed') {
			$filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';
			$this->writer->object();
			$pal = $this->mpdf->compress ? gzcompress($info['pal']) : $info['pal'];
			$this->writer->write('<<' . $filter . '/Length ' . $this->writer->streamLength($pal) . '>>');
			$this->writer->stream($pal);
			$this->writer->write('endobj');
		}
	}

	function _putform_bt($form, $hPt)
	{
		$cc = 0;
		$put_js = 0;
		$put_icon = 0;
		$this->writer->object();
		$n = $this->mpdf->n;

		// A radio button or a button that shares its name is listed through the field it is a kid of
		$group = $this->isPushButton($form) && isset($this->buttonGroups[$form['T']]);
		if ($form['subtype'] !== 'radio' && !$group) {
			$this->pdf_acro_array .= $n . ' 0 R '; // Add to /Field element
		}

		$this->forms[$form['n']]['obj'] = $n;
		$this->writer->write('<<');
		$this->writer->write('/Type /Annot ');
		$this->writer->write('/Subtype /Widget');
		$this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 7000 + $form['n'])));
		$this->writer->write('/M ' . $this->writer->dateString());
		$this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $hPt) . ' ]');

		if ($form['noprint'] && $this->mpdf->PDFA) {
			$this->mpdf->pdfaxWarning('Form buttons are printed in PDFA files (noprint ignored)');
		}
		$this->writer->write($form['noprint'] && !$this->mpdf->PDFA ? '/F 0 ' : '/F 4 ');

		$this->writer->write('/FT /Btn ');
		$this->writer->write('/H /P ');

		if ($group) {
			$this->writer->write('/Parent ' . $this->buttonGroups[$form['T']]['obj_id'] . ' 0 R ');
		} elseif ($form['subtype'] !== 'radio') {  // mPDF 5.3.23
			$this->writer->write('/T ' . $this->writer->string($form['T']));
		}

		$this->writer->write('/TU ' . $this->writer->string($form['TU']));

		if (isset($this->form_button_icon[$form['n']])) {
			$form['BS_W'] = 0;
		}

		if ($form['BS_W'] == 0) {
			$form['BC_C'] = $form['BG_C'];
		}

		$bstemp = '';
		$bstemp .= '/W ' . $form['BS_W'] . ' ';
		$bstemp .= '/S /' . $form['BS_S'] . ' ';
		$temp = '';
		$temp .= '/BC [ ' . $form['BC_C'] . ' ] ';
		$temp .= '/BG [ ' . $form['BG_C'] . ' ] ';

		if ($form['subtype'] === 'checkbox') {

			if ($form['disabled']) {
				$radio_color = '0.5 0.5 0.5';
				$radio_background_color = '0.9 0.9 0.9';
			} else {
				$radio_color = $this->form_radio_color;
				$radio_background_color = $this->form_radio_background_color;
			}

			$temp = '';
			$temp .= '/BC [ ' . $radio_color . ' ] ';
			$temp .= '/BG [ ' . $radio_background_color . ' ] ';
			$this->writer->write('/BS << /W 1 /S /S >>');
			$this->writer->write("/MK << $temp >>");
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']));

			if ($form['activ']) {
				$this->writer->write('/V /' . $this->writer->escape($form['V']) . ' ');
				$this->writer->write('/DV /' . $this->writer->escape($form['V']) . ' ');
				$this->writer->write('/AS /' . $this->writer->escape($form['V']) . ' ');
			} else {
				$this->writer->write('/AS /Off ');
			}

			$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' 0 Tf ' . $radio_color . ' rg'));
			$this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');

			$this->writer->write('/Opt [ ' . $this->writer->string($form['OPT']) . ' ' . $this->writer->string($form['OPT']) . ' ]');
		}

		if ($form['subtype'] === 'radio') {

			if ((isset($form['disabled']) && $form['disabled']) || (isset($this->form_radio_groups[$form['T']]['disabled']) && $this->form_radio_groups[$form['T']]['disabled'])) {
				$radio_color = '0.5 0.5 0.5';
				$radio_background_color = '0.9 0.9 0.9';
			} else {
				$radio_color = $this->form_radio_color;
				$radio_background_color = $this->form_radio_background_color;
			}

			$this->writer->write('/Parent ' . $this->form_radio_groups[$form['T']]['obj_id'] . ' 0 R ');

			$temp = '';
			$temp .= '/BC [ ' . $radio_color . ' ] ';
			$temp .= '/BG [ ' . $radio_background_color . ' ] ';

			$this->writer->write('/BS << /W 1 /S /S >>');
			$this->writer->write('/MK << ' . $temp . ' >> ');

			$form['FF'][] = self::FLAG_NOTOGGLEOFF;
			$form['FF'][] = self::FLAG_RADIO; // must be same as radio button group setting?
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']));

			$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' 0 Tf ' . $radio_color . ' rg'));

			$this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');

			if ($form['activ']) {
				$this->writer->write('/V /' . $this->writer->escape($form['V']) . ' ');
				$this->writer->write('/DV /' . $this->writer->escape($form['V']) . ' ');
				$this->writer->write('/AS /' . $this->writer->escape($form['V']) . ' ');
			} else {
				$this->writer->write('/AS /Off ');
			}
			$this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');
			// $this->writer->write('/Opt [ '.$this->writer->string($form['OPT']).' '.$this->writer->string($form['OPT']).' ]');
		}

		if ($form['subtype'] === 'reset') {
			$temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
			$this->writer->write("/BS << $bstemp >>");
			$this->writer->write('/MK << ' . $temp . ' >>');
			$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor']));
			if ($this->actionAllowed()) {
				$this->writer->write('/AA << /D << /S /ResetForm /Flags 1 >> >>');
			}
			$form['FF'][] = 17;
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']));
		}

		if ($form['subtype'] === 'submit') {

			$temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
			$this->writer->write("/BS << $bstemp >>");
			$this->writer->write("/MK << $temp >>");
			$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor']));

			// Bit 4 (8) = useGETmethod else use POST
			// Bit 3 (4) = HTML export format (charset chosen by Adobe)--- OR ---
			// Bit 6 (32) = XFDF export format (form of XML in UTF-8)
			if ($form['exporttype'] === 'xfdf') {
				$flag = 32;
			} elseif ($form['method'] === 'GET') { // 'xfdf' or 'html'
				$flag = 12;
			} else {
				$flag = 4;
			}
			// Bit 2 (2) = IncludeNoValueFields
			if ($this->formSubmitNoValueFields) {
				$flag += 2;
			}
			// To submit a value, needs to be in /AP dictionary, AND this object must contain a /Fields entry
			// listing all fields to output
			if ($this->actionAllowed()) {
				$this->writer->write('/AA << /D << /S /SubmitForm /F ' . $this->writer->string($form['URL']) . ' /Flags ' . $flag . ' >> >>');
			}
			$form['FF'][] = 17;
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']));
		}

		if ($form['subtype'] === 'js_button') {
			// Icon / image
			if (isset($this->form_button_icon[$form['n']])) {
				$cc++;
				$temp .= '/TP ' . $this->form_button_icon[$form['n']]['pos'] . ' ';
				$temp .= '/I ' . ($cc + $this->mpdf->n) . ' 0 R ';  // Normal icon
				$temp .= '/RI ' . ($cc + $this->mpdf->n) . ' 0 R ';  // onMouseOver
				$temp .= '/IX ' . ($cc + $this->mpdf->n) . ' 0 R ';  // onClick / onMouseDown
				$temp .= '/IF << /SW /A /S /A /A [0.0 0.0] >> '; // Icon fit dictionary
				if ($this->form_button_icon[$form['n']]['Indexed']) {
					$cc++;
				}
				$put_icon = 1;
			}
			$temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
			$temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
			$this->writer->write("/BS << $bstemp >>");
			$this->writer->write("/MK << $temp >>");
			$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor']));
			$form['FF'][] = 17;
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']));
			// Javascript
			if (isset($this->array_form_button_js[$form['n']])) {
				$cc++;
				$this->writer->write('/AA << /D ' . ($cc + $this->mpdf->n) . ' 0 R >>');
				$put_js = 1;
			}
		}

		$this->writeAppearanceReference($form);
		$this->writer->write('>>');
		$this->writer->write('endobj');

		// additional objects
		// obj icon
		if ($put_icon === 1) {
			$this->_put_button_icon($this->form_button_icon[$form['n']], $form['w'], $form['h']);
			$put_icon = null;
		}
		// obj + 1
		if ($put_js === 1) {
			$this->mpdf->_set_object_javascript($this->array_form_button_js[$form['n']]['js']);
			$put_js = null;
		}

		$this->writeAppearance($form);

		// RADIO and CHECK BOX appearance streams, on then off, the shapes drawn a font size across
		if ($form['subtype'] === 'radio' || $form['subtype'] === 'checkbox') {
			$box = [$form['style']['fontsize'], $form['style']['fontsize']];
			$matrix = sprintf('%.3F 0 0 %.3F 0 %.3F', $form['style']['fontsize'] * 1.33 / 10, $form['style']['fontsize'] * 1.25 / 10, $form['style']['fontsize']);
			$color = $this->appearanceColor($radio_color, 'rg');
			$background = $this->appearanceColor($radio_background_color, 'rg');
		}
		if ($form['subtype'] === 'radio') {
			$fill = $background . ' 3.778 -7.410 m 2.800 -7.410 1.947 -7.047 1.225 -6.322 c 0.500 -5.600 0.138 -4.747 0.138 -3.769 c 0.138 -2.788 0.500 -1.938 1.225 -1.213 c 1.947 -0.491 2.800 -0.128 3.778 -0.128 c 4.757 -0.128 5.610 -0.491 6.334 -1.213 c 7.056 -1.938 7.419 -2.788 7.419 -3.769 c 7.419 -4.747 7.056 -5.600 6.334 -6.322 c 5.610 -7.047 4.757 -7.410 3.778 -7.410 c h f ';
			$circle = '3.778 -6.963 m 4.631 -6.963 5.375 -6.641 6.013 -6.004 c 6.653 -5.366 6.972 -4.619 6.972 -3.769 c 6.972 -2.916 6.653 -2.172 6.013 -1.532 c 5.375 -0.894 4.631 -0.576 3.778 -0.576 c 2.928 -0.576 2.182 -0.894 1.544 -1.532 c 0.904 -2.172 0.585 -2.916 0.585 -3.769 c 0.585 -4.619 0.904 -5.366 1.544 -6.004 c 2.182 -6.641 2.928 -6.963 3.778 -6.963 c h 3.778 -7.410 m 2.800 -7.410 1.947 -7.047 1.225 -6.322 c 0.500 -5.600 0.138 -4.747 0.138 -3.769 c 0.138 -2.788 0.500 -1.938 1.225 -1.213 c 1.947 -0.491 2.800 -0.128 3.778 -0.128 c 4.757 -0.128 5.610 -0.491 6.334 -1.213 c 7.056 -1.938 7.419 -2.788 7.419 -3.769 c 7.419 -4.747 7.056 -5.600 6.334 -6.322 c 5.610 -7.047 4.757 -7.410 3.778 -7.410 c h f ';
			$r_on = 'q ' . $matrix . ' cm ' . $fill . $color . ' ' . $circle . '  ' . $color . ' 5.184 -5.110 m 4.800 -5.494 4.354 -5.685 3.841 -5.685 c 3.331 -5.685 2.885 -5.494 2.501 -5.110 c 2.119 -4.725 1.925 -4.279 1.925 -3.769 c 1.925 -3.257 2.119 -2.810 2.501 -2.429 c 2.885 -2.044 3.331 -1.853 3.841 -1.853 c 4.354 -1.853 4.800 -2.044 5.184 -2.429 c 5.566 -2.810 5.760 -3.257 5.760 -3.769 c 5.760 -4.279 5.566 -4.725 5.184 -5.110 c h f Q ';
			$r_off = 'q ' . $matrix . ' cm ' . $fill . $color . ' ' . $circle . '  Q ';

			$this->writeAppearanceStream($r_on, $box);
			$this->writeAppearanceStream($r_off, $box);
		}

		if ($form['subtype'] === 'checkbox') {
			$fill = $background . ' 7.395 -0.070 m 7.395 -7.344 l 0.121 -7.344 l 0.121 -0.070 l 7.395 -0.070 l h  f ';
			$square = '0.508 -6.880 m 6.969 -6.880 l 6.969 -0.534 l 0.508 -0.534 l 0.508 -6.880 l h 7.395 -0.070 m 7.395 -7.344 l 0.121 -7.344 l 0.121 -0.070 l 7.395 -0.070 l h ';
			$cb_on = 'q ' . $matrix . ' cm ' . $fill . $color . ' ' . $square . ' f ' . $color . ' 6.321 -1.352 m 5.669 -2.075 5.070 -2.801 4.525 -3.532 c 3.979 -4.262 3.508 -4.967 3.112 -5.649 c 3.080 -5.706 3.039 -5.779 2.993 -5.868 c 2.858 -6.118 2.638 -6.243 2.334 -6.243 c 2.194 -6.243 2.100 -6.231 2.052 -6.205 c 2.003 -6.180 1.954 -6.118 1.904 -6.020 c 1.787 -5.788 1.688 -5.523 1.604 -5.226 c 1.521 -4.930 1.480 -4.721 1.480 -4.600 c 1.480 -4.535 1.491 -4.484 1.512 -4.447 c 1.535 -4.410 1.579 -4.367 1.647 -4.319 c 1.733 -4.259 1.828 -4.210 1.935 -4.172 c 2.040 -4.134 2.131 -4.115 2.205 -4.115 c 2.267 -4.115 2.341 -4.232 2.429 -4.469 c 2.437 -4.494 2.444 -4.511 2.448 -4.522 c 2.451 -4.531 2.456 -4.546 2.465 -4.568 c 2.546 -4.795 2.614 -4.910 2.668 -4.910 c 2.714 -4.910 2.898 -4.652 3.219 -4.136 c 3.539 -3.620 3.866 -3.136 4.197 -2.683 c 4.426 -2.367 4.633 -2.103 4.816 -1.889 c 4.998 -1.676 5.131 -1.544 5.211 -1.493 c 5.329 -1.426 5.483 -1.368 5.670 -1.319 c 5.856 -1.271 6.066 -1.238 6.296 -1.217 c 6.321 -1.352 l h  f  Q ';
			$cb_off = 'q ' . $matrix . ' cm ' . $fill . $color . ' ' . $square . ' f Q ';
			$this->writeAppearanceStream($cb_on, $box);
			$this->writeAppearanceStream($cb_off, $box);
		}
		return $n;
	}

	function _putform_ch($form, $hPt)
	{
		$put_js = 0;
		$this->writer->object();
		$n = $this->mpdf->n;
		$this->pdf_acro_array .= $n . ' 0 R ';
		$this->forms[$form['n']]['obj'] = $n;

		$this->writer->write('<<');
		$this->writer->write('/Type /Annot ');
		$this->writer->write('/Subtype /Widget');
		$this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $hPt) . ' ]');
		$this->writer->write('/F 4');
		$this->writer->write('/FT /Ch');
		if ($form['Q']) {
			$this->writer->write('/Q ' . $form['Q'] . '');
		}
		$temp = '';
		$temp .= '/W ' . $form['BS_W'] . ' ';
		$temp .= '/S /' . $form['BS_S'] . ' ';
		$this->writer->write("/BS << $temp >>");

		$this->writer->write('/MK << ' . $this->markColors($form) . ' >>');

		$this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 6000 + $form['n'])));
		$this->writer->write('/M ' . $this->writer->dateString());

		$this->writer->write('/T ' . $this->writer->string($form['T']));
		$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor']));

		$opt = '';
		$count = count($form['OPT']['VAL']);
		for ($i = 0; $i < $count; $i++) {
			$opt .= '[ ' . $this->writer->string($form['OPT']['VAL'][$i]) . ' ' . $this->writer->string($form['OPT']['OPT'][$i]) . ' ] ';
		}
		$this->writer->write('/Opt [ ' . $opt . ']');

		// selected
		$selectItem = false;
		$selectIndex = false;
		foreach ($form['OPT']['SEL'] as $selectKey => $selectVal) {
			$selectName = $this->writer->string($form['OPT']['VAL'][$selectVal]);
			$selectItem .= ' ' . $selectName . ' ';
			$selectIndex .= ' ' . $selectVal . ' ';
		}
		if ($selectItem) {
			if (count($form['OPT']['SEL']) < 2) {
				$this->writer->write('/V ' . $selectItem . ' ');
				$this->writer->write('/DV ' . $selectItem . ' ');
			} else {
				$this->writer->write('/V [' . $selectItem . '] ');
				$this->writer->write('/DV [' . $selectItem . '] ');
			}
			$this->writer->write('/I [' . $selectIndex . '] ');
		}

		if (is_array($form['FF']) && count($form['FF']) > 0) {
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']) . ' ');
		}

		// Javascript
		if (isset($this->array_form_choice_js[$form['T']])) {
			$this->writer->write('/AA << /V ' . ($this->mpdf->n + 1) . ' 0 R >>');
			$put_js = 1;
		}

		$this->writeAppearanceReference($form);
		$this->writer->write('>>');
		$this->writer->write('endobj');

		// obj + 1
		if ($put_js === 1) {
			$this->mpdf->_set_object_javascript($this->array_form_choice_js[$form['T']]['js']);
			unset($this->array_form_choice_js[$form['T']]);
			$put_js = null;
		}

		$this->writeAppearance($form);

		return $n;
	}

	function _putform_tx($form, $hPt)
	{
		$put_js = 0;
		$this->writer->object();
		$n = $this->mpdf->n;
		$this->pdf_acro_array .= $n . ' 0 R ';
		$this->forms[$form['n']]['obj'] = $n;

		$this->writer->write('<<');
		$this->writer->write('/Type /Annot ');
		$this->writer->write('/Subtype /Widget ');

		$this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $hPt) . ' ] ');
		// PDF/A has every annotation printed; a hidden input takes up no space in any case
		$this->writer->write($form['hidden'] && !$this->mpdf->PDFA ? '/F 2 ' : '/F 4 ');
		$this->writer->write('/FT /Tx ');

		$this->writer->write('/H /N ');
		$this->writer->write('/R 0 ');

		if (is_array($form['FF']) && count($form['FF']) > 0) {
			$this->writer->write('/Ff ' . $this->_setflag($form['FF']) . ' ');
		}
		if (isset($form['maxlen']) && $form['maxlen'] > 0) {
			$this->writer->write('/MaxLen ' . $form['maxlen']);
		}

		$temp = '';
		$temp .= '/W ' . $form['BS_W'] . ' ';
		$temp .= '/S /' . $form['BS_S'] . ' ';
		$this->writer->write("/BS << $temp >>");

		$this->writer->write('/MK <<' . $this->markColors($form) . ' >>');

		$this->writer->write('/T ' . $this->writer->string($form['T']));
		$this->writer->write('/TU ' . $this->writer->string($form['TU']));
		if ($form['V'] || $form['V'] === '0') {
			$this->writer->write('/V ' . $this->writer->string($form['V']));
		}
		$this->writer->write('/DV ' . $this->writer->string($form['DV']));
		$this->writer->write('/DA ' . $this->writer->string('/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor']));
		if ($form['Q']) {
			$this->writer->write('/Q ' . $form['Q'] . '');
		}

		$this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 5000 + $form['n'])));
		$this->writer->write('/M ' . $this->writer->dateString());


		if (isset($this->array_form_text_js[$form['T']])) {
			$put_js = 1;
			$cc = 0;
			$js_str = '';

			if (isset($this->array_form_text_js[$form['T']]['F'])) {
				$cc++;
				$js_str .= '/F ' . ($cc + $this->mpdf->n) . ' 0 R ';
			}
			if (isset($this->array_form_text_js[$form['T']]['K'])) {
				$cc++;
				$js_str .= '/K ' . ($cc + $this->mpdf->n) . ' 0 R ';
			}
			if (isset($this->array_form_text_js[$form['T']]['V'])) {
				$cc++;
				$js_str .= '/V ' . ($cc + $this->mpdf->n) . ' 0 R ';
			}
			if (isset($this->array_form_text_js[$form['T']]['C'])) {
				$cc++;
				$js_str .= '/C ' . ($cc + $this->mpdf->n) . ' 0 R ';
				$this->pdf_array_co .= $this->mpdf->n . ' 0 R ';
			}
			$this->writer->write('/AA << ' . $js_str . ' >>');
		}

		$this->writeAppearanceReference($form);
		$this->writer->write('>>');
		$this->writer->write('endobj');

		if ($put_js == 1) {
			if (isset($this->array_form_text_js[$form['T']]['F'])) {
				$this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['F']['js']);
				unset($this->array_form_text_js[$form['T']]['F']);
			}
			if (isset($this->array_form_text_js[$form['T']]['K'])) {
				$this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['K']['js']);
				unset($this->array_form_text_js[$form['T']]['K']);
			}
			if (isset($this->array_form_text_js[$form['T']]['V'])) {
				$this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['V']['js']);
				unset($this->array_form_text_js[$form['T']]['V']);
			}
			if (isset($this->array_form_text_js[$form['T']]['C'])) {
				$this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['C']['js']);
				unset($this->array_form_text_js[$form['T']]['C']);
			}
		}

		$this->writeAppearance($form);
		return $n;
	}
}
