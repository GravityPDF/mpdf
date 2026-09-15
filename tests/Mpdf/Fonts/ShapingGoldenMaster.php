<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\Utils\UtfString;

/**
 * Captures what Otl::applyOTL() makes of a run of text, for one font.
 *
 * The other three masters pin the parser and the subsetter. This pins the shaper, which is the half
 * neither of them reaches: ParserGoldenMaster fixes the input applyOTL is given and says nothing
 * about what it does with it, and OtlDumpGoldenMaster fixes what a different consumer reports of the
 * same tables. What guards applyOTL otherwise is a handful of tests naming one rule in one font
 * each, so a regression in a phase no fixture happens to exercise would ship in silence.
 *
 * Every string is run through every font, including the scripts a font has no glyph for. That is the
 * point rather than an accident: applyOTL's phases - the character analysis, the script and language
 * selection, the choice of shaper, the line-breaking pass, and the reassembly at the end - all run
 * whether or not a glyph matches, and it is the state threaded between those phases that a split
 * puts at risk.
 */
class ShapingGoldenMaster extends GoldenMaster
{

	/**
	 * How many of a font's own characters to shape, in runs of this many.
	 *
	 * The corpus is mostly small subsets whose whole repertoire fits in a few runs. Noto Sans has
	 * 3,054 characters, which is not worth shaping in full to learn the same thing, so the run is
	 * taken across the sorted code points at a stride that spans the font instead of stopping in the
	 * Latin block at the front of it.
	 */
	const RUN_LENGTH = 8;

	const RUNS_PER_FONT = 24;

	/**
	 * The settings a document can arrive with, as useOTL and the feature tags CSS turned on or off.
	 *
	 * 0xFF is every script enabled, which is what mPDF passes for a complex-script font; 0x80 leaves
	 * only the complex scripts on, so Latin, Cyrillic, Greek and the CJK block are refused a script
	 * tag and come back unshaped. useOTL 0 returns the string before any of this and is not worth a
	 * fixture. The third is what font-kerning and font-variant-ligatures leave behind, which is the
	 * only way the feature filtering inside applyOTL is reached with anything to do.
	 */
	private static $settings = [
		'0xFF' => [0xFF, []],
		'0x80' => [0x80, []],
		'0xFF +kern -liga' => [0xFF, ['Plus' => ' kern', 'Minus' => ' liga']],
	];

	/**
	 * One short run per shaper applyOTL can choose, as code points.
	 *
	 * Between them these reach every branch of the shaper selection and of the line-breaking that
	 * sits between phases 3 and 4 - Indic, Arabic, Khmer, Thai, Lao, Sinhala, Myanmar, South East
	 * Asian and the generic path - which no font in the corpus covers on its own. The last two are
	 * about the seams rather than a script: a string that changes script part way through is split
	 * into subchunks shaped separately and reassembled, and a space is the one character the analysis
	 * groups as 'S' rather than 'C' or 'M'.
	 */
	private static $runs = [
		'latin' => [0x41, 0x56, 0x41, 0x54, 0x61, 0x72],
		'cyrillic' => [0x416, 0x430, 0x439],
		'greek' => [0x3B1, 0x3B2, 0x3C2],
		'hiragana' => [0x3042, 0x3043, 0x3044],
		'arabic' => [0x628, 0x640, 0x645, 0x644, 0x627],
		'syriac' => [0x710, 0x712, 0x713, 0x715],
		'nko' => [0x7CA, 0x7CB, 0x7CC],
		'devanagari' => [0x915, 0x94D, 0x937, 0x93F],
		'bengali' => [0x995, 0x9CD, 0x9B7, 0x9BF],
		'gurmukhi' => [0xA15, 0xA4D, 0xA38, 0xA3F],
		'tamil' => [0xB95, 0xBCD, 0xBB7, 0xBBF],
		'malayalam' => [0xD15, 0xD4D, 0xD37, 0xD3F],
		'sinhala' => [0xD9A, 0xDCA, 0xDBB, 0xDBB],
		'khmer' => [0x1780, 0x17D2, 0x1781, 0x17C1],
		'thai' => [0xE01, 0xE34, 0xE48, 0xE23],
		'lao' => [0xE81, 0xEB4, 0xE8D],
		'myanmar' => [0x1000, 0x103A, 0x1039, 0x1001],
		'new tai lue' => [0x1980, 0x19B0, 0x1981],
		'cham' => [0xAA00, 0xAA33, 0xAA01],
		'tai tham' => [0x1A20, 0x1A60, 0x1A21],
		'mixed scripts' => [0x41, 0x628, 0x915, 0x42],
		'spaced' => [0x41, 0x20, 0x628, 0x20, 0x42],
	];

	/**
	 * @return string What this master pins, as a directory-safe word
	 */
	protected function name()
	{
		return 'shaping';
	}

	/**
	 * @return string The extension its fixtures are written with
	 */
	protected function extension()
	{
		return 'txt';
	}

