<?php namespace Smnv\Resend;

/**
 * Crypto
 *
 * Symmetric encryption for stored settings values. The key is derived from
 * the site salt in config.php, which is never stored in the database, so a
 * database dump alone does not reveal encrypted values.
 *
 * Storage format:
 *   "rs1:" . base64(nonce . ciphertext)      libsodium XSalsa20-Poly1305
 *   "rs2:" . base64(iv . tag . ciphertext)   OpenSSL AES-256-GCM fallback
 *
 * Values without a known prefix are treated as legacy plaintext and returned
 * unchanged by decrypt(). If no site salt or crypto backend is available,
 * encrypt() returns an empty string rather than storing secrets in plaintext.
 */
class Crypto {

	const PREFIX_SODIUM = 'rs1:';
	const PREFIX_OPENSSL = 'rs2:';

	/**
	 * @param string $plaintext
	 * @param string $salt Site salt; empty salt prevents encryption
	 * @return string Prefixed ciphertext, or '' when encryption is unavailable
	 */
	public static function encrypt($plaintext, $salt) {
		$plaintext = (string) $plaintext;
		$salt = (string) $salt;
		if(!strlen($plaintext)) return '';
		if(!strlen($salt)) return '';
		$key = self::key($salt);
		if(function_exists('sodium_crypto_secretbox')) {
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			return self::PREFIX_SODIUM . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $key));
		}
		if(function_exists('openssl_encrypt')) {
			$iv = random_bytes(12);
			$tag = '';
			$cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
			if($cipher !== false) return self::PREFIX_OPENSSL . base64_encode($iv . $tag . $cipher);
		}
		return '';
	}

	/**
	 * @param string $stored
	 * @param string $salt
	 * @return string Plaintext; '' when an encrypted value cannot be decrypted
	 *  (wrong salt, tampered data); unchanged value when it has no known prefix
	 */
	public static function decrypt($stored, $salt) {
		$stored = (string) $stored;
		if(!self::isEncrypted($stored)) return $stored;
		$key = self::key((string) $salt);
		$raw = base64_decode(substr($stored, strlen(self::PREFIX_SODIUM)), true);
		if($raw === false) return '';
		if(strpos($stored, self::PREFIX_SODIUM) === 0) {
			if(!function_exists('sodium_crypto_secretbox_open')) return '';
			if(strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
			$nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $key);
			return $plaintext === false ? '' : $plaintext;
		}
		if(!function_exists('openssl_decrypt')) return '';
		if(strlen($raw) <= 28) return ''; // 12-byte iv + 16-byte tag
		$iv = substr($raw, 0, 12);
		$tag = substr($raw, 12, 16);
		$cipher = substr($raw, 28);
		$plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
		return $plaintext === false ? '' : $plaintext;
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	public static function isEncrypted($value) {
		$value = (string) $value;
		return strpos($value, self::PREFIX_SODIUM) === 0 || strpos($value, self::PREFIX_OPENSSL) === 0;
	}

	/**
	 * Derive a 32-byte key from the site salt.
	 *
	 * @param string $salt
	 * @return string
	 */
	protected static function key($salt) {
		return hash('sha256', 'resend-settings-v1:' . $salt, true);
	}
}
