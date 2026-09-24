<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
use Mpdf\Utils\PdfDate;
use Mpdf\Pdf\Protection;

final class BaseWriter
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \DateTimeInterface
	 */
	private $date;

	/**
	 * @var \Mpdf\Pdf\Protection
	 */
	private $protection;

	/**
	 * The profile of the ICC-based sRGB colour space, and of PDF/A's output intent where it names none
	 */
	const SRGB_PROFILE = __DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc';

	/**
	 * The profile of the ICC-based grey colour space
	 */
	const GRAY_PROFILE = __DIR__ . '/../../data/iccprofiles/Gray_sRGB_TRC.icc';

	/**
	 * The name the ICC-based sRGB colour space is set by. It is upper case throughout, because mPDF
	 * makes a stroking operator out of a filling one by upper-casing the whole of it.
	 */
	const CALIBRATED_RGB = 'CSRGB';


	/**
	 * The name the ICC-based grey colour space is set by, upper case for the same reason
	 */
	const CALIBRATED_GRAY = 'CSGRAY';

	/**
	 * @var int[] The object numbers of the ICC-based colour spaces written, by profile
	 */
	private $calibrated = [];

	public function __construct(Mpdf $mpdf, Protection $protection)
	{
		$this->mpdf = $mpdf;
		$this->protection = $protection;
	}

	public function write($s, $ln = true)
	{
		if ($this->mpdf->state === 2) {
			$this->endPage($s, $ln);
		} else {
			$this->mpdf->buffer->append($s, $ln);
		}
	}

	/**
	 * A hexadecimal string, encrypted when the document is
	 *
	 * @param string $hex Hexadecimal digits, which may be separated by white space as in the PDF syntax
	 *
	 * @return string
	 */
	public function hexString($hex)
	{
		if (!$this->mpdf->encrypted) {
			return '<' . $hex . '>';
		}

		$hex = preg_replace('/\s+/', '', $hex);
		if (strlen($hex) % 2) {
			$hex .= '0';
		}

		return '<' . bin2hex($this->protection->encrypt(hex2bin($hex))) . '>';
	}

	public function string($s)
	{
		if ($this->mpdf->encrypted) {
			$s = $this->protection->encrypt($s);
		}

		return '(' . $this->escape($s) . ')';
	}

	public function object($obj_id = false, $onlynewobj = false)
	{
		if (!$obj_id) {
			$obj_id = ++$this->mpdf->n;
		}

		// Begin a new object
		if (!$onlynewobj) {
			$this->mpdf->offsets[$obj_id] = $this->mpdf->buffer->getLength();
			$this->write($obj_id . ' 0 obj');
		}
	}

	/**
	 * The /Length of a stream that stream() writes with this data, which encryption lengthens
	 *
	 * @param string $s
	 *
	 * @return int
	 */
	public function streamLength($s)
	{
		if ($this->mpdf->encrypted) {
			return $this->protection->encryptedLength(strlen($s));
		}

		return strlen($s);
	}

	public function stream($s)
	{
		if ($this->mpdf->encrypted) {
			$s = $this->protection->encrypt($s);
		}

		$this->write('stream');
		$this->write($s);
		$this->write('endstream');
	}

	public function utf16BigEndianTextString($s) // _UTF16BEtextstring
	{
		$s = $this->utf8ToUtf16BigEndian($s, true);
		if ($this->mpdf->encrypted) {
			$s = $this->protection->encrypt($s);
		}

		return '(' . $this->escape($s) . ')';
	}

	// Converts UTF-8 strings to UTF16-BE.
	public function utf8ToUtf16BigEndian($str, $setbom = true) // UTF8ToUTF16BE
	{
		if ($this->mpdf->checkSIP && preg_match("/([\x{20000}-\x{2FFFF}])/u", $str)) {
			if (!in_array($this->mpdf->currentfontfamily, ['gb', 'big5', 'sjis', 'uhc', 'gbB', 'big5B', 'sjisB', 'uhcB', 'gbI', 'big5I', 'sjisI', 'uhcI',
				'gbBI', 'big5BI', 'sjisBI', 'uhcBI'])) {
				$str = preg_replace("/[\x{20000}-\x{2FFFF}]/u", chr(0), $str);
			}
		}
		if ($this->mpdf->checkSMP && preg_match("/([\x{10000}-\x{1FFFF}])/u", $str)) {
			$str = preg_replace("/[\x{10000}-\x{1FFFF}]/u", chr(0), $str);
		}

		$outstr = ''; // string to be returned
		if ($setbom) {
			$outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
		}

		$outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');

		return $outstr;
	}

	public function escape($s) // _escape
	{
		return strtr($s, [')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(13) => '\r']);
	}

	public function escapeSlashes($s) // _escapeName
	{
		return strtr($s, ['/' => '#2F']);
	}

	/**
	 * Un-escapes a PDF string
	 *
	 * @param string $s
	 * @return string
	 */
	public function unescape($s)
	{
		$out = '';
		for ($count = 0, $n = strlen($s); $count < $n; $count++) {
			if ($count === $n - 1 || $s[$count] !== '\\') {
				$out .= $s[$count];
			} else {
				switch ($s[++$count]) {
					case ')':
					case '(':
					case '\\':
						$out .= $s[$count];
						break;
					case 'f':
						$out .= chr(0x0C);
						break;
					case 'b':
						$out .= chr(0x08);
						break;
					case 't':
						$out .= chr(0x09);
						break;
					case 'r':
						$out .= chr(0x0D);
						break;
					case 'n':
						$out .= chr(0x0A);
						break;
					case "\r":
						if ($count !== $n - 1 && $s[$count + 1] === "\n") {
							$count++;
						}
						break;
					case "\n":
						break;
					default:
						// Octal-Values
						$ord = ord($s[$count]);
						if ($ord >= ord('0') && $ord <= ord('9')) {
							$oct = ''. $s[$count];
							$ord = ord($s[$count + 1]);
							if ($ord >= ord('0') && $ord <= ord('9')) {
								$oct .= $s[++$count];
								$ord = ord($s[$count + 1]);
								if ($ord >= ord('0') && $ord <= ord('9')) {
									$oct .= $s[++$count];
								}
							}
							$out .= chr(octdec($oct));
						} else {
							$out .= $s[$count];
						}
				}
			}
		}

		return $out;
	}

	private function endPage($s, $ln)
	{
		if ($this->mpdf->bufferoutput) {

			$this->mpdf->headerbuffer.= $s . "\n";

		} elseif ($this->mpdf->ColActive && !$this->mpdf->processingHeader && !$this->mpdf->processingFooter) {

			// Captures everything in buffer for columns; Almost everything is sent from fn. Cell() except:
			// Images sent from Image() or
			// later sent as write($textto) in printbuffer
			// Line()

			if (preg_match('/q \d+\.\d\d+ 0 0 (\d+\.\d\d+) \d+\.\d\d+ \d+\.\d\d+ cm \/(I|FO)\d+ Do Q/', $s, $m)) { // Image data

				$h = ($m[1] / Mpdf::SCALE);
				// Update/overwrite the lowest bottom of printing y value for a column
				$this->mpdf->ColDetails[$this->mpdf->CurrCol]['bottom_margin'] = $this->mpdf->y + $h;

			} elseif ($this->mpdf->tableLevel > 0 && preg_match('/\d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ ([\-]{0,1}\d+\.\d\d+) re/', $s, $m)) { // Rect in table

				$h = ($m[1] / Mpdf::SCALE);
				// Update/overwrite the lowest bottom of printing y value for a column
				$this->mpdf->ColDetails[$this->mpdf->CurrCol]['bottom_margin'] = max($this->mpdf->ColDetails[$this->mpdf->CurrCol]['bottom_margin'], $this->mpdf->y + $h);

			} elseif (isset($this->mpdf->ColDetails[$this->mpdf->CurrCol]['bottom_margin'])) {

				$h = $this->mpdf->ColDetails[$this->mpdf->CurrCol]['bottom_margin'] - $this->mpdf->y;

			} else {

				$h = 0;

			}

			if ($h < 0) {
				$h = -$h;
			}

			$this->mpdf->columnbuffer[] = [
				's' => $s, // Text string to output
				'col' => $this->mpdf->CurrCol, // Column when printed
				'x' => $this->mpdf->x, // x when printed
				'y' => $this->mpdf->y, // this->y when printed (after column break)
				'h' => $h        // actual y at bottom when printed = y+h
			];

		} elseif ($this->mpdf->table_rotate && !$this->mpdf->processingHeader && !$this->mpdf->processingFooter) {

			// Captures eveything in buffer for rotated tables;
			$this->mpdf->tablebuffer .= $s . "\n";

		} elseif ($this->mpdf->kwt && !$this->mpdf->processingHeader && !$this->mpdf->processingFooter) {

			// Captures eveything in buffer for keep-with-table (h1-6);
			$this->mpdf->kwt_buffer[] = [
				's' => $s, // Text string to output
				'x' => $this->mpdf->x, // x when printed
				'y' => $this->mpdf->y, // y when printed
			];

		} else {
			$this->mpdf->pages[$this->mpdf->page] .= $s . ($ln ? "\n" : '');
		}
	}

	/**
	 * The moment the document is dated, settled the first time it is asked for so every date in it agrees
	 *
	 * @return \DateTimeInterface
	 */
	public function date()
	{
		if ($this->date === null) {
			$this->date = PdfDate::documentDate($this->mpdf->creationDate);
		}

		return $this->date;
	}

	/**
	 * The colour space RGB is written in where it may not be DeviceRGB: PDF/X-4 permits DeviceRGB only
	 * where its output intent is RGB, so otherwise RGB is written in an ICC-based sRGB colour space. It is
	 * written the first time it is asked for, and so is asked for only between objects.
	 *
	 * @return int|null The object number of the ICC-based colour space, or null where RGB is DeviceRGB
	 */
	public function calibratedRgb()
	{
		return $this->mpdf->writesCalibratedRgb() ? $this->calibrated(self::SRGB_PROFILE, 3) : null;
	}

	/**
	 * The colour space grey is written in where it may not be DeviceGray: PDF/X-4 permits DeviceGray only
	 * where its output intent is grey or CMYK, so under an RGB one grey is written in an ICC-based space
	 * whose profile is data/iccprofiles/Gray_sRGB_TRC.icc. Written the first time it is asked for, so asked
	 * for between objects.
	 *
	 * @return int|null The object number of the ICC-based colour space, or null where grey is DeviceGray
	 */
	public function calibratedGray()
	{
		return $this->mpdf->writesCalibratedGray() ? $this->calibrated(self::GRAY_PROFILE, 1) : null;
	}

	/**
	 * The colour space DeviceCMYK an imported page paints in is taken to be where PDF/X-4 does not permit
	 * it: under an RGB or grey output intent, where mPDF converts its own CMYK, but cannot convert what it
	 * imports. DeviceCMYK says nothing of the press it was made for, so it is taken as the bundled SWOP
	 * profile, the one mPDF prints to by default. Written the first time it is asked for, so asked for
	 * between objects.
	 *
	 * @return int|null The object number of the ICC-based colour space, or null where CMYK is DeviceCMYK
	 */
	public function calibratedCmyk()
	{
		return $this->mpdf->pdfxConvertsCmyk() ? $this->calibrated(Mpdf::PDFX4_OUTPUT_PROFILE, 4) : null;
	}

	/**
	 * @return string The colour space grey is written in, for a dictionary to name: /DeviceGray, or a
	 *                reference to the ICC-based grey colour space - see calibratedGray()
	 */
	public function grayColorSpace()
	{
		$calibrated = $this->calibratedGray();

		return $calibrated === null ? '/DeviceGray' : $calibrated . ' 0 R';
	}

	/**
	 * @param string $path     An ICC profile
	 * @param int    $channels The number of colour components it has
	 *
	 * @return int The object number of the ICC-based colour space on that profile, written the first time
	 */
	private function calibrated($path, $channels)
	{
		if (!isset($this->calibrated[$path])) {
			$this->calibrated[$path] = $this->iccBased(file_get_contents($path), $channels);
		}

		return $this->calibrated[$path];
	}

	/**
	 * @param string $profile  An ICC profile
	 * @param int    $channels The number of colour components it has
	 *
	 * @return int The object number of an ICC-based colour space on that profile, written with it
	 */
	private function iccBased($profile, $channels)
	{
		$filter = '';
		if ($this->mpdf->compress) {
			$profile = gzcompress($profile);
			$filter = '/Filter /FlateDecode ';
		}

		$this->object();
		$this->write('<</N ' . $channels . ' ' . $filter . '/Length ' . strlen($profile) . '>>');
		$this->stream($profile);
		$this->write('endobj');

		$this->object();
		$this->write('[/ICCBased ' . ($this->mpdf->n - 1) . ' 0 R]');
		$this->write('endobj');

		return $this->mpdf->n;
	}

	/**
	 * That moment as a PDF date string, for the Info dictionary and each annotation
	 */
	public function dateString()
	{
		return $this->string('D:' . PdfDate::format($this->date()));
	}

}
