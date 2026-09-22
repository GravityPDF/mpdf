<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;
use Mpdf\Ua\UaPolicy;

/**
 * A script URL disguised by invisible characters, entities, percent-encoding, case
 * or an unusual scheme is still refused, in a link and in an image map area.
 *
 * @group pdfua
 * @group security
 */
class UrlSchemeBypassTest extends PdfUaTestCase
{

	/**
	 * A disguised script URL is refused by the policy.
	 *
	 * @dataProvider bypassedSchemeProvider
	 */
	public function testBypassedSchemeIsBlocked($href)
	{
		$this->assertTrue(
			UaPolicy::isPolicyBlockedHref($href),
			'Expected policy block for: ' . var_export($href, true)
		);
	}

	/**
	 * @return array[] Script URLs, each disguised a different way
	 */
	public function bypassedSchemeProvider()
	{
		return [
			// Invisible characters before the scheme
			'NBSP'             => ["\xC2\xA0javascript:alert(1.0)"],
			'NBSP-vbscript'    => ["\xC2\xA0vbscript:msgbox(1)"],
			'ZWSP'             => ["\xE2\x80\x8Bjavascript:alert(1.0)"],
			'ZWNJ'             => ["\xE2\x80\x8Cjavascript:alert(1.0)"],
			'ZWJ'              => ["\xE2\x80\x8Djavascript:alert(1.0)"],
			'BOM'              => ["\xEF\xBB\xBFjavascript:alert(1.0)"],
			'NULL'             => ["\x00javascript:alert(1.0)"],
			'multiple-mixed'   => ["\xC2\xA0\xE2\x80\x8B \tjavascript:alert(1.0)"],

			// Entity-encoded scheme
			'tab-entity'       => ['&Tab;javascript:alert(1.0)'],
			'newline-entity'   => ['&NewLine;javascript:alert(1.0)'],
			'first-letter-hex' => ['&#x6A;avascript:alert(1.0)'],
			'first-letter-dec' => ['&#106;avascript:alert(1.0)'],
			'colon-entity'     => ['javascript&#58;alert(1.0)'],

			// Percent-encoded scheme
			'percent-letter'   => ['%6Aavascript:alert(1.0)'],
			'percent-prefix'   => ['%20javascript:alert(1.0)'],

			// Characters inside the scheme name
			'newline-in-scheme' => ["java\nscript:alert(1.0)"],
			'space-in-scheme'   => ['j a v a s c r i p t :alert(1.0)'],
			'tab-in-scheme'     => ["java\tscript:alert(1.0)"],
			'nul-in-scheme'     => ["java\x00script:alert(1.0)"],

			// Upper and mixed case
			'upper-scheme'     => ['JAVASCRIPT:alert(1.0)'],
			'mixed-scheme'     => ['JaVaScRiPt:alert(1.0)'],
			'upper-vbscript'   => ['VBSCRIPT:msgbox(1)'],
			'upper-livescript' => ['LIVESCRIPT:alert(1.0)'],

			// Other script schemes
			'livescript'       => ['livescript:alert(1.0)'],
			'mocha'            => ['mocha:alert(1.0)'],
			'vbs'              => ['vbs:msgbox(1)'],
			'view-source'      => ['view-source:javascript:alert(1)'],

			// data: URLs of a type that can run script
			'data-html'        => ['data:text/html,<script>alert(1)</script>'],
			'data-html-base64' => ['data:text/html;base64,PHNjcmlwdD4='],
			'data-x-js'        => ['data:application/x-javascript,alert(1)'],
			'data-js'          => ['data:application/javascript,alert(1)'],
			'data-xhtml'       => ['data:application/xhtml+xml,<x/>'],
			'data-svg'         => ['data:image/svg+xml,<svg></svg>'],
			'data-html-mixed'  => ['DATA:Text/HTML,foo'],
		];
	}

	/**
	 * An upper-case scheme is still refused under a Turkish locale, where strtolower()
	 * before PHP 8 turned I into a dotless i.
	 */
	public function testUppercaseSchemeBlockedUnderTurkishLocale()
	{
		$saved = setlocale(LC_CTYPE, '0');
		$applied = setlocale(LC_CTYPE, 'tr_TR.UTF-8', 'tr_TR', 'turkish');
		try {
			$this->assertTrue(UaPolicy::isPolicyBlockedHref('JAVASCRIPT:alert(1)'));
			$this->assertTrue(UaPolicy::isPolicyBlockedHref('VBSCRIPT:msgbox(1)'));
			$this->assertTrue(UaPolicy::isPolicyBlockedHref('LiveScript:alert(1)'));
		} finally {
			if ($applied !== false && $saved !== false) {
				setlocale(LC_CTYPE, $saved);
			}
		}
	}

	/**
	 * An ordinary URL is let through by the policy.
	 *
	 * @dataProvider permittedSchemeProvider
	 */
	public function testPermittedHrefIsNotBlocked($href)
	{
		$this->assertFalse(
			UaPolicy::isPolicyBlockedHref($href),
			'Expected policy pass for: ' . var_export($href, true)
		);
	}

