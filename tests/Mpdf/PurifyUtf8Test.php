<?php

namespace Mpdf;

class PurifyUtf8Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const MESSAGE = 'Invalid input characters. Did you set $mpdf->charset_in properly?';

	/**
	 * @dataProvider invalidHtmlProvider
	 *
	 * @param string $html
	 * @param string $logEntry
	 */
	public function testInvalidHtmlFinishesTheDocumentAndLogsTheBadBytes($html, $logEntry)
	{
		$this->assertSame(
			['exception' => null, 'log' => [$logEntry]],
			$this->writeHtmlInOwnProcess($html)
		);
	}

	/**
	 * @return array
	 */
	public function invalidHtmlProvider()
	{
		return [
			'2-byte sequence cut after 1 byte' => ["<p>\xC3", '[[#195]]'],
			'3-byte sequence cut after 1 byte' => ["<p>\xE2", '[[#226]]'],
			'3-byte sequence cut after 2 bytes' => ["<p>\xE2\x82", '[[#226]][[#130]]'],
			'4-byte sequence cut after 1 byte' => ["<p>\xF0", '[[#240]]'],
			'4-byte sequence cut after 2 bytes' => ["<p>\xF0\x9F", '[[#240]][[#159]]'],
			'4-byte sequence cut after 3 bytes' => ["<p>\xF0\x9F\x98", '[[#240]][[#159]][[#152]]'],
			'cut sequence after a valid character' => ["\xC3\xA9<p>\xE2", '[[#226]]'],
			'cut sequence after a byte order mark' => ["\xEF\xBB\xBF<p>\xE2", '[[#226]]'],
			'run of cut sequences' => ["<p>\xC3\xE2\x82\xF0\x9F\x98", '[[#195]][[#226]][[#130]][[#240]][[#159]][[#152]]'],
			'nothing but a cut sequence' => ["\xE2", '[[#226]]'],
			'lead byte past the end of Unicode' => ["<p>\xF5", '[[#245]]'],
			'bad byte before the end' => ["<p>\xE2 ok</p>", '[[#226]]'],
			'stray continuation byte' => ["<p>\x80", '[[#128]]'],
			'overlong sequence' => ["<p>\xC0\x80", '[[#192]][[#128]]'],
			'surrogate' => ["<p>\xED\xA0\x80", '[[#237]][[#160]][[#128]]'],
			'code point past the end of Unicode' => ["<p>\xF4\x90\x80\x80", '[[#244]][[#144]][[#128]][[#128]]'],
			'5-byte sequence' => ["<p>\xF8\x88\x80\x80\x80", '[[#248]][[#136]][[#128]][[#128]][[#128]]'],
		];
	}

	/**
	 * Only the bytes that cannot be read are replaced.
	 */
	public function testTheTextAroundTheBadBytesIsDrawn()
	{
		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML("<p>before \xE2 after</p>");

		$this->assertSame(['before ? after'], $mpdf->drawnText);
	}

	/**
	 * Text passed straight to the drawing calls has its bad bytes logged and replaced.
	 */
	public function testInvalidTextIsLoggedAndReplaced()
	{
		$logger = new TestLogger();
		$mpdf = new Mpdf();
		$mpdf->setLogger($logger);

		$this->assertSame('before ? after', $mpdf->purify_utf8_text("before \xE2 after"));
		$this->assertSame(['[[#226]]'], array_column($logger->records, 'message'));
	}

	/**
	 * @dataProvider textCalls
	 *
	 * @param \Closure $write Draws the given text on the given document
	 */
	public function testInvalidTextFinishesTheDocument($write)
	{
		$mpdf = new Mpdf();
		$mpdf->AddPage();
		$write($mpdf, "before \xE2 after");

		$this->assertStringStartsWith('%PDF-', $mpdf->OutputBinaryData());
	}

	/**
	 * @dataProvider textCalls
	 *
	 * @param \Closure $write Draws the given text on the given document
	 */
	public function testDebugThrowsOnInvalidText($write)
	{
		$mpdf = new Mpdf(['debug' => true]);
		$mpdf->AddPage();

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Text contains invalid UTF-8 character(s)');

		$write($mpdf, "before \xE2 after");
	}

	/**
	 * The calls that draw text without going through HTML
	 *
	 * @return array
	 */
	public function textCalls()
	{
		return [
			'WriteCell' => [function ($mpdf, $text) {
				$mpdf->WriteCell(40, 5, $text);
			}],
			'WriteText' => [function ($mpdf, $text) {
				$mpdf->WriteText(20, 20, $text);
			}],
			'MultiCell' => [function ($mpdf, $text) {
				$mpdf->MultiCell(40, 5, $text);
			}],
		];
	}

	/**
	 * With debug on the document stops at the bad bytes, and they are still logged.
	 */
	public function testDebugThrowsAndLogsTheBadBytes()
	{
		$this->assertSame(
			['exception' => self::MESSAGE, 'log' => ['[[#226]]']],
			$this->writeHtmlInOwnProcess("<p>\xE2 ok</p>", ['debug'])
		);
	}

	/**
	 * An error handler that returns true used to make most invalid UTF-8 hang.
	 */
	public function testAnErrorHandlerThatSwallowsNoticesChangesNothing()
	{
		$this->assertSame(
			['exception' => null, 'log' => ['[[#226]]']],
			$this->writeHtmlInOwnProcess("<p>\xE2 ok</p>", ['swallow-errors'])
		);
	}

	/**
	 * @param string $html
	 * @param array $flags Any of "debug" and "swallow-errors"
	 *
	 * @return array
	 */
	private function writeHtmlInOwnProcess($html, $flags = [])
	{
		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
			. escapeshellarg(__DIR__ . '/Fixtures/purify-utf8.php') . ' ' . bin2hex($html)
			. ' ' . implode(' ', $flags);

		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$this->assertSame(0, proc_close($process), 'WriteHTML() ran out of time or crashed. ' . $errors);

		return json_decode($output, true);
	}
}
