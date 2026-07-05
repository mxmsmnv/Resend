<?php namespace Smnv\Resend;

/**
 * Templates
 *
 * Email template management. Only published templates can be sent.
 */
class Templates extends Resource {

	/**
	 * @param array $data name, html, alias, from, subject, reply_to, variables
	 */
	public function create(array $data) {
		return $this->client->post('/templates', $data);
	}

	public function all(array $params = []) {
		return $this->client->get('/templates', $params);
	}

	public function get($idOrAlias) {
		return $this->client->get('/templates/' . $this->seg($idOrAlias));
	}

	public function update($idOrAlias, array $data) {
		return $this->client->patch('/templates/' . $this->seg($idOrAlias), $data);
	}

	public function delete($idOrAlias) {
		return $this->client->delete('/templates/' . $this->seg($idOrAlias));
	}

	public function duplicate($idOrAlias) {
		return $this->client->post('/templates/' . $this->seg($idOrAlias) . '/duplicate');
	}

	public function publish($idOrAlias) {
		return $this->client->post('/templates/' . $this->seg($idOrAlias) . '/publish');
	}
}
