<?php namespace Smnv\Resend;

/**
 * Broadcasts
 *
 * Marketing broadcast campaigns to segments.
 */
class Broadcasts extends Resource {

	/**
	 * @param array $data segment_id, from, subject, html/text, name, reply_to, topic_id
	 */
	public function create(array $data) {
		return $this->client->post('/broadcasts', $data);
	}

	public function all(array $params = []) {
		return $this->client->get('/broadcasts', $params);
	}

	public function get($id) {
		return $this->client->get('/broadcasts/' . $this->seg($id));
	}

	public function update($id, array $data) {
		return $this->client->patch('/broadcasts/' . $this->seg($id), $data);
	}

	/**
	 * @param string $scheduledAt Optional, natural language or ISO 8601. Empty sends now.
	 */
	public function send($id, $scheduledAt = '') {
		$data = [];
		if(strlen($scheduledAt)) $data['scheduled_at'] = $scheduledAt;
		return $this->client->post('/broadcasts/' . $this->seg($id) . '/send', $data);
	}

	public function delete($id) {
		return $this->client->delete('/broadcasts/' . $this->seg($id));
	}
}
