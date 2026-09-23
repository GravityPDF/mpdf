<?php

namespace Mpdf\Pdf;

use Mpdf\Pdf\Protection\PasswordHash;

/**
 * Opens what the AES-256 standard security handler wrote, the way a reader does
 */
trait Aes256Reader
{

	/**
	 * Algorithm 2.A: the file key a password unwraps, or false when the password is not the one asked for
	 *
	 * @param string[] $entries The /U, /O, /UE and /OE values, keyed by name
	 * @param string $password
	 * @param bool $owner Whether to check the password as the owner password
	 *
	 * @return string|false
	 */
	private function fileKey($entries, $password, $owner)
	{
		$hash = new PasswordHash();
		$check = $owner ? $entries['O'] : $entries['U'];
		$userKey = $owner ? $entries['U'] : '';

		if ($hash->hash($password, substr($check, 32, 8), $userKey) !== substr($check, 0, 32)) {
			return false;
		}

		$key = $hash->hash($password, substr($check, 40, 8), $userKey);

		return openssl_decrypt($entries[$owner ? 'OE' : 'UE'], 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
	}

	/**
	 * @param string $data An IV and AES-256-CBC
	 * @param string $key
	 *
	 * @return string|false
	 */
	private function decrypt($data, $key)
	{
		return openssl_decrypt(substr($data, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($data, 0, 16));
	}

}
