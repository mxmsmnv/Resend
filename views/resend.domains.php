<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'domains/';
$statusCounts = [];
$verified = 0;
$needsWork = 0;
$regions = [];

foreach($domains as $d) {
	$status = (string) ($d['status'] ?? 'unknown');
	if(!strlen($status)) $status = 'unknown';
	if(!isset($statusCounts[$status])) $statusCounts[$status] = 0;
	$statusCounts[$status]++;
	if($status === 'verified') $verified++;
	if(in_array($status, ['failed', 'temporary_failure', 'not_started', 'pending'], true)) $needsWork++;
	$region = (string) ($d['region'] ?? '');
	if(strlen($region)) $regions[$region] = true;
}
arsort($statusCounts);
$domainCount = count($domains);
$readiness = $domainCount ? (int) round($verified / $domainCount * 100) : 0;
?>
<?= resendNav($adminUrl, 'domains') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Domains control whether Resend can send mail for your site. Add a sending domain or subdomain, publish the DNS records from its detail page, then verify when propagation is ready.</p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>test/">Test Send</a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Readiness', 'value' => $readiness . '%', 'extra' => '<progress class="uk-progress" value="' . $readiness . '" max="100"></progress>', 'note' => $verified . ' of ' . $domainCount . ' domains verified.'],
	['label' => 'Domains', 'value' => $domainCount, 'note' => 'Sending identities in Resend.'],
	['label' => 'Needs Work', 'value' => $needsWork, 'note' => 'Pending, failed or not started.'],
	['label' => 'Regions', 'value' => count($regions), 'note' => 'Regions represented here.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-2-3@l">
			<h3 class="uk-margin-remove-top">Setup Status</h3>
			<div class="uk-grid-medium" uk-grid>
				<div class="uk-width-1-2@m">
					<p class="uk-text-meta uk-text-uppercase uk-margin-remove">Current State</p>
					<p class="uk-margin-small-top uk-margin-small-bottom">
						<?php if(!$domainCount): ?>
							No sending domains have been added yet.
						<?php elseif($needsWork): ?>
							<?= (int) $needsWork ?> domain<?= $needsWork === 1 ? '' : 's' ?> still need DNS attention before production sending.
						<?php else: ?>
							All listed domains are verified and ready for sending.
						<?php endif; ?>
					</p>
					<?php if($domainCount): ?>
						<progress class="uk-progress" value="<?= $verified ?>" max="<?= $domainCount ?>"></progress>
						<p class="uk-text-meta uk-margin-remove"><?= $verified ?> verified, <?= $needsWork ?> needing work.</p>
					<?php endif; ?>
				</div>
				<div class="uk-width-1-2@m">
					<p class="uk-text-meta uk-text-uppercase uk-margin-remove">Next Step</p>
					<p class="uk-margin-small-top uk-margin-small-bottom">
						<?php if(!$domainCount): ?>
							Add a subdomain like <code>mail.example.com</code>, then open it to copy the DNS records.
						<?php elseif($needsWork): ?>
							Open the failed domain, publish the DNS records, then run Verify from the table below.
						<?php else: ?>
							Send a test email after changing sender defaults, templates or tracking settings.
						<?php endif; ?>
					</p>
					<?php if(count($statusCounts)): ?>
						<div class="uk-grid-small uk-child-width-auto" uk-grid>
							<?php foreach($statusCounts as $status => $count): ?>
							<div><span class="uk-margin-small-right"><?= $process->badge($status) ?></span><span class="uk-text-meta"><?= (int) $count ?></span></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top">Add Domain</h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="create">
				<div class="uk-margin-small-bottom">
					<label class="uk-form-label" for="name">Domain</label>
					<input class="uk-input" type="text" name="name" id="name" placeholder="mail.example.com" required>
				</div>
				<div class="uk-margin-small-bottom">
					<label class="uk-form-label" for="region">Region</label>
					<select class="uk-select" name="region" id="region">
						<option value="">Default (us-east-1)</option>
						<option value="us-east-1">us-east-1</option>
						<option value="eu-west-1">eu-west-1</option>
						<option value="sa-east-1">sa-east-1</option>
						<option value="ap-northeast-1">ap-northeast-1</option>
					</select>
				</div>
				<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary">Add Domain</button></p>
			</form>
			<?= resendNote("Use a subdomain like <code>mail.example.com</code> when you want sending reputation isolated from the root domain. Region cannot be changed after creation.") ?>
		</div>
	</div>
</div>

<?php if(!count($domains)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top">No Domains Yet</h3>
				<p class="uk-margin-remove-bottom">Add a sending domain or subdomain, then open it to copy the DNS records required by Resend.</p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>test/">Test Send</a>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th>Domain</th><th>Status</th><th>Region</th><th>Tracking</th><th>Created</th><th>Actions</th></tr>
		<?php foreach($domains as $d): ?>
		<?php
			$id = (string) ($d['id'] ?? '');
			$status = (string) ($d['status'] ?? 'unknown');
			if(!strlen($status)) $status = 'unknown';
			$tracking = [];
			$tracking[] = !empty($d['open_tracking']) ? 'Open on' : 'Open off';
			$tracking[] = !empty($d['click_tracking']) ? 'Click on' : 'Click off';
		?>
		<tr>
			<td>
				<a href="<?= $adminUrl ?>domain/?id=<?= rawurlencode($id) ?>"><?= $sanitizer->entities($d['name'] ?? '') ?></a>
				<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($id) ?></div>
			</td>
			<td><?= $process->badge($status) ?></td>
			<td><?= strlen((string) ($d['region'] ?? '')) ? $sanitizer->entities($d['region']) : '<span class="uk-text-muted">Default</span>' ?></td>
			<td><?= $sanitizer->entities(implode(', ', $tracking)) ?></td>
			<td><?= $process->formatDate($d['created_at'] ?? '') ?></td>
			<td>
				<a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>domain/?id=<?= rawurlencode($id) ?>">DNS</a>
				<?php if($status !== 'verified'): ?>
					<?= resendActionForm($actionUrl, ['action' => 'verify', 'id' => $id], 'Verify', $csrf) ?>
				<?php endif; ?>
				<?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $id], 'Delete', $csrf, 'danger', 'Delete this domain?') ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>
