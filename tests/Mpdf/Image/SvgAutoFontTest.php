<?php

namespace Mpdf\Image;

use Mpdf\Mpdf;
use Mpdf\PageStreams;

/**
 * svgAutoFont sends the picture's text through Svg::markScriptToLang(), which marks each run with the
 * language of the script it is written in so that the run can be given a font covering it.
 */
class SvgAutoFontTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const PICTURE = '<svg width="40mm" height="20mm" xmlns="http://www.w3.org/2000/svg"><text x="2" y="15">%s</text></svg>';

	/**
	 * markScriptToLang() splits the document at its tags and reads the tag before each run of text to
	 * decide whether the run is drawn, so the first piece, which has no tag before it, has to be
	 * answered for rather than looked up.
	 *
	 * The picture is one whose opening tag is wrapped, because that is what leaves a first piece with
	 * anything in it: ImageSVG() strips what precedes <svg only when a > or a space follows the name.
	 */
	public function testAutoFontingAnSvgReadsNothingOffTheStartOfTheDocument()
	{
		$raised = [];

		set_error_handler(function ($number, $message, $file, $line) use (&$raised) {
			// Filtered to the picture so a diagnostic raised elsewhere in the render cannot fail this
			if (false !== strpos($file, 'Svg.php')) {
				$raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			}

			return true;
		});

		$mpdf = $this->mpdf(['mode' => 'utf-8']);
		$mpdf->svgAutoFont = true;

		try {
			$mpdf->WriteHTML('<img src="' . __DIR__ . '/../../data/img/wrapped-open-tag.svg" />');
			$pdf = $mpdf->Output('', 'S');
		} finally {
			restore_error_handler();
			$mpdf->cleanup();
		}

		$this->assertSame([], $raised);
		$this->assertStringContainsString('/Subtype /Form', $pdf, 'the picture is drawn');
	}

	/**
	 * The tag before a run that follows a nested element is the closing </tspan>, which names no
	 * element that draws text, though the run belongs to the <text> around it just as the run before
	 * it does. Declining to mark a run is not a reason to drop it.
	 */
	public function testAPictureDrawsTheTextFollowingANestedTspan()
	{
		$picture = sprintf(self::PICTURE, 'Hello <tspan fill="red">red</tspan> world');

		$this->assertSame('Hello red world', $this->drawnPictureText($picture));
	}

	public function dataScripts()
	{
		return [
			'Arabic' => ["A \xD8\xA7 \xD8\xA8"],
			'Hebrew' => ["A \xD7\x90"],
		];
	}

	/**
	 * A picture's text passes no seam that records what it was drawn in, so this reads the fonts the
	 * document loaded, and measures them against the same text written as a paragraph rather than
	 * against a font by name, so it holds whichever font package answers for a script.
	 *
	 * autoArabic is left at its default, since it routes Arabic through a branch of its own that
	 * builds the language tag apart from the one every other script takes.
	 *
	 * @dataProvider dataScripts
	 *
	 * @param string $text
	 */
	public function testTextInAPictureIsGivenTheFontTheSameTextInADocumentIsGiven($text)
	{
		$inADocument = $this->fontsBeyondLatin('<p>%s</p>', $text);

		$this->assertNotEmpty($inADocument, 'the text is given a font of its own in a document');
		$this->assertSame($inADocument, $this->fontsBeyondLatin(self::PICTURE, $text));
	}

	/**
	 * The text a picture draws, joined out of the runs it is written as and decoded from the UTF-16
	 * they are written in. Empty where the picture was not drawn at all.
	 *
	 * @param string $picture
	 *
	 * @return string
	 */
	private function drawnPictureText($picture)
	{
		$mpdf = $this->mpdf(['mode' => 'utf-8']);
		$mpdf->svgAutoFont = true;
		$mpdf->WriteHTML($picture);

		$pdf = $this->output($mpdf);

		if (!preg_match('/\/Subtype \/Form.*?stream\n(.*?)\nendstream/s', $pdf, $form)) {
			return '';
		}

		return mb_convert_encoding($this->drawnText($form[1]), 'UTF-8', 'UTF-16BE');
	}

	/**
	 * The fonts the given text costs over the Latin it is written beside, which are the fonts its
	 * script was marked up to reach.
	 *
	 * @param string $template The markup to hold the text, with one %s for it
	 * @param string $text
	 *
	 * @return string[]
	 */
	private function fontsBeyondLatin($template, $text)
	{
		$beyond = array_diff(
			$this->fontsOf(sprintf($template, $text)),
			$this->fontsOf(sprintf($template, 'A'))
		);
		sort($beyond);

		return $beyond;
	}

	/**
	 * @param string $html
	 *
	 * @return string[] The fonts the document loaded
	 */
	private function fontsOf($html)
	{
		$mpdf = $this->mpdf([
			'mode' => 'utf-8',
			'autoScriptToLang' => true,
			'autoLangToFont' => true,
			'svgAutoFont' => true,
		]);
		$mpdf->WriteHTML($html);

		$fonts = array_keys($mpdf->fonts);
		$mpdf->cleanup();

		return $fonts;
	}

}
