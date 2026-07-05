<?php namespace Smnv\Resend;

/**
 * Stats
 *
 * Pure aggregation helpers for the dashboard charts. Array in, array out;
 * no API calls. There is no public Resend metrics API, so charts are built
 * from recent email list data.
 */
class Stats {

	/**
	 * Emails per calendar day for the last $days days, today inclusive.
	 *
	 * @param array $emails Email list items with a created_at ISO date
	 * @param int $days
	 * @param int|null $now Unix timestamp override for tests (defaults to time())
	 * @return array List of ['date' => 'Y-m-d', 'count' => int], oldest first
	 */
	public static function perDay(array $emails, $days = 14, $now = null) {
		$now = $now === null ? time() : (int) $now;
		$buckets = [];
		for($i = $days - 1; $i >= 0; $i--) {
			$buckets[date('Y-m-d', strtotime("-$i days", $now))] = 0;
		}
		foreach($emails as $email) {
			$ts = isset($email['created_at']) ? strtotime((string) $email['created_at']) : false;
			if($ts === false) continue;
			$key = date('Y-m-d', $ts);
			if(isset($buckets[$key])) $buckets[$key]++;
		}
		$out = [];
		foreach($buckets as $date => $count) $out[] = ['date' => $date, 'count' => $count];
		return $out;
	}

	/**
	 * Emails per calendar day with dashboard delivery-signal buckets.
	 *
	 * @param array $emails Email list items with created_at and last_event/status
	 * @param int $days
	 * @param int|null $now Unix timestamp override for tests (defaults to time())
	 * @return array List of ['date', 'count', 'delivered', 'opened', 'clicked', 'problem'], oldest first
	 */
	public static function perDaySignals(array $emails, $days = 14, $now = null) {
		$now = $now === null ? time() : (int) $now;
		$buckets = [];
		for($i = $days - 1; $i >= 0; $i--) {
			$buckets[date('Y-m-d', strtotime("-$i days", $now))] = [
				'count' => 0,
				'delivered' => 0,
				'opened' => 0,
				'clicked' => 0,
				'problem' => 0,
			];
		}
		foreach($emails as $email) {
			$ts = isset($email['created_at']) ? strtotime((string) $email['created_at']) : false;
			if($ts === false) continue;
			$key = date('Y-m-d', $ts);
			if(!isset($buckets[$key])) continue;
			$status = self::status($email);
			$buckets[$key]['count']++;
			if($status === 'delivered') $buckets[$key]['delivered']++;
			elseif($status === 'opened') $buckets[$key]['opened']++;
			elseif($status === 'clicked') $buckets[$key]['clicked']++;
			elseif($status === 'bounced' || $status === 'complained') $buckets[$key]['problem']++;
		}
		$out = [];
		foreach($buckets as $date => $counts) {
			$out[] = array_merge(['date' => $date], $counts);
		}
		return $out;
	}

	/**
	 * Email counts by status (last_event, falling back to status), most frequent first.
	 *
	 * @param array $emails
	 * @return array status => count
	 */
	public static function byStatus(array $emails) {
		$out = [];
		foreach($emails as $email) {
			$status = self::status($email);
			if(!isset($out[$status])) $out[$status] = 0;
			$out[$status]++;
		}
		arsort($out);
		return $out;
	}

	/**
	 * Normalize list item status.
	 *
	 * @param array $email
	 * @return string
	 */
	protected static function status(array $email) {
		$status = isset($email['last_event']) && strlen((string) $email['last_event'])
			? (string) $email['last_event']
			: (isset($email['status']) ? (string) $email['status'] : '');
		return strlen($status) ? $status : 'unknown';
	}
}
