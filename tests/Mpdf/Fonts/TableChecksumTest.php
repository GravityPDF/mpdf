<?php

namespace Mpdf\Fonts;

/**
 * The checksum of a table, taken the way a 64-bit build takes it and the way a 32-bit one does.
 *
 * CI runs only 64-bit builds, so the 32-bit sum is called here directly to be tested at all.
 */
class TableChecksumTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider tableProvider
	 */
	public function testChecksumsATable($data, array $expected)
	{
		$this->assertSame($expected, TableChecksum::of($data));
	}

	/**
	 * @dataProvider tableProvider
	 */
	public function testSumsTheSameWithoutA64BitInteger($data, array $expected)
	{
		if (strlen($data) % 4) {
			$data .= str_repeat("\0", 4 - (strlen($data) % 4));
		}

		$sumHalves = new \ReflectionMethod(TableChecksum::class, 'sumHalves');
		if (PHP_VERSION_ID < 80100) {
			$sumHalves->setAccessible(true);
		}

		$this->assertSame($expected, $sumHalves->invoke(null, $data));
	}

	/**
	 * Tables whose length is not a multiple of four, which are summed as if padded with zeros, and
	 * sums that carry past 16 and past 32 bits, including over more than one chunk
	 *
	 * @return array[] The table, and its checksum's high and low halves
	 */
	public function tableProvider()
	{
		return [
			'empty' => ['', [0, 0]],
			'one byte' => ["\x01", [0x0100, 0]],
			'three bytes' => ["\x01\x02\x03", [0x0102, 0x0300]],
			'six bytes' => ["\x00\x01\x00\x02\x80\x00", [0x8001, 0x0002]],
			'low half carries' => ["\x00\x00\xFF\xFF\x00\x00\x00\x01", [0x0001, 0x0000]],
			'past 32 bits' => ["\xFF\xFF\xFF\xFF\x00\x00\x00\x02", [0x0000, 0x0001]],
			'high bit set' => ["\x80\x00\x00\x00\x80\x00\x00\x00\x80\x00\x00\x01", [0x8000, 0x0001]],
			'over two chunks, odd length' => [str_repeat("\xFF", TableChecksum::CHUNK * 2 + 6), [0xFFFE, 0x7FFF]],
			'every byte value, odd length' => [str_repeat(implode('', array_map('chr', range(0, 255))), 300) . "\x7F", [0xD9A5, 0xCB00]],
		];
	}

	/**
	 * Every table of a real font sums to the checksum its table directory states. head is left out:
	 * its checksumAdjustment is part of what it covers, which is why the directory's figure for it
	 * is not the plain sum.
	 */
	public function testMatchesTheChecksumsAFontStates()
	{
		$font = file_get_contents(__DIR__ . '/../../../packages/Dejavu-Family/fonts/DejaVuSans.ttf');
		$numTables = unpack('n', substr($font, 4, 2))[1];

		$checked = 0;
		for ($i = 0; $i < $numTables; $i++) {
			$record = unpack('a4tag/nhi/nlo/Noffset/Nlength', substr($font, 12 + 16 * $i, 16));
			if ($record['tag'] === 'head') {
				continue;
			}

			$table = substr($font, $record['offset'], $record['length']);
			$this->assertSame([$record['hi'], $record['lo']], TableChecksum::of($table), $record['tag']);
			$checked++;
		}

		$this->assertGreaterThan(10, $checked);
	}
}
