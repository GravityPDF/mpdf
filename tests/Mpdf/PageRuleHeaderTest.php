<?php

namespace Mpdf;

/**
 * An @page rule that names no header or footer leaves the one set with SetHeader(), SetFooter() and the like, and a
 * page whose rules name none takes back the one an earlier page's rule replaced. Only header and footer in a rule
 * replace or remove them, in either CSS mode.
 */
class PageRuleHeaderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Two pages of body text
	 */
	const BODY = '<p>One</p><pagebreak /><p>Two</p>';

	/**
	 * @var string[] The texts of the headers and footers the documents can draw
	 */
	private static $headers = ['Own header', 'Named header', 'Later header', 'Own footer'];

	/**
	 * The headers and footers drawn on each page, by the rules and body given
	 *
	 * @dataProvider pageRuleProvider
	 *
	 * @param string $css
	 * @param string $body
	 * @param string[][] $expected For each page, the headers and footers it draws
	 * @param string $cssMode
	 */
	public function testThePageRulesReplaceOnlyTheHeadersTheyName($css, $body, array $expected, $cssMode)
	{
		$this->assertSame($expected, $this->draw($css, $body, ['cssMode' => $cssMode]));
	}

	/**
	 * Rules that name no header or footer, rules that name one for some pages, and one that turns the header off, in
	 * both CSS modes
	 *
	 * @return array[]
	 */
	public function pageRuleProvider()
	{
		$own = ['Own header', 'Own footer'];
		$named = '<htmlpageheader name="named"><p>Named header</p></htmlpageheader>';

		$cases = [
			'a rule that only sets a margin' => [
				'@page { margin-top: 30mm; }',
				self::BODY,
				[$own, $own],
			],
			'an empty :first rule' => [
				'@page :first { }',
				self::BODY,
				[$own, $own],
			],
			'a :first rule that blanks both' => [
				'@page { margin-top: 30mm; } @page :first { header: _blank; footer: _blank; }',
				self::BODY,
				[[], $own],
			],
			'a :first rule that names a header' => [
				'@page :first { header: html_named; }',
				$named . self::BODY,
				[['Named header', 'Own footer'], $own],
			],
			'a named page, then the unnamed one' => [
				'@page { margin-top: 30mm; } @page chapter { header: html_named; }',
				$named . '<p>One</p><div style="page: chapter"><p>Two</p></div><div style="page: auto"><p>Three</p></div>',
				[$own, ['Named header', 'Own footer'], $own],
			],
			'a rule that turns the header off' => [
				'@page { header: off; }',
				self::BODY,
				[['Own footer'], ['Own footer']],
			],
		];

		$data = [];
		foreach ($cases as $name => $case) {
			foreach ([CssMode::STANDARD, CssMode::LEGACY] as $cssMode) {
				$data[$name . ' (' . $cssMode . ')'] = array_merge($case, [$cssMode]);
			}
		}

		return $data;
	}

	/**
	 * A header set while a rule's header is in force is kept on the next page, rather than the one the rule replaced
	 */
	public function testAHeaderSetUnderARuleIsKept()
	{
		$drawn = $this->draw(
			'@page :first { header: html_named; }',
			'<htmlpageheader name="named"><p>Named header</p></htmlpageheader><htmlpageheader name="later"><p>Later header</p></htmlpageheader>'
			. '<p>One</p><sethtmlpageheader name="later" value="on" /><pagebreak /><p>Two</p>'
		);

		$this->assertSame(['Later header', 'Own footer'], $drawn[1]);
	}

	/**
	 * With mirrored margins a rule for left pages blanks the even pages' header, and the odd pages keep theirs
	 */
	public function testMirroredPagesKeepTheirOwnHeaders()
	{
		$drawn = $this->draw('@page { margin-top: 30mm; } @page :left { header: _blank; }', self::BODY . '<pagebreak /><p>Three</p>', ['mirrorMargins' => true]);

		$this->assertSame([['Own header', 'Own footer'], ['Own footer'], ['Own header', 'Own footer']], $drawn);
	}

	/**
	 * Sets a header and footer in code, writes the body after the @page rules and lists the headers and footers each
	 * page draws
	 *
	 * @param string $css
	 * @param string $body
	 * @param array $config
	 *
	 * @return string[][] For each page, the texts of $headers found in its content stream
	 */
	private function draw($css, $body, array $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->SetHeader('Own header');
		$mpdf->SetFooter('Own footer');
		$mpdf->WriteHTML('<style>' . $css . '</style>' . $body);

		$drawn = [];
		foreach ($this->pageContents($this->output($mpdf)) as $stream) {
			$drawn[] = array_values(array_filter(self::$headers, function ($text) use ($stream) {
				return strpos($stream, '(' . $text . ')') !== false;
			}));
		}

		return $drawn;
	}

}
