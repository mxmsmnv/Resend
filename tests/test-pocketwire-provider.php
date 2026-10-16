<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Resend.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/McpProviderTrait.php');
$read = strstr($trait, 'public function pocketWireRead');
$read = $read === false ? '' : (strstr($read, 'private function pocketWireCanView', true) ?: '');
$checks = [
	str_contains($module, "'pocketWireProvider' => true"),
	str_contains($trait, 'pocketWireProviderInfo()'),
	str_contains($trait, 'pocketWireManifest(User $actor)'),
	str_contains($trait, 'pocketWireRead(string $resource, string $identifier, array $query, User $actor)'),
	str_contains($trait, "hasPermission('resend-admin')") && str_contains($trait, "wire('user')"),
	str_contains($trait, "'id' => 'status'") && str_contains($trait, "'actions' => []"),
	str_contains($trait, "'layout' => 'dashboard'") && str_contains($trait, "'layout' => 'metrics'"),
	str_contains($trait, "'configuration_exposed' => false") && str_contains($trait, "'delivery_data_exposed' => false"),
	$read !== '' && !preg_match('/apiKey|fromEmail|webhookSigningSecret|client\\(|send\\(|mcpResendStatus\\(/', $read),
];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Resend PocketWire provider contract failed.\n"); exit(1); }
echo 'Resend PocketWire provider contract passed: ' . count($checks) . " checks.\n";
