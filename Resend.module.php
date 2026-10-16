<?php namespace ProcessWire;

use Smnv\Resend\Broadcasts;
use Smnv\Resend\Client;
use Smnv\Resend\ContactProperties;
use Smnv\Resend\Contacts;
use Smnv\Resend\Crypto;
use Smnv\Resend\Domains;
use Smnv\Resend\Emails;
use Smnv\Resend\Logs;
use Smnv\Resend\Segments;
use Smnv\Resend\Templates;
use Smnv\Resend\Topics;
use Smnv\Resend\Webhooks;

require_once __DIR__ . '/src/Crypto.php';
require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/Resource.php';
require_once __DIR__ . '/src/Emails.php';
require_once __DIR__ . '/src/Domains.php';
require_once __DIR__ . '/src/Segments.php';
require_once __DIR__ . '/src/Contacts.php';
require_once __DIR__ . '/src/ContactProperties.php';
require_once __DIR__ . '/src/Topics.php';
require_once __DIR__ . '/src/Templates.php';
require_once __DIR__ . '/src/Broadcasts.php';
require_once __DIR__ . '/src/Webhooks.php';
require_once __DIR__ . '/src/Logs.php';
require_once __DIR__ . '/src/Stats.php';
require_once __DIR__ . '/src/McpProviderTrait.php';

/**
 * Resend
 *
 * WireMail implementation and full API client for Resend (resend.com).
 *
 * Sending:
 *   $m = $mail->new(['module' => 'Resend']);
 *   $m->to('user@example.com')->subject('Hello')->bodyHTML('<p>Hi</p>')->send();
 *
 * Resend extras (fluent):
 *   $m->scheduledAt('in 1 hour');
 *   $m->addTag('category', 'welcome');
 *   $m->idempotencyKey('welcome-user-123');
 *   $m->useTemplate('tmpl_id', ['CTA' => 'Sign up']);
 *   $m->topicId('topic_id');
 *
 * API client (resource-based):
 *   $resend = $modules->get('Resend');
 *   $resend->domains()->all();
 *   $resend->contacts()->create(['email' => 'a@b.com']);
 *
 * All methods return decoded arrays. On error, ['error' => [...]] is returned
 * and the last error is available via $resend->getLastError().
 *
 * Copyright (c) 2026 Maxim Semenov <maxim@smnv.org>
 * License: MIT
 */

class Resend extends WireMail implements Module, ConfigurableModule {

	use ResendMcpProviderTrait;

	public static function getModuleInfo() {
		return [
			'title' => 'Resend',
			'summary' => 'Send email via the Resend API. Full API client: emails, batch, scheduling, domains, contacts, segments, topics, templates, broadcasts, webhooks, logs.',
			'version' => 103,
			'author' => 'Maxim Semenov',
			'href' => 'https://github.com/mxmsmnv/Resend',
			'icon' => 'envelope-o',
			'singular' => false,
			'autoload' => false,
			'mcpProvider' => true,
			'pocketWireProvider' => true,
			'requires' => [
				'ProcessWire>=3.0.184',
				'PHP>=8.1',
			],
			'installs' => ['ProcessResend', 'ResendWebhooks', 'WireMailResend'],
		];
	}

	const LOG_NAME = 'resend';

	/** Dedicated settings table; the API key is stored encrypted (see Crypto) */
	const SETTINGS_TABLE = 'resend_settings';

	/** Config defaults; also the whitelist of setting names */
	const CONFIG_DEFAULTS = [
		'apiKey' => '',
		'webhookSigningSecret' => '',
		'fromEmail' => '',
		'fromName' => '',
		'replyToEmail' => '',
		'defaultTopicId' => '',
		'defaultTags' => '',
		'globalBcc' => '',
		'redirectEmail' => '',
		'httpRetries' => 0,
		'disableSending' => 0,
		'logSends' => 1,
		'httpTimeout' => 30,
	];

	/** Settings stored encrypted at rest (see Crypto) */
	const ENCRYPTED_SETTINGS = ['apiKey', 'webhookSigningSecret'];

	/** @var array|null Per-request settings cache, shared across instances */
	protected static $settings = null;

	/** @var bool Whether the Modules::saveConfig hook was attached this request */
	protected static $configHooked = false;

	/** @var Client|null */
	protected $client = null;

	/** @var array Resource instances cache */
	protected $resources = [];

