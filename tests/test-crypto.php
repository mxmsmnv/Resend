<?php
/**
 * Crypto: encryption roundtrips, failure modes, legacy passthrough.
 */

require_once __DIR__ . '/../src/Crypto.php';

use Smnv\Resend\Crypto;

$salt = str_repeat('a1b2c3d4', 5);
$secret = 're_test_Abc123XyZ_secretKeyValue';

$enc = Crypto::encrypt($secret, $salt);
check('crypto: encrypted with rs1 prefix', strpos($enc, 'rs1:') === 0);
check('crypto: ciphertext differs from plaintext', strpos($enc, $secret) === false);
check('crypto: roundtrip decrypts', Crypto::decrypt($enc, $salt) === $secret);
check('crypto: two encryptions differ (random nonce)', Crypto::encrypt($secret, $salt) !== $enc);

check('crypto: wrong salt -> empty', Crypto::decrypt($enc, 'other-salt') === '');
check('crypto: tampered -> empty', Crypto::decrypt(substr($enc, 0, -6) . 'AAAAA=', $salt) === '');
check('crypto: garbage base64 -> empty', Crypto::decrypt('rs1:%%%not-base64%%%', $salt) === '');
check('crypto: truncated -> empty', Crypto::decrypt('rs1:' . base64_encode('short'), $salt) === '');

check('crypto: plaintext passes through decrypt', Crypto::decrypt($secret, $salt) === $secret);
check('crypto: empty salt refuses plaintext storage', Crypto::encrypt($secret, '') === '');
check('crypto: empty value stays empty', Crypto::encrypt('', $salt) === '');
check('crypto: isEncrypted detection', Crypto::isEncrypted($enc) && !Crypto::isEncrypted($secret));

// OpenSSL fallback format (rs2) must decrypt with the same key derivation
$key = hash('sha256', 'resend-settings-v1:' . $salt, true);
$iv = random_bytes(12);
$tag = '';
$cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
$rs2 = 'rs2:' . base64_encode($iv . $tag . $cipher);
check('crypto: openssl rs2 value decrypts', Crypto::decrypt($rs2, $salt) === $secret);
check('crypto: openssl rs2 wrong salt -> empty', Crypto::decrypt($rs2, 'nope') === '');

$long = str_repeat("multibyte \u{0434}\u{043e}\u{0431}\u{0440}\u{043e} ", 200);
check('crypto: long unicode roundtrip', Crypto::decrypt(Crypto::encrypt($long, $salt), $salt) === $long);
