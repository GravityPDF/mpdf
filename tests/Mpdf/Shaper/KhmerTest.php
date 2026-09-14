<?php

namespace Mpdf\Shaper;

use Mpdf\TextRecordingMpdf;

/**
 * Khmer is shaped by the Indic shaper, with a handful of blocks of its own. One of them, KHMER_FIX_2,
 * looks through the cluster for a Coeng followed by a Ra so it can move the pair in front of the base
 * consonant, and it read one position past the end of the cluster to do it.
 *
 * KhmerOS is the font throughout: the glyphs it substitutes in have no codepoints of their own, so
 * they are mapped into the Private Use Area as the subset is built, in the order they are first used.
 */
class KhmerTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1780 KHMER LETTER KA */
	const KA = 0x1780;

	/** U+179A KHMER LETTER RO */
	const RO = 0x179A;

	/** U+17D2 KHMER SIGN COENG, which subscripts the consonant after it */
	const COENG = 0x17D2;

	/** The subscript KA that KhmerOS's 'blwf' substitutes for Coeng + KA */
	const COENG_KA = 0xE000;

	/**
	 * A Coeng with nothing after it ends the run, so the read past the cluster was a read off the
	 * array: two warnings and a deprecation per run, and fatal to anything writing its output to
	 * stdout - utils/font_dump.php draws every codepoint of a font in a cell of its own, and so ended
	 * with "Data has already been sent to output" rather than a PDF.
	 */
	public function testACoengAtTheEndOfARunIsShapedWithoutReadingPastIt()
	{
		$raised = [];

		set_error_handler(function ($no, $message, $file) use (&$raised) {
			if (false !== strpos($file, 'Indic.php')) {
				$raised[] = $message;
			}

			return true;
		});

		$drawn = $this->drawn([self::COENG]);

		restore_error_handler();

		$this->assertSame([], $raised);
		$this->assertSame([0x25CC, self::COENG], $drawn);
	}

	/**
	 * The cluster the Khmer syllable pattern matches takes at most four Coeng groups, so a fifth
	 * leaves the Coeng as the last character of its cluster. Where the character after it is a Ra it
	 * belongs to the next cluster, and marking the pair for pre-base positioning moved that Coeng in
	 * front of the base consonant of a cluster the Ra is not even in - the Coeng was drawn first,
	 * before the KA it subscripts.
	 */
	public function testACoengThatEndsAClusterIsNotMovedInFrontOfTheBase()
	{
		$drawn = $this->drawn([
			self::KA, self::COENG, self::KA, self::COENG, self::KA,
			self::COENG, self::KA, self::COENG, self::KA, self::COENG,
			self::RO,
		]);

		$this->assertSame([
			self::KA, self::COENG_KA, self::COENG_KA, self::COENG_KA, self::COENG_KA,
			self::COENG, self::RO,
		], $drawn);
	}

	/**
	 * The same five Coeng groups without the Ra after them, which is what the cluster reads as when
	 * the character past its end is anything else. This is the order the run above now takes too.
	 */
	public function testAClusterEndingInACoengIsDrawnInTheOrderItWasWritten()
	{
		$drawn = $this->drawn([
			self::KA, self::COENG, self::KA, self::COENG, self::KA,
			self::COENG, self::KA, self::COENG, self::KA, self::COENG,
		]);

		$this->assertSame([
			self::KA, self::COENG_KA, self::COENG_KA, self::COENG_KA, self::COENG_KA,
			self::COENG,
		], $drawn);
	}

	/**
	 * A Coeng and Ra inside one cluster are still moved in front of the base, which is what the block
	 * is for: the Coeng + RO pair is drawn first, and the KA it was written after follows it.
	 */
	public function testACoengAndRaInsideAClusterAreMovedInFrontOfTheBase()
	{
		$drawn = $this->drawn([self::KA, self::COENG, self::RO]);

		$this->assertSame([0xE01B, self::KA], $drawn);
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:khmeros">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
