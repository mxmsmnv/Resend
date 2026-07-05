<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
?>
<?= resendNav($adminUrl, 'domains') ?>

<?php if(!count($domain)): ?>
	<?= resendEmpty('Domain not found.') ?>
<?php else: ?>

<?php
$status = (string) ($domain['status'] ?? 'unknown');
if(!strlen($status)) $status = 'unknown';
$records = isset($domain['records']) && is_array($domain['records']) ? $domain['records'] : [];
$recordTotal = count($records);
$recordReady = 0;
foreach($records as $r) {
	if(($r['status'] ?? '') === 'verified') $recordReady++;
}
$dnsReadiness = $recordTotal ? (int) round($recordReady / $recordTotal * 100) : ($status === 'verified' ? 100 : 0);
$dnsAudit = $process->domainDnsAudit($domain['name'] ?? '', $records);
?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Use this page while setting up or auditing a sending domain. Publish the DNS records exactly as shown, then verify after DNS propagation has had time to complete.</p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>domains/">Back to Domains</a></div>
			<div><?= resendActionForm($adminUrl . 'domain/?id=' . rawurlencode($domain['id'] ?? ''), ['action' => 'delete'], 'Delete Domain', $csrf, 'danger', 'Delete this domain from Resend? DNS records at your provider are not removed automatically.') ?></div>
		</div>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Domain', 'value' => $sanitizer->entities($domain['name'] ?? ''), 'note' => '<span class="uk-text-monospace">' . $sanitizer->entities($domain['id'] ?? '') . '</span>'],
	['label' => 'Status', 'value' => $process->badge($status), 'note' => $status === 'verified' ? 'Ready for production sending.' : 'DNS verification is not complete yet.'],
	['label' => 'DNS Readiness', 'value' => $dnsReadiness . '%', 'extra' => '<progress class="uk-progress" value="' . $dnsReadiness . '" max="100"></progress>', 'note' => $recordReady . ' of ' . $recordTotal . ' records verified.'],
	['label' => 'Region', 'value' => strlen((string) ($domain['region'] ?? '')) ? $sanitizer->entities($domain['region']) : 'Default', 'note' => 'Created ' . $process->formatDate($domain['created_at'] ?? '')],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-2@m">
			<h3 class="uk-margin-remove-top">Tracking</h3>
			<form class="uk-form-stacked" method="post" action="<?= $adminUrl ?>domains/">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="tracking">
				<input type="hidden" name="id" value="<?= $sanitizer->entities($domain['id'] ?? '') ?>">
				<div class="uk-grid-small" uk-grid>
					<div class="uk-width-1-2@m">
						<label><input class="uk-checkbox" type="checkbox" name="open_tracking" value="1" <?= !empty($domain['open_tracking']) ? 'checked' : '' ?>> Open tracking</label>
					</div>
					<div class="uk-width-1-2@m">
						<label><input class="uk-checkbox" type="checkbox" name="click_tracking" value="1" <?= !empty($domain['click_tracking']) ? 'checked' : '' ?>> Click tracking</label>
					</div>
				</div>
				<p class="uk-margin-small-top uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary">Save Tracking</button></p>
			</form>
			<?= resendNote("Open and click tracking affect how Resend instruments messages for this domain. Keep these settings aligned with your privacy policy.") ?>
		</div>
		<div class="uk-width-1-2@m">
			<h3 class="uk-margin-remove-top">Next Step</h3>
			<?php if($status === 'verified'): ?>
				<p>This domain is verified. Use Test Send when changing sender defaults, templates or routing.</p>
				<p class="uk-margin-remove-bottom"><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>test/">Test Send</a></p>
			<?php elseif($recordTotal): ?>
				<p>Publish every DNS record below at your DNS provider, wait for propagation, then trigger verification from the Domains list.</p>
				<p class="uk-margin-remove-bottom"><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>domains/">Verify from Domains</a></p>
			<?php else: ?>
				<p>Resend did not return DNS records for this domain. Refresh the domain from Resend or check whether creation completed successfully.</p>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if($recordTotal): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-grid-divider" uk-grid>
			<div class="uk-width-1-3@l">
				<h3 class="uk-margin-remove-top">DNS Assistant</h3>
				<p class="uk-text-muted">This checks the public DNS that is visible right now and explains the safest next step.</p>
				<p class="uk-text-meta uk-margin-remove-bottom">If SPF already exists, do not add a second SPF TXT record. Use the combined value shown here.</p>
			</div>
			<div class="uk-width-2-3@l">
				<?php if(!count($dnsAudit)): ?>
					<p class="uk-text-muted uk-margin-remove">DNS lookup is not available on this server.</p>
				<?php else: ?>
					<div class="uk-grid-medium uk-child-width-1-1" uk-grid>
						<?php foreach($dnsAudit as $item): ?>
						<?php
							$auditStatus = (string) ($item['status'] ?? 'missing');
							$auditLabel = 'Add';
							if($auditStatus === 'found') $auditLabel = 'Ready';
							elseif($auditStatus === 'merge') $auditLabel = 'Merge SPF';
							elseif($auditStatus === 'coexist') $auditLabel = 'Add alongside';
							$labelClass = 'uk-label';
							if($auditStatus === 'found') $labelClass .= ' uk-label-success';
							elseif(in_array($auditStatus, ['merge', 'coexist'], true)) $labelClass .= ' uk-label-warning';
							elseif($auditStatus === 'missing') $labelClass .= ' uk-label-danger';
							$requiredValue = (string) ($item['value'] ?? '');
							if(($item['type'] ?? '') === 'MX' && strlen((string) ($item['priority'] ?? ''))) {
								$requiredValue .= ' (priority ' . (string) $item['priority'] . ')';
							}
						?>
						<div>
							<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
								<div>
									<span class="<?= $labelClass ?>"><?= $sanitizer->entities($auditLabel) ?></span>
									<strong class="uk-margin-small-left"><?= $sanitizer->entities($item['type']) ?></strong>
									<span class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($item['host']) ?></span>
								</div>
							</div>
							<p class="uk-margin-remove"><?= $sanitizer->entities($item['message']) ?></p>
							<?php if(strlen((string) ($item['suggested'] ?? ''))): ?>
								<p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom">Use this SPF value:</p>
								<pre class="uk-margin-small-top uk-margin-remove-bottom"><code><?= $sanitizer->entities($item['suggested']) ?></code></pre>
							<?php elseif(in_array($auditStatus, ['missing', 'coexist'], true)): ?>
								<p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom">Required value:</p>
								<pre class="uk-margin-small-top uk-margin-remove-bottom"><code><?= $sanitizer->entities($requiredValue) ?></code></pre>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<h2>DNS Records</h2>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th>Type</th><th>Name</th><th>Value</th><th>Priority</th><th>TTL</th><th>Status</th></tr>
		<?php foreach($records as $r): ?>
		<tr>
			<td><span class="uk-label"><?= $sanitizer->entities($r['type'] ?? '') ?></span></td>
			<td class="uk-text-monospace"><?= $sanitizer->entities($r['name'] ?? '') ?></td>
			<td class="uk-text-monospace uk-text-break"><?= $sanitizer->entities($r['value'] ?? '') ?></td>
			<td><?= $sanitizer->entities((string) ($r['priority'] ?? '')) ?></td>
			<td><?= $sanitizer->entities((string) ($r['ttl'] ?? '')) ?></td>
			<td><?= $process->badge($r['status'] ?? 'unknown') ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php else: ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3>No DNS Records Returned</h3>
		<p>Resend did not include DNS records for this domain response.</p>
	</div>
<?php endif; ?>

<?php endif; ?>
