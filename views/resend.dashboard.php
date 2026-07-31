<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;

if(!function_exists(__NAMESPACE__ . '\resendDashboardBars')) {
	function resendDashboardBars(array $days, $sanitizer) {
		$width = 720;
		$height = 220;
		$top = 22;
		$bottom = 42;
		$left = 28;
		$plotHeight = $height - $top - $bottom;
		$count = count($days);
		if(!$count) return '';
		$signals = [
			'delivered' => [resendT('Delivered'), 'var(--uk-success, #32d296)', '1'],
			'opened' => [resendT('Opened'), 'var(--uk-primary, #1e87f0)', '.92'],
			'clicked' => [resendT('Clicked'), 'var(--uk-warning, #faa05a)', '1'],
			'problem' => [resendT('Bounced/Complained'), 'var(--uk-danger, #f0506e)', '1'],
		];
		$max = 1;
		foreach($days as $day) $max = max($max, (int) ($day['count'] ?? 0));
		$slot = ($width - ($left * 2)) / $count;
		$out = "<svg role='img' aria-label='Sending activity and delivery signals for the last 14 days' viewBox='0 0 $width $height' width='100%' height='$height' xmlns='http://www.w3.org/2000/svg'>";
		$out .= "<line x1='$left' y1='" . ($height - $bottom) . "' x2='" . ($width - $left) . "' y2='" . ($height - $bottom) . "' stroke='var(--pw-border-color, currentColor)' stroke-width='1'/>";
		foreach($days as $i => $day) {
			$value = (int) ($day['count'] ?? 0);
			$barHeight = $max ? (int) round(($value / $max) * $plotHeight) : 0;
			$x = (int) round($left + ($i * $slot) + 4);
			$y = (int) ($height - $bottom - $barHeight);
			$barWidth = max(8, (int) floor($slot - 8));
			$date = (string) ($day['date'] ?? '');
			$label = $sanitizer->entities(substr($date, 5));
			$title = $sanitizer->entities($date . ': ' . $value);
			$out .= "<g><title>$title</title>";
			$out .= "<rect x='$x' y='$y' width='$barWidth' height='$barHeight' fill='var(--pw-muted-color, currentColor)' opacity='.18'/>";
			$stackY = $height - $bottom;
			foreach($signals as $key => $meta) {
				$signalValue = (int) ($day[$key] ?? 0);
				if(!$signalValue) continue;
				$signalHeight = $max ? max(1, (int) round(($signalValue / $max) * $plotHeight)) : 0;
				$stackY -= $signalHeight;
				$fill = $meta[1];
				$opacity = $meta[2];
				$signalTitle = $sanitizer->entities($meta[0] . ': ' . $signalValue);
				$out .= "<rect x='$x' y='$stackY' width='$barWidth' height='$signalHeight' fill='$fill' opacity='$opacity'><title>$signalTitle</title></rect>";
			}
			if($value > 0) {
				$textY = max(12, $y - 6);
				$out .= "<text x='" . ($x + (int) ($barWidth / 2)) . "' y='$textY' text-anchor='middle' font-size='11' fill='var(--pw-text-color, currentColor)'>$value</text>";
			}
			if($i % 2 === 0 || $i === $count - 1) {
				$out .= "<text x='" . ($x + (int) ($barWidth / 2)) . "' y='" . ($height - 18) . "' text-anchor='middle' font-size='10' fill='var(--pw-muted-color, currentColor)'>$label</text>";
			}
			$out .= "</g>";
		}
		$out .= "</svg>";
		$out .= "<div class='uk-grid-small uk-child-width-auto uk-flex-middle uk-margin-small-top' uk-grid>";
		foreach($signals as $key => $meta) {
			$fill = $meta[1];
			$opacity = $meta[2];
			$legendLabel = $sanitizer->entities($meta[0]);
			$out .= "<div class='uk-flex uk-flex-middle'><span style='display:inline-block;width:12px;height:12px;background:$fill;opacity:$opacity;margin-right:6px;'></span><span class='uk-text-meta'>$legendLabel</span></div>";
		}
		$out .= "</div>";
		return $out;
	}
}

