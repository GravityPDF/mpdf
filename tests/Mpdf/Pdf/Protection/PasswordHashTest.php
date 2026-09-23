<?php

namespace Mpdf\Pdf\Protection;

use Mpdf\MpdfException;

class PasswordHashTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Pdf\Protection\PasswordHash
	 */
	private $passwordHash;

	/**
	 * Makes the hash under test
	 */
	protected function set_up()
	{
		$this->passwordHash = new PasswordHash();
	}

	/**
	 * Hashes of Algorithm 2.B as pypdf's AlgV5.calculate_hash() makes them
	 *
	 * @return array
	 */
	public function hashProvider()
	{
		return [
			'empty user password' => ['', '12345678', '', '6s4mJ5jQ9zJZgrSGVA/xwRiHHhUV5ZrTrjXwk6JqVKY='],
			'UTF-8 user password' => ["\xC3\xBCser", 'abcdefgh', '', 'P2guiPTt3fojJcAxDAGp9gVRDJ5sSsMAlqauL6asya4='],
			'owner password over /U' => ['owner', 'ABCDEFGH', implode('', array_map('chr', range(0, 47))), '/WOpIJ4TULunvVkR3YuINDEg4J79vlVHNOc72buXLXw='],
		];
	}

	/**
	 * @dataProvider hashProvider
	 *
	 * @param string $password
	 * @param string $salt
	 * @param string $userKey
	 * @param string $expected Base64
	 */
	public function testTheHashIsTheOneAnotherImplementationMakes($password, $salt, $userKey, $expected)
	{
		$this->assertSame($expected, base64_encode($this->passwordHash->hash($password, $salt, $userKey)));
	}

	/**
	 * Passwords and what SASLprep makes of them
	 *
	 * @return array
	 */
	public function prepareProvider()
	{
		return [
			'ASCII' => ['secret', 'secret'],
			'non-ASCII space' => ["pass\xC2\xA0word", 'pass word'],
			'soft hyphen' => ["pass\xC2\xADword", 'password'],
			'zero width space' => ["pass\xE2\x80\x8Bword", 'password'],
			'compatibility ligature' => ["\xEF\xAC\x81le", 'file'],
			'decomposed accent' => ["cafe\xCC\x81", "caf\xC3\xA9"],
			'longer than 127 bytes' => [str_repeat('a', 200), str_repeat('a', 127)],
		];
	}

	/**
	 * @dataProvider prepareProvider
	 *
	 * @param string $password
	 * @param string $expected
	 */
	public function testAPasswordIsPrepared($password, $expected)
	{
		$this->assertSame($expected, $this->passwordHash->prepare($password));
	}

	/**
	 * A stack trace does not carry the password where PHP can redact it
	 */
	public function testAPasswordIsKeptOutOfStackTraces()
	{
		if (PHP_VERSION_ID < 80200) {
			$this->markTestSkipped('SensitiveParameter is PHP 8.2 and later');
		}

		if (ini_get('zend.exception_ignore_args')) {
			$this->markTestSkipped('Stack traces carry no arguments with zend.exception_ignore_args on');
		}

		try {
			$this->passwordHash->prepare("secret\x07");
			$this->fail('The password was not refused');
		} catch (MpdfException $e) {
			$this->assertInstanceOf('SensitiveParameterValue', $e->getTrace()[0]['args'][0]);
		}
	}

	/**
	 * A reader could not be given the same bytes to open it with
	 */
	public function testAPasswordThatIsNotUtf8IsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('A PDF password must be UTF-8');

		$this->passwordHash->prepare("caf\xE9");
	}

	/**
	 * SASLprep prohibits control characters
	 */
	public function testAPasswordWithAProhibitedCharacterIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('A PDF password cannot hold the character U+0007');

		$this->passwordHash->prepare("bell\x07");
	}

}
