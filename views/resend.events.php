<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
?>
<?= resendNav($adminUrl, 'events') ?>
<?= resendIntro('events') ?>

<?php if(!$receiverInstalled): ?>
	<?= resendNote("The ResendWebhooks module is not installed. Install it from the Modules screen to activate the receiver endpoint.") ?>
<?php elseif(!$secretConfigured): ?>
	<?= resendNote("No webhook signing secret is configured, so the endpoint is inactive. Create a webhook in Resend pointing to <code>/resend-webhook/</code> on this site, then store its signing secret in the module config.") ?>
<?php endif; ?>

<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th>Event</th><th>Recipient</th><th>Email ID</th><th>Received</th></tr>
	<?php foreach($events as $e): ?>
	<?php
	$type = (string) ($e['type'] ?? '');
	$suffix = strpos($type, '.') !== false ? substr(strrchr($type, '.'), 1) : $type;
	?>
	<tr>
		<td><span class="uk-label"><?= $sanitizer->entities($type) ?></span></td>
		<td><?= $sanitizer->entities($e['recipient'] ?? '') ?></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($e['email_id'] ?? '') ?></td>
		<td><?= $sanitizer->entities($e['created'] ?? '') ?></td>
	</tr>
	<?php endforeach; ?>
	<?php if(!count($events)): ?><?= resendEmptyRow(4, 'No verified webhook events have been stored locally yet.') ?><?php endif; ?>
</table>
</div>

<?php if($hasMore): ?>
<?php $last = end($events); ?>
<p class="uk-margin"><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>events/?before=<?= (int) $last['id'] ?>">Older events</a></p>
<?php endif; ?>
