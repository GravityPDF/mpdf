<?php

namespace Mpdf\Gif;

/**
 * The GIF decoder mPDF falls back on without GD, against ImageMagick, over one 7 x 5 picture saved
 * three ways: with a global colour table and a transparent colour, interlaced, and with only a local
 * colour table. Each .rgb beside a fixture is `magick <gif> -alpha off -depth 8 RGB:<rgb>`.
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

		$table = $local ? $gif->m_img->m_gih->m_colorTable : $gif->m_gfh->m_colorTable;
		$palette = $table->toString();

		$actual = '';
		foreach (str_split($gif->m_img->m_data) as $index) {
			$actual .= substr($palette, ord($index) * 3, 3);
		}

		$this->assertSame(bin2hex(file_get_contents(self::DIR . $name . '.rgb')), bin2hex($actual));
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

}
