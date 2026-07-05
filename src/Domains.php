<?php namespace Smnv\Resend;

/**
 * Domains
 *
 * Sending domain management and verification.
 */
class Domains extends Resource {

	/**
	 * @param string $name Domain name
	 * @param array $options region, custom_return_path etc.
	 */
	public function create($name, array $options = []) {
		return $this->client->post('/domains', array_merge(['name' => $name], $options));
	}

	public function all(array $params = []) {
		return $this->client->get('/domains', $params);
	}

	public function get($id) {
		return $this->client->get('/domains/' . $this->seg($id));
	}

	/**
	 * @param array $data click_tracking, open_tracking, tls etc.
	 */
	public function update($id, array $data) {
		return $this->client->patch('/domains/' . $this->seg($id), $data);
	}

	public function verify($id) {
		return $this->client->post('/domains/' . $this->seg($id) . '/verify');
	}

	public function delete($id) {
		return $this->client->delete('/domains/' . $this->seg($id));
	}
}
