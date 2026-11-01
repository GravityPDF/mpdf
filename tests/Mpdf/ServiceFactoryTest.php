<?php

namespace Mpdf;

use Mpdf\Container\SimpleContainer;
use Mpdf\Fonts\FontFileFinder;

class ServiceFactoryTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function testFontFileFinderFromContainer()
	{
		$finder = new RecordingFontFileFinder(__DIR__ . '/../../ttfonts');
		$fontData = (new Config\FontVariables())->getDefaults()['fontdata'];

		$mpdf = new Mpdf(
			['fontdata' => $fontData + ['angerthas' => ['R' => 'angerthas.ttf']]],
			new SimpleContainer(['fontFileFinder' => $finder])
		);

		// The added directory reaches the container's finder, so it can find a font outside the default fontDir
		$mpdf->AddFontDirectory(__DIR__ . '/../data/ttf');
		$mpdf->SetFont('angerthas');

		$this->assertContains('angerthas.ttf', $finder->lookups);
		$this->assertSame('angerthas', $mpdf->FontFamily);
	}

	/**
	 * @dataProvider defaultFontFileFinderProvider
	 */
	public function testDefaultFontFileFinder($container)
	{
		$mpdf = new Mpdf([], $container);

		$property = new \ReflectionProperty(Mpdf::class, 'fontFileFinder');
		$property->setAccessible(true);

		$this->assertSame(FontFileFinder::class, get_class($property->getValue($mpdf)));
	}

	public function defaultFontFileFinderProvider()
	{
		return [
			'no container key' => [new SimpleContainer([])],
			'no container' => [null],
		];
	}

}
