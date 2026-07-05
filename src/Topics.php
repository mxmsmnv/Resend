<?php namespace Smnv\Resend;

/**
 * Topics
 *
 * Subscription topic management.
 */
class Topics extends Resource {

	/**
	 * @param array $data name, description, default_subscription ('opt-in'|'opt-out')
	 */
	public function create(array $data) {
		return $this->client->post('/topics', $data);
	}

	public function all(array $params = []) {
		return $this->client->get('/topics', $params);
	}

	public function get($id) {
		return $this->client->get('/topics/' . $this->seg($id));
	}

	public function update($id, array $data) {
		return $this->client->patch('/topics/' . $this->seg($id), $data);
	}

	public function delete($id) {
		return $this->client->delete('/topics/' . $this->seg($id));
	}
}