	/** Default Resend-specific send options; restored after each completed send */
	const OPTION_DEFAULTS = [
		'scheduledAt' => '',
		'tags' => [],
		'idempotencyKey' => '',
		'template' => null,
		'topicId' => '',
	];

	/** @var array Extra Resend-specific send options */
	protected $resendOptions = self::OPTION_DEFAULTS;

	/** @var string|null Last sent email id (single send) */
	protected $lastEmailId = null;

	/** @var int Recipient count before the dev redirect rewrites the payload */
	protected $intendedRecipients = 0;

	public function __construct() {
		parent::__construct();
		foreach(self::CONFIG_DEFAULTS as $key => $value) $this->set($key, $value);
	}

	public function init() {
		// Settings live in a dedicated table, not in the modules table
		foreach($this->loadSettings() as $key => $value) $this->set($key, $value);
		if(!self::$configHooked) {
			self::$configHooked = true;
			// Route module config saves (admin config screen) into our table
			$this->wire()->modules->addHookBefore('saveConfig', $this, 'hookModulesSaveConfig');
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * API client accessors
	 * ------------------------------------------------------------------
	 */

	/**
	 * @return Client
	 */
	public function client() {
		if($this->client === null) {
			$this->client = new Client($this, $this->apiKey, (int) $this->httpTimeout, (int) $this->httpRetries);
		}
		return $this->client;
	}

	/**
	 * @return Emails
	 */
	public function emails() {
		return $this->resource(Emails::class);
	}

	/**
	 * @return Domains
	 */
	public function domains() {
		return $this->resource(Domains::class);
	}

	/**
	 * @return Segments
	 */
	public function segments() {
		return $this->resource(Segments::class);
	}

	/**
	 * @return Contacts
	 */
	public function contacts() {
		return $this->resource(Contacts::class);
	}

	/**
	 * @return ContactProperties
	 */
	public function contactProperties() {
		return $this->resource(ContactProperties::class);
	}

	/**
	 * @return Topics
	 */
	public function topics() {
		return $this->resource(Topics::class);
	}

	/**
	 * @return Templates
	 */
	public function templates() {
		return $this->resource(Templates::class);
	}

	/**
	 * @return Broadcasts
	 */
	public function broadcasts() {
		return $this->resource(Broadcasts::class);
	}

	/**
	 * @return Webhooks
	 */
	public function webhooks() {
		return $this->resource(Webhooks::class);
	}

	/**
	 * @return Logs
	 */
	public function logs() {
		return $this->resource(Logs::class);
	}

	/**
	 * @param string $class
	 * @return object
	 */
	protected function resource($class) {
		if(!isset($this->resources[$class])) {
			$this->resources[$class] = new $class($this->client());
		}
		return $this->resources[$class];
	}

	/**
	 * Whether a response array is an error.
	 *
	 * @param mixed $result
	 * @return bool
	 */
	public function isError($result) {
		return $this->client()->isError($result);
	}

	/**
	 * @return array|null
	 */
	public function getLastError() {
		return $this->client()->getLastError();
	}

	/**
	 * @return array|null
	 */
	public function getLastResponse() {
		return $this->client()->getLastResponse();
	}

	/**
	 * Verify a Resend (Svix) webhook signature.
	 *
	 * @param string $payload Raw request body
	 * @param array $headers Request headers
	 * @param string $signingSecret Webhook signing secret (whsec_...); defaults
	 *  to the webhookSigningSecret module setting when omitted
	 * @return bool
	 */
	public function verifyWebhookSignature($payload, array $headers, $signingSecret = '') {
		if(!strlen((string) $signingSecret)) $signingSecret = (string) $this->webhookSigningSecret;
		return Webhooks::verifySignature($payload, $headers, $signingSecret);
	}

	/**
	 * Permission level of the configured API key.
	 *
	 * Detected with a probe request (GET /domains) and cached for an hour,
	 * keyed by a hash of the API key so swapping the key re-detects
	 * immediately. Indeterminate results (invalid key, network error) are
	 * not cached and return an empty string.
	 *
	 * @return string 'full_access', 'sending_access' or '' when unknown
	 */
	public function apiKeyPermission() {
		if(!strlen($this->apiKey)) return '';
		$cache = $this->wire()->cache;
		$cacheKey = $this->permissionCacheKey();
		$cached = $cache->get($cacheKey);
		if(is_string($cached) && strlen($cached)) return $cached;
		$result = $this->domains()->all();
		if(!$this->isError($result)) {
			$permission = 'full_access';
		} elseif($result['error']['statusCode'] === 401 && $result['error']['name'] === 'restricted_api_key') {
			$permission = 'sending_access';
		} else {
			return '';
		}
		$cache->save($cacheKey, $permission, 3600);
		return $permission;
	}

	/**
	 * Drop the cached permission and re-detect. Needed when the key's
	 * permission is changed on the Resend side while the key itself stays
	 * the same, so the hash-keyed cache would otherwise serve the old level.
	 *
	 * @return string New permission ('full_access', 'sending_access' or '')
	 */
	public function refreshApiKeyPermission() {
		if(!strlen($this->apiKey)) return '';
		$this->wire()->cache->delete($this->permissionCacheKey());
		return $this->apiKeyPermission();
	}

	/**
	 * @return string
	 */
	protected function permissionCacheKey() {
		return 'resend-key-permission-' . substr(sha1($this->apiKey), 0, 16);
	}

	/*
	 * ------------------------------------------------------------------
	 * WireMail implementation
	 * ------------------------------------------------------------------
	 */

	/**
	 * Send the email via Resend API.
	 *
	 * Resend-specific options (schedule, tags, idempotency key, template, topic)
	 * are reset after a completed send. On failure they are kept, so a retry
	 * reuses the same idempotency key.
	 *
	 * @return int Number of emails accepted by Resend
	 */
	public function ___send() {

		if(!strlen($this->apiKey)) {
			$this->logError('API key is not configured');
			return 0;
		}

		$payload = $this->buildPayload();
		if($payload === false) return 0;

		if((int) $this->disableSending) {
			$this->logMessage('Sending disabled (test mode). Payload: ' . $this->jsonForLog($this->payloadForLog($payload)));
			$this->resendOptions = self::OPTION_DEFAULTS;
			return $this->intendedRecipients;
		}

		$idempotencyKey = $this->resendOptions['idempotencyKey'];
		if(!strlen($idempotencyKey) && (int) $this->httpRetries > 0) {
			// With retries enabled a network failure after the server processed
			// the request could double-send; an auto key makes retries safe
			$idempotencyKey = 'pw-auto-' . bin2hex(random_bytes(16));
		}

		$result = $this->emails()->send($payload, $idempotencyKey);

		if($this->isError($result)) {
			$this->logError('Send failed: ' . $this->client()->errorToString($result['error']));
			return 0;
		}

		$this->lastEmailId = isset($result['id']) ? $result['id'] : null;
		$this->resendOptions = self::OPTION_DEFAULTS;

		if((int) $this->logSends) {
			$to = implode(', ', (array) $payload['to']);
			$redirected = isset($payload['headers']['X-Original-To']) ? " (redirected from {$payload['headers']['X-Original-To']})" : '';
			$this->logMessage("Sent id={$this->lastEmailId} to={$to}{$redirected} subject={$payload['subject']}");
		}

		return $this->intendedRecipients;
	}

	/**
	 * Build the /emails payload from WireMail properties and Resend options.
	 *
	 * @return array|false
	 */
	protected function buildPayload() {

		$mail = $this->mail;

		// From
		$fromEmail = strlen($mail['from']) ? $mail['from'] : $this->fromEmail;
		$fromName = strlen($mail['fromName']) ? $mail['fromName'] : $this->fromName;
		if(!strlen($fromEmail)) {
			$this->logError('No from address (set in module config or via from())');
			return false;
		}
		$from = $this->formatAddress($fromEmail, $fromName);

		// To (with optional names)
		$to = [];
		foreach($mail['to'] as $email) {
			$name = isset($mail['toName'][$email]) ? $mail['toName'][$email] : '';
			$to[] = $this->formatAddress($email, $name);
		}
		if(!count($to)) {
			$this->logError('No recipients');
			return false;
		}

		$payload = [
			'from' => $from,
			'to' => $to,
			'subject' => $this->sanitizeLine($mail['subject']),
		];

		// Template mode excludes html/text
		if(is_array($this->resendOptions['template'])) {
			$payload['template'] = $this->resendOptions['template'];
		} else {
			if(strlen($mail['bodyHTML'])) $payload['html'] = $mail['bodyHTML'];
			if(strlen($mail['body'])) $payload['text'] = $mail['body'];
			if(!isset($payload['html']) && !isset($payload['text'])) {
				$this->logError('No body or bodyHTML set');
				return false;
			}
		}

		// CC / BCC via headers set by WireMail::header('cc', ...)
		$cc = $this->extractAddressHeader('cc');
		$bcc = $this->extractAddressHeader('bcc');
		if(count($cc)) $payload['cc'] = $cc;
		if(count($bcc)) $payload['bcc'] = $bcc;

		// Reply-To
		$replyTo = strlen($mail['replyTo']) ? $mail['replyTo'] : $this->replyToEmail;
		if(strlen($replyTo)) {
			$replyToName = isset($mail['replyToName']) && strlen($mail['replyToName']) ? $mail['replyToName'] : '';
			if(strpos($replyTo, '<') !== false) {
				// Already in "Name <email>" format
				$payload['reply_to'] = $this->sanitizeLine($replyTo);
			} else {
				$payload['reply_to'] = $this->formatAddress($replyTo, $replyToName);
			}
		}

		// Custom headers (excluding cc/bcc handled above)
		$headers = [];
		foreach($mail['header'] as $key => $value) {
			$lower = strtolower($key);
			if($lower === 'cc' || $lower === 'bcc') continue;
			$headers[$this->sanitizeLine($key)] = $this->sanitizeLine($value);
		}
		if(count($headers)) $payload['headers'] = $headers;

		// Attachments
		if(count($mail['attachments'])) {
			$attachments = [];
			foreach($mail['attachments'] as $filename => $filepath) {
				if(!is_file($filepath)) continue;
				$attachments[] = [
					'filename' => $this->sanitizeLine(is_string($filename) && strlen($filename) ? $filename : basename($filepath)),
					'content' => base64_encode(file_get_contents($filepath)),
				];
			}
			if(count($attachments)) $payload['attachments'] = $attachments;
		}

		// Resend extras
		if(strlen($this->resendOptions['scheduledAt'])) {
			$payload['scheduled_at'] = $this->resendOptions['scheduledAt'];
		}
		// Config default tags first; per-send tags with the same name win
		$tags = $this->defaultTagsArray();
		foreach($this->resendOptions['tags'] as $tag) $tags[$tag['name']] = $tag;
		if(count($tags)) $payload['tags'] = array_values($tags);
		$topicId = strlen($this->resendOptions['topicId']) ? $this->resendOptions['topicId'] : $this->defaultTopicId;
		if(strlen($topicId)) {
			$payload['topic_id'] = $topicId;
		}

		$this->intendedRecipients = count($to);

		if(strlen($this->redirectEmail)) {
			// Dev redirect: deliver to one address, record the originals in headers
			$headers = isset($payload['headers']) ? $payload['headers'] : [];
			$headers['X-Original-To'] = $this->sanitizeLine(implode(', ', (array) $payload['to']));
			if(isset($payload['cc'])) {
				$headers['X-Original-Cc'] = $this->sanitizeLine(implode(', ', $payload['cc']));
				unset($payload['cc']);
			}
			if(isset($payload['bcc'])) {
				$headers['X-Original-Bcc'] = $this->sanitizeLine(implode(', ', $payload['bcc']));
				unset($payload['bcc']);
			}
			$payload['headers'] = $headers;
			$payload['to'] = [$this->sanitizeLine($this->redirectEmail)];
		} elseif(strlen($this->globalBcc)) {
			$bcc = isset($payload['bcc']) ? $payload['bcc'] : [];
			foreach(explode(',', $this->globalBcc) as $address) {
				$address = $this->sanitizeLine($address);
				if(strlen($address) && !in_array($address, $bcc)) $bcc[] = $address;
			}
			if(count($bcc)) $payload['bcc'] = $bcc;
		}

		return $payload;
	}

	/**
	 * Parse the defaultTags setting ("name:value, name:value") into tag arrays
	 * keyed by name.
	 *
	 * @return array
	 */
	protected function defaultTagsArray() {
		$out = [];
		foreach(preg_split('/[,\n]/', (string) $this->defaultTags) as $pair) {
			$pair = trim($pair);
			if(!strlen($pair)) continue;
			$parts = explode(':', $pair, 2);
			$name = $this->sanitizeTag(trim($parts[0]));
			if(!strlen($name)) continue;
			$value = $this->sanitizeTag(isset($parts[1]) ? trim($parts[1]) : '');
			$out[$name] = ['name' => $name, 'value' => strlen($value) ? $value : '1'];
		}
		return $out;
	}

	/**
	 * Format an address as "Name <email>" with CR/LF stripped.
	 *
	 * @param string $email
	 * @param string $name
	 * @return string
	 */
	protected function formatAddress($email, $name = '') {
		$email = $this->sanitizeLine($email);
		$name = $this->sanitizeLine($name);
		// Strip angle brackets from names to keep the address parseable
		$name = str_replace(['<', '>'], '', $name);
		return strlen($name) ? "$name <$email>" : $email;
	}

	/**
	 * Strip CR/LF from a single-line value (header injection prevention).
	 *
	 * @param string $value
	 * @return string
	 */
	protected function sanitizeLine($value) {
		return trim(str_replace(["\r", "\n"], ' ', (string) $value));
	}

	/**
	 * Extract cc/bcc addresses from WireMail headers.
	 *
	 * @param string $type 'cc' or 'bcc'
	 * @return array
	 */
	protected function extractAddressHeader($type) {
		$out = [];
		foreach($this->mail['header'] as $key => $value) {
			if(strtolower($key) !== $type) continue;
			// Split on commas outside double quotes: '"Doe, John" <a@b.com>' stays intact
			foreach(preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) as $address) {
				$address = $this->sanitizeLine($address);
				if(strlen($address)) $out[] = $address;
			}
		}
		return $out;
	}

	/*
	 * ------------------------------------------------------------------
	 * Fluent Resend extras
	 * ------------------------------------------------------------------
	 */

	/**
	 * Schedule the email. Natural language ('in 1 hour') or ISO 8601.
	 *
	 * @param string $when
	 * @return $this
	 */
	public function scheduledAt($when) {
		$this->resendOptions['scheduledAt'] = $this->sanitizeLine($when);
		return $this;
	}

	/**
	 * Add a tag (key/value, ASCII letters, numbers, _ and - only).
	 *
	 * @param string $name
	 * @param string $value
	 * @return $this
	 */
	public function addTag($name, $value) {
		$this->resendOptions['tags'][] = [
			'name' => $this->sanitizeTag($name),
			'value' => $this->sanitizeTag($value),
		];
		return $this;
	}

	/**
	 * Set an idempotency key to prevent duplicate sends.
	 *
	 * @param string $key
	 * @return $this
	 */
	public function idempotencyKey($key) {
		$this->resendOptions['idempotencyKey'] = (string) $key;
		return $this;
	}

	/**
	 * Send using a published Resend template. Excludes html/text body.
	 *
	 * @param string $id Template id or alias
	 * @param array $variables Key/value template variables
	 * @return $this
	 */
	public function useTemplate($id, array $variables = []) {
		$template = ['id' => (string) $id];
		if(count($variables)) $template['variables'] = $variables;
		$this->resendOptions['template'] = $template;
		return $this;
	}

	/**
	 * Set the topic id for subscription-aware sending.
	 *
	 * @param string $topicId
	 * @return $this
	 */
	public function topicId($topicId) {
		$this->resendOptions['topicId'] = (string) $topicId;
		return $this;
	}

	/**
	 * Get id of the last sent email.
	 *
	 * @return string|null
	 */
	public function getLastEmailId() {
		return $this->lastEmailId;
	}

	protected function sanitizeTag($value) {
		return substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $value), 0, 256);
	}

	/*
	 * ------------------------------------------------------------------
	 * Logging
	 * ------------------------------------------------------------------
	 */

	/**
	 * Payload copy safe for logging: attachment contents replaced with their
	 * size, html/text bodies truncated.
	 *
	 * @param array $payload
	 * @return array
	 */
	protected function payloadForLog(array $payload) {
		if(isset($payload['attachments'])) {
			foreach($payload['attachments'] as $i => $attachment) {
				$payload['attachments'][$i]['content'] = '[' . strlen($attachment['content']) . ' bytes base64]';
			}
		}
		foreach(['html', 'text'] as $key) {
			if(isset($payload[$key]) && strlen($payload[$key]) > 1024) {
				$payload[$key] = substr($payload[$key], 0, 1024) . '... [truncated]';
			}
		}
		return $payload;
	}

	protected function jsonForLog(array $data) {
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '[unencodable payload]' : $json;
	}

	protected function logMessage($message) {
		$this->wire()->log->save(self::LOG_NAME, $this->sanitizeLine($message));
	}

	protected function logError($message) {
		$message = $this->sanitizeLine($message);
		$this->wire()->log->save(self::LOG_NAME, 'ERROR: ' . $message);
		$this->error($message, Notice::log);
	}

	/*
	 * ------------------------------------------------------------------
	 * Settings storage (dedicated table, encrypted API key)
	 * ------------------------------------------------------------------
	 */

	public function ___install() {
		$this->createSettingsTable();
	}

	public function ___uninstall() {
		$this->wire()->database->exec('DROP TABLE IF EXISTS `' . self::SETTINGS_TABLE . '`');
		try {
			$this->wire()->cache->delete('resend-key-permission-*');
		} catch(\Exception $e) {
			// Cache entries expire on their own within an hour
		}
	}

	public function ___upgrade($fromVersion, $toVersion) {
		$this->createSettingsTable();
		$this->loadSettings(); // triggers legacy config migration when the table is empty
		$modules = $this->wire()->modules;
		if(!$modules->isInstalled('ResendWebhooks')) {
			try {
				$modules->install('ResendWebhooks');
			} catch(\Exception $e) {
				// Available for manual install from the modules screen
			}
		}
		if(!$modules->isInstalled('WireMailResend')) {
			try {
				$modules->install('WireMailResend');
			} catch(\Exception $e) {
				// Available for manual install from the modules screen
			}
		}
	}

	protected function createSettingsTable() {
		$config = $this->wire()->config;
		$engine = $config->dbEngine ?: 'InnoDB';
		$charset = $config->dbCharset ?: 'utf8mb4';
		$this->wire()->database->exec(
			'CREATE TABLE IF NOT EXISTS `' . self::SETTINGS_TABLE . '` (' .
			'`name` VARCHAR(128) NOT NULL, ' .
			'`value` MEDIUMTEXT, ' .
			'`modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
			"PRIMARY KEY (`name`)) ENGINE=$engine DEFAULT CHARSET=$charset"
		);
	}

	/**
	 * Site salt used for settings encryption. Per config.php guidance,
	 * tableSalt is meant for non-authentication hashing; userAuthSalt is the
	 * fallback for older installs without it. Never stored in the database.
	 *
	 * @return string
	 */
	protected function settingsSalt() {
		$config = $this->wire()->config;
		$salt = (string) $config->tableSalt;
		return strlen($salt) ? $salt : (string) $config->userAuthSalt;
	}

	/**
	 * Load settings from the dedicated table (per-request cache). Creates the
	 * table and migrates legacy module config on first use after an upgrade.
	 *
	 * @return array name => value, apiKey decrypted
	 */
	protected function loadSettings() {
		if(is_array(self::$settings)) return self::$settings;
		$database = $this->wire()->database;
		$rows = [];
		try {
			$query = $database->query('SELECT `name`, `value` FROM `' . self::SETTINGS_TABLE . '`');
			$rows = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
		} catch(\Exception $e) {
			// Table missing: pre-1.8 installation loading updated files
			try {
				$this->createSettingsTable();
			} catch(\Exception $e2) {
				return [];
			}
		}
		if(!is_array($rows)) $rows = [];
		if(!count($rows)) $rows = $this->migrateLegacyConfig();
		$settings = array_intersect_key($rows, self::CONFIG_DEFAULTS);
		foreach(self::ENCRYPTED_SETTINGS as $name) {
			if(!isset($settings[$name]) || !strlen($settings[$name])) continue;
			$decrypted = Crypto::decrypt($settings[$name], $this->settingsSalt());
			if(!strlen($decrypted) && Crypto::isEncrypted($settings[$name])) {
				$this->logError("Stored $name cannot be decrypted (site salt changed?). Re-enter it in the module config.");
			}
			$settings[$name] = $decrypted;
		}
		self::$settings = $settings;
		return $settings;
	}

	/**
	 * One-time migration of legacy config from the modules table.
	 *
	 * @return array Stored rows (apiKey encrypted)
	 */
	protected function migrateLegacyConfig() {
		$modules = $this->wire()->modules;
		$legacy = $modules->getConfig('Resend');
		if(!is_array($legacy)) return [];
		$legacy = array_intersect_key($legacy, self::CONFIG_DEFAULTS);
		if(!count($legacy)) return [];
		$rows = $this->saveSettings($legacy);
		// Clear the modules table copy; the empty-data save passes the hook through
		$modules->saveConfig('Resend', []);
		$this->wire()->log->save(self::LOG_NAME, 'Settings migrated to the ' . self::SETTINGS_TABLE . ' table; encrypted settings require tableSalt or userAuthSalt.');
		return $rows;
	}

	/**
	 * Persist settings to the dedicated table. The API key is encrypted with
	 * the site salt before storage.
	 *
	 * @param array $data name => plaintext value; unknown names are dropped
	 * @return array Rows as written (apiKey encrypted)
	 */
	public function saveSettings(array $data) {
		$rows = array_intersect_key($data, self::CONFIG_DEFAULTS);
		foreach(self::ENCRYPTED_SETTINGS as $name) {
			if(!isset($rows[$name])) continue;
			if(strlen((string) $rows[$name])) {
				$encrypted = Crypto::encrypt($rows[$name], $this->settingsSalt());
				if(!strlen($encrypted)) {
					$this->logError("Cannot store $name because encrypted settings are unavailable. Configure tableSalt or userAuthSalt in config.php.");
				}
				$rows[$name] = $encrypted;
			} else {
				$rows[$name] = '';
			}
		}
		$stmt = $this->wire()->database->prepare(
			'INSERT INTO `' . self::SETTINGS_TABLE . '` (`name`, `value`) VALUES (:name, :value) ' .
			'ON DUPLICATE KEY UPDATE `value` = :value2'
		);
		foreach($rows as $name => $value) {
			$stmt->execute([':name' => $name, ':value' => (string) $value, ':value2' => (string) $value]);
		}
		self::$settings = null;
		return $rows;
	}

	/**
	 * Hook before Modules::saveConfig — intercepts module config screen saves
	 * for this module, stores them in the dedicated table and keeps the
	 * modules table entry empty. Empty-data saves pass through untouched.
	 *
	 * @param HookEvent $event
	 */
	public function hookModulesSaveConfig(HookEvent $event) {
		$class = $event->arguments(0);
		$className = is_object($class) ? $class->className() : (string) $class;
		if($className !== 'Resend') return;
		$data = $event->arguments(1);
		// Modules::saveConfig($class, $property, $value) single-value form
		if(is_string($data)) $data = [$data => $event->arguments(2)];
		if(!is_array($data) || !count($data)) return;
		$settings = $this->loadSettings();
		// A blank secret on the config screen means "keep the current value"
		foreach(self::ENCRYPTED_SETTINGS as $name) {
			if(array_key_exists($name, $data) && !strlen((string) $data[$name])) {
				$data[$name] = isset($settings[$name]) ? $settings[$name] : '';
			}
		}
		$this->saveSettings(array_merge($settings, $data));
		$event->arguments(1, []);
		$event->arguments(2, null);
	}

	/*
	 * ------------------------------------------------------------------
	 * Module config
	 * ------------------------------------------------------------------
	 */

	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {

		$modules = $this->wire()->modules;
		if($this->className() === 'WireMailResend') {
			/** @var InputfieldMarkup $f */
			$f = $modules->get('InputfieldMarkup');
			$f->label = $this->_('Compatibility Alias');
			$f->icon = 'info-circle';
			$url = $this->wire()->sanitizer->entities($this->wire()->config->urls->admin . 'module/edit?name=Resend');
			$f->value = '<p>WireMailResend is installed only so modules that look for <code>WireMail*</code> providers can detect Resend.</p>' .
				'<p>It uses the same encrypted settings, sender defaults and delivery behavior as Resend. Configure the module from <a href="' . $url . '">Resend settings</a>.</p>';
			$inputfields->add($f);
			return $inputfields;
		}

		/*
		 * API Access
		 */
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('API Access');
		$fs->icon = 'key';

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'apiKey');
		$f->label = $this->_('API Key');
		// The stored key is never rendered back into the form
		$f->attr('value', '');
		$f->attr('type', 'password');
		$f->attr('autocomplete', 'new-password');
		$f->icon = 'key';
		if(strlen($this->apiKey)) {
			$f->description = $this->_('A key is configured and stored encrypted in the database. Enter a new key to replace it, or leave blank to keep the current one.');
			$f->collapsed = Inputfield::collapsedYes;
		} else {
			$f->description = $this->_('Resend API key (re_...). Create one at resend.com/api-keys. Stored encrypted in a dedicated table using the site salt from config.php.');
			$f->required = true;
		}
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'webhookSigningSecret');
		$f->label = $this->_('Webhook Signing Secret');
		$f->attr('value', '');
		$f->attr('type', 'password');
		$f->attr('autocomplete', 'new-password');
		$f->icon = 'plug';
		if(strlen($this->webhookSigningSecret)) {
			$f->description = $this->_('A secret is configured and stored encrypted. Enter a new value to replace it, or leave blank to keep the current one.');
			$f->collapsed = Inputfield::collapsedYes;
		} else {
			$f->description = $this->_('Optional. Webhook signing secret (whsec_...). When set, verifyWebhookSignature() works without passing the secret. Stored encrypted.');
			$f->collapsed = Inputfield::collapsedNo;
		}
		$fs->add($f);

		$inputfields->add($fs);

		/*
		 * Sending Defaults
		 */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Sending Defaults');
		$fs->icon = 'envelope-o';

		$f = $modules->get('InputfieldEmail');
		$f->attr('name', 'fromEmail');
		$f->label = $this->_('Default From Email');
		$f->description = $this->_('Must belong to a domain verified in Resend.');
		$f->attr('value', $this->fromEmail);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'fromName');
		$f->label = $this->_('Default From Name');
		$f->attr('value', $this->fromName);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldEmail');
		$f->attr('name', 'replyToEmail');
		$f->label = $this->_('Default Reply-To Email');
		$f->attr('value', $this->replyToEmail);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'defaultTopicId');
		$f->label = $this->_('Default Topic ID');
		$f->description = $this->_('Optional. Applied to all sends unless overridden via topicId().');
		$f->attr('value', $this->defaultTopicId);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'defaultTags');
		$f->label = $this->_('Default Tags');
		$f->description = $this->_('Optional. name:value pairs, comma separated (ASCII letters, digits, _ and -). Attached to every send; per-send tags with the same name take precedence.');
		$f->attr('value', $this->defaultTags);
		$f->attr('placeholder', 'site:example, env:production');
		$fs->add($f);

		$inputfields->add($fs);

		/*
		 * Delivery Behavior
		 */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Delivery Behavior');
		$fs->icon = 'sliders';

		$f = $modules->get('InputfieldEmail');
		$f->attr('name', 'redirectEmail');
		$f->label = $this->_('Redirect All Email To (dev)');
		$f->description = $this->_('Development mode: every email is delivered to this address instead of the real recipients; originals are recorded in X-Original-To/Cc/Bcc headers. Leave empty in production.');
		$f->attr('value', $this->redirectEmail);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'globalBcc');
		$f->label = $this->_('Global BCC');
		$f->description = $this->_('Optional. Blind copy of every sent email, e.g. for archiving. One or more addresses, comma separated. Not applied while the redirect is active.');
		$f->attr('value', $this->globalBcc);
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'httpRetries');
		$f->label = $this->_('HTTP Retries');
		$f->description = $this->_('Automatic retries on rate limiting (429) and network errors, with exponential backoff. 0 disables, maximum 5.');
		$f->attr('value', (int) $this->httpRetries);
		$f->attr('min', 0);
		$f->attr('max', 5);
		$f->columnWidth = 25;
		$fs->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'httpTimeout');
		$f->label = $this->_('HTTP Timeout (seconds)');
		$f->attr('value', (int) $this->httpTimeout);
		$f->columnWidth = 25;
		$fs->add($f);

		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'logSends');
		$f->label = $this->_('Log successful sends');
		$f->attr('checked', (int) $this->logSends ? 'checked' : '');
		$f->columnWidth = 25;
		$fs->add($f);

		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'disableSending');
		$f->label = $this->_('Disable sending (test mode)');
		$f->description = $this->_('Payloads are logged but not sent.');
		$f->attr('checked', (int) $this->disableSending ? 'checked' : '');
		$f->columnWidth = 25;
		$fs->add($f);

		$inputfields->add($fs);

		return $inputfields;
	}
}
