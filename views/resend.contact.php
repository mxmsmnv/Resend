<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'contact/?id=' . rawurlencode($id);
?>
<?= resendNav($adminUrl, 'contacts') ?>

<?php if(!count($contact)): ?>
	<?= resendEmpty('Contact not found.') ?>
<?php else: ?>

<?php
$email = (string) ($contact['email'] ?? '');
$name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
$unsub = !empty($contact['unsubscribed']);
$properties = !empty($contact['properties']) && is_array($contact['properties']) ? $contact['properties'] : [];
$memberIds = array_column($memberSegments, 'id');
$available = array_filter($segments, function($s) use($memberIds) { return !in_array($s['id'], $memberIds); });
$topicOptIn = 0;
foreach($topics as $t) if(($t['subscription'] ?? '') === 'opt-in') $topicOptIn++;
?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= resendE('A contact profile combines identity, global subscription state, segment membership, topic preferences and custom properties.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/"><?= resendE('Back to Contacts') ?></a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Contact', 'value' => $sanitizer->entities($email), 'note' => '<span class="uk-text-monospace">' . $sanitizer->entities($contact['id'] ?? '') . '</span>'],
	['label' => 'Status', 'value' => $unsub ? $process->badge('unsubscribed') : $process->badge('active'), 'note' => $unsub ? 'Excluded from broadcasts.' : 'Available for broadcasts.'],
	['label' => 'Segments', 'value' => count($memberSegments), 'note' => 'Audience memberships.'],
	['label' => 'Topics', 'value' => $topicOptIn . ' / ' . count($topics), 'note' => 'Opted in topic streams.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-2@m">
			<h3 class="uk-margin-remove-top"><?= resendE('Identity') ?></h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="update">
				<div class="uk-grid-small uk-flex-bottom" uk-grid>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('First Name') ?></label><input class="uk-input" type="text" name="first_name" value="<?= $sanitizer->entities($contact['first_name'] ?? '') ?>"></div>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Last Name') ?></label><input class="uk-input" type="text" name="last_name" value="<?= $sanitizer->entities($contact['last_name'] ?? '') ?>"></div>
					<div class="uk-width-auto@m"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Save Identity') ?></button></div>
				</div>
			</form>
			<p class="uk-text-meta">Created <?= $process->formatDate($contact['created_at'] ?? '') ?><?= strlen($name) ? ', shown as ' . $sanitizer->entities($name) : '' ?>.</p>
		</div>
		<div class="uk-width-1-2@m">
			<h3 class="uk-margin-remove-top"><?= resendE('Subscription') ?></h3>
			<p><?= $unsub ? 'This contact is globally unsubscribed and will be skipped by broadcasts.' : 'This contact is globally active. Topic preferences can still opt them out of individual streams.' ?></p>
			<p class="uk-margin-remove-bottom">
				<?php if($unsub): ?>
					<?= resendActionForm($actionUrl, ['action' => 'resubscribe'], 'Resubscribe', $csrf, 'secondary') ?>
				<?php else: ?>
					<?= resendActionForm($actionUrl, ['action' => 'unsubscribe'], 'Unsubscribe', $csrf, 'secondary') ?>
				<?php endif; ?>
			</p>
		</div>
	</div>
</div>

<div class="uk-grid-small uk-margin-medium-bottom" uk-grid="match: > div > .uk-card">
	<div class="uk-width-2-3@l">
		<h2><?= resendE('Segments') ?></h2>
		<div class="uk-overflow-auto">
		<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
			<tr><th><?= resendE('Name') ?></th><th><?= resendE('ID') ?></th><th><?= resendE('Actions') ?></th></tr>
			<?php foreach($memberSegments as $s): ?>
			<tr>
				<td><?= $sanitizer->entities($s['name'] ?? '') ?></td>
				<td class="uk-text-monospace"><?= $sanitizer->entities($s['id'] ?? '') ?></td>
				<td><?= resendActionForm($actionUrl, ['action' => 'removesegment', 'segment_id' => $s['id']], 'Remove', $csrf, 'danger', 'Remove this contact from the segment?') ?></td>
			</tr>
			<?php endforeach; ?>
			<?php if(!count($memberSegments)): ?><?= resendEmptyRow(3, 'This contact is not a member of any segment yet.') ?><?php endif; ?>
		</table>
		</div>
	</div>
	<div class="uk-width-1-3@l">
		<div class="uk-card uk-card-default uk-card-body uk-card-small uk-height-1-1">
			<h3><?= resendE('Add to Segment') ?></h3>
			<?php if(count($available)): ?>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="addsegment">
				<label class="uk-form-label"><?= resendE('Segment') ?></label>
				<select class="uk-select" name="segment_id">
					<?php foreach($available as $s): ?>
					<option value="<?= $sanitizer->entities($s['id']) ?>"><?= $sanitizer->entities($s['name'] ?? '') ?></option>
					<?php endforeach; ?>
				</select>
				<p class="uk-margin-small-top uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Add') ?></button></p>
			</form>
			<?php else: ?>
				<p class="uk-text-muted uk-margin-remove-bottom"><?= resendE('This contact already belongs to every available segment.') ?></p>
			<?php endif; ?>
			<?= resendNote("Segment membership controls which broadcast audiences include this contact.") ?>
		</div>
	</div>
</div>

<h2><?= resendE('Topic Subscriptions') ?></h2>
<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th><?= resendE('Topic') ?></th><th><?= resendE('Subscription') ?></th><th><?= resendE('Actions') ?></th></tr>
	<?php foreach($topics as $t): ?>
	<?php $optedIn = ($t['subscription'] ?? '') === 'opt-in'; ?>
	<tr>
		<td>
			<?= $sanitizer->entities($t['name'] ?? ($t['id'] ?? '')) ?>
			<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($t['id'] ?? '') ?></div>
		</td>
		<td><?= $process->badge($optedIn ? 'active' : 'unsubscribed') ?></td>
		<td><?= resendActionForm($actionUrl, [
			'action' => 'topic',
			'topic_id' => $t['id'] ?? '',
			'subscription' => $optedIn ? 'opt-out' : 'opt-in',
		], $optedIn ? 'Opt out' : 'Opt in', $csrf, 'secondary') ?></td>
	</tr>
	<?php endforeach; ?>
	<?php if(!count($topics)): ?><?= resendEmptyRow(3, 'No topic subscriptions are available for this contact.') ?><?php endif; ?>
</table>
</div>

<?php if(count($properties)): ?>
<h2><?= resendE('Properties') ?></h2>
<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
	<dl class="uk-description-list uk-description-list-divider uk-margin-remove-bottom">
		<?php foreach($properties as $key => $value): ?>
		<dt class="uk-text-monospace"><?= $sanitizer->entities($key) ?></dt>
		<dd><?= $sanitizer->entities(is_scalar($value) ? (string) $value : resendJson($value)) ?></dd>
		<?php endforeach; ?>
	</dl>
</div>
<?php endif; ?>

<?php endif; ?>
