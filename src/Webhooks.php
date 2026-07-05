<?php namespace Smnv\Resend;

/**
 * Webhooks
 *
 * Webhook endpoint management and Svix signature verification.
 */
class Webhooks extends Resource {

	const EVENT_TYPES = [
		'email.sent', 'email.delivered', 'email.delivery_delayed', 'email.bounced',
		'email.complained', 'email.opened', 'email.clicked', 'email.failed',
		'email.scheduled', 'email.suppressed', 'email.received',
		'contact.created', 'contact.updated', 'contact.deleted',
		'domain.created', 'domain.updated', 'domain.deleted',
	];

	/**
	 * @param string $endpointUrl
	 * @param array $events See self::EVENT_TYPES
	 */
	public function create($endpointUrl, array $events) {
		return $this->client->post('/webhooks', ['endpoint_url' => $endpointUrl, 'events' => $events]);
	}

	public function all(array $params = []) {
		return $this->client->get('/webhooks', $params);
	}

	public function get($id) {
		return $this->client->get('/webhooks/' . $this->seg($id));
	}

	public function update($id, array $data) {
		return $this->client->patch('/webhooks/' . $this->seg($id), $data);
	}

	public function delete($id) {
		return $this->client->delete('/webhooks/' . $this->seg($id));
	}

	/**
	 * Verify a Resend (Svix) webhook signature.
	 *
	 * Implements HMAC-SHA256 over "id.timestamp.body" with constant-time
	 * comparison and a 5-minute timestamp tolerance against replay attacks.
	 *
	 * @param string $payload Raw request body
	 * @param array $headers Request headers (svix-id, svix-timestamp, svix-signature)
	 * @param string $signingSecret Webhook signing secret (whsec_...)
	 * @return bool
	 */
	public static function verifySignature($payload, array $headers, $signingSecret) {
		if(!is_string($payload) || !strlen($payload)) return false;
		if(!is_string($signingSecret) || strpos($signingSecret, '_') === false) return false;
		$headers = array_change_key_case($headers, CASE_LOWER);
		$id = isset($headers['svix-id']) ? (string) $headers['svix-id'] : '';
		$timestamp = isset($headers['svix-timestamp']) ? (string) $headers['svix-timestamp'] : '';
		$signatures = isset($headers['svix-signature']) ? (string) $headers['svix-signature'] : '';
		if(!strlen($id) || !strlen($timestamp) || !strlen($signatures)) return false;
		// Reject non-numeric or stale timestamps (5 minutes tolerance)
		if(!ctype_digit($timestamp)) return false;
		if(abs(time() - (int) $timestamp) > 300) return false;
		$secret = base64_decode(substr($signingSecret, strpos($signingSecret, '_') + 1), true);
		if($secret === false || !strlen($secret)) return false;
		$signedContent = "$id.$timestamp.$payload";
		$expected = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));
		foreach(explode(' ', $signatures) as $versioned) {
			$parts = explode(',', $versioned, 2);
			// Only v1 (HMAC-SHA256) signatures; other versions use different schemes
			if(count($parts) === 2 && $parts[0] === 'v1' && hash_equals($expected, $parts[1])) return true;
		}
		return false;
	}
}
