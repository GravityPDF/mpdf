<?php

namespace Mpdf\Tag;

use Mpdf\Image\ImageSizing;
use Mpdf\Mpdf;
use Mpdf\Utils\NumericString;

class Img extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$this->mpdf->ignorefollowingspaces = false;
		$objattr = [];
		$objattr['margin_top'] = 0;
		$objattr['margin_bottom'] = 0;
		$objattr['margin_left'] = 0;
		$objattr['margin_right'] = 0;
		$objattr['padding_top'] = 0;
		$objattr['padding_bottom'] = 0;
		$objattr['padding_left'] = 0;
		$objattr['padding_right'] = 0;
		$objattr['width'] = 0;
		$objattr['height'] = 0;
		$objattr['border_top']['w'] = 0;
		$objattr['border_bottom']['w'] = 0;
		$objattr['border_left']['w'] = 0;
		$objattr['border_right']['w'] = 0;
		if (isset($attr['SRC'])) {
			$srcpath = $attr['SRC'];
			$orig_srcpath = (isset($attr['ORIG_SRC']) ? $attr['ORIG_SRC'] : '');
			$properties = $this->cssManager->MergeCSS('', 'IMG', $attr);
			if (isset($properties ['DISPLAY']) && strtolower($properties ['DISPLAY']) === 'none') {
				return;
			}
			if (isset($properties['Z-INDEX']) && $this->mpdf->current_layer == 0) {
				$v = (int) $properties['Z-INDEX'];
				if ($v > 0) {
					$objattr['z-index'] = $v;
				}
			}

			$objattr['visibility'] = 'visible';
			if (isset($properties['VISIBILITY'])) {
				$v = strtolower($properties['VISIBILITY']);
				if (($v === 'hidden' || $v === 'printonly' || $v === 'screenonly') && $this->mpdf->visibility === 'visible') {
					$objattr['visibility'] = $v;
				}
			}

			// VSPACE and HSPACE converted to margins in MergeCSS
			if (isset($properties['MARGIN-TOP'])) {
				$objattr['margin_top'] = $this->sizeConverter->convert(
					$properties['MARGIN-TOP'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['MARGIN-BOTTOM'])) {
				$objattr['margin_bottom'] = $this->sizeConverter->convert(
					$properties['MARGIN-BOTTOM'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['MARGIN-LEFT'])) {
				$objattr['margin_left'] = $this->sizeConverter->convert(
					$properties['MARGIN-LEFT'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['MARGIN-RIGHT'])) {
				$objattr['margin_right'] = $this->sizeConverter->convert(
					$properties['MARGIN-RIGHT'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}

			if (isset($properties['PADDING-TOP'])) {
				$objattr['padding_top'] = $this->sizeConverter->convert(
					$properties['PADDING-TOP'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['PADDING-BOTTOM'])) {
				$objattr['padding_bottom'] = $this->sizeConverter->convert(
					$properties['PADDING-BOTTOM'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['PADDING-LEFT'])) {
				$objattr['padding_left'] = $this->sizeConverter->convert(
					$properties['PADDING-LEFT'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}
			if (isset($properties['PADDING-RIGHT'])) {
				$objattr['padding_right'] = $this->sizeConverter->convert(
					$properties['PADDING-RIGHT'],
					$this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
					$this->mpdf->FontSize,
					false
				);
			}

			if (isset($properties['BORDER-TOP'])) {
				$objattr['border_top'] = $this->mpdf->border_details($properties['BORDER-TOP']);
			}
			if (isset($properties['BORDER-BOTTOM'])) {
				$objattr['border_bottom'] = $this->mpdf->border_details($properties['BORDER-BOTTOM']);
			}
			if (isset($properties['BORDER-LEFT'])) {
				$objattr['border_left'] = $this->mpdf->border_details($properties['BORDER-LEFT']);
			}
			if (isset($properties['BORDER-RIGHT'])) {
				$objattr['border_right'] = $this->mpdf->border_details($properties['BORDER-RIGHT']);
			}

			if (isset($properties['VERTICAL-ALIGN'])) {
				$objattr['vertical-align'] = $this->getAlign($properties['VERTICAL-ALIGN']);
			}
			// In a cell a percentage width is of the cell's content width, which is only known once the table is laid out
			$percent = [];
			$w = $this->length($properties, $attr, 'WIDTH', 0, $percent, 'w');
			$h = $this->length($properties, $attr, 'HEIGHT', 0);
			$maxw = $this->length($properties, $attr, 'MAX-WIDTH', false, $percent, 'maxw');
			$maxh = $this->length($properties, $attr, 'MAX-HEIGHT', false);
			$minw = $this->length($properties, $attr, 'MIN-WIDTH', false, $percent, 'minw');
			$minh = $this->length($properties, $attr, 'MIN-HEIGHT', false);

			if (isset($properties['OPACITY']) && $properties['OPACITY'] > 0 && $properties['OPACITY'] <= 1) {
				$objattr['opacity'] = $properties['OPACITY'];
			}
			if ($this->mpdf->HREF) {
				if (strpos($this->mpdf->HREF, '.') === false && strpos($this->mpdf->HREF, '@') !== 0) {
					$href = $this->mpdf->HREF;
					while (array_key_exists($href, $this->mpdf->internallink)) {
						$href = '#' . $href;
					}
					$this->mpdf->internallink[$href] = $this->mpdf->AddLink();
					$objattr['link'] = $this->mpdf->internallink[$href];
				} else {
					$objattr['link'] = $this->mpdf->HREF;
				}
			}
			$extraheight = $objattr['padding_top'] + $objattr['padding_bottom'] + $objattr['margin_top']
				+ $objattr['margin_bottom'] + $objattr['border_top']['w'] + $objattr['border_bottom']['w'];

			$extrawidth = $objattr['padding_left'] + $objattr['padding_right'] + $objattr['margin_left']
				+ $objattr['margin_right'] + $objattr['border_left']['w'] + $objattr['border_right']['w'];

			// mPDF 5.7.3 TRANSFORMS
			if (isset($properties['BACKGROUND-COLOR']) && $properties['BACKGROUND-COLOR'] != '') {
				$objattr['bgcolor'] = $this->colorConverter->convert($properties['BACKGROUND-COLOR'], $this->mpdf->PDFAXwarnings);
			}

			/* -- BACKGROUNDS -- */
			if (isset($properties['GRADIENT-MASK']) && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $properties['GRADIENT-MASK'])) {
				$objattr['GRADIENT-MASK'] = $properties['GRADIENT-MASK'];
			}
			/* -- END BACKGROUNDS -- */

			// mPDF 6
			$interpolation = false;
			if (!empty($properties['IMAGE-RENDERING'])) {
				$interpolation = false;
				if (strtolower($properties['IMAGE-RENDERING']) === 'crisp-edges') {
					$interpolation = false;
				} elseif (strtolower($properties['IMAGE-RENDERING']) === 'optimizequality') {
					$interpolation = true;
				} elseif (strtolower($properties['IMAGE-RENDERING']) === 'smooth') {
					$interpolation = true;
				} elseif (strtolower($properties['IMAGE-RENDERING']) === 'auto') {
					$interpolation = $this->mpdf->interpolateImages;
				}
				$info['interpolation'] = $interpolation;
			}

			// Image file
			$info = $this->imageProcessor->getImage($srcpath, true, true, $orig_srcpath, $interpolation); // mPDF 6
			if (!$info) {
				$info = $this->imageProcessor->getImage($this->mpdf->noImageFile);
				if ($info) {
					$srcpath = $this->mpdf->noImageFile;
					$w = ($info['w'] * (25.4 / $this->mpdf->img_dpi));
					$h = ($info['h'] * (25.4 / $this->mpdf->img_dpi));
				}
			}
			if (!$info) {
				return;
			}

			$image_orientation = 0;
			if (isset($attr['ROTATE'])) {
				$image_orientation = $attr['ROTATE'];
			} elseif (isset($properties['IMAGE-ORIENTATION'])) {
				$image_orientation = $properties['IMAGE-ORIENTATION'];
			}
			if ($image_orientation) {
				if ($image_orientation == 90 || $image_orientation == -90 || $image_orientation == 270) {
					$tmpw = $info['w'];
					$info['w'] = $info['h'];
					$info['h'] = $tmpw;
				}
				$objattr['ROTATE'] = $image_orientation;
			}

			$objattr['file'] = $srcpath;
			// The size it takes when given neither width nor height
			/* -- IMAGES-WMF -- */
			if ($info['type'] === 'wmf') {
				// WMF units are twips (1/20pt)
				// divide by 20 to get points
				// divide by k to get user units
				$naturalW = abs($info['w']) / (20 * Mpdf::SCALE);
				$naturalH = abs($info['h']) / (20 * Mpdf::SCALE);
			} else { 							/* -- END IMAGES-WMF -- */
				if ($info['type'] === 'svg') {
					// SVG units are pixels
					$naturalW = abs($info['w']) / Mpdf::SCALE;
					$naturalH = abs($info['h']) / Mpdf::SCALE;
				} else {
					//Put image at default image dpi
					$naturalW = ($info['w'] / Mpdf::SCALE) * (72 / $this->mpdf->img_dpi);
					$naturalH = ($info['h'] / Mpdf::SCALE) * (72 / $this->mpdf->img_dpi);
				}
			}
			if (isset($properties['IMAGE-RESOLUTION'])) {
				if (preg_match('/from-image/i', $properties['IMAGE-RESOLUTION']) && isset($info['set-dpi']) && $info['set-dpi'] > 0) {
					$naturalW *= $this->mpdf->img_dpi / $info['set-dpi'];
					$naturalH *= $this->mpdf->img_dpi / $info['set-dpi'];
				} elseif (preg_match('/(\d+)dpi/i', $properties['IMAGE-RESOLUTION'], $m)) {
					$dpi = $m[1];
					if ($dpi > 0) {
						$naturalW *= $this->mpdf->img_dpi / $dpi;
						$naturalH *= $this->mpdf->img_dpi / $dpi;
					}
				}
			}

			$maxHeight = $this->mpdf->h - ($this->mpdf->tMargin + $this->mpdf->bMargin + 1);
			if ($this->mpdf->fullImageHeight) {
				$maxHeight = $this->mpdf->fullImageHeight;
			}

			$sizing = [
				'w' => $w,
				'h' => $h,
				'minw' => $minw,
				'maxw' => $maxw,
				'minh' => $minh,
				'maxh' => $maxh,
				'natural_w' => $naturalW,
				'natural_h' => $naturalH,
				'extrawidth' => $extrawidth,
				'extraheight' => $extraheight,
				// The page's room, which it is resized to fit
				'fit_w' => $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'],
				'fit_h' => $maxHeight,
				'percent' => $percent,
			];
			list($w, $h) = ImageSizing::fit($sizing, $info['w'], $info['h']);
			$objattr['type'] = 'image';
			$objattr['itype'] = $info['type'];

			$objattr['orig_h'] = $info['h'];
			$objattr['orig_w'] = $info['w'];
			/* -- IMAGES-WMF -- */
			if ($info['type'] === 'wmf') {
				$objattr['wmf_x'] = $info['x'];
				$objattr['wmf_y'] = $info['y'];
			} else { 						/* -- END IMAGES-WMF -- */
				if ($info['type'] === 'svg') {
					$objattr['wmf_x'] = $info['x'];
					$objattr['wmf_y'] = $info['y'];
				}
			}
			$objattr['height'] = $h + $extraheight;
			$objattr['width'] = $w + $extrawidth;
			$objattr['image_height'] = $h;
			$objattr['image_width'] = $w;

			/* -- BORDER-RADIUS -- */
			// A percentage is of the border box, horizontal radii of its width and vertical of its height, resolved now as a
			// block's are: a picture later narrowed to what is left of its line keeps the radius it was given. One still to
			// be sized against its cell has them resolved again then.
			$radii = [];
			$radiusPercent = [];
			foreach (['TL' => 'TOP-LEFT', 'TR' => 'TOP-RIGHT', 'BR' => 'BOTTOM-RIGHT', 'BL' => 'BOTTOM-LEFT'] as $corner => $name) {
				if (!isset($properties['BORDER-' . $name . '-RADIUS-H'], $properties['BORDER-' . $name . '-RADIUS-V'])) {
					continue;
				}
				foreach ([$properties['BORDER-' . $name . '-RADIUS-H'], $properties['BORDER-' . $name . '-RADIUS-V']] as $axis => $value) {
					$share = $this->percentage($value);
					if ($share === null) {
						$radii[$corner][$axis] = $this->sizeConverter->convert($value, 0, $this->mpdf->FontSize, false);
					} else {
						$radii[$corner][$axis] = 0;
						$radiusPercent[$corner][$axis] = $share;
					}
				}
			}
			$radii = ImageSizing::radii($objattr, $radii, $radiusPercent);
			$sizing['radius_percent'] = array_intersect_key($radiusPercent, $radii);
			if ($radii) {
				$objattr['border_radius'] = $radii;
			}
			/* -- END BORDER-RADIUS -- */
			if ($percent) {
				$objattr['cell_sizing'] = $sizing;
				for ($level = 1; $level <= $this->mpdf->tableLevel; $level++) {
					$this->mpdf->table[$level][$this->mpdf->tbctr[$level]]['cell_sized_images'] = true;
				}
			}
			/* -- CSS-IMAGE-FLOAT -- */
			if (!$this->mpdf->ColActive && !$this->mpdf->tableLevel && !$this->mpdf->listlvl && !$this->mpdf->kwt) {
				if (isset($properties['FLOAT']) && (strtoupper($properties['FLOAT']) === 'RIGHT' || strtoupper($properties['FLOAT']) === 'LEFT')) {
					$objattr['float'] = strtoupper(substr($properties['FLOAT'], 0, 1));
				}
			}
			/* -- END CSS-IMAGE-FLOAT -- */
			// mPDF 5.7.3 TRANSFORMS
			if (isset($properties['TRANSFORM']) && !$this->mpdf->ColActive && !$this->mpdf->kwt) {
				$objattr['transform'] = $properties['TRANSFORM'];
			}

			$e = Mpdf::OBJECT_IDENTIFIER . "type=image,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;

			/* -- TABLES -- */
			// Output it to buffers
			if ($this->mpdf->tableLevel) {
				$this->mpdf->_saveCellTextBuffer($e, $this->mpdf->HREF);
				$this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] += $objattr['width'];
			} else {
				/* -- END TABLES -- */
				$this->mpdf->_saveTextBuffer($e, $this->mpdf->HREF);
			} // *TABLES*
			/* -- ANNOTATIONS -- */
			if ($this->mpdf->title2annots && isset($attr['TITLE'])) {
				$objattr = [];
				$objattr['margin_top'] = 0;
				$objattr['margin_bottom'] = 0;
				$objattr['margin_left'] = 0;
				$objattr['margin_right'] = 0;
				$objattr['width'] = 0;
				$objattr['height'] = 0;
				$objattr['border_top']['w'] = 0;
				$objattr['border_bottom']['w'] = 0;
				$objattr['border_left']['w'] = 0;
				$objattr['border_right']['w'] = 0;
				$objattr['CONTENT'] = $attr['TITLE'];
				$objattr['type'] = 'annot';
				$objattr['POS-X'] = 0;
				$objattr['POS-Y'] = 0;
				$objattr['ICON'] = 'Comment';
				$objattr['AUTHOR'] = '';
				$objattr['SUBJECT'] = '';
				$objattr['OPACITY'] = $this->mpdf->annotOpacity;
				$objattr['COLOR'] = $this->colorConverter->convert('yellow', $this->mpdf->PDFAXwarnings);
				$e = Mpdf::OBJECT_IDENTIFIER . "type=annot,objattr=" . serialize($this->withSpanVisibility($objattr)) . Mpdf::OBJECT_IDENTIFIER;
				if ($this->mpdf->tableLevel) { // *TABLES*
					$this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['textbuffer'][] = [$e]; // *TABLES*
				} // *TABLES*
				else { // *TABLES*
					$this->mpdf->textbuffer[] = [$e];
				} // *TABLES*
			}
			/* -- END ANNOTATIONS -- */
		}
	}

	/**
	 * A length the image is given in CSS, or else in its HTML attribute, in millimetres. Inside a table a percentage
	 * of the width goes into $percent under $key instead, to be resolved against the cell, and $default stands in.
	 *
	 * @param array $properties
	 * @param array $attr
	 * @param string $name The property and attribute, e.g. MAX-WIDTH
	 * @param int|false $default What an image not given the length has
	 * @param array $percent
	 * @param string|null $key Where a percentage of the width is put in $percent
	 *
	 * @return float|int|false
	 */
	private function length(array $properties, array $attr, $name, $default, array &$percent = [], $key = null)
	{
		if (isset($properties[$name])) {
			$value = $properties[$name];
		} elseif (isset($attr[$name])) {
			$value = $attr[$name];
		} else {
			return $default;
		}

		if ($key !== null && $this->mpdf->tableLevel && NumericString::containsPercentChar($value)) {
			$percent[$key] = (float) $value;

			return $default;
		}

		return $this->sizeConverter->convert($value, $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->FontSize, false);
	}

	/**
	 * The number of a percentage, or null for any other length
	 *
	 * @param string $value
	 *
	 * @return float|null
	 */
	private function percentage($value)
	{
		return NumericString::containsPercentChar($value) ? (float) $value : null;
	}

	public function close(&$ahtml, &$ihtml)
	{
	}
}
