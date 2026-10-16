<?php namespace ProcessWire;

/**
 * ResendWebhooks
 *
 * Public webhook receiver for Resend events. Registers a URL hook at
 * /resend-webhook/, verifies the Svix signature with the secret stored in
 * the Resend module settings, stores events in the resend_events table
 * (deduplicated by Svix id) and fires the hookable processEvent() so site
 * code can react:
 *
 *   $wire->addHookAfter('ResendWebhooks::processEvent', function($e) {
 *       $type = $e->arguments(0);  // e.g. 'email.bounced'
 *       $event = $e->arguments(1); // full decoded payload
 *   });
 *
 * The endpoint is active only while a webhook signing secret is configured
 * in the Resend module; without one the URL falls through to the regular 404.
 *
 * Copyright (c) 2026 Maxim Semenov <maxim@smnv.org>
 * License: MIT
 */

class ResendWebhooks extends WireData implements Module {

	public static function getModuleInfo() {
		return [
			'title' => 'Resend Webhooks',
			'summary' => 'Receives Resend webhook events at /resend-webhook/, verifies signatures, stores events locally.',
			'version' => 103,
			'author' => 'Maxim Semenov',
			'icon' => 'plug',
			'singular' => true,
			'autoload' => true,
			'requires' => ['Resend'],
		];
	}

	const EVENTS_TABLE = 'resend_events';
	const ENDPOINT_PATH = '/resend-webhook/';
	const PRUNE_DAYS = 90;

	public function init() {
		$this->addHook(self::ENDPOINT_PATH, $this, 'hookEndpoint');
	}

	/*
	 * ------------------------------------------------------------------
	 * Endpoint
	 * ------------------------------------------------------------------
	 */

	/**
	 * URL hook handler for the webhook endpoint.
	 *
	 * @param HookEvent $event
	 * @return string|false
	 */
	public function hookEndpoint(HookEvent $event) {
		/** @var Resend $resend */
		$resend = $this->wire()->modules->get('Resend');
		if(!$resend || !strlen((string) $resend->webhookSigningSecret)) {
			// No secret configured: fall through to the regular 404
			return false;
		}
		if(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
			http_response_code(405);
			return 'Method not allowed';
		}
		$payload = (string) file_get_contents('php://input');
		$headers = $this->requestHeaders();
		if(!$resend->verifyWebhookSignature($payload, $headers)) {
			$this->wire()->log->save(Resend::LOG_NAME, 'Webhook rejected: invalid signature');
			http_response_code(401);
			return 'Invalid signature';
		}
		$data = json_decode($payload, true);
		if(!is_array($data) || empty($data['type'])) {
			http_response_code(400);
			return 'Invalid payload';
		}
		$eventId = isset($headers['svix-id']) ? (string) $headers['svix-id'] : '';
		$stored = $this->storeEvent($eventId, (string) $data['type'], isset($data['data']) && is_array($data['data']) ? $data['data'] : []);
		// Dispatch only the first delivery; Svix retries reuse the same id
		if($stored) $this->processEvent((string) $data['type'], $data);
		http_response_code(200);
		header('Content-Type: application/json; charset=utf-8');
		return '{"received":true}';
	}

	/**
	 * Hookable dispatch for verified, newly received events.
	 *
	 * @param string $type Event type, e.g. 'email.bounced'
	 * @param array $event Full decoded webhook payload ('type', 'data', ...)
	 */
	public function ___processEvent($type, array $event) {
	}

	/*
	 * ------------------------------------------------------------------
	 * Event storage
	 * ------------------------------------------------------------------
	 */

