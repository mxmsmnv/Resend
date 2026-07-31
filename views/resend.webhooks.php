<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'webhooks/';
$enabled = 0;
$disabled = 0;
foreach($webhooks as $w) {
	if(($w['status'] ?? '') === 'disabled') $disabled++;
	else $enabled++;
}
$eventLabels = [
	'email.sent' => ['Delivery', 'Sent', 'When Resend accepts a message.'],
	'email.delivered' => ['Delivery', 'Delivered', 'When a message reaches the recipient server.'],
	'email.delivery_delayed' => ['Delivery', 'Delayed', 'When delivery is taking longer than expected.'],
	'email.bounced' => ['Delivery', 'Bounced', 'When delivery permanently fails.'],
	'email.complained' => ['Delivery', 'Complained', 'When a recipient reports spam.'],
	'email.failed' => ['Delivery', 'Failed', 'When Resend cannot send the message.'],
	'email.scheduled' => ['Delivery', 'Scheduled', 'When a message is queued for later.'],
	'email.suppressed' => ['Delivery', 'Suppressed', 'When sending is blocked for policy or recipient state.'],
	'email.opened' => ['Engagement', 'Opened', 'When open tracking records a view.'],
	'email.clicked' => ['Engagement', 'Clicked', 'When click tracking records a click.'],
	'email.received' => ['Inbound', 'Inbound received', 'When a configured inbound domain receives mail.'],
	'contact.created' => ['Audience', 'Contact created', 'When a new contact is added.'],
	'contact.updated' => ['Audience', 'Contact updated', 'When a contact changes.'],
	'contact.deleted' => ['Audience', 'Contact deleted', 'When a contact is removed.'],
	'domain.created' => ['Domains', 'Domain created', 'When a sending domain is added.'],
	'domain.updated' => ['Domains', 'Domain updated', 'When a domain status or settings change.'],
	'domain.deleted' => ['Domains', 'Domain deleted', 'When a sending domain is removed.'],
];
$eventGroups = [];
foreach($eventTypes as $event) {
	$meta = $eventLabels[$event] ?? ['Other', $event, ''];
	$eventGroups[$meta[0]][] = ['event' => $event, 'label' => $meta[1], 'note' => $meta[2]];
}
?>
<?= resendNav($adminUrl, 'webhooks') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= resendE('Send Resend activity to another system when it needs to react to deliveries, engagement, inbound mail, contact changes or domain updates.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>events/"><?= resendE('Received Events') ?></a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Webhooks', 'value' => count($webhooks), 'note' => 'External destinations configured.'],
	['label' => 'Enabled', 'value' => $enabled, 'note' => 'Currently receiving selected events.'],
	['label' => 'Disabled', 'value' => $disabled, 'note' => 'Paused destinations.'],
	['label' => 'Event Types', 'value' => count($eventTypes), 'note' => 'Available event signals.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('Create Webhook') ?></h3>
			<p class="uk-text-muted"><?= resendE('Add the HTTPS endpoint that should receive Resend updates. After creation, copy the signing secret immediately and store it with that endpoint.') ?></p>
			<p class="uk-text-meta uk-margin-remove-bottom"><?= resendE('Choose only the signals your endpoint actually handles.') ?></p>
		</div>
		<div class="uk-width-2-3@l">
	<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="create">
		<div class="uk-margin">
			<label class="uk-form-label"><?= resendE('Destination URL') ?></label>
			<input class="uk-input" type="url" name="endpoint_url" placeholder="https://example.com/resend-webhook/" required>
		</div>
		<p class="uk-form-label"><?= resendE('Send These Updates') ?></p>
		<div class="uk-grid-medium uk-child-width-1-2@m" uk-grid>
			<?php foreach($eventGroups as $group => $events): ?>
			<div>
				<p class="uk-text-meta uk-text-uppercase uk-margin-remove"><?= resendE($group) ?></p>
				<div class="uk-margin-small-top">
					<?php foreach($events as $event): ?>
					<label class="uk-display-block uk-margin-small-bottom">
						<input class="uk-checkbox" type="checkbox" name="events[]" value="<?= $sanitizer->entities($event['event']) ?>">
						<span><?= resendE($event['label']) ?></span>
						<?php if(strlen($event['note'])): ?><span class="uk-text-meta"> <?= resendE($event['note']) ?></span><?php endif; ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<p class="uk-margin-remove-bottom uk-margin-top"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Create Webhook') ?></button></p>
	</form>
		</div>
	</div>
</div>

<?php if(!count($webhooks)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top"><?= resendE('No Webhooks Yet') ?></h3>
				<p class="uk-margin-remove-bottom"><?= resendE('Create one when another system needs to receive delivery updates, inbound mail, contact changes or domain status changes.') ?></p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>events/"><?= resendE('View Received Events') ?></a>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th><?= resendE('Destination') ?></th><th><?= resendE('Updates Sent') ?></th><th><?= resendE('Status') ?></th><th><?= resendE('Actions') ?></th></tr>
		<?php foreach($webhooks as $w): ?>
		<?php
			$events = (array) ($w['events'] ?? []);
			$eventSummary = [];
			foreach($events as $event) {
				$eventSummary[] = isset($eventLabels[$event]) ? $eventLabels[$event][1] : $event;
			}
		?>
		<tr>
			<td>
				<span class="uk-text-monospace"><?= $sanitizer->entities($w['endpoint_url'] ?? ($w['endpoint'] ?? '')) ?></span>
				<div class="uk-text-meta"><?= $sanitizer->entities($w['id'] ?? '') ?></div>
			</td>
			<td>
				<?= $sanitizer->entities(implode(', ', $eventSummary)) ?>
				<div class="uk-text-meta"><?= count($events) ?> selected</div>
			</td>
			<td><?= $process->badge($w['status'] ?? '') ?></td>
			<td>
				<?= resendActionForm($actionUrl, ['action' => 'toggle', 'id' => $w['id'], 'status' => $w['status'] ?? ''], ($w['status'] ?? '') === 'disabled' ? 'Enable' : 'Disable', $csrf, 'secondary') ?>
				<?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $w['id']], 'Delete', $csrf, 'danger', 'Delete this webhook?') ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>
