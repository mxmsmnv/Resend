<?php namespace ProcessWire;

/**
 * Client transport: retry behavior with a scripted WireHttp stub.
 * Runs without ProcessWire; stubs are defined only when the real core
 * classes are absent.
 */

if(!class_exists('ProcessWire\Wire')) {
	class Wire {
		public function wire($o = null) { return $o; }
	}
}
if(!class_exists('ProcessWire\WireHttp')) {
	class WireHttp {
		public static $queue = [];
		public static $attempts = 0;
		public static $lastHeaders = [];
		public static $lastTimeout = 0;
		public static $lastBody = null;
		protected $code = 0;
		public function setTimeout($t) { self::$lastTimeout = $t; }
		public function setHeaders(array $h) { self::$lastHeaders = $h; }
		public function get($url) { return $this->next(); }
		public function post($url, $data = []) { self::$lastBody = $data; return $this->next(); }
		public function send($url, $data = '', $m = 'POST') { self::$lastBody = $data; return $this->next(); }
		public function getHttpCode() { return $this->code; }
		public function getError() { return 'network error'; }
		protected function next() {
			self::$attempts++;
			$step = count(self::$queue) ? array_shift(self::$queue) : [false, 0];
			if($step[0] === 'throw') throw new \Exception('transport exploded');
			$this->code = $step[1];
			return $step[0];
		}
	}
}

namespace Smnv\Resend\Tests;

use ProcessWire\Wire;
use ProcessWire\WireHttp;
use Smnv\Resend\Client;

require_once __DIR__ . '/../src/Client.php';

function checkT($label, $ok) { \check($label, $ok); }

$client2 = new Client(new Wire(), 're_x', 5, 2);

WireHttp::$queue = [['{"name":"rate_limit"}', 429], ['{"name":"rate_limit"}', 429], ['{"id":"ok1"}', 200]];
WireHttp::$attempts = 0;
$t0 = microtime(true);
$res = $client2->get('/domains');
$elapsed = microtime(true) - $t0;
checkT('client: 429 retried to success in 3 attempts', ($res['id'] ?? '') === 'ok1' && WireHttp::$attempts === 3);
checkT('client: exponential backoff waited (~1.5s)', $elapsed >= 1.4 && $elapsed < 3.5);

WireHttp::$queue = [['{"name":"rate_limit","message":"slow"}', 429]];
WireHttp::$attempts = 0;
$client0 = new Client(new Wire(), 're_x', 5, 0);
$res = $client0->get('/domains');
checkT('client: retries=0 -> single attempt, 429 surfaced', WireHttp::$attempts === 1 && ($res['error']['statusCode'] ?? 0) === 429);

WireHttp::$queue = [['{"name":"validation_error"}', 400], ['{"id":"nope"}', 200]];
WireHttp::$attempts = 0;
$res = $client2->get('/x');
checkT('client: 400 never retried', WireHttp::$attempts === 1 && ($res['error']['statusCode'] ?? 0) === 400);

WireHttp::$queue = [[false, 0], ['{"id":"ok2"}', 200]];
WireHttp::$attempts = 0;
$res = $client2->post('/emails', ['to' => 'a@b.c']);
checkT('client: network error retried', ($res['id'] ?? '') === 'ok2' && WireHttp::$attempts === 2);

WireHttp::$queue = [[false, 0], [false, 0], [false, 0]];
WireHttp::$attempts = 0;
$res = $client2->get('/z');
checkT('client: exhausted retries -> error', isset($res['error']) && WireHttp::$attempts === 3);

$clientX = new Client(new Wire(), 're_x', 5, 99);
$rp = new \ReflectionProperty(Client::class, 'retries');
checkT('client: retries clamped to 5', $rp->getValue($clientX) === 5);

WireHttp::$queue = [];
WireHttp::$attempts = 0;
$res = $client2->request('TRACE', '/x');
checkT('client: unsupported method -> client error, no attempt', ($res['error']['name'] ?? '') === 'client_error' && WireHttp::$attempts === 0);

$resEmptyKey = (new Client(new Wire(), '', 5))->get('/domains');
checkT('client: missing key -> client error', ($resEmptyKey['error']['name'] ?? '') === 'client_error');

WireHttp::$queue = [['{"id":"ok3"}', 200]];
WireHttp::$attempts = 0;
$clientHeader = new Client(new Wire(), 're_x', -10, 0);
$res = $clientHeader->post('/emails', ['to' => 'a@b.c'], [
	"User-Agent\r\n" => 'bad',
	"X-Test\r\nInjected" => "one\ntwo",
]);
checkT('client: required User-Agent cannot be overridden', ($res['id'] ?? '') === 'ok3' && WireHttp::$lastHeaders['User-Agent'] === Client::USER_AGENT);
checkT('client: extra headers are CRLF-stripped', isset(WireHttp::$lastHeaders['X-TestInjected']) && WireHttp::$lastHeaders['X-TestInjected'] === 'onetwo');
checkT('client: timeout clamped to safe minimum', WireHttp::$lastTimeout === 1);
checkT('client: JSON body encoded once', WireHttp::$lastBody === '{"to":"a@b.c"}');

WireHttp::$queue = [['{"id":"should-not-send"}', 200]];
WireHttp::$attempts = 0;
$res = $client2->post('/emails', ['html' => "\xB1"]);
checkT('client: invalid JSON payload fails before HTTP attempt', ($res['error']['name'] ?? '') === 'client_error' && WireHttp::$attempts === 0);

WireHttp::$queue = [['{"error":{"name":"nested_error","message":"nested message"}}', 422]];
WireHttp::$attempts = 0;
$res = $client2->get('/nested');
checkT('client: nested API error normalized', ($res['error']['name'] ?? '') === 'nested_error' && ($res['error']['message'] ?? '') === 'nested message');

WireHttp::$queue = [['throw', 0], ['{"id":"ok4"}', 200]];
WireHttp::$attempts = 0;
$res = $client2->get('/throws');
checkT('client: transport exception retried', ($res['id'] ?? '') === 'ok4' && WireHttp::$attempts === 2);

WireHttp::$queue = [['throw', 0], ['throw', 0], ['throw', 0]];
WireHttp::$attempts = 0;
$res = $client2->get('/throws-again');
checkT('client: exhausted transport exception normalized', ($res['error']['message'] ?? '') === 'transport exploded' && WireHttp::$attempts === 3);
