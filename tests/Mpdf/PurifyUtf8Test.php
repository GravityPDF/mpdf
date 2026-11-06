<?php

namespace Mpdf;

class PurifyUtf8Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const MESSAGE = 'Invalid input characters. Did you set $mpdf->in_charset properly?';

	/**
	 * @dataProvider invalidHtmlProvider
	 *
	 * @param string $html
	 * @param string $logEntry
	 */
	public function testInvalidHtmlThrowsAndLogsTheBadBytes($html, $logEntry)
	{
		$this->assertSame(
			['exception' => self::MESSAGE, 'log' => [$logEntry]],
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
	 * Whether mbstring keeps a leading byte order mark differs between PHP versions, which moves
	 * the first difference and so the log entry.
	 */
	public function testACutSequenceAfterAByteOrderMarkThrows()
	{
		$result = $this->writeHtmlInOwnProcess("\xEF\xBB\xBF<p>\xE2");

		$this->assertSame(self::MESSAGE, $result['exception']);
	}

	/**
	 * A handler that returns true keeps a notice out of error_get_last().
	 */
	public function testAnErrorHandlerThatSwallowsNoticesChangesNothing()
	{
		$this->assertSame(
			['exception' => self::MESSAGE, 'log' => ['[[#226]]']],
			$this->writeHtmlInOwnProcess("<p>\xE2 ok</p>", true)
		);
	}

	/**
	 * @param string $html
	 * @param bool $swallowErrors
	 *
	 * @return array
	 */
	private function writeHtmlInOwnProcess($html, $swallowErrors = false)
	{
		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
			. escapeshellarg(__DIR__ . '/Fixtures/purify-utf8.php') . ' ' . bin2hex($html)
			. ($swallowErrors ? ' swallow-errors' : '');

		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$this->assertSame(0, proc_close($process), 'WriteHTML() ran out of time or crashed. ' . $errors);

		return json_decode($output, true);
	}
}
