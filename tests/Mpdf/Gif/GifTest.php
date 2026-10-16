<?php

namespace Mpdf\Gif;

/**
 * The GIF decoder mPDF falls back on without GD, against ImageMagick, over one 7 x 5 picture saved
 * three ways: with a global colour table and a transparent colour, interlaced, and with only a local
 * colour table, and over a three-frame animation. Each .rgb beside a fixture is
 * `magick <gif> -alpha off -depth 8 RGB:<rgb>`, with `<gif>[n]` for frame n of the animation.
 */
class GifTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const DIR = __DIR__ . '/../../data/img/gif/';

	/**
	 * Every pixel, looked up in the colour table the frame is drawn from, is the colour ImageMagick
	 * reads, and the frame reports the transparency its graphic control extension gave it
	 *
	 * @dataProvider images
	 *
	 * @param string         $name        The fixture, a GIF with the ImageMagick RGB dump of it beside it
	 * @param bool           $interlaced  Whether the frame is stored interlaced
	 * @param bool           $local       Whether the frame carries its own colour table
	 * @param int|false|null $transparent The colour index made transparent, false for a graphic control
	 *                                    extension that makes none, or null for no extension at all
	 */
	public function testEveryPixelIsWhatImageMagickReads($name, $interlaced, $local, $transparent)
	{
		$data = file_get_contents(self::DIR . $name . '.gif');

		$gif = new Gif();
		$this->assertTrue($gif->loadFile($data, 0));

		$this->assertSame(7, $gif->m_gfh->m_nWidth);
		$this->assertSame(5, $gif->m_gfh->m_nHeight);
		$this->assertSame($interlaced, $gif->m_img->m_gih->m_bInterlace);
		$this->assertSame($local, $gif->m_img->m_gih->m_bLocalClr);

		// ImageProcessor::processGif() reads the transparency through isset(), so a frame without a
		// graphic control extension has to leave it null rather than false
		if ($transparent === null) {
			$this->assertFalse(isset($gif->m_img->m_bTrans));
		} elseif ($transparent === false) {
			$this->assertFalse($gif->m_img->m_bTrans);
		} else {
			$this->assertTrue($gif->m_img->m_bTrans);
			$this->assertSame($transparent, $gif->m_img->m_nTrans);
		}

		$this->assertSame(bin2hex(file_get_contents(self::DIR . $name . '.rgb')), bin2hex($this->rgb($gif)));
	}

	/**
	 * @return array[] Each fixture's name, whether it is interlaced, whether it has a local colour
	 *                 table, and its transparency
	 */
	public function images()
	{
		return [
			'global colour table, transparent' => ['global', false, false, 4],
			'interlaced' => ['interlaced', true, false, null],
			'local colour table' => ['local', false, true, false],
		];
	}

	/**
	 * Any frame of an animation loads, not only the first. The fixture is three 5 x 3 frames, the second and
	 * third with a local colour table, made by
	 * `magick -size 5x3 gradient:red-blue \( -size 3x5 gradient:white-black -rotate 90 \) \( -size 5x3 gradient:lime-yellow \) -set delay 10 -loop 0 animated.gif`.
	 * The decoder has already read the block terminator when it meets the End code of the first and
	 * third frames, but not of the second.
	 *
	 * @dataProvider frames
	 *
	 * @param int  $frame The frame to load
	 * @param bool $local Whether the frame carries its own colour table
	 */
	public function testEveryFrameOfAnAnimationIsWhatImageMagickReads($frame, $local)
	{
		$data = file_get_contents(self::DIR . 'animated.gif');

		$gif = new Gif();
		$this->assertTrue($gif->loadFile($data, $frame));
		$this->assertSame($local, $gif->m_img->m_gih->m_bLocalClr);

		$this->assertSame(bin2hex(file_get_contents(self::DIR . 'animated-' . $frame . '.rgb')), bin2hex($this->rgb($gif)));
	}

	/**
	 * @return array[] Each frame of the animation, and whether it has a local colour table
	 */
	public function frames()
	{
		return [
			'first frame' => [0, false],
			'second frame' => [1, true],
			'third frame' => [2, true],
		];
	}

	/**
	 * Asking for a frame past the last one reaches the trailer and fails
	 */
	public function testThereIsNoFrameAfterTheLast()
	{
		$data = file_get_contents(self::DIR . 'animated.gif');

		$gif = new Gif();
		$this->assertFalse($gif->loadFile($data, 3));
	}

	/**
	 * @param Gif $gif A loaded GIF
	 *
	 * @return string The loaded frame's pixels as RGB, each index looked up in the colour table it is drawn from
	 */
	private function rgb(Gif $gif)
	{
		$table = $gif->m_img->m_gih->m_bLocalClr ? $gif->m_img->m_gih->m_colorTable : $gif->m_gfh->m_colorTable;
		$palette = $table->toString();

		$rgb = '';
		foreach (str_split($gif->m_img->m_data) as $index) {
			$rgb .= substr($palette, ord($index) * 3, 3);
		}

		return $rgb;
	}

}
