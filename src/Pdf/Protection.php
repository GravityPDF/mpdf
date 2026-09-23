<?php

namespace Mpdf\Pdf;

use Mpdf\MpdfException;
use Mpdf\Pdf\Protection\PasswordHash;

/**
 * The standard security handler at AES-256 (ISO 32000-2, revision 6; PDF 1.7 Adobe extension level 8)
 *
 * The file key is 32 random bytes, stored wrapped under each password in /UE and /OE. Every string and stream is
 * encrypted with that key alone, each with its own random IV.
 */
class Protection
{

	/**
	 * Bits 7, 8 and 13 to 32 of /P are reserved and set, as a signed 32-bit integer
	 */
	const RESERVED_PERMISSION_BITS = -3904;

	/**
	 * @var int[] Array of permission => bit
	 */
	private $options = [
		'print' => 4, // bit 3
		'modify' => 8, // bit 4
		'copy' => 16, // bit 5
		'annot-forms' => 32, // bit 6
		'fill-forms' => 256, // bit 9
		'extract' => 512, // bit 10
		'assemble' => 1024, // bit 11
		'print-highres' => 2048 // bit 12
	];

	/**
	 * @var \Mpdf\Pdf\Protection\PasswordHash
	 */
	private $passwordHash;

	/**
	 * @var string
	 */
	private $fileKey;

	/**
	 * @var string
	 */
	private $oValue;

	/**
	 * @var string
	 */
	private $uValue;

	/**
	 * @var string
	 */
	private $oeValue;

	/**
	 * @var string
	 */
	private $ueValue;

	/**
	 * @var int
	 */
	private $pValue;

	/**
	 * @var string
	 */
	private $permsValue;

	/**
	 * Nothing is encrypted until setProtection() makes the key
	 */
	public function __construct()
	{
		$this->passwordHash = new PasswordHash();
	}

	/**
	 * Makes a new file key and the entries that open it. Nothing changes unless all of them are made.
	 *
	 * @param string[]|string|null $permissions What a user who opens the document with the user password may do;
	 *                                          empty or null permits nothing
	 * @param string $user_pass UTF-8; empty lets anyone open the document
	 * @param string|null $owner_pass UTF-8; null makes a random one
	 *
	 * @return bool Always true: the document is to be encrypted
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public function setProtection(
		$permissions = [],
		#[\SensitiveParameter]
		$user_pass = '',
		#[\SensitiveParameter]
		$owner_pass = null
	) {
		if ($permissions === null || $permissions === '') {
			$permissions = [];
		} elseif (is_string($permissions)) {
			$permissions = [$permissions];
		} elseif (!is_array($permissions)) {
			throw new MpdfException('PDF permissions must be an array of permission names');
		}

		if (!function_exists('openssl_encrypt')) {
			throw new MpdfException('Unable to set PDF file protection, the OpenSSL extension is not available.');
		}

		$pValue = $this->getProtectionBitsFromOptions($permissions);

		if ($owner_pass === null) {
			$owner_pass = bin2hex(random_bytes(23));
		}

		$user_pass = $this->passwordHash->prepare($user_pass);
		$owner_pass = $this->passwordHash->prepare($owner_pass);

		$fileKey = random_bytes(32);

		list($uValue, $ueValue) = $this->passwordEntries($fileKey, $user_pass, '');
		list($oValue, $oeValue) = $this->passwordEntries($fileKey, $owner_pass, $uValue);

		// Algorithm 10: /P, a flag saying metadata is encrypted, and "adb", encrypted with the file key
		$perms = pack('V', $pValue) . "\xFF\xFF\xFF\xFF" . 'Tadb' . random_bytes(4);

		$this->permsValue = $this->aes($perms, 'aes-256-ecb', $fileKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, '');
		$this->fileKey = $fileKey;
		$this->pValue = $pValue;
		$this->uValue = $uValue;
		$this->ueValue = $ueValue;
		$this->oValue = $oValue;
		$this->oeValue = $oeValue;

		return true;
	}

	/**
	 * Encrypts a string or stream: a random IV followed by the data in AES-256-CBC with PKCS#7 padding
	 *
	 * @param string $data
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When there is no key to encrypt with
	 */
	public function encrypt($data)
	{
		if ($this->fileKey === null) {
			throw new MpdfException('Unable to encrypt without a key: call SetProtection() first');
		}

		$iv = random_bytes(16);

		return $iv . $this->aes($data, 'aes-256-cbc', $this->fileKey, OPENSSL_RAW_DATA, $iv);
	}

