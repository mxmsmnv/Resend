<?php
/**
 * Test runner. Plain PHP, no dependencies, no ProcessWire bootstrap:
 * only classes that work standalone are covered (Crypto, Stats, Cli,
 * Client transport with a stubbed WireHttp, Webhooks signature).
 *
 * Usage: php tests/run.php
 * Exit code 0 when all checks pass, 1 otherwise.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$GLOBALS['testPass'] = 0;
$GLOBALS['testFail'] = 0;

function check($label, $ok) {
	if($ok) {
		$GLOBALS['testPass']++;
		echo "  ok: $label\n";
	} else {
		$GLOBALS['testFail']++;
		echo "FAIL: $label\n";
	}
}

foreach(glob(__DIR__ . '/test-*.php') as $file) {
	echo "\n== " . basename($file) . " ==\n";
	require $file;
}

echo "\n{$GLOBALS['testPass']} passed, {$GLOBALS['testFail']} failed\n";
exit($GLOBALS['testFail'] ? 1 : 0);
