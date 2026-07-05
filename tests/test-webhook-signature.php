<?php
/**
 * Webhooks::verifySignature: Svix HMAC verification.
 */

require_once __DIR__ . '/../src/Client.php';
require_once __DIR__ . '/../src/Resource.php';
require_once __DIR__ . '/../src/Webhooks.php';

use Smnv\Resend\Webhooks;

$secretRaw = random_bytes(24);
$signingSecret = 'whsec_' . base64_encode($secretRaw);
$payload = '{"type":"email.delivered","data":{"id":"x"}}';
$id = 'msg_test123';
$ts = (string) time();
$sig = base64_encode(hash_hmac('sha256', "$id.$ts.$payload", $secretRaw, true));

$headers = ['Svix-Id' => $id, 'Svix-Timestamp' => $ts, 'Svix-Signature' => "v1,$sig"];
check('webhook: valid v1 signature accepted', Webhooks::verifySignature($payload, $headers, $signingSecret) === true);

$headers['Svix-Signature'] = "v2,$sig";
check('webhook: non-v1 version rejected', Webhooks::verifySignature($payload, $headers, $signingSecret) === false);

$headers['Svix-Signature'] = "v2,junk v1,$sig";
check('webhook: v1 found among multiple versions', Webhooks::verifySignature($payload, $headers, $signingSecret) === true);

$staleTs = (string) (time() - 400);
$staleSig = base64_encode(hash_hmac('sha256', "$id.$staleTs.$payload", $secretRaw, true));
check('webhook: stale timestamp rejected', Webhooks::verifySignature($payload, ['Svix-Id' => $id, 'Svix-Timestamp' => $staleTs, 'Svix-Signature' => "v1,$staleSig"], $signingSecret) === false);

check('webhook: tampered payload rejected', Webhooks::verifySignature($payload . 'x', ['Svix-Id' => $id, 'Svix-Timestamp' => $ts, 'Svix-Signature' => "v1,$sig"], $signingSecret) === false);
check('webhook: empty payload rejected', Webhooks::verifySignature('', $headers, $signingSecret) === false);
check('webhook: malformed secret rejected', Webhooks::verifySignature($payload, $headers, 'no-underscore') === false);