	/**
	 * Store an event. Duplicate deliveries (same Svix id) are ignored.
	 *
	 * @param string $eventId Svix message id
	 * @param string $type
	 * @param array $data Event data section
	 * @return bool True when newly stored
	 */
	public function storeEvent($eventId, $type, array $data) {
		$emailId = '';
		if(isset($data['email_id'])) $emailId = (string) $data['email_id'];
		elseif(isset($data['id'])) $emailId = (string) $data['id'];
		$recipient = '';
		if(isset($data['to'])) $recipient = implode(', ', (array) $data['to']);
		elseif(isset($data['email'])) $recipient = (string) $data['email'];
		$values = [
			':event_id' => strlen($eventId) ? substr($eventId, 0, 64) : ('noid-' . bin2hex(random_bytes(12))),
			':type' => substr((string) $type, 0, 64),
			':email_id' => substr($emailId, 0, 64),
			':recipient' => substr($recipient, 0, 250),
			':data' => $this->jsonForStorage($data),
		];
		$sql = 'INSERT IGNORE INTO `' . self::EVENTS_TABLE . '` (`event_id`, `type`, `email_id`, `recipient`, `data`) ' .
			'VALUES (:event_id, :type, :email_id, :recipient, :data)';
		$database = $this->wire()->database;
		try {
			$stmt = $database->prepare($sql);
			$stmt->execute($values);
		} catch(\Exception $e) {
			// Table missing (module files updated without reinstall)
			try {
				$this->createEventsTable();
				$stmt = $database->prepare($sql);
				$stmt->execute($values);
			} catch(\Exception $e2) {
				return false;
			}
		}
		$stored = $stmt->rowCount() > 0;
		if($stored && random_int(1, 50) === 1) $this->prune();
		return $stored;
	}

	/**
	 * Latest stored events, newest first.
	 *
	 * @param int $limit
	 * @param int $beforeId Return events with id below this (pagination)
	 * @return array
	 */
	public function latestEvents($limit = 25, $beforeId = 0) {
		$limit = max(1, min(100, (int) $limit));
		$sql = 'SELECT `id`, `event_id`, `type`, `email_id`, `recipient`, `data`, `created` FROM `' . self::EVENTS_TABLE . '`';
		if((int) $beforeId > 0) $sql .= ' WHERE `id` < ' . (int) $beforeId;
		$sql .= ' ORDER BY `id` DESC LIMIT ' . $limit;
		try {
			return $this->wire()->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return [];
		}
	}

	/**
	 * Delete events older than PRUNE_DAYS.
	 */
	public function prune() {
		try {
			$this->wire()->database->exec(
				'DELETE FROM `' . self::EVENTS_TABLE . '` WHERE `created` < DATE_SUB(NOW(), INTERVAL ' . self::PRUNE_DAYS . ' DAY)'
			);
		} catch(\Exception $e) {
			// Non-critical maintenance
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * Install / uninstall
	 * ------------------------------------------------------------------
	 */

	public function ___install() {
		$this->createEventsTable();
	}

	public function ___uninstall() {
		$this->wire()->database->exec('DROP TABLE IF EXISTS `' . self::EVENTS_TABLE . '`');
	}

	protected function createEventsTable() {
		$config = $this->wire()->config;
		$engine = $config->dbEngine ?: 'InnoDB';
		$charset = $config->dbCharset ?: 'utf8mb4';
		$this->wire()->database->exec(
			'CREATE TABLE IF NOT EXISTS `' . self::EVENTS_TABLE . '` (' .
			'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
			'`event_id` VARCHAR(64) NOT NULL, ' .
			'`type` VARCHAR(64) NOT NULL, ' .
			"`email_id` VARCHAR(64) NOT NULL DEFAULT '', " .
			"`recipient` VARCHAR(250) NOT NULL DEFAULT '', " .
			'`data` MEDIUMTEXT, ' .
			'`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
			'PRIMARY KEY (`id`), ' .
			'UNIQUE KEY `event_id` (`event_id`), ' .
			'KEY `type` (`type`), ' .
			'KEY `created` (`created`)' .
			") ENGINE=$engine DEFAULT CHARSET=$charset"
		);
	}

	/**
	 * @param array $data
	 * @return string
	 */
	protected function jsonForStorage(array $data) {
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '{}' : $json;
	}

	/*
	 * ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Request headers, lowercased keys (getallheaders() is not available
	 * on all SAPIs).
	 *
	 * @return array
	 */
	protected function requestHeaders() {
		if(function_exists('getallheaders')) {
			$headers = getallheaders();
			if(is_array($headers)) return array_change_key_case($headers, CASE_LOWER);
		}
		$headers = [];
		foreach($_SERVER as $key => $value) {
			if(strpos($key, 'HTTP_') !== 0) continue;
			$name = strtolower(str_replace('_', '-', substr($key, 5)));
			$headers[$name] = $value;
		}
		return $headers;
	}
}