if(!function_exists(__NAMESPACE__ . '\resendDashboardStatusBars')) {
	function resendDashboardStatusBars(array $statuses, $total, $process, $sanitizer) {
		if(!$total) return '<p class="uk-text-meta">' . resendE('No recent email statuses are available yet.') . '</p>';
		$out = '';
		foreach($statuses as $status => $count) {
			$count = (int) $count;
			$percent = $total ? (int) round($count / $total * 100) : 0;
			$out .= "<div class='uk-margin-small-bottom'>";
			$out .= "<div class='uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom'><span>" . $process->badge($status) . "</span><span class='uk-text-meta'>$count / $total ($percent%)</span></div>";
			$out .= "<progress class='uk-progress' value='$count' max='$total'></progress>";
			$out .= "</div>";
		}
		return $out;
	}
}
?>
<?= resendNav($adminUrl, '', $restricted) ?>
<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted"><?= resendE('Start here when checking the integration. The dashboard highlights readiness, recent delivery behavior and the next operational actions to check before sending.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>domains/"><?= resendE('Manage Domains') ?></a></div>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>emails/"><?= resendE('View Emails') ?></a></div>
			<div><a class="uk-button uk-button-primary" style="text-decoration: none;" href="<?= $adminUrl ?>test/"><?= resendE('Test Send') ?></a></div>
		</div>
	</div>
</div>

<?php if(!$configured): ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3><?= resendE('Configuration Required') ?></h3>
		<p><?= resendE('Configure the') ?> <a href="<?= wire()->config->urls->admin ?>module/edit?name=Resend"><?= resendE('Resend module') ?></a> <?= resendE('with your Resend API key, default sender and delivery options to get started.') ?></p>
		<p><a class="uk-button uk-button-primary" style="text-decoration: none;" href="<?= wire()->config->urls->admin ?>module/edit?name=Resend"><?= resendE('Open Settings') ?></a></p>
	</div>
<?php elseif($restricted): ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3><?= resendE('Sending-Only API Key') ?></h3>
		<p><?= resendE('The configured API key is restricted to sending emails. WireMail sending and the') ?> <a href="<?= $adminUrl ?>test/"><?= resendE('Test Send') ?></a> <?= resendE('screen work; management sections (emails, domains, contacts, broadcasts, ...) are unavailable and locked in the navigation above.') ?></p>
		<p><?= resendE('To unlock them, create a key with') ?> <strong><?= resendE('Full access') ?></strong> <?= resendE('permission at resend.com/api-keys and update it in the') ?> <a href="<?= wire()->config->urls->admin ?>module/edit?name=Resend"><?= resendE('module configuration') ?></a>.</p>
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<?= resendActionForm($adminUrl, ['action' => 'recheck'], 'Re-check permission', $csrf) ?>
			<div><a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>test/"><?= resendE('Send Test') ?></a></div>
		</div>
		<?= resendNote("The permission level is cached for an hour. If you changed this key's permission in the Resend dashboard, re-check to pick it up immediately.") ?>
	</div>
