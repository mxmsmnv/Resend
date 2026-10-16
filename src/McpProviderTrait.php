<?php namespace ProcessWire;

/** Secret-free MCP readiness. Sending remains an explicit site-owned integration. */
trait ResendMcpProviderTrait {
    public function pocketWireProviderInfo(): array {
        return ['id' => 'resend', 'title' => 'Resend', 'module' => 'Resend', 'moduleVersion' => '1.0.3'];
    }

    public function pocketWireManifest(User $actor): array {
        $ready = $this->pocketWireCanView($actor);
        return [
            'id' => 'resend', 'title' => 'Resend', 'module' => 'Resend', 'moduleVersion' => '1.0.3',
            'status' => $ready ? 'native_read_only' : 'unsupported',
            'permissions' => ['scope' => 'providers.read', 'processWire' => ['resend-admin']],
            'presentation' => ['layout' => 'dashboard', 'icon' => 'mail-outline', 'summary' => 'Local Resend module status.', 'accentColor' => '#111827'],
            'resources' => $ready ? [['id' => 'status', 'title' => 'Status', 'method' => 'GET', 'endpoint' => '/api/pocketwire/v1/providers/resend/status', 'presentation' => ['layout' => 'metrics']]] : [],
            'actions' => [],
        ];
    }

    public function pocketWireRead(string $resource, string $identifier, array $query, User $actor): array {
        if(!$this->pocketWireCanView($actor)) throw new WirePermissionException('The resend-admin permission is required.');
        if($resource !== 'status' || $identifier !== '') throw new Wire404Exception('Resend resource was not found.');
        return [
            'version' => '1.0.3',
            'module_ready' => true,
            'configuration_exposed' => false,
            'delivery_data_exposed' => false,
            'external_request_performed' => false,
            'send_tools' => false,
        ];
    }

    private function pocketWireCanView(User $actor): bool {
        $current = $this->wire('user');
        return (int)$current->id === (int)$actor->id
            && ($actor->isSuperuser() || ($actor->isLoggedin() && $actor->hasPermission('resend-admin')));
    }

    public function mcpProviderInfo(): array {
        return ['name' => 'resend', 'title' => 'Resend', 'version' => '1.0.3'];
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
            'version' => '1.0.3',
            'api_key_configured' => trim((string)$this->apiKey) !== '',
            'from_identity_configured' => trim((string)$this->fromEmail) !== '',
            'sending_disabled' => (bool)$this->disableSending,
            'webhook_secret_configured' => trim((string)$this->webhookSigningSecret) !== '',
            'external_request_performed' => false,
            'send_tools' => false,
        ];
    }
}
