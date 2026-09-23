<?php

namespace Mpdf\Pdf\Protection;

use Mpdf\MpdfException;

/**
 * The password side of the AES-256 standard security handler (ISO 32000-2, 7.6.4.3.3 and 7.6.4.3.4)
 */
class PasswordHash
{

	/**
	 * RFC 3454 C.1.2, the spaces other than U+0020, which SASLprep maps to it. U+200B is in B.1 as well, and is mapped
	 * to nothing, as other SASLprep implementations do.
	 */
	const NON_ASCII_SPACE = '/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u';

	/**
	 * RFC 3454 B.1, the characters SASLprep maps to nothing
	 */
	const MAPPED_TO_NOTHING = '/[\x{00AD}\x{034F}\x{1806}\x{180B}-\x{180D}\x{200B}-\x{200D}\x{2060}\x{FE00}-\x{FE0F}\x{FEFF}]/u';

	/**
	 * RFC 3454 C.2 to C.9, the characters SASLprep prohibits: control characters, private use, non-characters, and
	 * those that change how text is displayed or tag it
	 */
	const PROHIBITED = '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{0340}\x{0341}\x{06DD}\x{070F}\x{180E}\x{200E}\x{200F}\x{2028}-\x{202E}\x{2060}-\x{2063}\x{206A}-\x{206F}\x{2FF0}-\x{2FFB}\x{E000}-\x{F8FF}\x{FDD0}-\x{FDEF}\x{FFF9}-\x{FFFF}\x{1D173}-\x{1D17A}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{E0001}\x{E0020}-\x{E007F}\x{EFFFE}-\x{10FFFF}]/u';

	/**
	 * Only this many bytes of a prepared password are used
	 */
	const MAX_LENGTH = 127;

	/**
	 * The bytes of a password that are hashed: SASLprep'd UTF-8 cut to 127 bytes, as a reader prepares what is typed
	 * into it
	 *
	 * The mapping, NFKC and prohibition steps of SASLprep are applied. The bidirectional check is not: a password that
	 * fails it is used as it stands.
	 *
	 * @param string $password UTF-8
	 *
	 * @return string
	 *
	 * @throws \Mpdf\MpdfException When the password is not UTF-8 or holds a character SASLprep prohibits
	 */
	public function prepare(
		#[\SensitiveParameter]
		$password
	) {
		$password = (string) $password;

		if (!preg_match('//u', $password)) {
			throw new MpdfException('A PDF password must be UTF-8');
		}

		$password = preg_replace(self::NON_ASCII_SPACE, ' ', $password);
		$password = preg_replace(self::MAPPED_TO_NOTHING, '', $password);
		$password = \Normalizer::normalize($password, \Normalizer::FORM_KC);

		if (preg_match(self::PROHIBITED, $password, $match)) {
			throw new MpdfException(sprintf('A PDF password cannot hold the character U+%04X', $this->codePoint($match[0])));
		}

		return substr($password, 0, self::MAX_LENGTH);
	}

	/**
	 * Algorithm 2.B: SHA-256 of the password and salt, then at least 64 rounds of AES-128 over it, each hashed again
	 * with SHA-256, SHA-384 or SHA-512 as its output picks
	 *
	 * @param string $password A prepared password
	 * @param string $salt 8 bytes
	 * @param string $userKey The 48-byte /U value when hashing the owner password, otherwise empty
	 *
	 * @return string 32 bytes
	 */
	public function hash($password, $salt, $userKey = '')
	{
		$algorithms = ['sha256', 'sha384', 'sha512'];

		$k = hash('sha256', $password . $salt . $userKey, true);

		$round = 0;
		do {
			$k1 = str_repeat($password . $k . $userKey, 64);
			$e = openssl_encrypt($k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16));

			// The first 16 bytes of E as a number modulo 3: 256 is 1 modulo 3, so their sum gives the same
			$sum = array_sum(unpack('C16', $e));
			$k = hash($algorithms[$sum % 3], $e, true);

			$round++;
		} while ($round < 64 || ord(substr($e, -1)) > $round - 32);

		return substr($k, 0, 32);
	}

	/**
	 * @param string $character One UTF-8 encoded character
	 *
	 * @return int
	 */
	private function codePoint($character)
	{
		$utf32 = unpack('N', mb_convert_encoding($character, 'UTF-32BE', 'UTF-8'));

		return $utf32[1];
	}
}