<?php else: ?>

	<?php
	$verified = 0;
	$needsWork = [];
	$domainStatuses = [];
	foreach($domains as $d) {
		$status = (string) ($d['status'] ?? 'unknown');
		if(!isset($domainStatuses[$status])) $domainStatuses[$status] = 0;
		$domainStatuses[$status]++;
		if($status === 'verified') {
			$verified++;
		} elseif(in_array($status, ['failed', 'temporary_failure', 'not_started', 'pending'], true)) {
			$needsWork[] = $d;
		}
	}
	$domainCount = count($domains);
	$readiness = $domainCount ? (int) round($verified / $domainCount * 100) : 0;
	$failed = count($needsWork);
	$delivered = (int) ($statsStatuses['delivered'] ?? 0);
	$opened = (int) ($statsStatuses['opened'] ?? 0);
	$clicked = (int) ($statsStatuses['clicked'] ?? 0);
	$bounced = (int) ($statsStatuses['bounced'] ?? 0);
	$complained = (int) ($statsStatuses['complained'] ?? 0);
	$positive = $delivered + $opened + $clicked;
	$deliveryHealth = $statsTotal ? (int) round($positive / $statsTotal * 100) : 0;
	$engagement = $statsTotal ? (int) round(($opened + $clicked) / $statsTotal * 100) : 0;
	$maxDay = 0;
	foreach($statsDays as $day) $maxDay = max($maxDay, (int) ($day['count'] ?? 0));
	$attention = [];
	if(!$domainCount) $attention[] = ['Add a sending domain', $adminUrl . 'domains/'];
	if($failed) $attention[] = ['Review ' . $failed . ' domain' . ($failed === 1 ? '' : 's') . ' needing attention', $adminUrl . 'domains/'];
	if(!$statsTotal) $attention[] = ['Send a test email to confirm delivery', $adminUrl . 'test/'];
	if($bounced || $complained) $attention[] = ['Inspect bounced or complained messages', $adminUrl . 'emails/'];
	if(!count($attention)) $attention[] = ['Everything looks ready; send another test when changing settings', $adminUrl . 'test/'];
	?>
	<?= resendSummaryPanel([
		['label' => 'Domain Readiness', 'value' => $readiness . '%', 'extra' => '<progress class="uk-progress" value="' . $readiness . '" max="100"></progress>', 'note' => $verified . ' of ' . $domainCount . ' domains verified.'],
		['label' => 'Recent Delivery', 'value' => $deliveryHealth . '%', 'extra' => '<progress class="uk-progress" value="' . $deliveryHealth . '" max="100"></progress>', 'note' => $positive . ' positive final events in sample.'],
		['label' => 'Engagement', 'value' => $engagement . '%', 'extra' => '<progress class="uk-progress" value="' . $engagement . '" max="100"></progress>', 'note' => ($opened + $clicked) . ' opens or clicks tracked.'],
		['label' => 'Sample', 'value' => (int) $statsTotal, 'note' => 'Most recent emails analyzed. ' . $maxDay . ' max in one day.'],
	]) ?>

	<div class="uk-grid-small uk-margin-medium-bottom" uk-grid="match: > div > .uk-card">
		<div class="uk-width-2-3@l">
			<div class="uk-card uk-card-default uk-card-body uk-card-small uk-height-1-1">
				<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
					<h3 class="uk-margin-remove"><?= resendE('Sending Activity') ?></h3>
					<span class="uk-text-meta"><?= resendE('Last 14 days') ?></span>
				</div>
				<?= resendDashboardBars($statsSignalDays, $sanitizer) ?>
				<p class="uk-text-meta">Based on the <?= (int) $statsTotal ?> most recent emails returned by Resend.</p>
			</div>
		</div>
		<div class="uk-width-1-3@l">
			<div class="uk-card uk-card-default uk-card-body uk-card-small uk-height-1-1">
				<h3><?= resendE('Status Breakdown') ?></h3>
				<?= resendDashboardStatusBars($statsStatuses, (int) $statsTotal, $process, $sanitizer) ?>
			</div>
		</div>
	</div>

	<div class="uk-grid-medium uk-child-width-1-2@l uk-margin-medium-bottom" uk-grid="match: > div > .uk-card">
		<div>
			<div class="uk-card uk-card-default uk-card-body uk-card-small uk-height-1-1">
				<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
					<h3 class="uk-margin-remove"><?= resendE('Next Actions') ?></h3>
					<span class="uk-text-meta"><?= count($attention) ?> item<?= count($attention) === 1 ? '' : 's' ?></span>
				</div>
				<?php $primaryAction = $attention[0]; ?>
				<p class="uk-margin-small-top"><?= resendE('Recommended next step for this workspace.') ?></p>
				<p class="uk-margin-remove-bottom">
					<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $sanitizer->entities($primaryAction[1]) ?>"><?= $sanitizer->entities($primaryAction[0]) ?></a>
				</p>
				<?php if(count($attention) > 1): ?>
					<div class="uk-margin-small-top">
						<?php foreach(array_slice($attention, 1) as $item): ?>
						<div class="uk-text-meta"><a href="<?= $sanitizer->entities($item[1]) ?>"><?= $sanitizer->entities($item[0]) ?></a></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<div>
			<div class="uk-card uk-card-default uk-card-body uk-card-small uk-height-1-1">
				<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
					<h3 class="uk-margin-remove"><?= resendE('Domain Mix') ?></h3>
					<span class="uk-text-meta"><?= $domainCount ?> total</span>
				</div>
				<?php if(count($domainStatuses)): ?>
					<div class="uk-grid-small uk-child-width-1-2@s" uk-grid>
						<?php foreach($domainStatuses as $status => $count): ?>
						<?php $pct = $domainCount ? (int) round($count / $domainCount * 100) : 0; ?>
						<div>
							<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
								<span><?= $process->badge($status) ?></span>
								<span class="uk-text-meta"><?= (int) $count ?> (<?= $pct ?>%)</span>
							</div>
							<progress class="uk-progress" value="<?= (int) $count ?>" max="<?= $domainCount ?>"></progress>
						</div>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<p class="uk-text-muted uk-margin-remove"><?= resendE('No domains. Add and verify a domain before production sending.') ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="uk-grid-small uk-child-width-1-1 uk-margin-medium-bottom" uk-grid>
		<div>
			<div class="uk-card uk-card-default uk-card-body uk-card-small">
				<div class="uk-grid-small uk-flex-middle" uk-grid>
					<div class="uk-width-1-4@l uk-width-1-3@m">
						<h3 class="uk-margin-remove"><?= resendE('Delivery Signals') ?></h3>
					</div>
					<div class="uk-width-expand">
						<div class="uk-grid-small uk-child-width-1-4@l uk-child-width-1-2@s" uk-grid>
							<div><span class="uk-text-meta uk-text-uppercase"><?= resendE('Delivered') ?></span><div class="uk-text-large"><?= $delivered ?></div></div>
							<div><span class="uk-text-meta uk-text-uppercase"><?= resendE('Opened') ?></span><div class="uk-text-large"><?= $opened ?></div></div>
							<div><span class="uk-text-meta uk-text-uppercase"><?= resendE('Clicked') ?></span><div class="uk-text-large"><?= $clicked ?></div></div>
							<div><span class="uk-text-meta uk-text-uppercase"><?= resendE('Bounced or Complained') ?></span><div class="uk-text-large"><?= $bounced + $complained ?></div></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<h2><?= resendE('Domains') ?></h2>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th><?= resendE('Domain') ?></th><th><?= resendE('Status') ?></th><th><?= resendE('Region') ?></th><th><?= resendE('Created') ?></th></tr>
		<?php foreach($domains as $d): ?>
		<tr>
			<td><a href="<?= $adminUrl ?>domain/?id=<?= rawurlencode($d['id']) ?>"><?= $sanitizer->entities($d['name'] ?? '') ?></a></td>
			<td><?= $process->badge($d['status'] ?? '') ?></td>
			<td><?= $sanitizer->entities($d['region'] ?? '') ?></td>
			<td><?= $process->formatDate($d['created_at'] ?? '') ?></td>
		</tr>
		<?php endforeach; ?>
		<?php if(!count($domains)): ?><?= resendEmptyRow(4, 'No domains are configured yet. Add and verify a domain before production sending.') ?><?php endif; ?>
	</table>
	</div>

	<h2><?= resendE('Recent Emails') ?></h2>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th><?= resendE('To') ?></th><th><?= resendE('Subject') ?></th><th><?= resendE('Status') ?></th><th><?= resendE('Date') ?></th></tr>
		<?php foreach($emails as $e): ?>
		<tr>
			<td><?= $sanitizer->entities(implode(', ', (array) ($e['to'] ?? []))) ?></td>
			<td><a href="<?= $adminUrl ?>email/?id=<?= rawurlencode($e['id']) ?>"><?= $sanitizer->entities($e['subject'] ?? '') ?></a></td>
			<td><?= $process->badge($e['last_event'] ?? ($e['status'] ?? '')) ?></td>
			<td><?= $process->formatDate($e['created_at'] ?? '') ?></td>
		</tr>
		<?php endforeach; ?>
		<?php if(!count($emails)): ?><?= resendEmptyRow(4, 'No recent sent emails were returned by the API.') ?><?php endif; ?>
	</table>
	</div>

<?php endif; ?>