	/**
	 * @return array[] URLs that run no script
	 */
	public function permittedSchemeProvider()
	{
		return [
			'http'              => ['http://example.com/path'],
			'https'             => ['https://example.com/path?q=1#frag'],
			'mailto'            => ['mailto:foo@example.com'],
			'tel'               => ['tel:+1234567890'],
			'sms'               => ['sms:+1234567890'],
			'ftp'               => ['ftp://example.com/file'],
			'file'              => ['file:///etc/hosts'],
			'fragment'          => ['#section'],
			'relative'          => ['./page.html'],
			'data-png'          => ['data:image/png;base64,AAAA'],
			'data-jpeg'         => ['data:image/jpeg;base64,AAAA'],
			'data-text'         => ['data:text/plain;base64,SGVsbG8='],
			'data-css'          => ['data:text/css,body{}'],
			'fragment-with-js'  => ['https://safe.example.com/#javascript:fake'],
		];
	}

	/**
	 * Runs $body with HTTP_HOST unset.
	 *
	 * With a host set, GetFullPath() turns a URL with a leading invisible character into
	 * a relative path under that host, which would hide the script URL from the policy.
	 *
	 * @param callable $body
	 */
	private function withCleanHttpHost(callable $body)
	{
		$savedHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		unset($_SERVER['HTTP_HOST']);
		try {
			$body();
		} finally {
			if ($savedHost !== null) {
				$_SERVER['HTTP_HOST'] = $savedHost;
			}
		}
	}

	/**
	 * A link to a script URL behind a no-break space throws in strict mode.
	 */
	public function testNbspPrefixThrowsInStrictMode()
	{
		$this->withCleanHttpHost(function () {
			$this->expectException(\Mpdf\MpdfException::class);
			$mpdf = $this->makeMpdf();
			$this->getOutput(
				$mpdf,
				'<p><a href="' . "\xC2\xA0" . 'javascript:alert(1.0)">x</a></p>'
			);
		});
	}

	/**
	 * A link to a script URL with an entity-encoded scheme throws in strict mode.
	 */
	public function testEntityEncodedSchemeThrowsInStrictMode()
	{
		$this->withCleanHttpHost(function () {
			$this->expectException(\Mpdf\MpdfException::class);
			$mpdf = $this->makeMpdf();
			$this->getOutput(
				$mpdf,
				'<p><a href="&#x6A;avascript:alert(1.0)">x</a></p>'
			);
		});
	}

	/**
	 * A link to a text/html data: URL throws in strict mode.
	 */
	public function testDataTextHtmlThrowsInStrictMode()
	{
		$this->withCleanHttpHost(function () {
			$this->expectException(\Mpdf\MpdfException::class);
			$mpdf = $this->makeMpdf();
			$this->getOutput(
				$mpdf,
				'<p><a href="data:text/html,<script>alert(1)</script>x.">click</a></p>'
			);
		});
	}

	/**
	 * A link to a livescript: URL throws in strict mode.
	 */
	public function testLivescriptThrowsInStrictMode()
	{
		$this->withCleanHttpHost(function () {
			$this->expectException(\Mpdf\MpdfException::class);
			$mpdf = $this->makeMpdf();
			$this->getOutput($mpdf, '<p><a href="livescript:alert(1.0)">x</a></p>');
		});
	}

	/**
	 * A link to a script URL behind a no-break space is dropped in auto mode.
	 */
	public function testNbspPrefixStrippedInAutoMode()
	{
		$savedHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		unset($_SERVER['HTTP_HOST']);
		try {
			$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
			$output = $this->getOutput(
				$mpdf,
				'<p><a href="' . "\xC2\xA0" . 'javascript:alert(1.0)">x</a></p>'
			);
			$this->assertStringNotContainsString('javascript:alert(1.0)', $output);
			$this->assertStringNotContainsString('/S /URI', $output);
			$this->assertStringNotContainsString('/S /Link', $output);
		} finally {
			if ($savedHost !== null) {
				$_SERVER['HTTP_HOST'] = $savedHost;
			}
		}
	}

	/**
	 * An image map area linking to a script URL throws in strict mode.
	 */
	public function testAreaJavascriptHrefThrowsInStrictMode()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf = $this->makeMpdf();
		$html = '<map name="m"><area shape="rect" coords="0,0,10,10" '
			. 'href="javascript:alert(1.0)" alt="bad"></map>'
			. '<img src="' . __DIR__ . '/../../../data/img/checkerboard.png" usemap="#m">';
		$this->getOutput($mpdf, $html);
	}

	/**
	 * An image map area linking to a script URL is dropped with a warning in auto mode.
	 */
	public function testAreaJavascriptHrefStrippedInAutoMode()
	{
		$savedHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		unset($_SERVER['HTTP_HOST']);
		try {
			$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
			$html = '<map name="m"><area shape="rect" coords="0,0,10,10" '
				. 'href="javascript:alert(1.0)" alt="bad"></map>'
				. '<p>after</p>';
			$output = $this->getOutput($mpdf, $html);
			$this->assertStringNotContainsString('javascript:alert(1.0)', $output);
			$this->assertStringNotContainsString('/S /URI', $output);

			$warnings = $mpdf->getPdfUaWarnings();
			$found = false;
			foreach ($warnings as $w) {
				if (stripos($w, 'javascript:alert(1.0)') !== false) {
					$found = true;
					break;
				}
			}
			$this->assertTrue($found, 'Expected a warning citing the stripped <area> href.');
		} finally {
			if ($savedHost !== null) {
				$_SERVER['HTTP_HOST'] = $savedHost;
			}
		}
	}
}
