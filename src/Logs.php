<?php namespace Smnv\Resend;

/**
 * Logs
 *
 * API request logs.
 */
class Logs extends Resource {

	public function all(array $params = []) {
		return $this->client->get('/logs', $params);
	}

	public function get($id) {
		return $this->client->get('/logs/' . $this->seg($id));
	}
}