	/**
	 * @return string The fixture as it would be written now
	 */
	public function capture($name)
	{
		// mPDF matches a font family against /^([a-z_0-9\-]+)([BI]{0,2})$/, so a file name with capitals
		// in it silently resolves to some other font and shapes against that instead
		$fontkey = strtolower($name);

		try {
			// Setting it as the default font loads and caches it during construction, and puts it in
			// CurrentFont, which is where applyOTL reads it from. A font with no OTL tables is refused
			// there rather than shaped, and is recorded as refused: that is a parser answer the other
			// masters already state, and stating it here too keeps a font that starts shaping a diff
			// rather than a fixture nobody committed.
			$mpdf = new Mpdf([
				'mode' => 'utf-8',
				'tempDir' => $this->tmpDir,
				'fontDir' => [self::FONT_DIR],
				'fontdata' => [$fontkey => ['R' => $name . '.ttf', 'useOTL' => 0xFF]],
				'default_font' => $fontkey,
			]);
		} catch (\Exception $e) {
			return sprintf("%s: %s\n", get_class($e), $this->withoutPath($e->getMessage()));
		}

		// A shaper of our own rather than the one inside Mpdf, which is private. It reads the same
		// font cache - ServiceFactory puts it below the temp directory, and a shaper pointed anywhere
		// else finds no GDEF data and shapes nothing - and the same CurrentFont, and holds the
		// Coverage and GDEF memoisation the renderer's would, so the runs after the first are shaped
		// the way a document's would be.
		$otl = new Otl($mpdf, $this->fontCache());

		$capture = '';
		foreach ($this->runs($mpdf) as $label => $codes) {
			$capture .= sprintf("=== %s ===\n", $label);
			foreach (self::$settings as $setting => $applied) {
				list($useOtl, $mpdf->OTLtags) = $applied;
				$capture .= $this->shape($otl, $codes, $useOtl, $setting);
			}
		}

		$mpdf->cleanup();

		return $capture;
	}

	/**
	 * The fixed runs, then the font's own characters.
	 */
	private function runs(Mpdf $mpdf)
	{
		$runs = self::$runs;
		$characters = $this->characters($mpdf->CurrentFont['glyphIDtoUni']);

		// Spanning stride rather than the first RUNS_PER_FONT * RUN_LENGTH characters, which on a text
		// font would be the Latin block and nothing else
		$wanted = self::RUNS_PER_FONT * self::RUN_LENGTH;
		$stride = max(1, (int) floor(count($characters) / $wanted));

		$sampled = [];
		for ($i = 0; $i < count($characters); $i += $stride) {
			$sampled[] = $characters[$i];
		}

		foreach (array_chunk($sampled, self::RUN_LENGTH) as $n => $run) {
			if ($n >= self::RUNS_PER_FONT) {
				break;
			}
			$runs[sprintf('font characters %d', $n)] = $run;
		}

		return $runs;
	}

	/**
	 * One shaped run: what went in, what came out, the group each character ended up in, and any
	 * positioning. Written on one line per setting so that a diff points at the run that moved.
	 */
	private function shape(Otl $otl, array $codes, $useOtl, $setting)
	{
		$in = '';
		foreach ($codes as $code) {
			$in .= UtfString::code2utf($code);
		}

		$out = $otl->applyOTL($in, $useOtl);

		$line = sprintf(
			'%-16s  %s  =>  %s',
			$setting,
			$this->hex($codes),
			$this->hex(array_map(function ($char) {
				return $char['uni'];
			}, isset($otl->OTLdata['char_data']) ? $otl->OTLdata['char_data'] : []))
		);

		if (!empty($otl->OTLdata['group'])) {
			$line .= '  group=' . $otl->OTLdata['group'];
		}

		if (!empty($otl->OTLdata['GPOSinfo'])) {
			$line .= '  gpos=' . json_encode($otl->OTLdata['GPOSinfo'], JSON_UNESCAPED_SLASHES);
		}

		// The return value is the reassembled string, and should be the characters listed above it.
		// Said rather than assumed: phase 11 builds it from the same char_data, and a split that broke
		// one and not the other would otherwise go unseen.
		if ($out !== $this->utf8($otl)) {
			$line .= '  RETURNED ' . bin2hex($out);
		}

		return $line . "\n";
	}

	/**
	 * @param Otl $otl A shaper that has just run
	 *
	 * @return string The text its char_data now spells, which is what phase 11 reassembles
	 */
	private function utf8(Otl $otl)
	{
		$string = '';
		foreach (isset($otl->OTLdata['char_data']) ? $otl->OTLdata['char_data'] : [] as $char) {
			$string .= UtfString::code2utf($char['uni']);
		}

		return $string;
	}

	/**
	 * @param array $codes Unicode code points
	 *
	 * @return string Them as space-separated hex, or "-" for none
	 */
	private function hex(array $codes)
	{
		if (!$codes) {
			return '-';
		}

		$hex = [];
		foreach ($codes as $code) {
			$hex[] = sprintf('%04X', $code);
		}

		return implode(' ', $hex);
	}

	/**
	 * Every character this font has a glyph for, read out of the map the shaper itself works from.
	 *
	 * Not the cmap: that is format 4 only, so a font whose interesting characters live above the
	 * Basic Multilingual Plane - Takri and Wancho both do - would be sampled down to its space and
	 * its .notdef. glyphIDtoUni is three bytes per glyph id, and carries the Private Use Area codes
	 * the parser assigns under useOTL as well, which are real shaping input for the Arabic path.
	 */
	private function characters($glyphIDtoUni)
	{
		$characters = [];
		for ($i = 0; $i + 2 < strlen($glyphIDtoUni); $i += 3) {
			$code = (ord($glyphIDtoUni[$i]) << 16) + (ord($glyphIDtoUni[$i + 1]) << 8) + ord($glyphIDtoUni[$i + 2]);
			if ($code) {
				$characters[$code] = $code;
			}
		}
		ksort($characters);

		return array_values($characters);
	}

	/**
	 * The cache Mpdf will have written the font into, which is where ServiceFactory puts it
	 */
	private function fontCache()
	{
		return new FontCache(new \Mpdf\Cache($this->tmpDir . '/mpdf/ttfontdata'));
	}
}
