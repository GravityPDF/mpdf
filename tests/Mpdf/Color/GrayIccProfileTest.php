<?php

namespace Mpdf\Color;

/**
 * The grey ICC profile mPDF generates, read back field by field as ICC.1:2001-04 lays it out
 */
class GrayIccProfileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $profile;

	/**
	 * Builds the profile
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->profile = GrayIccProfile::build();
	}

	/**
	 * A version 2.1 display profile from grey to XYZ under D50, which says how long it is
	 */
	public function testTheHeaderDescribesAGreyDisplayProfile()
	{
		$header = unpack('Nsize/Ncmm/Nversion/a4class/a4space/a4pcs', substr($this->profile, 0, 24));

		$this->assertSame(strlen($this->profile), $header['size']);
		$this->assertSame(0x02100000, $header['version']);
		$this->assertSame('mntr', $header['class']);
		$this->assertSame('GRAY', $header['space']);
		$this->assertSame('XYZ ', $header['pcs']);
		$this->assertSame('acsp', substr($this->profile, 36, 4));
		$this->assertSame([0.9642, 1.0, 0.8249], $this->xyz(substr($this->profile, 68, 12)), 'the illuminant, D50');
	}

	/**
	 * The four tags a monochrome profile needs, each within the profile and on a four-byte boundary
	 */
	public function testTheTagTableHoldsTheTagsAMonochromeProfileNeeds()
	{
		$tags = $this->tags();

		$this->assertSame(['desc', 'cprt', 'wtpt', 'kTRC'], array_keys($tags));
		foreach ($tags as $signature => $tag) {
			$this->assertSame(0, $tag['offset'] % 4, $signature);
			$this->assertLessThanOrEqual(strlen($this->profile), $tag['offset'] + $tag['size'], $signature);
		}

		$this->assertStringStartsWith('desc', $this->tag('desc'));
		$this->assertStringContainsString("Gray, sRGB tone curve (mPDF)\0", $this->tag('desc'));
		$this->assertStringStartsWith('text', $this->tag('cprt'));
		$this->assertSame('XYZ ', substr($this->tag('wtpt'), 0, 4));
		$this->assertSame([0.9642, 1.0, 0.8249], $this->xyz(substr($this->tag('wtpt'), 8)), 'the media white point, D50');
	}

	/**
	 * The tone curve is sRGB's, from black to white, so that grey g is the sRGB neutral (g, g, g)
	 */
	public function testTheToneCurveIsSrgbs()
	{
		$curve = $this->tag('kTRC');
		$this->assertSame('curv', substr($curve, 0, 4));

		$count = unpack('N', substr($curve, 8, 4))[1];
		$this->assertSame(GrayIccProfile::CURVE_ENTRIES, $count);
		$this->assertSame(12 + 2 * $count, strlen($curve));

		$entries = array_values(unpack('n*', substr($curve, 12)));
		$this->assertSame(0, $entries[0]);
		$this->assertSame(65535, $entries[$count - 1]);

		// The linear segment near black, and the power segment above it
		$this->assertSame((int) round(10 / 1023 / 12.92 * 65535), $entries[10]);
		$this->assertSame((int) round(pow((512 / 1023 + 0.055) / 1.055, 2.4) * 65535), $entries[512]);
	}

	/**
	 * Built the same each time, so that a document written twice is the same
	 */
	public function testTheProfileIsTheSameEachTime()
	{
		$this->assertSame($this->profile, GrayIccProfile::build());
	}

	/**
	 * @return array[] Each tag's offset and size, by signature, in the order of the tag table
	 */
	private function tags()
	{
		$count = unpack('N', substr($this->profile, 128, 4))[1];

		$tags = [];
		for ($i = 0; $i < $count; $i++) {
			$entry = unpack('a4signature/Noffset/Nsize', substr($this->profile, 132 + 12 * $i, 12));
			$tags[$entry['signature']] = ['offset' => $entry['offset'], 'size' => $entry['size']];
		}

		return $tags;
	}

	/**
	 * @param string $signature
	 *
	 * @return string The tag's data
	 */
	private function tag($signature)
	{
		$tag = $this->tags()[$signature];

		return substr($this->profile, $tag['offset'], $tag['size']);
	}

	/**
	 * @param string $data Three s15Fixed16Numbers
	 *
	 * @return float[] Them, to four places
	 */
	private function xyz($data)
	{
		return array_map(function ($value) {
			return round($value / 65536, 4);
		}, array_values(unpack('N3', $data)));
	}
}
