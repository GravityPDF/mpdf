<?php

namespace Mpdf\Pdf;

use Mpdf\MpdfException;

class ProtectionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use Aes256Reader;

	/**
	 * @var \Mpdf\Pdf\Protection
	 */
	private $protection;

	/**
	 * Makes the protection under test
	 */
	protected function set_up()
	{
		$this->protection = new Protection();
	}

	/**
	 * Each password unwraps the key the data is encrypted with, and neither stands in for the other
	 */
	public function testEachPasswordOpensTheFileKey()
	{
		$this->protection->setProtection(['print'], "\xC3\xBCser", 'owner');

		$this->assertSame(48, strlen($this->protection->getUValue()));
		$this->assertSame(48, strlen($this->protection->getOValue()));

		foreach ([["\xC3\xBCser", false], ['owner', true]] as $password) {
			$key = $this->fileKey($this->entries(), $password[0], $password[1]);
			$this->assertSame('Hello', $this->decrypt($this->protection->encrypt('Hello'), $key));
		}

		$this->assertFalse($this->fileKey($this->entries(), 'owner', false));
		$this->assertFalse($this->fileKey($this->entries(), "\xC3\xBCser", true));
	}

	/**
	 * /Perms is /P, the rest of its bits set, "T" for encrypted metadata and "adb", under the file key
	 */
	public function testPermsHoldsThePermissions()
	{
		$this->protection->setProtection(['print', 'copy', 'print-highres'], '', 'owner');

		$this->assertSame(-3904 | 4 | 16 | 2048, $this->protection->getPValue());

		$key = $this->fileKey($this->entries(), '', false);
		$perms = openssl_decrypt($this->protection->getPermsValue(), 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
		$p = unpack('V', substr($perms, 0, 4));

		$this->assertSame($this->protection->getPValue() & 0xFFFFFFFF, $p[1] & 0xFFFFFFFF);
		$this->assertSame("\xFF\xFF\xFF\xFFTadb", substr($perms, 4, 8));
	}

	/**
	 * A permission given as a string is the one permission
	 */
	public function testSingleStringPermission()
	{
		$this->assertTrue($this->protection->setProtection('copy', '', 'owner'));
		$this->assertSame(-3904 | 16, $this->protection->getPValue());
	}

	/**
	 * Permissions that are empty or null
	 *
	 * @return array
	 */
	public function noPermissionProvider()
	{
		return [[null], [''], [[]]];
	}

	/**
	 * Empty or null permits nothing, and still encrypts: a document with a password is never written in the clear
	 *
	 * @dataProvider noPermissionProvider
	 *
	 * @param mixed $permissions
	 */
	public function testNoPermissionsStillEncrypts($permissions)
	{
		$this->assertTrue($this->protection->setProtection($permissions, 'secret'));
		$this->assertSame(-3904, $this->protection->getPValue());
		$this->assertSame(48, strlen($this->protection->getUValue()));
	}

	/**
	 * An unknown permission is refused
	 */
	public function testInvalidPermissions()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Invalid permission type "fly-a-broomstick"');

		$this->protection->setProtection(['fly-a-broomstick']);
	}

	/**
	 * Permissions that are neither a name nor a list of them are refused
	 */
	public function testPermissionsOfAnotherTypeAreRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('PDF permissions must be an array of permission names');

		$this->protection->setProtection(4);
	}

	/**
	 * A call that fails leaves the protection set before whole, so /P still matches /Perms and the key
	 */
	public function testAFailedCallLeavesTheProtectionAsItWas()
	{
		$this->protection->setProtection(['print'], 'user', 'owner');
		$before = [$this->protection->getPValue(), $this->protection->getPermsValue(), $this->entries()];
		$encrypted = $this->protection->encrypt('Hello');

		try {
			$this->protection->setProtection(['copy'], "bell\x07", 'owner');
			$this->fail('The password was not refused');
		} catch (MpdfException $e) {
			$this->assertSame([$this->protection->getPValue(), $this->protection->getPermsValue(), $this->entries()], $before);
			$this->assertSame('Hello', $this->protection->decrypt($encrypted));
		}
	}

	/**
	 * Without a key there is nothing to encrypt with, which is an error rather than data dropped
	 */
	public function testEncryptingWithoutAKeyIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Unable to encrypt without a key');

		$this->protection->encrypt('Hello');
	}

	/**
	 * The /U of the key in use is recognised, and another's is not
	 */
	public function testTheUValueInUseIsRecognised()
	{
		$this->assertFalse($this->protection->isUValue(str_repeat("\0", 48)));

		$this->protection->setProtection([], 'user', 'owner');
		$u = $this->protection->getUValue();

		$this->assertTrue($this->protection->isUValue($u));
		$this->assertFalse($this->protection->isUValue(strrev($u)));
	}

	/**
	 * Lengths either side of a block boundary
	 *
	 * @return array
	 */
	public function lengthProvider()
	{
		return [[0], [1], [15], [16], [17], [1000]];
	}

	/**
	 * @dataProvider lengthProvider
	 *
	 * @param int $length
	 */
	public function testDataIsEncryptedBehindARandomIvToTheLengthPredicted($length)
	{
		$this->protection->setProtection([], '', 'owner');

		$data = str_repeat('x', $length);
		$encrypted = $this->protection->encrypt($data);

		$this->assertSame($this->protection->encryptedLength($length), strlen($encrypted));
		$this->assertNotSame($encrypted, $this->protection->encrypt($data));
		$this->assertSame($data, $this->protection->decrypt($encrypted));
	}

	/**
	 * @return string[] The password entries of the protection under test
	 */
	private function entries()
	{
		return [
			'U' => $this->protection->getUValue(),
			'O' => $this->protection->getOValue(),
			'UE' => $this->protection->getUEValue(),
			'OE' => $this->protection->getOEValue(),
		];
	}

}
