<?php namespace Smnv\Resend;

/**
 * Contacts
 *
 * Contact management, segment membership and topic subscriptions.
 */
class Contacts extends Resource {

	/**
	 * @param array $data email (required), first_name, last_name, unsubscribed, properties
	 */
	public function create(array $data) {
		return $this->client->post('/contacts', $data);
	}

	/**
	 * @param string $idOrEmail Contact id or email address
	 */
	public function get($idOrEmail) {
		return $this->client->get('/contacts/' . $this->seg($idOrEmail));
	}

	public function update($idOrEmail, array $data) {
		return $this->client->patch('/contacts/' . $this->seg($idOrEmail), $data);
	}

	public function delete($idOrEmail) {
		return $this->client->delete('/contacts/' . $this->seg($idOrEmail));
	}

	public function all(array $params = []) {
		return $this->client->get('/contacts', $params);
	}

	public function addToSegment($idOrEmail, $segmentId) {
		return $this->client->post('/contacts/' . $this->seg($idOrEmail) . '/segments/' . $this->seg($segmentId));
	}

	public function removeFromSegment($idOrEmail, $segmentId) {
		return $this->client->delete('/contacts/' . $this->seg($idOrEmail) . '/segments/' . $this->seg($segmentId));
	}

	public function segments($idOrEmail, array $params = []) {
		return $this->client->get('/contacts/' . $this->seg($idOrEmail) . '/segments', $params);
	}

	public function topics($idOrEmail, array $params = []) {
		return $this->client->get('/contacts/' . $this->seg($idOrEmail) . '/topics', $params);
	}

	/**
	 * @param array $topics Array of ['id' => topicId, 'subscription' => 'opt-in'|'opt-out']
	 */
	public function updateTopics($idOrEmail, array $topics) {
		return $this->client->patch('/contacts/' . $this->seg($idOrEmail) . '/topics', ['topics' => $topics]);
	}
}
