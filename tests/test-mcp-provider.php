<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Resend.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/McpProviderTrait.php');
$checks = [str_contains($module, "'mcpProvider' => true"), str_contains($trait, "'resend_status'"), !str_contains($trait, '->send('), !str_contains($trait, '->client('), str_contains($trait, "'external_request_performed' => false"), str_contains($trait, "'additionalProperties' => false")];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Resend MCP provider contract failed.\n"); exit(1); }
echo "Resend MCP provider contract passed.\n";
