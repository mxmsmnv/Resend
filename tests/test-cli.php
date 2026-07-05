<?php
/**
 * Cli: config commands, properties commands, output masking. Uses a mocked
 * module and memory streams; no ProcessWire, no HTTP.
 */

require_once __DIR__ . '/../src/Client.php';
require_once __DIR__ . '/../src/Resource.php';
require_once __DIR__ . '/../src/ContactProperties.php';
require_once __DIR__ . '/../src/Webhooks.php';
require_once __DIR__ . '/../src/Cli.php';

use Smnv\Resend\Cli;

class CliTestFakeProps {
	public $calls = [];
	public function all(array $params = []) {
		$this->calls[] = ['all', $params];
		return ['data' => [['id' => 'prop_1', 'key' => 'plan', 'type' => 'string', 'fallback_value' => 'free']]];
	}
	public function create(array $data) {
		$this->calls[] = ['create', $data];
		return ['id' => 'prop_new'];
	}
	public function delete($id) {
		$this->calls[] = ['delete', $id];
		return ['deleted' => true];
	}
}

class CliTestFakeLogs {
	public function all(array $params = []) {
		return ['data' => [[
			'id' => 'log_1',
			'method' => 'GET',
			'endpoint' => '/emails',
			'response_status' => 200,
			'created_at' => '2026-07-04 03:47:00',
		]]];
	}
}

class CliTestFakeModule {
	const CONFIG_DEFAULTS = ['apiKey' => '', 'webhookSigningSecret' => '', 'fromEmail' => '', 'httpRetries' => 0];
	const ENCRYPTED_SETTINGS = ['apiKey', 'webhookSigningSecret'];
	public $props;
	public $logs;
	public $saved = [];
	public $values = ['apiKey' => 'super-secret-key', 'webhookSigningSecret' => '', 'fromEmail' => 'a@b.c', 'httpRetries' => 2];
	public function __construct() { $this->props = new CliTestFakeProps(); $this->logs = new CliTestFakeLogs(); }
	public function __get($n) { return $this->values[$n] ?? null; }
	public function contactProperties() { return $this->props; }
	public function logs() { return $this->logs; }
	public function saveSettings(array $data) {
		$rows = array_intersect_key($data, self::CONFIG_DEFAULTS);
		$this->saved = $rows;
		return $rows;
	}
	public function isError($r) { return is_array($r) && isset($r['error']); }
}

function cliTestRun($mock, array $argv) {
	$out = fopen('php://memory', 'w+');
	$err = fopen('php://memory', 'w+');
	$cli = new Cli($mock, $out, $err);
	$code = $cli->run(array_merge(['bin/resend'], $argv));
	rewind($out); rewind($err);
	return [$code, stream_get_contents($out), stream_get_contents($err)];
}

$mock = new CliTestFakeModule();

list($code, $out) = cliTestRun($mock, ['help']);
check('cli: help lists config and import commands', $code === 0 && strpos($out, 'config:set <name>') !== false && strpos($out, 'contacts:import') !== false);
check('cli: help omits removed apikeys commands', strpos($out, 'apikeys:') === false);

list($code, $out) = cliTestRun($mock, ['config:list']);
check('cli: config:list masks set secret', strpos($out, '(set, stored encrypted)') !== false);
check('cli: config:list marks empty secret', strpos($out, '(not set)') !== false);
check('cli: config:list shows plain values', strpos($out, 'a@b.c') !== false);
check('cli: secret value never printed', strpos($out, 'super-secret-key') === false);

list($code, $out) = cliTestRun($mock, ['config:set', 'fromEmail', 'new@x.y']);
check('cli: config:set writes value', $code === 0 && $mock->saved === ['fromEmail' => 'new@x.y']);

list($code, $out) = cliTestRun($mock, ['config:set', 'apiKey', 're_brand_new']);
check('cli: config:set does not echo secret', $code === 0 && strpos($out, 're_brand_new') === false);

list($code, $out, $err) = cliTestRun($mock, ['config:set', 'bogus', 'v']);
check('cli: unknown setting rejected with hint', $code === 1 && strpos($err, 'Unknown setting: bogus') !== false && strpos($err, 'fromEmail') !== false);

list($code, $out) = cliTestRun($mock, ['properties:list']);
check('cli: properties:list renders table', $code === 0 && strpos($out, 'plan') !== false);

list($code, $out) = cliTestRun($mock, ['logs:list']);
check('cli: logs:list renders response_status', $code === 0 && strpos($out, '/emails') !== false && strpos($out, '200') !== false);

list($code, $out, $err) = cliTestRun($mock, ['properties:create', 'x', '--type=float']);
check('cli: invalid property type rejected', $code === 1 && strpos($err, 'Invalid type') !== false);

list($code, $out, $err) = cliTestRun($mock, ['nonexistent:command']);
check('cli: unknown command -> exit 1', $code === 1 && strpos($err, 'Unknown command') !== false);

list($code, $out, $err) = cliTestRun($mock, ['apikeys:list']);
check('cli: removed apikeys command -> exit 1', $code === 1 && strpos($err, 'Unknown command') !== false);

// contacts:import dry run parses CSV without touching the API
$csv = tempnam(sys_get_temp_dir(), 'resend-test-') . '.csv';
file_put_contents($csv, "email,first_name,last_name\nuser1@example.com,User,One\nnot-an-email,Bad,Row\nuser2@example.com,,\n");
list($code, $out, $err) = cliTestRun($mock, ['contacts:import', $csv, '--dry-run']);
check('cli: import dry-run counts valid rows', strpos($out, 'Imported: 2, failed: 1') !== false);
check('cli: import reports invalid line', strpos($err, 'invalid email: not-an-email') !== false);
unlink($csv);
