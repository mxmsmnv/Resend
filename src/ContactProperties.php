<?php namespace Smnv\Resend;

/**
 * ContactProperties
 *
 * Custom contact property definitions.
 */
class ContactProperties extends Resource {

	const TYPES = ['string', 'number', 'boolean', 'date'];

	/**
	 * @param array $data key, type (see self::TYPES), fallback_value
	 */
	public function create(array $data) {
		return $this->client->post('/contact-properties', $data);
	}

	public function all(array $params = []) {
		return $this->client->get('/contact-properties', $params);
	}

	public function get($id) {
		return $this->client->get('/contact-properties/' . $this->seg($id));
	}

	public function update($id, array $data) {
		return $this->client->patch('/contact-properties/' . $this->seg($id), $data);
	}

	public function delete($id) {
		return $this->client->delete('/contact-properties/' . $this->seg($id));
	}
}
