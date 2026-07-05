<?php namespace Smnv\Resend;

/**
 * Resource
 *
 * Base class for Resend API resource groups.
 */
abstract class Resource {

	/** @var Client */
	protected $client;

	public function __construct(Client $client) {
		$this->client = $client;
	}

	/**
	 * URL-encode a path segment.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function seg($value) {
		return rawurlencode((string) $value);
	}
}
