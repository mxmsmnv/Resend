<?php namespace Smnv\Resend;

use ProcessWire\Wire;
use ProcessWire\WireHttp;

/**
 * Client
 *
 * HTTP transport for the Resend REST API. Handles authentication,
 * request execution, JSON decoding and error normalization.
 */
class Client {

	const API_BASE = 'https://api.resend.com';
	const USER_AGENT = 'Resend-ProcessWire/1.0.1 (+https://github.com/mxmsmnv/Resend)';

	/** @var Wire Owning module, used to wire WireHttp instances */
	protected $wire;

	/** @var string */
	protected $apiKey;

	/** @var int */
	protected $timeout;

	/** @var int Extra attempts on transient failures (429, network errors) */
	protected $retries;

	/** @var array|null */
	protected $lastError = null;

	/** @var array|null */
	protected $lastResponse = null;

	/**
	 * @param Wire $wire Owning Wire-derived object (the Resend module)
	 * @param string $apiKey
	 * @param int $timeout Seconds
	 * @param int $retries Automatic retries on 429 and network errors (max 5)
	 */
	public function __construct(Wire $wire, $apiKey, $timeout = 30, $retries = 0) {
		$this->wire = $wire;
		$this->apiKey = (string) $apiKey;
		$this->timeout = max(1, min(120, (int) $timeout));
		$this->retries = max(0, min(5, (int) $retries));
	}

	public function get($endpoint, array $params = []) {
		return $this->request('GET', $endpoint, $params);
	}

	public function post($endpoint, array $data = [], array $headers = []) {
		return $this->request('POST', $endpoint, $data, $headers);
	}

	public function patch($endpoint, array $data = []) {
		return $this->request('PATCH', $endpoint, $data);
	}

	public function delete($endpoint) {
		return $this->request('DELETE', $endpoint);
	}

	/**
	 * Perform an API request.
	 *
	 * @param string $method GET|POST|PATCH|DELETE
	 * @param string $endpoint Path beginning with /
	 * @param array $data Query params (GET) or JSON body
	 * @param array $extraHeaders
	 * @return array Decoded response, or ['error' => ['name', 'message', 'statusCode']]
	 */
	public function request($method, $endpoint, array $data = [], array $extraHeaders = []) {

		if(!strlen($this->apiKey)) {
			return $this->clientError('API key is not configured');
		}

		if(!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'])) {
			return $this->clientError("Unsupported method: $method");
		}

		$url = self::API_BASE . $endpoint;
		if($method === 'GET' && count($data)) $url .= '?' . http_build_query($data);

		$response = false;
		$statusCode = 0;
		$http = null;
		$attempts = $this->retries + 1;
		$body = '';
		$transportError = '';
		if($method !== 'GET' && count($data)) {
			$body = $this->encodeJson($data);
			if($body === false) return $this->clientError('Request payload could not be encoded as JSON');
		}

		for($attempt = 1; $attempt <= $attempts; $attempt++) {
			$http = $this->wire->wire(new WireHttp());
			$http->setTimeout($this->timeout);
			$http->setHeaders(array_merge($this->sanitizeHeaders($extraHeaders), [
				'Authorization' => 'Bearer ' . $this->apiKey,
				'Content-Type' => 'application/json',
				'User-Agent' => self::USER_AGENT,
			]));

			try {
				switch($method) {
					case 'GET':
						$response = $http->get($url);
						break;
					case 'POST':
						// Batch endpoint expects a JSON array body; encode manually.
						// Bodyless actions (cancel, verify, publish) must send an empty
						// body: an empty PHP array would encode to [] where the API
						// expects an object.
						$response = $http->post($url, $body);
						break;
					default: // PATCH, DELETE
						$response = $http->send($url, $body, $method);
				}
				$statusCode = $http->getHttpCode();
				$transportError = '';
			} catch(\Exception $e) {
				$response = false;
				$statusCode = 0;
				$transportError = $e->getMessage();
			}

			// Retry only transient failures: rate limit or network error.
			// Other 4xx/5xx responses are returned as-is; sends carry an
			// idempotency key when the caller sets one, keeping retries safe.
			$retryable = $statusCode === 429 || ($response === false && $statusCode === 0);
			if(!$retryable || $attempt === $attempts) break;
			usleep(500000 * (1 << ($attempt - 1))); // 0.5s, 1s, 2s, 4s, 8s
		}
		$decoded = is_string($response) && strlen($response) ? json_decode($response, true) : null;
		if(!is_array($decoded)) $decoded = [];
		$this->lastResponse = $decoded;

		if($response === false || $statusCode >= 400) {
			$errorData = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : $decoded;
			$message = strlen($transportError) ? $transportError : $http->getError();
			$error = [
				'name' => isset($errorData['name']) ? $errorData['name'] : 'http_error',
				'message' => isset($errorData['message']) ? $errorData['message'] : $message,
				'statusCode' => $statusCode,
			];
			$this->lastError = $error;
			return ['error' => $error];
		}

		$this->lastError = null;
		return $decoded;
	}

	/**
	 * Whether a response array is an error.
	 *
	 * @param mixed $result
	 * @return bool
	 */
	public function isError($result) {
		return is_array($result) && isset($result['error']);
	}

	/**
	 * @return array|null
	 */
	public function getLastError() {
		return $this->lastError;
	}

	/**
	 * @return array|null
	 */
	public function getLastResponse() {
		return $this->lastResponse;
	}

	/**
	 * Build a client-side error result.
	 *
	 * @param string $message
	 * @return array
	 */
	public function clientError($message) {
		$error = ['name' => 'client_error', 'message' => $message, 'statusCode' => 0];
		$this->lastError = $error;
		return ['error' => $error];
	}

	/**
	 * Format an error array as a string.
	 *
	 * @param array $error
	 * @return string
	 */
	public function errorToString(array $error) {
		return (isset($error['name']) ? $error['name'] : 'error') . ': '
			. (isset($error['message']) ? $error['message'] : 'unknown')
			. ' (' . (isset($error['statusCode']) ? $error['statusCode'] : 0) . ')';
	}

	/**
	 * Strip CR/LF from header values to prevent header injection.
	 *
	 * @param array $headers
	 * @return array
	 */
	protected function sanitizeHeaders(array $headers) {
		$out = [];
		foreach($headers as $name => $value) {
			$name = str_replace(["\r", "\n"], '', (string) $name);
			$value = str_replace(["\r", "\n"], '', (string) $value);
			if(!strlen($name)) continue;
			$out[$name] = $value;
		}
		return $out;
	}

	/**
	 * Encode a request body as JSON, failing closed on invalid payloads.
	 *
	 * @param array $data
	 * @return string|false
	 */
	protected function encodeJson(array $data) {
		return json_encode($data);
	}
}
