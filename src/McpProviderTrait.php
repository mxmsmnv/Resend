<?php namespace ProcessWire;

/** Secret-free MCP readiness. Sending remains an explicit site-owned integration. */
trait ResendMcpProviderTrait {
    public function mcpProviderInfo(): array {
        return ['name' => 'resend', 'title' => 'Resend', 'version' => '1.0.2'];
    }

    public function mcpTools(): array {
        return [[
            'name' => 'resend_status',
            'title' => 'Resend readiness',
            'description' => 'Return local delivery readiness without API keys, webhook secrets, recipients, message bodies, contact data, or external API requests.',
            'handler' => [$this, 'mcpResendStatus'],
            'scope' => 'read', 'read_only' => true, 'destructive' => false,
            'idempotent' => true, 'open_world' => false,
            'input_schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => [], 'additionalProperties' => false],
        ]];
    }

    public function mcpResendStatus(): array {
        return [
            'version' => '1.0.2',
            'api_key_configured' => trim((string)$this->apiKey) !== '',
            'from_identity_configured' => trim((string)$this->fromEmail) !== '',
            'sending_disabled' => (bool)$this->disableSending,
            'webhook_secret_configured' => trim((string)$this->webhookSigningSecret) !== '',
            'external_request_performed' => false,
            'send_tools' => false,
        ];
    }
}
