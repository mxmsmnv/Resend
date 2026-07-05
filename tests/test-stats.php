<?php
/**
 * Stats: per-day bucketing and status aggregation for dashboard charts.
 */

require_once __DIR__ . '/../src/Stats.php';

use Smnv\Resend\Stats;

$now = strtotime('2026-07-02 12:00:00');

$emails = [
	['created_at' => '2026-07-02T09:00:00.000Z', 'last_event' => 'delivered'],
	['created_at' => '2026-07-02 10:30:00',      'last_event' => 'delivered'],
	['created_at' => '2026-07-01 23:59:59',      'last_event' => 'bounced'],
	['created_at' => '2026-06-19 00:00:00',      'last_event' => 'delivered'], // first day of the window
	['created_at' => '2026-06-18 23:59:59',      'last_event' => 'delivered'], // outside the window
	['created_at' => 'garbage',                  'last_event' => 'delivered'], // unparseable date
	['last_event' => 'opened'],                                                // no created_at
	['created_at' => '2026-07-02 11:00:00',      'status' => 'scheduled'],     // status fallback
	['created_at' => '2026-07-02 11:30:00'],                                   // no status at all
];

$days = Stats::perDay($emails, 14, $now);
check('stats: 14 buckets', count($days) === 14);
check('stats: oldest first', $days[0]['date'] === '2026-06-19' && $days[13]['date'] === '2026-07-02');
check('stats: window start counted', $days[0]['count'] === 1);
check('stats: today counted', $days[13]['count'] === 4);
check('stats: yesterday counted', $days[12]['date'] === '2026-07-01' && $days[12]['count'] === 1);
check('stats: outside-window and garbage excluded', array_sum(array_column($days, 'count')) === 6);
check('stats: empty input -> zero buckets', array_sum(array_column(Stats::perDay([], 14, $now), 'count')) === 0);

$signalDays = Stats::perDaySignals($emails, 14, $now);
check('stats: signal buckets keep totals', $signalDays[13]['count'] === 4 && $signalDays[12]['count'] === 1);
check('stats: signal buckets count delivery signals', $signalDays[13]['delivered'] === 2 && $signalDays[12]['problem'] === 1);
check('stats: signal buckets ignore non-signal statuses', $signalDays[13]['opened'] === 0 && $signalDays[13]['clicked'] === 0);

$statuses = Stats::byStatus($emails);
check('stats: most frequent first', array_key_first($statuses) === 'delivered' && $statuses['delivered'] === 5);
check('stats: status fallback used', isset($statuses['scheduled']) && $statuses['scheduled'] === 1);
check('stats: missing status -> unknown', isset($statuses['unknown']) && $statuses['unknown'] === 1);
check('stats: total preserved', array_sum($statuses) === count($emails));
