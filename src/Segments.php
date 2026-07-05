<?php namespace Smnv\Resend;

/**
 * Segments
 *
 * Audience segment management.
 */
class Segments extends Resource {

	public function create($name) {
		return $this->client->post('/segments', ['name' => $name]);
	}

	public function all(array $params = []) {
		return $this->client->get('/segments', $params);
	}

	public function get($id) {
		return $this->client->get('/segments/' . $this->seg($id));
	}

	public function delete($id) {
		return $this->client->delete('/segments/' . $this->seg($id));
	}

	public function contacts($segmentId, array $params = []) {
		return $this->client->get('/segments/' . $this->seg($segmentId) . '/contacts', $params);
	}
}
