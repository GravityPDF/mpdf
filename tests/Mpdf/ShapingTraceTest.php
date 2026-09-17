<?php

namespace Mpdf;

/**
 * The trace Otl echoes, one step at a time, where debugOTL is set.
 */
class ShapingTraceTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var array[]
	 */
	private $run = [
		['hex' => '00627', 'uni' => 0x627],
		['hex' => '00644', 'uni' => 0x644, 'GPOSinfo' => ['XAdvance' => -120, 'YPlacement' => 30]],
		['hex' => '0064E', 'uni' => 0x64E, 'GPOSinfo' => []],
	];

	public function testAStepShowsTheLookupAndTheRunWithTheGlyphItAppliedAtInBold()
	{
		$this->assertSame(
			'<div style="padding-left: 0em;">GSUB LookupID #3 Subtable#0 Type: 4 Format: 1<br />'
			. '<div style="font-family:monospace">Glyph position: 1 Current Glyph: 00644<br />'
			. '00627 <b>00644 </b>0064E <br />'
			. '1575  <b>1604  </b>1614  <br />'
			. '</div></div>',
			OtlDump::shapingStep($this->run, 'GSUB', 3, 0, 4, 1, 1, '00644', 0)
		);
	}

	public function testAGPOSStepAlsoShowsThePositioningOfEveryGlyphThatHasAny()
	{
		$this->assertSame(
			'<div style="padding-left: 2em;">GPOS LookupID #12 Subtable#2 Type: 6 Format: 1<br />'
			. '<div style="font-family:monospace">Glyph position: 2 Current Glyph: 0064E<br />'
			. '00627 00644 <b>0064E </b><br />'
			. '1575  1604  <b>1614  </b><br />'
			. "00644 &#x00644; Array\n(\n    [XAdvance] => -120\n    [YPlacement] => 30\n)\n "
			. '</div></div>',
			OtlDump::shapingStep($this->run, 'GPOS', 12, 2, 6, 1, 2, '0064E', 1)
		);
	}

	public function testTheStartOfARunHasNoGlyphInBold()
	{
		$this->assertSame(
			'<div style="padding-left: 0em;">BEGIN LookupID #- Subtable#- Type: - Format: -<br />'
			. '<div style="font-family:monospace">Glyph position: -1 Current Glyph: -<br />'
			. '00627 00644 0064E <br />'
			. '1575  1604  1614  <br />'
			. '</div></div>',
			OtlDump::shapingStep($this->run, 'BEGIN', '-', '-', '-', '-', -1, '-', 0)
		);
	}

	public function testAnEmptyRunShowsTheLookupAlone()
	{
		$this->assertSame(
			'<div style="padding-left: 0em;">GSUB LookupID #0 Subtable#0 Type: 1 Format: 1<br />'
			. '<div style="font-family:monospace">Glyph position: 0 Current Glyph: -<br />'
			. '<br /><br />'
			. '</div></div>',
			OtlDump::shapingStep([], 'GSUB', 0, 0, 1, 1, 0, '-', 0)
		);
	}

	/**
	 * The trace ends the process once the run is shaped, so it is run in one of its own.
	 */
	public function testTheShaperTracesTheRunFromBeginningToEnd()
	{
		$tempDir = sys_get_temp_dir() . '/mpdf-shaping-trace-' . uniqid('', true);

		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr '
			. escapeshellarg(__DIR__ . '/Fixtures/shaping-trace.php') . ' ' . escapeshellarg($tempDir);

		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
		$trace = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$this->assertSame(0, proc_close($process), $errors);

		$steps = preg_split('/(?=<div style="padding-left: )/', $trace, -1, PREG_SPLIT_NO_EMPTY);
		$lookups = array_map(function ($step) {
			return substr($step, strpos($step, '>') + 1, strpos($step, ' LookupID') - strpos($step, '>') - 1);
		}, $steps);

		$this->assertSame(['BEGIN', 'GSUB', 'GSUB', 'GPOS', 'GPOS', 'END'], $lookups);
		$this->assertStringContainsString('0E001 0E005 <b>0E001 </b>0E005 <br />', $steps[2]);
	}

}