	/**
	 * Whether this is the /U entry of the key encrypt() uses
	 *
	 * @param string $uValue
	 *
	 * @return bool
	 */
	public function isUValue($uValue)
	{
		return $this->uValue !== null && hash_equals($this->uValue, $uValue);
	}

	/**
	 * @param string $data What encrypt() made
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the data was not encrypted with this document's key
	 */
	public function decrypt($data)
	{
		$plain = openssl_decrypt(substr($data, 16), 'aes-256-cbc', $this->fileKey, OPENSSL_RAW_DATA, substr($data, 0, 16));

		if ($plain === false) {
			throw new MpdfException('Unable to decrypt data that was not encrypted with the key of this document');
		}

		return $plain;
	}

	/**
	 * How long encrypt() makes data of this length: the IV and the data padded to a whole block
	 *
	 * @param int $length
	 *
	 * @return int
	 */
	public function encryptedLength($length)
	{
		return 32 + $length - $length % 16;
	}

	/**
	 * @return string
	 */
	public function getOValue()
	{
		return $this->oValue;
	}

	/**
	 * @return string
	 */
	public function getUValue()
	{
		return $this->uValue;
	}

	/**
	 * @return string
	 */
	public function getOEValue()
	{
		return $this->oeValue;
	}

	/**
	 * @return string
	 */
	public function getUEValue()
	{
		return $this->ueValue;
	}

	/**
	 * @return int
	 */
	public function getPValue()
	{
		return $this->pValue;
	}

	/**
	 * @return string
	 */
	public function getPermsValue()
	{
		return $this->permsValue;
	}

	/**
	 * @param string[] $permissions
	 *
	 * @return int /P as a signed 32-bit integer
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function getProtectionBitsFromOptions($permissions)
	{
		$protection = self::RESERVED_PERMISSION_BITS;

		foreach ($permissions as $permission) {
			if (!isset($this->options[$permission])) {
				throw new MpdfException(sprintf('Invalid permission type "%s"', $permission));
			}

			$protection |= $this->options[$permission];
		}

		return $protection;
	}

	/**
	 * Algorithms 8 and 9: the /U or /O entry that checks a password, and the /UE or /OE entry that holds the file key
	 * under it, AES-256-CBC with a zero IV and no padding
	 *
	 * @param string $fileKey
	 * @param string $password A prepared password
	 * @param string $userKey Empty for the user password, /U for the owner password
	 *
	 * @return string[]
	 */
	private function passwordEntries($fileKey, $password, $userKey)
	{
		$validationSalt = random_bytes(8);
		$keySalt = random_bytes(8);

		$check = $this->passwordHash->hash($password, $validationSalt, $userKey) . $validationSalt . $keySalt;
		$key = $this->passwordHash->hash($password, $keySalt, $userKey);

		return [$check, $this->aes($fileKey, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16))];
	}

	/**
	 * openssl_encrypt(), throwing where it fails rather than handing back false
	 *
	 * @param string $data
	 * @param string $cipher
	 * @param string $key
	 * @param int $options
	 * @param string $iv
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function aes($data, $cipher, $key, $options, $iv)
	{
		$encrypted = openssl_encrypt($data, $cipher, $key, $options, $iv);

		if ($encrypted === false) {
			throw new MpdfException(sprintf('Unable to encrypt with %s: %s', $cipher, openssl_error_string()));
		}

		return $encrypted;
	}
}
