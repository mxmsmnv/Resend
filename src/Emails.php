<?php namespace Smnv\Resend;

/**
 * Emails
 *
 * Sending, retrieving and managing sent and received emails.
 */
class Emails extends Resource {

	/**
	 * Send an email.
	 *
	 * @param array $payload See Resend POST /emails
	 * @param string $idempotencyKey Optional, max 256 chars, 24h window
	 * @return array
	 */
	public function send(array $payload, $idempotencyKey = '') {
		$headers = [];
		if(strlen($idempotencyKey)) $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 256);
		return $this->client->post('/emails', $payload, $headers);
	}

	/**
	 * Send up to 100 emails in one call. attachments/scheduled_at not supported by the batch API.
	 *
	 * @param array $emails Array of email payloads
	 * @param string $idempotencyKey Optional
	 * @return array
	 */
	public function sendBatch(array $emails, $idempotencyKey = '') {
		if(count($emails) > 100) return $this->client->clientError('Batch is limited to 100 emails per call');
		$headers = [];
		if(strlen($idempotencyKey)) $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 256);
		return $this->client->post('/emails/batch', array_values($emails), $headers);
	}

	public function get($id) {
		return $this->client->get('/emails/' . $this->seg($id));
	}

	/**
	 * List sent emails (cursor pagination: limit, after, before).
	 */
	public function all(array $params = []) {
		return $this->client->get('/emails', $params);
	}

	/**
	 * Reschedule a scheduled email.
	 */
	public function update($id, $scheduledAt) {
		return $this->client->patch('/emails/' . $this->seg($id), ['scheduled_at' => $scheduledAt]);
	}

	/**
	 * Cancel a scheduled email.
	 */
	public function cancel($id) {
		return $this->client->post('/emails/' . $this->seg($id) . '/cancel');
	}

	public function attachments($emailId, array $params = []) {
		return $this->client->get('/emails/' . $this->seg($emailId) . '/attachments', $params);
	}

	public function attachment($emailId, $attachmentId) {
		return $this->client->get('/emails/' . $this->seg($emailId) . '/attachments/' . $this->seg($attachmentId));
	}

	public function receivedAll(array $params = []) {
		return $this->client->get('/emails/receiving', $params);
	}

	public function received($id) {
		return $this->client->get('/emails/receiving/' . $this->seg($id));
	}

	public function receivedAttachments($emailId, array $params = []) {
		return $this->client->get('/emails/receiving/' . $this->seg($emailId) . '/attachments', $params);
	}

	public function receivedAttachment($emailId, $attachmentId) {
		return $this->client->get('/emails/receiving/' . $this->seg($emailId) . '/attachments/' . $this->seg($attachmentId));
	}
}
