<?php

namespace Mpdf;

/**
 * A background image named in a stylesheet's url() loads whatever quoting, spacing and characters the URL is written
 * with, as it does in a browser.
 */
class StylesheetUrlTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Bootstrap 5's form-select arrow: double quoted, with single quotes, spaces and a "://" inside
	 */
	const BOOTSTRAP_ARROW = "data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e";

	/**
	 * @var string A directory holding "sub dir/bg (1).jpg"
	 */
	private $dir;

	/**
	 * Copy a test image to a name with a space and parentheses in it
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-url-' . uniqid() . '/';
		mkdir($this->dir . 'sub dir', 0777, true);
		copy(__DIR__ . '/../data/img/bg.jpg', $this->dir . 'sub dir/bg (1).jpg');
	}

	/**
	 * Remove the copied image
	 */
	protected function tear_down()
	{
		unlink($this->dir . 'sub dir/bg (1).jpg');
		rmdir($this->dir . 'sub dir');
		rmdir($this->dir);

		parent::tear_down();
	}

	/**
	 * The background image loads, as a raster image or, for an SVG, a form XObject
	 *
	 * @dataProvider urlProvider
	 *
	 * @param string $url
	 */
	public function testBackgroundImageLoads($url)
	{
		foreach (['background-image: ' . $url, 'background: ' . $url . ' no-repeat'] as $declaration) {
			$mpdf = new Mpdf();
			$mpdf->SetBasePath($this->dir);
			$mpdf->WriteHTML('<style>div.bg { height: 20mm; ' . $declaration . '; }</style><div class="bg">x</div>');

			$this->assertSame(1, count($mpdf->images) + count($mpdf->formobjects), $declaration);
		}
	}

	/**
	 * url() values that name an image
	 *
	 * @return array
	 */
	public function urlProvider()
	{
		return [
			'double quoted, space and parentheses' => ['url("sub dir/bg (1).jpg")'],
			'single quoted, space and parentheses' => ["url('sub dir/bg (1).jpg')"],
			'percent-encoded' => ['url(sub%20dir/bg%20%281%29.jpg)'],
			'whitespace inside the parentheses' => ['url(  "sub dir/bg (1).jpg"  )'],
			'unquoted, whitespace inside the parentheses' => ['url(  sub%20dir/bg%20%281%29.jpg  )'],
			'SVG data URI with single quotes inside' => ['url("' . self::BOOTSTRAP_ARROW . '")'],
			'SVG data URI with double quotes inside' => ["url('" . str_replace("'", '"', self::BOOTSTRAP_ARROW) . "')"],
			'SVG data URI with a ;utf8 parameter' => ['url("' . str_replace('svg+xml,', 'svg+xml;utf8,', self::BOOTSTRAP_ARROW) . '")'],
		];
	}

}
